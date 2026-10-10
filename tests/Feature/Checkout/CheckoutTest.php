<?php

namespace Tests\Feature\Checkout;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->address = Address::factory()->for($this->user)->create(['is_default' => true, 'city' => 'Pasig']);
    }

    private function variant(int $stock = 10, string $price = '500.00', array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create(['base_price' => $price]))
            ->create(['stock_quantity' => $stock, ...$attributes]);
    }

    private function addToCart(ProductVariant $variant, int $quantity, ?User $user = null): void
    {
        app(CartService::class)->add($user ?? $this->user, $variant->id, $quantity);
    }

    private function checkout(array $payload = [], ?string $key = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->postJson(
            '/api/v1/checkout',
            array_merge(['address_id' => $this->address->id, 'payment_method' => 'cod'], $payload),
            ['Idempotency-Key' => $key ?? (string) Str::uuid()],
        );
    }

    public function test_checkout_creates_an_order_deducts_stock_and_clears_the_cart(): void
    {
        $shirt = $this->variant(stock: 10, price: '500.00', attributes: ['sku' => 'SHIRT-M', 'size' => 'M', 'color' => 'Rose']);
        $dress = $this->variant(stock: 3, price: '900.00', attributes: ['price_override' => '950.00']);
        $this->addToCart($shirt, 2);
        $this->addToCart($dress, 1);

        $response = $this->checkout(['notes' => 'Please call first.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.subtotal', '1950.00')
            ->assertJsonPath('data.shipping_fee', '120.00')
            ->assertJsonPath('data.total_amount', '2070.00')
            ->assertJsonPath('data.shipping_address.city', 'Pasig')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.status_history.0.new_status', 'pending');

        $order = Order::firstOrFail();
        $this->assertSame($response->json('data.order_number'), $order->order_number);
        $this->assertMatchesRegularExpression('/^PC-\d{6}-[A-Z2-9]{6}$/', $order->order_number);
        $this->assertSame($this->user->id, $order->user_id);
        $this->assertSame('Please call first.', $order->notes);

        // Item snapshots.
        $item = $order->items()->where('sku_snapshot', 'SHIRT-M')->firstOrFail();
        $this->assertSame([$shirt->product->name, 'M', 'Rose', '500.00', 2, '1000.00'], [
            $item->product_name_snapshot, $item->size_snapshot, $item->color_snapshot, $item->unit_price, $item->quantity, $item->line_total,
        ]);

        // Stock deducted, with one sale movement per line pointing at the order.
        $this->assertSame(8, $shirt->fresh()->stock_quantity);
        $this->assertSame(2, $dress->fresh()->stock_quantity);

        $movements = InventoryMovement::where('movement_type', InventoryMovementType::Sale)->get();
        $this->assertCount(2, $movements);
        $this->assertSame(-3, (int) $movements->sum('quantity_change'));
        $this->assertTrue($movements->every(fn ($m) => $m->reference_id === $order->id && $m->reference_type === $order->getMorphClass()));

        $this->assertSame(0, CartItem::count());
    }

    public function test_client_supplied_prices_and_totals_are_ignored(): void
    {
        $this->addToCart($this->variant(price: '500.00'), 1);

        $this->checkout([
            'subtotal' => '1.00', 'shipping_fee' => '0.00', 'total_amount' => '1.00', 'discount_amount' => '499.00',
            'payment_status' => 'paid', 'order_status' => 'delivered',
            'items' => [['unit_price' => '1.00', 'quantity' => 50]],
        ])->assertCreated()->assertJsonPath('data.total_amount', '620.00');

        $order = Order::firstOrFail();
        $this->assertSame('620.00', $order->total_amount);
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->order_status);
        $this->assertSame(1, $order->items()->firstOrFail()->quantity);
    }

    public function test_the_price_is_taken_at_checkout_time_and_the_snapshot_survives_later_changes(): void
    {
        $variant = $this->variant(price: '500.00', attributes: ['size' => 'M']);
        $originalName = $variant->product->name;
        $this->addToCart($variant, 1);

        $variant->product->update(['base_price' => '650.00']);
        $this->checkout()->assertCreated()->assertJsonPath('data.subtotal', '650.00');

        $variant->product->update(['base_price' => '999.00', 'name' => 'Renamed Product']);
        $variant->update(['size' => 'XL']);

        $item = OrderItem::firstOrFail();
        $this->assertSame('650.00', $item->unit_price);
        $this->assertSame($originalName, $item->product_name_snapshot);
        $this->assertSame('M', $item->size_snapshot);
    }

    public function test_submitting_the_same_checkout_twice_creates_only_one_order(): void
    {
        $variant = $this->variant(stock: 10);
        $this->addToCart($variant, 2);
        $key = (string) Str::uuid();

        $first = $this->checkout(key: $key)->assertCreated()->json('data.order_number');
        $second = $this->checkout(key: $key)->assertOk()->json('data.order_number');

        $this->assertSame($first, $second);
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $variant->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::where('movement_type', InventoryMovementType::Sale)->count());
    }

    public function test_a_replayed_key_returns_the_original_order_even_after_the_cart_was_refilled(): void
    {
        $variant = $this->variant(stock: 10);
        $this->addToCart($variant, 1);
        $key = (string) Str::uuid();

        $first = $this->checkout(key: $key)->assertCreated()->json('data.order_number');

        $this->addToCart($variant, 3);
        $this->checkout(key: $key)->assertOk()->assertJsonPath('data.order_number', $first);

        $this->assertSame(1, Order::count());
        $this->assertSame(9, $variant->fresh()->stock_quantity);
        $this->assertSame(3, CartItem::firstOrFail()->quantity);
    }

    public function test_the_database_rejects_a_second_order_with_the_same_key_for_the_same_customer(): void
    {
        $this->addToCart($this->variant(), 1);
        $key = (string) Str::uuid();
        $this->checkout(key: $key)->assertCreated();

        $duplicate = Order::firstOrFail()->replicate();
        $duplicate->order_number = 'PC-DUPLICATE-1';

        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate->save();
    }

    public function test_the_same_key_from_a_different_customer_is_a_separate_order(): void
    {
        $other = User::factory()->create();
        $otherAddress = Address::factory()->for($other)->create();
        $variant = $this->variant();
        $key = (string) Str::uuid();

        $this->addToCart($variant, 1);
        $this->addToCart($variant, 1, $other);

        $this->checkout(key: $key)->assertCreated();
        $this->checkout(['address_id' => $otherAddress->id], $key, $other)->assertCreated();

        $this->assertSame(2, Order::count());
    }

    public function test_checkout_fails_and_changes_nothing_when_stock_is_insufficient(): void
    {
        $plenty = $this->variant(stock: 10);
        $scarce = $this->variant(stock: 5);
        $this->addToCart($plenty, 2);
        $this->addToCart($scarce, 4);

        // Someone else bought most of the scarce item after it went into this cart.
        ProductVariant::whereKey($scarce->id)->update(['stock_quantity' => 1]);
        $scarceLine = CartItem::where('product_variant_id', $scarce->id)->firstOrFail();

        $this->checkout()
            ->assertUnprocessable()
            ->assertJsonValidationErrors("items.{$scarceLine->id}")
            ->assertJsonMissingValidationErrors('items.'.CartItem::where('product_variant_id', $plenty->id)->value('id'));

        // Rolled back completely: no order, no stock change on either item, cart intact.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(10, $plenty->fresh()->stock_quantity);
        $this->assertSame(1, $scarce->fresh()->stock_quantity);
        $this->assertSame(2, CartItem::count());
    }

    public function test_a_failure_midway_through_checkout_rolls_everything_back(): void
    {
        $first = $this->variant(stock: 10);
        $second = $this->variant(stock: 10);
        $this->addToCart($first, 2);
        $this->addToCart($second, 2);

        // Make the second stock deduction blow up after the order and first deduction were written.
        $calls = 0;
        InventoryMovement::creating(function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Simulated failure');
            }
        });

        try {
            app(CheckoutService::class)->placeOrder($this->user, $this->address, 'rollback-test-key-0001');
            $this->fail('Checkout should have thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        } finally {
            InventoryMovement::flushEventListeners();
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
        $this->assertSame(2, CartItem::count());
    }

    public function test_checkout_rejects_items_that_are_no_longer_purchasable(): void
    {
        $variant = $this->variant();
        $this->addToCart($variant, 1);
        $variant->product->update(['status' => 'archived']);

        $this->checkout()->assertUnprocessable();

        $variant->product->update(['status' => 'published']);
        $variant->update(['is_active' => false]);

        $this->checkout()->assertUnprocessable();

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_checkout_requires_a_non_empty_cart_a_valid_address_cod_and_an_idempotency_key(): void
    {
        $this->checkout()->assertUnprocessable()->assertJsonValidationErrors('cart');

        $this->addToCart($this->variant(), 1);
        $foreignAddress = Address::factory()->create();

        $this->checkout(['address_id' => $foreignAddress->id])->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->checkout(['payment_method' => 'gcash'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(0, Order::count());
    }

    public function test_guests_cannot_check_out(): void
    {
        $this->postJson('/api/v1/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnauthorized();
    }

    public function test_two_customers_cannot_both_buy_the_last_unit(): void
    {
        $other = User::factory()->create();
        $otherAddress = Address::factory()->for($other)->create();
        $variant = $this->variant(stock: 1);

        $this->addToCart($variant, 1);
        $this->addToCart($variant, 1, $other);

        $this->checkout()->assertCreated();
        $this->checkout(['address_id' => $otherAddress->id], null, $other)->assertUnprocessable();

        $this->assertSame(1, Order::count());
        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }
}
