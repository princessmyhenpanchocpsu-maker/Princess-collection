<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Wraps the array produced by CartService::summary().
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->resource['lines']->map(function (array $line) {
                $variant = $line['variant'];
                $product = $variant->product;
                $image = $product?->imageForColor($variant->color);

                return [
                    'id' => $line['item']->id,
                    'quantity' => $line['item']->quantity,
                    'unit_price' => $variant->price,
                    'line_total' => $line['lineTotal'],
                    'available_stock' => $variant->stock_quantity,
                    // null when fine; otherwise unavailable | out_of_stock | insufficient_stock
                    'issue' => $line['issue'],
                    'variant' => [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'size' => $variant->size,
                        'color' => $variant->color,
                    ],
                    'product' => [
                        'name' => $product?->name ?? 'Unavailable item',
                        'slug' => $product?->slug,
                        'image_url' => $image ? Storage::disk('public')->url($image->image_path) : null,
                    ],
                ];
            })->values(),
            'item_count' => $this->resource['item_count'],
            'subtotal' => $this->resource['subtotal'],
            'shipping_fee' => $this->resource['shipping_fee'],
            'total' => $this->resource['total'],
            'can_checkout' => $this->resource['can_checkout'],
        ];
    }
}
