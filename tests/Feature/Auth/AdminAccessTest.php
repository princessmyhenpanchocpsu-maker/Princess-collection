<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A probe behind the exact middleware stack used by the real /admin route group.
        Route::middleware(['api', 'auth:sanctum', 'active', 'admin'])
            ->get('/api/v1/admin/_probe', fn () => response()->json(['ok' => true]));
    }

    public function test_a_guest_cannot_reach_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/_probe')->assertUnauthorized();
    }

    public function test_a_customer_cannot_reach_admin_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/_probe')
            ->assertForbidden();
    }

    public function test_an_admin_can_reach_admin_routes(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/v1/admin/_probe')
            ->assertOk();
    }

    public function test_a_deactivated_admin_cannot_reach_admin_routes(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->getJson('/api/v1/admin/_probe')
            ->assertForbidden();
    }

    public function test_the_admin_seeder_creates_one_admin_from_configuration(): void
    {
        config(['store.admin' => [
            'name' => 'Store Admin',
            'email' => 'Owner@Example.com',
            'password' => 'seeded-secret-1',
        ]]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::count());

        $admin = User::firstOrFail();

        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('seeded-secret-1', $admin->password));
    }

    public function test_the_admin_seeder_refuses_to_run_without_credentials(): void
    {
        config(['store.admin' => ['name' => 'Store Admin', 'email' => null, 'password' => null]]);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminUserSeeder::class);
    }
}
