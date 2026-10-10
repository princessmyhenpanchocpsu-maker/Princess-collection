<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'base_price' => fake()->numberBetween(3, 15) * 100 - 1,
            'status' => ProductStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Archived]);
    }
}
