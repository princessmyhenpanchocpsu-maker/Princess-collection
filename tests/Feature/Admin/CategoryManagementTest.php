<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_can_create_a_category_with_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post('/api/v1/admin/categories', [
            'name' => 'Summer Dresses',
            'description' => 'Light and breezy.',
            'image' => $this->pngUpload('My Photo (1).png'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.slug', 'summer-dresses')->assertJsonPath('data.is_active', true);

        $category = Category::firstOrFail();
        $this->assertMatchesRegularExpression('#^categories/[0-9a-f-]{36}\.png$#', $category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_category_names_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Dresses']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/categories', ['name' => 'Dresses'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_an_admin_can_list_search_and_update_categories(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Pants', 'slug' => 'pants']);
        Category::factory()->inactive()->create(['name' => 'Old Stock']);

        $this->actingAs($admin)->getJson('/api/v1/admin/categories')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/v1/admin/categories?search=pan')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->putJson('/api/v1/admin/categories/pants', ['name' => 'Pants & Trousers', 'description' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pants & Trousers')
            ->assertJsonPath('data.slug', 'pants');

        $this->assertSame('Updated', $category->fresh()->description);
    }

    public function test_deleting_a_category_archives_it_and_hides_its_products(): void
    {
        $category = Category::factory()->create(['slug' => 'shorts']);
        $product = Product::factory()->create(['category_id' => $category]);

        $this->actingAs($this->admin())->deleteJson('/api/v1/admin/categories/shorts')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/categories')->assertJsonMissing(['slug' => 'shorts']);
        $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound();
    }

    public function test_only_admins_can_use_the_category_admin_api(): void
    {
        $category = Category::factory()->create();

        $requests = [
            ['getJson', '/api/v1/admin/categories', []],
            ['postJson', '/api/v1/admin/categories', ['name' => 'Hacked']],
            ['getJson', "/api/v1/admin/categories/{$category->slug}", []],
            ['putJson', "/api/v1/admin/categories/{$category->slug}", ['name' => 'Hacked']],
            ['deleteJson', "/api/v1/admin/categories/{$category->slug}", []],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $this->{$method}($uri, $payload)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());

        foreach ($requests as [$method, $uri, $payload]) {
            $this->{$method}($uri, $payload)->assertForbidden();
        }

        $this->assertSame(1, Category::count());
        $this->assertTrue($category->fresh()->is_active);
    }
}
