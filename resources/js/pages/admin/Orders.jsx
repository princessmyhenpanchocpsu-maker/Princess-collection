import { Link } from 'react-router-dom';
import client from '../../api/client';
import { DataTable, PageHeader, SearchInput } from '../../components/admin/AdminUI';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { formatDate, formatPeso, titleCase } from '../../lib/format';

export const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

export function OrdersTable({ resource, onPageChange, toolbar, emptyMessage }) {
    return (
        <DataTable
            resource={resource}
            columns={['Order', 'Customer', 'Placed', 'Items', 'Payment', 'Status', '>Total']}
            emptyTitle="No orders found"
            emptyMessage={emptyMessage}
            onPageChange={onPageChange}
            toolbar={toolbar}
        >
            {(rows) =>
                rows.map((order) => (
                    <tr key={order.order_number}>
                        <td>
                            <Link to={`/admin/orders/${order.order_number}`} className="whitespace-nowrap font-medium text-stone-900 underline-offset-4 hover:underline">
                                {order.order_number}
                            </Link>
                        </td>
                        <td>
                            <p className="text-stone-900">{order.customer?.name ?? order.recipient_name}</p>
                            <p className="text-xs text-stone-500">{order.customer?.email}</p>
                        </td>
                        <td className="whitespace-nowrap">{formatDate(order.placed_at, { time: true })}</td>
                        <td className="tabular-nums">{order.items_count}</td>
                        <td>
                            <OrderStatusBadge status={order.payment_status} />
                        </td>
                        <td>
                            <OrderStatusBadge status={order.status} />
                        </td>
                        <td className="whitespace-nowrap text-right tabular-nums">{formatPeso(order.total_amount)}</td>
                    </tr>
                ))
            }
        </DataTable>
    );
}

export default function Orders() {
    const [query, setQuery, key] = useQueryParams();
    const resource = useApi((signal) => client.get('/admin/orders', { params: query, signal }), [key]);

    return (
        <>
            <PageHeader title="Orders" description="Review orders and move them through fulfilment." />
            <OrdersTable
                resource={resource}
                onPageChange={(page) => setQuery({ page })}
                emptyMessage="Try a different search, status or date range."
                toolbar={
                    <>
                        <SearchInput value={query.search} onChange={(search) => setQuery({ search })} placeholder="Order no., name, email, phone" className="w-full sm:w-72" />
                        <select value={query.status ?? ''} onChange={(event) => setQuery({ status: event.target.value })} className="input w-auto" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            {ORDER_STATUSES.map((status) => (
                                <option key={status} value={status}>
                                    {titleCase(status)}
                                </option>
                            ))}
                        </select>
                        <label className="flex items-center gap-2 text-sm text-stone-600">
                            From
                            <input type="date" value={query.date_from ?? ''} max={query.date_to} onChange={(event) => setQuery({ date_from: event.target.value })} className="input w-auto" />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-stone-600">
                            To
                            <input type="date" value={query.date_to ?? ''} min={query.date_from} onChange={(event) => setQuery({ date_to: event.target.value })} className="input w-auto" />
                        </label>
                    </>
                }
            />
        </>
    );
}
