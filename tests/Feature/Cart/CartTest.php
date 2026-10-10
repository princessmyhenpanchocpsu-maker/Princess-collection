<?php

namespace Tests\Feature\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variant(array $attributes = [], array $product = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create(['base_price' => '500.00', ...$product]))
            ->create($attributes);
    }

    public function test_a_guest_has_no_cart(): void
    {
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', ['product_variant_id' => 1, 'quantity' => 1])->assertUnauthorized();
    }

    public function test_a_customer_can_add_items_and_totals_are_calculated_on_the_server(): void
    {
        $user = User::factory()->create();
        $first = $this->variant();
        $second = $this->variant(['price_override' => '750.50']);

        $this->actingAs($user)->getJson('/api/v1/cart')->assertOk()
            ->assertJsonPath('data.items', [])->assertJsonPath('data.total', '0.00')->assertJsonPath('data.can_checkout', false);

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $first->id, 'quantity' => 2])->assertCreated();

        // Any price the client sends is ignored.
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $second->id, 'quantity' => 1, 'unit_price' => '1.00'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.item_count', 3)
            ->assertJsonPath('data.items.0.line_total', '1000.00')
            ->assertJsonPath('data.items.1.unit_price', '750.50')
            ->assertJsonPath('data.subtotal', '1750.50')
            ->assertJsonPath('data.shipping_fee', '120.00')
            ->assertJsonPath('data.total', '1870.50')
            ->assertJsonPath('data.can_checkout', true);
    }

    public function test_adding_the_same_variant_again_increases_the_existing_line(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant(['stock_quantity' => 5]);

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5);

        // One more would exceed stock.
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(5, CartItem::firstOrFail()->quantity);
    }

    public function test_free_shipping_applies_at_the_threshold(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant([], ['base_price' => '1000.00']);

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '2000.00')
            ->assertJsonPath('data.shipping_fee', '0.00')
            ->assertJsonPath('data.total', '2000.00');
    }

    public function test_unavailable_or_out_of_stock_variants_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $cases = [
            'inactive variant' => $this->variant(['is_active' => false]),
            'draft product' => $this->variant([], ['status' => 'draft']),
            'archived product' => $this->variant([], ['status' => 'archived']),
        ];

        foreach ($cases as $variant) {
            $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
        }

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => 999999, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $this->variant(['stock_quantity' => 0])->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $this->variant()->id, 'quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(0, CartItem::count());
    }

    public function test_a_customer_can_change_quantity_and_remove_lines(): void
    {
        $user = User::factory()->create();
        $variant = $this->variant(['stock_quantity' => 4]);

        $itemId = $this->actingAs($user)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
            ->json('data.items.0.id');

        $this->actingAs($user)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()->assertJsonPath('data.items.0.quantity', 4);

        $this->actingAs($user)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->actingAs($user)->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_the_cart_flags_lines_that_became_unavailable_after_being_added(): void
    {
        $user = User::factory()->create();
        $lowStock = $this->variant(['stock_quantity' => 5]);
        $archived = $this->variant();

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $lowStock->id, 'quantity' => 4])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $archived->id, 'quantity' => 1])->assertCreated();

        ProductVariant::whereKey($lowStock->id)->update(['stock_quantity' => 2]);
        $archived->product->update(['status' => 'archived']);

        $this->actingAs($user)->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.issue', 'insufficient_stock')
            ->assertJsonPath('data.items.0.available_stock', 2)
            ->assertJsonPath('data.items.1.issue', 'unavailable')
            ->assertJsonPath('data.can_checkout', false);
    }

    public function test_a_customer_cannot_see_or_change_another_customers_cart(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $variant = $this->variant();

        $itemId = $this->actingAs($owner)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->json('data.items.0.id');

        $this->actingAs($intruder)->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($intruder)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])->assertNotFound();
        $this->actingAs($intruder)->deleteJson("/api/v1/cart/items/{$itemId}")->assertNotFound();

        $this->assertSame(2, CartItem::findOrFail($itemId)->quantity);
    }
}
