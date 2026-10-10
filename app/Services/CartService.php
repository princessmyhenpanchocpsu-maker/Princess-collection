<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly StoreSettings $settings) {}

    public function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Add a variant to the cart, merging into the existing line if there is one.
     */
    public function add(User $user, int $variantId, int $quantity): CartItem
    {
        $variant = ProductVariant::with('product.category')->find($variantId);

        if (! $variant?->isPurchasable()) {
            throw ValidationException::withMessages(['product_variant_id' => 'This item is no longer available.']);
        }

        return DB::transaction(function () use ($user, $variant, $quantity) {
            // Locking the cart row serialises concurrent adds from the same customer.
            $cart = Cart::whereKey($this->cartFor($user)->id)->lockForUpdate()->firstOrFail();
            $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
            $inCart = (int) $item->quantity;

            $this->assertInStock($variant, $inCart + $quantity, $inCart);

            $item->quantity = $inCart + $quantity;
            $item->save();

            return $item;
        });
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $variant = $item->variant()->with('product.category')->firstOrFail();

        if (! $variant->isPurchasable()) {
            throw ValidationException::withMessages(['quantity' => 'This item is no longer available. Please remove it from your cart.']);
        }

        $this->assertInStock($variant, $quantity);

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    /**
     * The cart as the customer should see it, with every price and total calculated here on the server.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $cart = Cart::where('user_id', $user->id)->first();

        $items = $cart
            ? $cart->items()->with(['variant.product.category', 'variant.product.images'])->oldest()->orderBy('id')->get()
            : collect();

        $subtotal = '0.00';

        $lines = $items->map(function (CartItem $item) use (&$subtotal) {
            $variant = $item->variant;
            $purchasable = $variant->isPurchasable();
            $lineTotal = bcmul($variant->price, (string) $item->quantity, 2);

            $issue = match (true) {
                ! $purchasable => 'unavailable',
                $variant->stock_quantity === 0 => 'out_of_stock',
                $item->quantity > $variant->stock_quantity => 'insufficient_stock',
                default => null,
            };

            if ($purchasable) {
                $subtotal = bcadd($subtotal, $lineTotal, 2);
            }

            return compact('item', 'variant', 'lineTotal', 'issue');
        });

        $shipping = $lines->isEmpty() ? '0.00' : $this->settings->shippingFeeFor($subtotal);

        return [
            'lines' => $lines,
            'item_count' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'total' => bcadd($subtotal, $shipping, 2),
            'can_checkout' => $lines->isNotEmpty() && $lines->every(fn (array $line) => $line['issue'] === null),
        ];
    }

    private function assertInStock(ProductVariant $variant, int $wanted, int $alreadyInCart = 0): void
    {
        if ($wanted <= $variant->stock_quantity) {
            return;
        }

        $message = $variant->stock_quantity === 0
            ? 'This item is out of stock.'
            : "Only {$variant->stock_quantity} left in stock".($alreadyInCart > 0 ? " and you already have {$alreadyInCart} in your cart." : '.');

        throw ValidationException::withMessages(['quantity' => $message]);
    }
}
