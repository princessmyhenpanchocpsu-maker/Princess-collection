<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    /**
     * Everything shown comes from the snapshot columns; the live product is only used for a link and thumbnail.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        $image = $product?->relationLoaded('images') ? $product->imageForColor($this->color_snapshot) : null;

        return [
            'id' => $this->id,
            'product_name' => $this->product_name_snapshot,
            'sku' => $this->sku_snapshot,
            'size' => $this->size_snapshot,
            'color' => $this->color_snapshot,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
            'product_slug' => $product?->slug,
            'image_url' => $image ? Storage::disk('public')->url($image->image_path) : null,
        ];
    }
}
