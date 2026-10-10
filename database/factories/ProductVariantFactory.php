<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            // Random suffix keeps (product, size, color) unique when several variants share a product.
            'color' => 'Color '.Str::upper(Str::random(6)),
            'price_override' => null,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_quantity' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
