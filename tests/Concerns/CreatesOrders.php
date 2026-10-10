<?php

namespace Tests\Concerns;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Support\Str;

trait CreatesOrders
{
    protected function makeVariant(int $stock = 10, string $price = '500.00', array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->create(['base_price' => $price]))
            ->create(['stock_quantity' => $stock, ...$attributes]);
    }

    /**
     * Place a real order through the cart and checkout services.
     *
     * @param  list<array{0: ProductVariant, 1: int}>  $lines
     */
    protected function placeOrder(?User $user = null, array $lines = []): Order
    {
        $user ??= User::factory()->create();
        $lines = $lines ?: [[$this->makeVariant(), 2]];
        $address = $user->addresses()->first() ?? Address::factory()->for($user)->create(['is_default' => true]);

        foreach ($lines as [$variant, $quantity]) {
            app(CartService::class)->add($user, $variant->id, $quantity);
        }

        [$order] = app(CheckoutService::class)->placeOrder($user, $address, (string) Str::uuid());

        return $order;
    }
}
