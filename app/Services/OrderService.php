<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Move an order to a new status. The order row is locked for the duration, so two
     * simultaneous requests (e.g. a customer cancelling while an admin confirms) are
     * applied one after the other and the second is judged against the updated status.
     *
     * @throws InvalidOrderTransitionException
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?User $actor,
        ?string $note = null,
        ?string $trackingNumber = null,
        bool $byCustomer = false,
    ): Order {
        DB::transaction(function () use ($order, $to, $actor, $note, $trackingNumber, $byCustomer) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->order_status;

            if ($byCustomer && ! $from->isCancellableByCustomer()) {
                throw new InvalidOrderTransitionException("This order is already {$from->value} and can no longer be cancelled. Please contact us for help.");
            }

            if (! $from->canTransitionTo($to)) {
                throw new InvalidOrderTransitionException("An order that is {$from->value} cannot be changed to {$to->value}.");
            }

            $locked->order_status = $to;

            if ($trackingNumber !== null) {
                $locked->tracking_number = $trackingNumber;
            }

            // Cash is collected at the door, so a delivered COD order is paid. Set only by an admin action.
            if ($to === OrderStatus::Delivered && $locked->payment_method === PaymentMethod::CashOnDelivery) {
                $locked->payment_status = PaymentStatus::Paid;
            }

            $locked->save();

            $this->recordHistory($locked, $from, $to, $actor, $note);

            if ($to === OrderStatus::Cancelled) {
                $this->restoreStock($locked, $actor);
            }
        });

        return $order->refresh();
    }

    /**
     * Set or correct the courier tracking number without changing the status.
     */
    public function updateTracking(Order $order, ?string $trackingNumber, ?User $actor): Order
    {
        DB::transaction(function () use ($order, $trackingNumber, $actor) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $locked->tracking_number = $trackingNumber;
            $locked->save();

            $this->recordHistory($locked, $locked->order_status, $locked->order_status, $actor,
                $trackingNumber ? "Tracking number set to {$trackingNumber}." : 'Tracking number removed.');
        });

        return $order->refresh();
    }

    private function recordHistory(Order $order, OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note): void
    {
        (new OrderStatusHistory)->forceFill([
            'order_id' => $order->id,
            'changed_by' => $actor?->id,
            'previous_status' => $from,
            'new_status' => $to,
            'note' => $note,
        ])->save();
    }

    /**
     * Put a cancelled order's items back on the shelf. Cancelled is a terminal status reached
     * under a row lock, so this runs once per order; the movement check is a second safeguard.
     */
    private function restoreStock(Order $order, ?User $actor): void
    {
        $alreadyRestored = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::CancellationRestock)
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->exists();

        if ($alreadyRestored) {
            return;
        }

        $items = $order->items()->whereNotNull('product_variant_id')->orderBy('product_variant_id')->get();
        $variants = ProductVariant::whereIn('id', $items->pluck('product_variant_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            if ($variant = $variants->get($item->product_variant_id)) {
                $this->inventory->adjust(
                    $variant,
                    $item->quantity,
                    InventoryMovementType::CancellationRestock,
                    $actor,
                    "Order {$order->order_number} cancelled",
                    $order,
                );
            }
        }
    }
}
