<?php

namespace Tests\Feature\Admin;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rose Knit Crop Top',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Soft ribbed knit.',
            'base_price' => '449.00',
            'status' => 'published',
            'variants' => [
                ['size' => 'S', 'color' => 'Rose', 'stock_quantity' => 8],
                ['size' => 'M', 'color' => 'Rose', 'stock_quantity' => 0, 'price_override' => '479.00', 'sku' => 'custom-sku'],
            ],
        ], $overrides);
    }

    public function test_an_admin_can_create_a_product_with_variants_and_opening_stock(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'rose-knit-crop-top')
            ->assertJsonPath('data.total_stock', 8)
            ->assertJsonPath('data.variants.0.sku', 'RKCT-S-ROSE')
            ->assertJsonPath('data.variants.1.sku', 'CUSTOM-SKU')
            ->assertJsonPath('data.variants.1.price', '479.00');

        $variant = ProductVariant::where('sku', 'RKCT-S-ROSE')->firstOrFail();
        $this->assertSame(8, $variant->stock_quantity);

        // Opening stock is recorded as a movement by the admin; zero stock records nothing.
        $this->assertSame(1, InventoryMovement::count());
        $movement = InventoryMovement::firstOrFail();
        $this->assertSame(InventoryMovementType::StockIn, $movement->movement_type);
        $this->assertSame(8, $movement->quantity_change);
        $this->assertSame($admin->id, $movement->user_id);
    }

    public function test_product_creation_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        ProductVariant::factory()->create(['sku' => 'TAKEN-1']);

        $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => '', 'category_id' => 999, 'base_price' => '-5', 'status' => 'live',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'category_id', 'base_price', 'status', 'variants']);

        $this->actingAs($admin)->postJson('/api/v1/admin/products', $this->payload(['variants' => [
            ['size' => 'S', 'color' => 'Rose'],
            ['size' => 's', 'color' => 'rose'],
            ['size' => 'M', 'color' => 'Rose', 'sku' => 'taken-1'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['variants.1.color', 'variants.2.sku']);

        $this->actingAs($admin)->postJson('/api/v1/admin/products', $this->payload(['base_price' => '449.999']))
            ->assertUnprocessable()->assertJsonValidationErrors('base_price');

        // Only the product behind the pre-existing TAKEN-1 variant exists; nothing was created.
        $this->assertSame(1, Product::count());
    }

    public function test_an_admin_can_update_a_product_and_its_variants(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
        $existing = ProductVariant::factory()->for($product)->create(['size' => 'S', 'color' => 'Rose', 'stock_quantity' => 5]);
        ProductVariant::factory()->for($product)->create(['size' => 'L', 'color' => 'Rose']);
        $foreign = ProductVariant::factory()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/products/old-name', $this->payload([
            'name' => 'New Name',
            'category_id' => $product->category_id,
            'base_price' => '599.00',
            'variants' => [
                // Stock on an existing variant must be ignored here.
                ['id' => $existing->id, 'size' => 'S', 'color' => 'Blush', 'stock_quantity' => 999, 'is_active' => false],
                ['size' => 'M', 'color' => 'Blush', 'stock_quantity' => 4],
            ],
        ]))->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.slug', 'old-name')->assertJsonCount(3, 'data.variants');

        $existing->refresh();
        $this->assertSame('Blush', $existing->color);
        $this->assertFalse($existing->is_active);
        $this->assertSame(5, $existing->stock_quantity);
        $this->assertSame(4, $product->variants()->where('size', 'M')->firstOrFail()->stock_quantity);

        // A variant id from another product is rejected, as is a duplicate of an untouched variant.
        $this->actingAs($admin)->putJson('/api/v1/admin/products/old-name', $this->payload([
            'category_id' => $product->category_id,
            'variants' => [['id' => $foreign->id, 'size' => 'S', 'color' => 'Red']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');

        $this->actingAs($admin)->putJson('/api/v1/admin/products/old-name', $this->payload([
            'category_id' => $product->category_id,
            'variants' => [['size' => 'L', 'color' => 'rose']],
        ]))->assertUnprocessable()->assertJsonValidationErrors('variants.0.color');
    }

    public function test_deleting_a_product_archives_it_and_removes_it_from_the_storefront(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['slug' => 'summer-dress']);
        ProductVariant::factory()->for($product)->create();

        $this->actingAs($admin)->deleteJson('/api/v1/admin/products/summer-dress')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
        $this->assertNull($product->fresh()->deleted_at);

        $this->actingAs($admin)->getJson('/api/v1/admin/products?status=archived')->assertOk()->assertJsonCount(1, 'data');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/products/summer-dress')->assertNotFound();
        $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');
    }

    public function test_the_admin_product_list_is_searchable_filterable_and_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        $dress = Product::factory()->create(['name' => 'Floral Dress']);
        ProductVariant::factory()->for($dress)->create(['sku' => 'FD-S-BLUSH']);
        $draft = Product::factory()->draft()->create(['name' => 'Linen Pants']);
        Product::factory()->count(3)->create();

        $list = fn (string $query) => $this->actingAs($admin)->getJson("/api/v1/admin/products?{$query}")->assertOk();

        $list('per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
        $list('search=floral')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $dress->slug);
        $list('search=FD-S')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $dress->slug);
        $list('status=draft')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $draft->slug);
        $list("category_id={$dress->category_id}")->assertJsonCount(1, 'data');
    }

    public function test_only_admins_can_use_the_product_admin_api(): void
    {
        $product = Product::factory()->create();
        $payload = $this->payload();

        $requests = [
            ['getJson', '/api/v1/admin/products', []],
            ['postJson', '/api/v1/admin/products', $payload],
            ['getJson', "/api/v1/admin/products/{$product->slug}", []],
            ['putJson', "/api/v1/admin/products/{$product->slug}", $payload],
            ['deleteJson', "/api/v1/admin/products/{$product->slug}", []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $this->{$method}($uri, $data)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());

        foreach ($requests as [$method, $uri, $data]) {
            $this->{$method}($uri, $data)->assertForbidden();
        }

        $this->assertSame(1, Product::count());
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
    }
}
