import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import client from '../../api/client';
import { AddressLines } from '../../components/AddressForm';
import { PageHeader, StatTile } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import ConfirmationModal from '../../components/ConfirmationModal';
import { ErrorState } from '../../components/EmptyState';
import LoadingSpinner from '../../components/LoadingSpinner';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import { errorMessage } from '../../lib/errors';
import { formatDate, formatPeso } from '../../lib/format';
import { OrdersTable } from './Orders';

export default function CustomerDetail() {
    const { id } = useParams();
    const toast = useToast();
    const { data, loading, error, reload, setData } = useApi((signal) => client.get(`/admin/customers/${id}`, { signal }), [id]);
    const [page, setPage] = useState(1);
    const orders = useApi((signal) => client.get('/admin/orders', { params: { user_id: id, page, per_page: 10 }, signal }), [id, page]);
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);

    if (loading && !data) return <LoadingSpinner className="py-24" />;
    if (error) return <ErrorState error={error} onRetry={reload} title="We couldn’t load this customer" className="py-24" />;

    const customer = data.data;

    const setActive = async (isActive) => {
        setBusy(true);
        try {
            const response = await client.patch(`/admin/customers/${customer.id}`, { is_active: isActive });
            setData({ data: { ...customer, ...response.data.data, addresses: customer.addresses, orders_count: customer.orders_count, total_spent: customer.total_spent } });
            setConfirming(false);
            toast.success(isActive ? 'Account reactivated.' : 'Account deactivated.');
        } catch (failure) {
            toast.error(errorMessage(failure));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <PageHeader title={customer.name} description={`${customer.email}${customer.phone ? ` · ${customer.phone}` : ''}`}>
                <OrderStatusBadge status={customer.is_active ? 'active' : 'inactive'} className="px-3 py-1 text-sm" />
                {customer.is_active ? (
                    <Button variant="secondary" className="text-red-700 hover:border-red-400 hover:text-red-800" onClick={() => setConfirming(true)}>
                        Deactivate account
                    </Button>
                ) : (
                    <Button loading={busy} onClick={() => setActive(true)}>
                        Reactivate account
                    </Button>
                )}
                <Link to="/admin/customers" className="btn btn-secondary">
                    Back to customers
                </Link>
            </PageHeader>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <StatTile label="Orders" value={customer.orders_count} />
                <StatTile label="Total spent" value={formatPeso(customer.total_spent)} hint="Excludes cancelled orders" />
                <StatTile label="Customer since" value={formatDate(customer.joined_at)} />
            </div>

            <h2 className="mb-3 mt-8 font-semibold text-stone-900">Orders</h2>
            <OrdersTable resource={orders} onPageChange={setPage} emptyMessage="This customer has not placed an order yet." />

            <h2 className="mb-3 mt-8 font-semibold text-stone-900">Saved addresses</h2>
            {customer.addresses.length === 0 ? (
                <p className="card p-5 text-sm text-stone-500">No saved addresses.</p>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {customer.addresses.map((address) => (
                        <li key={address.id} className="card p-5">
                            <AddressLines address={address} className="text-stone-700" />
                            {address.is_default && <span className="mt-2 inline-block rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">Default</span>}
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmationModal
                open={confirming}
                title={`Deactivate ${customer.name}?`}
                message="They will be signed out and unable to log in or place orders. Their orders and details are kept, and you can reactivate the account at any time."
                confirmLabel="Deactivate account"
                cancelLabel="Cancel"
                loading={busy}
                onConfirm={() => setActive(false)}
                onCancel={() => setConfirming(false)}
            />
        </>
    );
}
