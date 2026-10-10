<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** Set by the detail endpoint to include the description, gallery, variants and related products. */
    public bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : collect();
        // Variants read their fallback price from the product; point them at this instance to avoid a query each.
        $variants->each->setRelation('product', $this->resource);
        $prices = $variants->map(fn ($variant) => $variant->price_override ?? $this->base_price);

        $minPrice = $this->min_price ?? ($prices->isEmpty() ? $this->base_price : $prices->min());
        $maxPrice = $this->max_price ?? ($prices->isEmpty() ? $this->base_price : $prices->max());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'base_price' => $this->base_price,
            'price' => number_format((float) $minPrice, 2, '.', ''),
            'max_price' => number_format((float) $maxPrice, 2, '.', ''),
            'in_stock' => (int) ($this->total_stock ?? $variants->sum('stock_quantity')) > 0,
            'total_stock' => (int) ($this->total_stock ?? $variants->sum('stock_quantity')),
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'image' => $this->whenLoaded('images', fn () => $this->images->isEmpty() ? null : ProductImageResource::make($this->images->first())),
            'colors' => $variants->pluck('color')->unique()->values(),
            'sizes' => $variants->pluck('size')->unique()->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            $this->mergeWhen($this->detailed, fn () => [
                'description' => $this->description,
                'images' => ProductImageResource::collection($this->images),
                'variants' => ProductVariantResource::collection($variants),
                'related' => self::collection($this->whenLoaded('related')),
            ]),
        ];
    }
}
