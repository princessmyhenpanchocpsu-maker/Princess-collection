import { Package } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../../api/client';
import EmptyState, { ErrorState } from '../../components/EmptyState';
import LoadingSpinner from '../../components/LoadingSpinner';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import Pagination from '../../components/Pagination';
import useApi from '../../hooks/useApi';
import { formatDate, formatPeso } from '../../lib/format';

export default function Orders() {
    const [page, setPage] = useState(1);
    const { data, loading, error, reload } = useApi((signal) => client.get('/orders', { params: { page }, signal }), [page]);

    if (loading && !data) return <LoadingSpinner />;
    if (error) return <ErrorState error={error} onRetry={reload} />;
    if (data.data.length === 0) {
        return (
            <EmptyState
                icon={Package}
                title="No orders yet"
                message="When you place an order, you can follow it here."
                action={
                    <Link to="/shop" className="btn btn-primary">
                        Start shopping
                    </Link>
                }
            />
        );
    }

    return (
        <div>
            <h2 className="mb-5 font-display text-xl font-semibold text-brown">Order history</h2>
            <ul className={`space-y-3 ${loading ? 'opacity-60' : ''}`}>
                {data.data.map((order) => (
                    <li key={order.order_number}>
                        <Link to={`/orders/${order.order_number}`} className="card flex flex-wrap items-center justify-between gap-4 p-5 transition hover:shadow-md">
                            <div>
                                <p className="font-medium text-ink">{order.order_number}</p>
                                <p className="mt-0.5 text-sm text-brown/70">
                                    {formatDate(order.placed_at)} · {order.items_count} {order.items_count === 1 ? 'item' : 'items'}
                                </p>
                            </div>
                            <div className="flex items-center gap-4">
                                <OrderStatusBadge status={order.status} />
                                <p className="font-semibold tabular-nums text-ink">{formatPeso(order.total_amount)}</p>
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
            <Pagination meta={data.meta} onPageChange={setPage} className="mt-6" />
        </div>
    );
}
