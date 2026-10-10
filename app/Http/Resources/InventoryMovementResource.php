<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->relationLoaded('variant') ? $this->variant : null;
        $reference = $this->relationLoaded('reference') ? $this->reference : null;

        return [
            'id' => $this->id,
            'type' => $this->movement_type->value,
            'quantity_change' => $this->quantity_change,
            'reason' => $this->reason,
            'order_number' => $reference instanceof Order ? $reference->order_number : null,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'variant' => $variant === null ? null : [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'product_name' => $variant->product?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
