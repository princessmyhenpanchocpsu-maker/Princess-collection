<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_an_admin_can_list_and_search_customers_with_order_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $maria = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);
        User::factory()->inactive()->create(['name' => 'Ana Reyes']);

        $this->placeOrder($maria, [[$this->makeVariant(price: '500.00'), 1]]);          // 620
        $cancelled = $this->placeOrder($maria, [[$this->makeVariant(price: '900.00'), 1]]);
        app(OrderService::class)->transition($cancelled, OrderStatus::Cancelled, $admin);

        $list = fn (string $query) => $this->actingAs($admin)->getJson("/api/v1/admin/customers?{$query}")->assertOk();

        // Admin accounts are not customers.
        $list('')->assertJsonCount(2, 'data')->assertJsonMissing(['email' => $admin->email]);
        $list('search=maria')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.total_spent', '620.00');
        $list('is_active=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana Reyes');
    }

    public function test_an_admin_can_view_a_customer_and_their_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Address::factory()->for($customer)->create(['is_default' => true]);
        $order = $this->placeOrder($customer);

        $this->actingAs($admin)->getJson("/api/v1/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email)
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonMissingPath('data.password');

        $this->actingAs($admin)->getJson("/api/v1/admin/orders?user_id={$customer->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $order->order_number);

        $this->actingAs($admin)->getJson("/api/v1/admin/customers/{$admin->id}")->assertNotFound();
    }

    public function test_deactivating_a_customer_locks_them_out_and_can_be_reversed(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$customer->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'password'])->assertUnprocessable();
        $this->actingAs($customer->fresh())->getJson('/api/v1/cart')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$customer->id}", ['is_active' => true])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'password'])->assertOk();
    }

    public function test_admin_accounts_cannot_be_deactivated_and_other_fields_cannot_be_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$admin->id}", ['is_active' => false])->assertNotFound();
        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$otherAdmin->id}", ['is_active' => false])->assertNotFound();

        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$customer->id}", ['is_active' => true, 'role' => 'admin', 'email' => 'x@example.com'])
            ->assertOk();

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($otherAdmin->fresh()->is_active);
        $this->assertFalse($customer->fresh()->isAdmin());
        $this->assertSame($customer->email, $customer->fresh()->email);
    }

    public function test_only_admins_can_manage_customers(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $this->getJson('/api/v1/admin/customers')->assertUnauthorized();

        $this->actingAs($customer);
        $this->getJson('/api/v1/admin/customers')->assertForbidden();
        $this->getJson("/api/v1/admin/customers/{$other->id}")->assertForbidden();
        $this->patchJson("/api/v1/admin/customers/{$other->id}", ['is_active' => false])->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
    }
}
