<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = [], array $variant = []): Product
    {
        $product = Product::factory()->create($attributes);
        ProductVariant::factory()->for($product)->create($variant);

        return $product;
    }

    public function test_the_product_list_only_shows_published_products_in_active_categories(): void
    {
        $visible = $this->product(['name' => 'Visible Dress']);
        $this->product(['status' => 'draft']);
        $this->product(['status' => 'archived']);
        $this->product(['category_id' => Category::factory()->inactive()]);
        $this->product()->delete();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $visible->slug)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_hidden_products_return_404_on_the_detail_endpoint(): void
    {
        $draft = $this->product(['status' => 'draft']);
        $orphan = $this->product(['category_id' => Category::factory()->inactive()]);

        $this->getJson("/api/v1/products/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/v1/products/{$orphan->slug}")->assertNotFound();
        $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
    }

    public function test_product_details_include_variants_stock_and_related_products(): void
    {
        $product = $this->product(['base_price' => '500.00'], ['size' => 'M', 'color' => 'Rose', 'stock_quantity' => 3]);
        ProductVariant::factory()->for($product)->create(['size' => 'L', 'color' => 'Rose', 'price_override' => '650.00', 'stock_quantity' => 0]);
        ProductVariant::factory()->for($product)->inactive()->create(['size' => 'XL', 'color' => 'Rose']);
        $related = $this->product(['category_id' => $product->category_id]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.price', '500.00')
            ->assertJsonPath('data.max_price', '650.00')
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.0.price', '500.00')
            ->assertJsonPath('data.variants.0.stock_quantity', 3)
            ->assertJsonPath('data.variants.1.price', '650.00')
            ->assertJsonPath('data.variants.1.in_stock', false)
            ->assertJsonPath('data.related.0.slug', $related->slug);
    }

    public function test_products_can_be_searched_filtered_and_sorted(): void
    {
        $dresses = Category::factory()->create(['slug' => 'dresses']);
        $cheap = $this->product(['name' => 'Linen Day Dress', 'base_price' => '300.00', 'category_id' => $dresses], ['size' => 'S', 'color' => 'Rose']);
        $pricey = $this->product(['name' => 'Silk Evening Dress', 'base_price' => '1500.00', 'category_id' => $dresses], ['size' => 'M', 'color' => 'Black']);
        $soldOut = $this->product(['name' => 'Cotton Tee', 'base_price' => '250.00'], ['size' => 'S', 'color' => 'Black', 'stock_quantity' => 0]);

        $slugs = fn (string $query) => $this->getJson("/api/v1/products?{$query}")->assertOk()->json('data.*.slug');

        $this->assertSame([$cheap->slug, $pricey->slug], $slugs('search=dress&sort=price_asc'));
        $this->assertSame([$pricey->slug, $cheap->slug], $slugs('category=dresses&sort=price_desc'));
        $this->assertSame([$cheap->slug], $slugs('size=S&in_stock=1'));
        $this->assertSame([$soldOut->slug], $slugs('size=S&color=Black'));
        $this->assertSame([$pricey->slug], $slugs('min_price=1000'));
        $this->assertEqualsCanonicalizing([$cheap->slug, $soldOut->slug], $slugs('max_price=300'));
        $this->assertSame([], $slugs('has_sales=1'));
    }

    public function test_product_list_is_paginated_and_validates_its_query(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/products?sort=hacked&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page']);
    }

    public function test_categories_list_and_category_products(): void
    {
        $category = Category::factory()->create(['name' => 'Dresses', 'slug' => 'dresses']);
        $hidden = Category::factory()->inactive()->create();
        $inCategory = $this->product(['category_id' => $category]);
        $this->product();

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'dresses', 'products_count' => 1])
            ->assertJsonMissing(['slug' => $hidden->slug]);

        $this->getJson('/api/v1/categories/dresses/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $inCategory->slug)
            ->assertJsonPath('category.name', 'Dresses');

        $this->getJson("/api/v1/categories/{$hidden->slug}/products")->assertNotFound();
    }

    public function test_filter_options_and_public_settings_are_available(): void
    {
        $this->product(['base_price' => '400.00'], ['size' => 'L', 'color' => 'Sage']);
        $this->product(['base_price' => '900.00'], ['size' => 'XS', 'color' => 'Rose']);

        $this->getJson('/api/v1/products/filters')
            ->assertOk()
            ->assertJsonPath('data.sizes', ['XS', 'L'])
            ->assertJsonPath('data.colors', ['Rose', 'Sage'])
            ->assertJsonPath('data.price.min', '400.00')
            ->assertJsonPath('data.price.max', '900.00');

        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.shipping_fee', '120.00');
    }

    public function test_the_demo_seeder_creates_the_catalogue_once_with_matching_stock_movements(): void
    {
        Storage::fake('public');

        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame(5, Category::count());
        $this->assertSame(8, Product::count());
        $this->assertSame(95, ProductVariant::count());
        $this->assertGreaterThan(0, ProductVariant::where('stock_quantity', 0)->count());

        // Every unit of stock is explained by a movement record.
        $this->assertSame(
            (int) ProductVariant::sum('stock_quantity'),
            (int) InventoryMovement::sum('quantity_change'),
        );

        $this->getJson('/api/v1/products?per_page=48')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson('/api/v1/products/elegant-midi-dress')
            ->assertOk()
            ->assertJsonPath('data.price', '1499.00')
            ->assertJsonPath('data.max_price', '1599.00');
    }
}
