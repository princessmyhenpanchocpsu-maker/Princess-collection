<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StoreSettings $settings,
    ) {}

    /**
     * Turn the customer's saved cart into an order.
     *
     * Nothing from the client is trusted except which address to ship to: items come from the
     * database cart, and every price, fee and total is recalculated here. The whole operation is
     * one transaction, so any failure leaves stock, cart and orders exactly as they were.
     *
     * @return array{0: Order, 1: bool} the order, and whether this call created it
     *
     * @throws CheckoutException
     */
    public function placeOrder(User $user, Address $address, string $idempotencyKey, ?string $notes = null): array
    {
        try {
            return DB::transaction(function () use ($user, $address, $idempotencyKey, $notes) {
                // Lock the cart first: a double-click or retry from the same customer waits here,
                // then finds the order the first request created.
                $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();

                if ($existing = $this->findByKey($user, $idempotencyKey)) {
                    return [$existing, false];
                }

                $items = $cart ? $cart->items()->orderBy('id')->get() : collect();

                if ($items->isEmpty()) {
                    throw new CheckoutException('Your cart is empty.');
                }

                // Lock every variant row (in id order, to avoid deadlocks) until the transaction ends.
                $variants = ProductVariant::with('product.category')
                    ->whereIn('id', $items->pluck('product_variant_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $errors = [];

                foreach ($items as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $label = $variant ? "{$variant->product?->name} ({$variant->size}, {$variant->color})" : 'An item';

                    if (! $variant?->isPurchasable()) {
                        $errors["items.{$item->id}"] = ["{$label} is no longer available."];
                    } elseif ($variant->stock_quantity === 0) {
                        $errors["items.{$item->id}"] = ["{$label} is out of stock."];
                    } elseif ($item->quantity > $variant->stock_quantity) {
                        $errors["items.{$item->id}"] = ["Only {$variant->stock_quantity} left of {$label}."];
                    }
                }

                if ($errors) {
                    throw new CheckoutException('Some items in your cart need attention before you can check out.', $errors);
                }

                $subtotal = '0.00';
                $lines = [];

                foreach ($items as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    $unitPrice = $variant->price;
                    $lineTotal = bcmul($unitPrice, (string) $item->quantity, 2);
                    $subtotal = bcadd($subtotal, $lineTotal, 2);
                    $lines[] = [$variant, $item->quantity, $unitPrice, $lineTotal];
                }

                $shippingFee = $this->settings->shippingFeeFor($subtotal);

                $order = new Order;
                $order->forceFill([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->phone,
                    'shipping_address_snapshot' => $address->only([
                        'recipient_name', 'phone', 'address_line', 'barangay', 'city', 'province', 'postal_code', 'delivery_instructions',
                    ]),
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'discount_amount' => '0.00',
                    'total_amount' => bcadd($subtotal, $shippingFee, 2),
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'payment_status' => PaymentStatus::Unpaid,
                    'order_status' => OrderStatus::Pending,
                    'notes' => $notes,
                ])->save();

                foreach ($lines as [$variant, $quantity, $unitPrice, $lineTotal]) {
                    (new OrderItem)->forceFill([
                        'order_id' => $order->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'product_name_snapshot' => $variant->product->name,
                        'sku_snapshot' => $variant->sku,
                        'size_snapshot' => $variant->size,
                        'color_snapshot' => $variant->color,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                    ])->save();

                    $this->inventory->adjust($variant, -$quantity, InventoryMovementType::Sale, $user, "Order {$order->order_number}", $order);
                }

                (new OrderStatusHistory)->forceFill([
                    'order_id' => $order->id,
                    'changed_by' => $user->id,
                    'previous_status' => null,
                    'new_status' => OrderStatus::Pending,
                    'note' => 'Order placed.',
                ])->save();

                $cart->items()->delete();

                return [$order, true];
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with the same key raced past the first check; the database let only one win.
            if ($existing = $this->findByKey($user, $idempotencyKey)) {
                return [$existing, false];
            }

            throw $e;
        }
    }

    private function findByKey(User $user, string $idempotencyKey): ?Order
    {
        return Order::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
    }

    private function generateOrderNumber(): string
    {
        // No 0/O or 1/I, so numbers are easy to read out over the phone.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $number = 'PC-'.now()->format('ymd').'-'.$suffix;
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
