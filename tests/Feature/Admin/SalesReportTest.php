<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00'); // a Wednesday
        $this->admin = User::factory()->admin()->create();
    }

    private function orderOn(string $date, string $price, int $quantity = 1): Order
    {
        $order = $this->placeOrder(null, [[$this->makeVariant(price: $price), $quantity]]);
        Order::whereKey($order->id)->update(['created_at' => Carbon::parse($date)]);

        return $order;
    }

    private function report(string $query = '')
    {
        return $this->actingAs($this->admin)->getJson("/api/v1/admin/reports/sales?{$query}")->assertOk();
    }

    public function test_the_daily_report_groups_sales_and_excludes_cancelled_orders(): void
    {
        $this->orderOn('2026-10-14 09:00', '500.00');           // 620
        $this->orderOn('2026-10-14 23:30', '1000.00', 2);       // 2000, free shipping
        $this->orderOn('2026-10-12 12:00', '300.00');           // 420
        $cancelled = $this->orderOn('2026-10-12 13:00', '900.00');
        app(OrderService::class)->transition($cancelled, OrderStatus::Cancelled, $this->admin);
        $this->orderOn('2026-08-01 12:00', '700.00');           // outside the default 30 days

        $this->report()
            ->assertJsonPath('data.group', 'daily')
            ->assertJsonPath('data.date_from', '2026-09-15')
            ->assertJsonPath('data.date_to', '2026-10-14')
            ->assertJsonPath('data.rows', [
                ['period' => '2026-10-14', 'orders' => 2, 'items_sold' => 3, 'subtotal' => '2500.00', 'shipping' => '120.00', 'total' => '2620.00'],
                ['period' => '2026-10-12', 'orders' => 1, 'items_sold' => 1, 'subtotal' => '300.00', 'shipping' => '120.00', 'total' => '420.00'],
            ])
            ->assertJsonPath('data.summary.orders', 3)
            ->assertJsonPath('data.summary.items_sold', 4)
            ->assertJsonPath('data.summary.total', '3040.00')
            ->assertJsonPath('data.summary.average_order_value', '1013.33')
            ->assertJsonPath('data.summary.cancelled_orders', 1)
            ->assertJsonCount(3, 'data.bestsellers');
    }

    public function test_weekly_and_monthly_grouping_and_date_range_filtering(): void
    {
        $this->orderOn('2026-10-14 09:00', '500.00');   // week of Mon 12 Oct
        $this->orderOn('2026-10-12 09:00', '500.00');   // same week
        $this->orderOn('2026-10-11 09:00', '500.00');   // Sunday: week of Mon 5 Oct
        $this->orderOn('2026-09-30 09:00', '500.00');   // September

        $this->assertSame(
            ['2026-10-12' => 2, '2026-10-05' => 1, '2026-09-28' => 1],
            collect($this->report('group=weekly')->json('data.rows'))->pluck('orders', 'period')->all(),
        );

        $this->assertSame(
            ['2026-10-01' => 3, '2026-09-01' => 1],
            collect($this->report('group=monthly')->json('data.rows'))->pluck('orders', 'period')->all(),
        );

        $this->report('date_from=2026-10-11&date_to=2026-10-12')
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.summary.orders', 2);
    }

    public function test_report_filters_are_validated_and_admin_only(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/reports/sales?group=hourly&date_from=2026-10-14&date_to=2026-10-01')
            ->assertUnprocessable()->assertJsonValidationErrors(['group', 'date_to']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/reports/sales')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/reports/sales')->assertForbidden();
    }
}
