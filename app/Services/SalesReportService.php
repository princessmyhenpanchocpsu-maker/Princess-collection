<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only figures for the dashboard and sales reports, always computed from the database.
 * "Sales" everywhere means orders that are not cancelled.
 */
class SalesReportService
{
    // The first day of the day, ISO week (Monday) or month an order was placed in.
    private const PERIOD_SQL = [
        'daily' => 'date(created_at)',
        'weekly' => 'date(date_sub(created_at, interval weekday(created_at) day))',
        'monthly' => "date(date_format(created_at, '%Y-%m-01'))",
    ];

    /**
     * @return array<string, int|string>
     */
    public function dashboardStats(): array
    {
        return [
            'products' => Product::where('status', '!=', ProductStatus::Archived)->count(),
            'customers' => User::where('role', UserRole::Customer)->count(),
            'orders' => Order::count(),
            'pending_orders' => Order::where('order_status', OrderStatus::Pending)->count(),
            'low_stock' => $this->lowStockVariants()->count(),
            'total_sales' => $this->money($this->salesQuery()->sum('total_amount')),
        ];
    }

    /**
     * Active variants of live products at or below their low-stock threshold.
     */
    public function lowStockVariants(): Builder
    {
        return ProductVariant::query()
            ->active()
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->whereHas('product', fn (Builder $q) => $q->where('status', '!=', ProductStatus::Archived));
    }

    /**
     * Top products by units sold. Grouped by the name snapshot so products deleted later still count.
     *
     * @return Collection<int, object>
     */
    public function bestsellers(int $limit = 5, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.order_status', '!=', OrderStatus::Cancelled->value)
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->groupBy('order_items.product_name_snapshot')
            ->selectRaw('order_items.product_name_snapshot as name, max(products.slug) as slug, sum(order_items.quantity) as units_sold, sum(order_items.line_total) as revenue')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'name' => $row->name,
                'slug' => $row->slug,
                'units_sold' => (int) $row->units_sold,
                'revenue' => $this->money($row->revenue),
            ]);
    }

    /**
     * Sales grouped by day, week or month within a date range.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function sales(string $group, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $query = $this->salesQuery($from, $to);

        $period = self::PERIOD_SQL[$group];
        $itemsSold = '(select coalesce(sum(oi.quantity), 0) from order_items oi where oi.order_id = orders.id)';

        $rows = (clone $query)
            ->toBase()
            ->selectRaw("{$period} as period, count(*) as orders, sum({$itemsSold}) as items_sold, sum(subtotal) as subtotal, sum(shipping_fee) as shipping, sum(total_amount) as total")
            ->groupByRaw($period)
            ->orderByRaw("{$period} desc")
            ->get()
            ->map(fn (object $row) => [
                'period' => (string) $row->period,
                'orders' => (int) $row->orders,
                'items_sold' => (int) $row->items_sold,
                'subtotal' => $this->money($row->subtotal),
                'shipping' => $this->money($row->shipping),
                'total' => $this->money($row->total),
            ]);

        $orders = (int) $rows->sum('orders');
        $total = $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['total'], 2), '0.00');

        return [
            'rows' => $rows,
            'summary' => [
                'orders' => $orders,
                'items_sold' => (int) $rows->sum('items_sold'),
                'subtotal' => $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['subtotal'], 2), '0.00'),
                'shipping' => $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['shipping'], 2), '0.00'),
                'total' => $total,
                'average_order_value' => $orders > 0 ? bcdiv($total, (string) $orders, 2) : '0.00',
                'cancelled_orders' => Order::where('order_status', OrderStatus::Cancelled)
                    ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))
                    ->count(),
            ],
        ];
    }

    private function salesQuery(?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        return Order::query()
            ->where('order_status', '!=', OrderStatus::Cancelled)
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to));
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }
}
