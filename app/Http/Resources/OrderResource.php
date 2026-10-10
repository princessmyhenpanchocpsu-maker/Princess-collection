<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return [
            'order_number' => $this->order_number,
            'status' => $this->order_status->value,
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shipping_fee,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'shipping_address' => $this->shipping_address_snapshot,
            'tracking_number' => $this->tracking_number,
            'notes' => $this->notes,
            'can_cancel' => $this->order_status->isCancellableByCustomer(),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'placed_at' => $this->created_at?->toIso8601String(),
            $this->mergeWhen($isAdmin, fn () => [
                'allowed_transitions' => array_map(fn (OrderStatus $s) => $s->value, $this->order_status->allowedTransitions()),
                'customer' => $this->whenLoaded('user', fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]),
            ]),
        ];
    }
}
