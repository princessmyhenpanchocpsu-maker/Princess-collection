<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<string, mixed>  $data  validated ProductRequest data
     */
    public function create(array $data, User $admin): Product
    {
        return DB::transaction(function () use ($data, $admin) {
            $product = Product::create([
                ...Arr::only($data, ['name', 'category_id', 'description', 'base_price', 'status']),
                'slug' => $data['slug'] ?? Slugger::unique(Product::class, $data['name']),
            ]);

            $this->saveVariants($product, $data['variants'], $admin);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated ProductRequest data
     */
    public function update(Product $product, array $data, User $admin): Product
    {
        return DB::transaction(function () use ($product, $data, $admin) {
            // The slug stays stable when the name changes so existing links keep working.
            $product->update(array_filter(
                Arr::only($data, ['name', 'slug', 'category_id', 'description', 'base_price', 'status']),
                fn ($value, $key) => $key !== 'slug' || filled($value),
                ARRAY_FILTER_USE_BOTH,
            ));

            if (isset($data['variants'])) {
                $this->saveVariants($product, $data['variants'], $admin);
            }

            return $product;
        });
    }

    /**
     * Update the rows that carry an id and create the rest. Variants are never deleted here
     * (orders and stock history point at them); switch `is_active` off to retire one.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function saveVariants(Product $product, array $rows, User $admin): void
    {
        foreach ($rows as $row) {
            $attributes = [
                'size' => trim($row['size']),
                'color' => trim($row['color']),
                'price_override' => $row['price_override'] ?? null,
                'low_stock_threshold' => $row['low_stock_threshold'] ?? 5,
                'is_active' => $row['is_active'] ?? true,
            ];
            $sku = Str::upper(trim((string) ($row['sku'] ?? '')));

            if (! empty($row['id'])) {
                $variant = $product->variants()->findOrFail($row['id']);
                $variant->update($sku === '' ? $attributes : [...$attributes, 'sku' => $sku]);

                continue;
            }

            $variant = $product->variants()->create([
                ...$attributes,
                'sku' => $sku !== '' ? $sku : $this->generateSku($product, $attributes['size'], $attributes['color']),
            ]);

            if (($row['stock_quantity'] ?? 0) > 0) {
                $this->inventory->adjust($variant, (int) $row['stock_quantity'], InventoryMovementType::StockIn, $admin, 'Opening stock');
            }
        }
    }

    private function generateSku(Product $product, string $size, string $color): string
    {
        $initials = Str::of($product->name)->upper()->explode(' ')->filter()->map(fn ($word) => $word[0])->take(6)->implode('');
        $base = Str::upper(Str::slug("{$initials} {$size} ".Str::slug($color, '')));
        $sku = $base;
        $suffix = 2;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }
}
