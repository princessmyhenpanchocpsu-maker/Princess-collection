<?php

namespace Tests\Feature\Admin;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function adjust(array $payload)
    {
        return $this->actingAs($this->admin)->postJson('/api/v1/admin/inventory/adjustments', $payload);
    }

    public function test_the_inventory_list_shows_stock_per_variant_with_low_stock_filters(): void
    {
        $healthy = $this->makeVariant(stock: 20, attributes: ['sku' => 'HEALTHY-1']);
        $low = $this->makeVariant(stock: 3, attributes: ['sku' => 'LOW-1']);
        $out = $this->makeVariant(stock: 0, attributes: ['sku' => 'OUT-1']);
        ProductVariant::factory()->for(Product::factory()->archived())->create(['stock_quantity' => 0]);

        $list = fn (string $query) => $this->actingAs($this->admin)->getJson("/api/v1/admin/inventory?{$query}")->assertOk();

        $list('')->assertJsonCount(3, 'data');
        $this->assertEqualsCanonicalizing(['LOW-1', 'OUT-1'], $list('stock=low')->json('data.*.sku'));
        $list('stock=out')->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'OUT-1')->assertJsonPath('data.0.is_low_stock', true);
        $list('search=HEALTHY')->assertJsonCount(1, 'data')->assertJsonPath('data.0.stock_quantity', 20);
        $list('search='.urlencode($low->product->name))->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.name', $low->product->name);
    }

    public function test_stock_in_adds_stock_and_records_a_movement(): void
    {
        $variant = $this->makeVariant(stock: 2);

        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'stock_in', 'quantity' => 10, 'reason' => 'Supplier delivery'])
            ->assertCreated()
            ->assertJsonPath('data.variant.stock_quantity', 12)
            ->assertJsonPath('data.movement.type', 'stock_in')
            ->assertJsonPath('data.movement.quantity_change', 10)
            ->assertJsonPath('data.movement.user.id', $this->admin->id);

        $this->assertSame(12, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id, 'user_id' => $this->admin->id,
            'movement_type' => 'stock_in', 'quantity_change' => 10, 'reason' => 'Supplier delivery',
        ]);
    }

    public function test_adjustments_can_reduce_stock_but_never_below_zero(): void
    {
        $variant = $this->makeVariant(stock: 5);

        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'adjustment', 'quantity' => -2, 'reason' => 'Damaged'])
            ->assertCreated()->assertJsonPath('data.variant.stock_quantity', 3);

        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'adjustment', 'quantity' => -4, 'reason' => 'Stocktake'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(3, $variant->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_adjustments_are_validated(): void
    {
        $variant = $this->makeVariant();

        $this->adjust([])->assertUnprocessable()->assertJsonValidationErrors(['product_variant_id', 'type', 'quantity', 'reason']);
        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'stock_in', 'quantity' => -5, 'reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'adjustment', 'quantity' => 0, 'reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'sale', 'quantity' => 1, 'reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->adjust(['product_variant_id' => 999999, 'type' => 'stock_in', 'quantity' => 1, 'reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');

        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_movement_history_lists_every_change_including_sales(): void
    {
        $variant = $this->makeVariant(stock: 10);
        $other = $this->makeVariant(stock: 10);
        $order = $this->placeOrder(null, [[$variant, 2]]);
        $this->adjust(['product_variant_id' => $variant->id, 'type' => 'stock_in', 'quantity' => 5, 'reason' => 'Restock'])->assertCreated();
        $this->adjust(['product_variant_id' => $other->id, 'type' => 'stock_in', 'quantity' => 1, 'reason' => 'Restock'])->assertCreated();

        $this->actingAs($this->admin)->getJson("/api/v1/admin/inventory/movements?product_variant_id={$variant->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'stock_in')
            ->assertJsonPath('data.0.quantity_change', 5)
            ->assertJsonPath('data.1.type', 'sale')
            ->assertJsonPath('data.1.quantity_change', -2)
            ->assertJsonPath('data.1.order_number', $order->order_number)
            ->assertJsonPath('data.1.variant.sku', $variant->sku);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/inventory/movements?type=stock_in')->assertOk()->assertJsonCount(2, 'data');

        // The ledger always explains the current stock level.
        $this->assertSame(13, $variant->fresh()->stock_quantity);
        $this->assertSame(3, (int) InventoryMovement::where('product_variant_id', $variant->id)->sum('quantity_change'));
        $this->assertSame(InventoryMovementType::Sale, InventoryMovement::where('quantity_change', -2)->firstOrFail()->movement_type);
    }

    public function test_only_admins_can_use_inventory(): void
    {
        $variant = $this->makeVariant();
        $payload = ['product_variant_id' => $variant->id, 'type' => 'stock_in', 'quantity' => 100, 'reason' => 'Hack'];

        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
        $this->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/inventory')->assertForbidden();
        $this->getJson('/api/v1/admin/inventory/movements')->assertForbidden();
        $this->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertForbidden();

        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }
}
