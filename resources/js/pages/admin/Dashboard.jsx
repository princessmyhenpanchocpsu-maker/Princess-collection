import { Boxes, Clock, PhilippinePeso, ShoppingCart, Shirt, TriangleAlert, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import client from '../../api/client';
import { PageHeader, StatTile } from '../../components/admin/AdminUI';
import { ErrorState } from '../../components/EmptyState';
import LoadingSpinner from '../../components/LoadingSpinner';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import useApi from '../../hooks/useApi';
import { formatDate, formatPeso } from '../../lib/format';

function Panel({ title, to, linkLabel, children, empty }) {
    return (
        <section className="card">
            <div className="flex items-center justify-between border-b border-stone-200 px-5 py-3.5">
                <h2 className="font-semibold text-stone-900">{title}</h2>
                {to && (
                    <Link to={to} className="text-sm text-stone-600 underline-offset-4 hover:text-stone-900 hover:underline">
                        {linkLabel}
                    </Link>
                )}
            </div>
            {empty ? <p className="px-5 py-8 text-center text-sm text-stone-500">{empty}</p> : children}
        </section>
    );
}

export default function Dashboard() {
    const { data, loading, error, reload } = useApi((signal) => client.get('/admin/dashboard', { signal }));

    if (loading && !data) return <LoadingSpinner className="py-24" />;
    if (error) return <ErrorState error={error} onRetry={reload} className="py-24" />;

    const { stats, recent_orders: recentOrders, bestsellers, low_stock: lowStock } = data.data;

    return (
        <>
            <PageHeader title="Dashboard" description="Live figures from your store database." />

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <StatTile label="Total sales" value={formatPeso(stats.total_sales)} hint="Excludes cancelled orders" icon={PhilippinePeso} to="/admin/reports" Link={Link} />
                <StatTile label="Orders" value={stats.orders} icon={ShoppingCart} to="/admin/orders" Link={Link} />
                <StatTile label="Pending orders" value={stats.pending_orders} hint="Waiting for confirmation" icon={Clock} to="/admin/orders?status=pending" Link={Link} />
                <StatTile label="Products" value={stats.products} hint="Not archived" icon={Shirt} to="/admin/products" Link={Link} />
                <StatTile label="Customers" value={stats.customers} icon={Users} to="/admin/customers" Link={Link} />
                <StatTile label="Low stock" value={stats.low_stock} hint="Variants at or below threshold" icon={Boxes} to="/admin/inventory?stock=low" Link={Link} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <Panel title="Recent orders" to="/admin/orders" linkLabel="All orders" empty={recentOrders.length === 0 && 'No orders yet.'}>
                        <div className="overflow-x-auto">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Order</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col">Placed</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" className="text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentOrders.map((order) => (
                                        <tr key={order.order_number}>
                                            <td>
                                                <Link to={`/admin/orders/${order.order_number}`} className="font-medium text-stone-900 underline-offset-4 hover:underline">
                                                    {order.order_number}
                                                </Link>
                                            </td>
                                            <td>{order.customer?.name ?? order.recipient_name}</td>
                                            <td className="whitespace-nowrap">{formatDate(order.placed_at, { time: true })}</td>
                                            <td>
                                                <OrderStatusBadge status={order.status} />
                                            </td>
                                            <td className="text-right tabular-nums">{formatPeso(order.total_amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Bestsellers" to="/admin/reports" linkLabel="Reports" empty={bestsellers.length === 0 && 'No sales yet.'}>
                        <ol className="divide-y divide-stone-100">
                            {bestsellers.map((product, index) => (
                                <li key={product.name} className="flex items-center gap-3 px-5 py-3 text-sm">
                                    <span className="w-5 text-stone-400">{index + 1}</span>
                                    <span className="min-w-0 flex-1 truncate text-stone-800">{product.name}</span>
                                    <span className="whitespace-nowrap tabular-nums text-stone-500">{product.units_sold} sold</span>
                                </li>
                            ))}
                        </ol>
                    </Panel>

                    <Panel title="Low stock" to="/admin/inventory?stock=low" linkLabel="Inventory" empty={lowStock.length === 0 && 'Everything is well stocked.'}>
                        <ul className="divide-y divide-stone-100">
                            {lowStock.map((variant) => (
                                <li key={variant.id} className="flex items-center gap-3 px-5 py-3 text-sm">
                                    <TriangleAlert className={`h-4 w-4 shrink-0 ${variant.stock_quantity === 0 ? 'text-red-600' : 'text-amber-600'}`} aria-hidden="true" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-stone-800">{variant.product?.name}</span>
                                        <span className="text-xs text-stone-500">
                                            {variant.size} · {variant.color}
                                        </span>
                                    </span>
                                    <span className="whitespace-nowrap font-medium tabular-nums text-stone-800">{variant.stock_quantity === 0 ? 'Out of stock' : `${variant.stock_quantity} left`}</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>
            </div>
        </>
    );
}
