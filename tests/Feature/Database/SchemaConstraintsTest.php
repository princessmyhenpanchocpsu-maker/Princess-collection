<?php

namespace Tests\Feature\Database;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariant(array $overrides = []): ProductVariant
    {
        $category = Category::firstOrCreate(['slug' => 'dresses'], ['name' => 'Dresses']);

        $product = Product::firstOrCreate(['slug' => 'floral-summer-dress'], [
            'category_id' => $category->id,
            'name' => 'Floral Summer Dress',
            'base_price' => '899.00',
            'status' => ProductStatus::Published,
        ]);

        $variant = $product->variants()->make(array_merge([
            'sku' => 'FSD-S-PINK',
            'size' => 'S',
            'color' => 'Pink',
        ], $overrides));
        $variant->stock_quantity = 3;
        $variant->save();

        return $variant;
    }

    public function test_models_are_related_and_prices_fall_back_to_the_product(): void
    {
        $variant = $this->makeVariant();
        $override = $this->makeVariant(['sku' => 'FSD-M-PINK', 'size' => 'M', 'price_override' => '949.50']);

        $product = $variant->product;
        $product->images()->create(['image_path' => 'products/demo.jpg', 'sort_order' => 1]);

        $this->assertSame('Dresses', $product->category->name);
        $this->assertCount(2, $product->variants);
        $this->assertCount(1, $product->images);
        $this->assertSame('899.00', $variant->price);
        $this->assertSame('949.50', $override->price);
        $this->assertTrue($variant->isLowStock());
        $this->assertTrue(Product::published()->whereKey($product->id)->exists());
    }

    public function test_a_product_cannot_have_two_variants_with_the_same_size_and_color(): void
    {
        $this->makeVariant();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->makeVariant(['sku' => 'FSD-S-PINK-2']);
    }

    public function test_stock_can_never_go_below_zero(): void
    {
        $variant = $this->makeVariant();

        try {
            ProductVariant::whereKey($variant->id)->decrement('stock_quantity', 4);
            $this->fail('The database accepted a negative stock quantity.');
        } catch (QueryException) {
            $this->assertSame(3, $variant->fresh()->stock_quantity);
        }
    }

    public function test_a_cart_holds_one_line_per_variant(): void
    {
        $variant = $this->makeVariant();
        $cart = User::factory()->create()->cart()->create();

        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);
    }

    public function test_order_items_keep_their_snapshot_when_the_product_is_removed(): void
    {
        $variant = $this->makeVariant();
        $user = User::factory()->create();

        $order = new Order;
        $order->forceFill([
            'order_number' => 'PC-TEST-0001',
            'user_id' => $user->id,
            'recipient_name' => 'Maria Santos',
            'recipient_phone' => '09171234567',
            'shipping_address_snapshot' => ['city' => 'Quezon City'],
            'subtotal' => '899.00',
            'total_amount' => '899.00',
        ])->save();

        $item = new OrderItem;
        $item->forceFill([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Floral Summer Dress',
            'sku_snapshot' => 'FSD-S-PINK',
            'size_snapshot' => 'S',
            'color_snapshot' => 'Pink',
            'unit_price' => '899.00',
            'quantity' => 1,
            'line_total' => '899.00',
        ])->save();

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->order_status);
        $this->assertSame(['city' => 'Quezon City'], $order->shipping_address_snapshot);
        $this->assertSame('899.00', $order->total_amount);
        $this->assertTrue($user->orders->contains($order));

        // Hard-deleting the product (not possible through the app, which only archives) must not erase history.
        $variant->product->forceDelete();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame('Floral Summer Dress', $item->product_name_snapshot);
        $this->assertSame('899.00', $item->line_total);
    }
}
