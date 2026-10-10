<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_the_dashboard_reports_real_figures_from_the_database(): void
    {
        $admin = User::factory()->admin()->create();
        $popular = $this->makeVariant(stock: 20, price: '500.00');
        $other = $this->makeVariant(stock: 6, price: '1000.00');
        Product::factory()->archived()->create();

        $this->placeOrder(null, [[$popular, 5]]);                        // 2500 + free shipping
        $this->placeOrder(null, [[$popular, 1], [$other, 1]]);           // 1500 + 120
        $cancelled = $this->placeOrder(null, [[$other, 2]]);             // excluded from sales
        app(OrderService::class)->transition($cancelled, OrderStatus::Cancelled, $admin);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertOk();

        $response->assertJsonPath('data.stats', [
            'products' => 2,
            'customers' => 3,
            'orders' => 3,
            'pending_orders' => 2,
            'low_stock' => 1,
            'total_sales' => '4120.00',
        ]);

        $response->assertJsonCount(3, 'data.recent_orders')
            ->assertJsonPath('data.recent_orders.0.order_number', $cancelled->order_number)
            ->assertJsonPath('data.bestsellers.0.name', $popular->product->name)
            ->assertJsonPath('data.bestsellers.0.units_sold', 6)
            ->assertJsonPath('data.bestsellers.0.revenue', '3000.00')
            ->assertJsonPath('data.bestsellers.1.units_sold', 1)
            ->assertJsonCount(1, 'data.low_stock')
            ->assertJsonPath('data.low_stock.0.sku', $other->sku)
            ->assertJsonPath('data.low_stock.0.stock_quantity', 5);
    }

    public function test_an_empty_store_reports_zeros(): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.orders', 0)
            ->assertJsonPath('data.stats.total_sales', '0.00')
            ->assertJsonPath('data.bestsellers', [])
            ->assertJsonPath('data.recent_orders', []);
    }

    public function test_only_admins_can_see_the_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }
}
