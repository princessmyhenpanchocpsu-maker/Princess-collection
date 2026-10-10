import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import client from '../../api/client';
import { AddressLines } from '../../components/AddressForm';
import { PageHeader } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import ConfirmationModal from '../../components/ConfirmationModal';
import { ErrorState } from '../../components/EmptyState';
import FormInput from '../../components/FormInput';
import LoadingSpinner from '../../components/LoadingSpinner';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import OrderSummary, { LineItem } from '../../components/OrderSummary';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import { errorMessage, fieldErrors } from '../../lib/errors';
import { formatDate, formatPeso, titleCase } from '../../lib/format';

const ACTION_LABELS = {
    confirmed: 'Confirm order',
    processing: 'Start processing',
    shipped: 'Mark as shipped',
    delivered: 'Mark as delivered',
    cancelled: 'Cancel order',
};

export default function OrderDetail() {
    const { orderNumber } = useParams();
    const toast = useToast();
    const { data, loading, error, reload, setData } = useApi((signal) => client.get(`/admin/orders/${orderNumber}`, { signal }), [orderNumber]);
    const order = data?.data;

    const [note, setNote] = useState('');
    const [tracking, setTracking] = useState('');
    const [busy, setBusy] = useState(null);
    const [formError, setFormError] = useState(null);
    const [confirming, setConfirming] = useState(null);

    useEffect(() => {
        setTracking(order?.tracking_number ?? '');
    }, [order?.tracking_number]);

    if (loading && !order) return <LoadingSpinner className="py-24" />;
    if (error) return <ErrorState error={error} onRetry={reload} title="We couldn’t load this order" className="py-24" />;

    const send = async (payload, successMessage, action) => {
        setBusy(action);
        setFormError(null);
        try {
            const response = await client.patch(`/admin/orders/${order.order_number}/status`, payload);
            setData(response.data);
            setNote('');
            setConfirming(null);
            toast.success(successMessage);
        } catch (failure) {
            const validation = fieldErrors(failure);
            setFormError(validation.status ?? validation.tracking_number ?? validation.note ?? errorMessage(failure));
            setConfirming(null);
            reload();
        } finally {
            setBusy(null);
        }
    };

    const changeStatus = (status) => {
        const payload = { status, note: note.trim() || null };
        // Send the tracking number along when shipping, or whenever it was edited.
        if (tracking.trim() !== (order.tracking_number ?? '')) payload.tracking_number = tracking.trim() || null;

        return send(payload, `Order marked as ${status}.`, status);
    };

    const trackingChanged = tracking.trim() !== (order.tracking_number ?? '');
    const forward = order.allowed_transitions.filter((status) => status !== 'cancelled');
    const canCancel = order.allowed_transitions.includes('cancelled');

    return (
        <>
            <PageHeader title={`Order ${order.order_number}`} description={`Placed ${formatDate(order.placed_at, { time: true })}`}>
                <OrderStatusBadge status={order.status} className="px-3 py-1 text-sm" />
                <Link to="/admin/orders" className="btn btn-secondary">
                    Back to orders
                </Link>
            </PageHeader>

            <div className="grid gap-6 xl:grid-cols-[1fr_24rem]">
                <div className="space-y-6">
                    <section className="card p-5">
                        <h2 className="mb-4 font-semibold text-stone-900">Items</h2>
                        <div className="space-y-4">
                            {order.items.map((item) => (
                                <LineItem
                                    key={item.id}
                                    imageUrl={item.image_url}
                                    name={item.product_name}
                                    meta={`${item.size} · ${item.color} · SKU ${item.sku} · ${formatPeso(item.unit_price)} each`}
                                    quantity={item.quantity}
                                    price={item.line_total}
                                />
                            ))}
                        </div>
                        <div className="mt-5 border-t border-stone-200 pt-4 sm:ml-auto sm:max-w-xs">
                            <OrderSummary subtotal={order.subtotal} shippingFee={order.shipping_fee} discount={order.discount_amount} total={order.total_amount} />
                        </div>
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-4 font-semibold text-stone-900">Status history</h2>
                        <ol className="space-y-4">
                            {[...order.status_history].reverse().map((entry) => (
                                <li key={entry.id} className="flex gap-3 text-sm">
                                    <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-stone-400" aria-hidden="true" />
                                    <div>
                                        <p className="font-medium text-stone-900">
                                            {entry.previous_status && entry.previous_status !== entry.new_status ? `${titleCase(entry.previous_status)} → ${titleCase(entry.new_status)}` : titleCase(entry.new_status)}
                                        </p>
                                        {entry.note && <p className="text-stone-600">{entry.note}</p>}
                                        <p className="text-xs text-stone-500">
                                            {formatDate(entry.created_at, { time: true })}
                                            {entry.changed_by && ` · ${entry.changed_by.name ?? titleCase(entry.changed_by.role)} (${entry.changed_by.role})`}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>

                <aside className="space-y-6">
                    <section className="card space-y-4 p-5">
                        <h2 className="font-semibold text-stone-900">Update order</h2>
                        {formError && (
                            <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                                {formError}
                            </p>
                        )}

                        <div>
                            <FormInput label="Courier tracking number" maxLength={100} value={tracking} onChange={(event) => setTracking(event.target.value)} />
                            {trackingChanged && (
                                <Button
                                    variant="secondary"
                                    className="mt-2"
                                    loading={busy === 'tracking'}
                                    onClick={() => send({ tracking_number: tracking.trim() || null }, 'Tracking number saved.', 'tracking')}
                                >
                                    Save tracking number
                                </Button>
                            )}
                        </div>

                        {order.allowed_transitions.length === 0 ? (
                            <p className="rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-600">This order is {order.status} and can no longer change status.</p>
                        ) : (
                            <>
                                <FormInput as="textarea" rows={2} label="Note for the history" hint="Optional. The customer can see it." maxLength={500} value={note} onChange={(event) => setNote(event.target.value)} />
                                <div className="flex flex-col gap-2">
                                    {forward.map((status) => (
                                        <Button key={status} loading={busy === status} disabled={Boolean(busy)} onClick={() => changeStatus(status)}>
                                            {ACTION_LABELS[status]}
                                        </Button>
                                    ))}
                                    {canCancel && (
                                        <Button variant="secondary" className="text-red-700 hover:border-red-400 hover:text-red-800" disabled={Boolean(busy)} onClick={() => setConfirming('cancelled')}>
                                            Cancel order
                                        </Button>
                                    )}
                                </div>
                                {forward.includes('delivered') && <p className="text-xs text-stone-500">Marking a Cash on Delivery order as delivered also marks it as paid.</p>}
                            </>
                        )}
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-3 font-semibold text-stone-900">Customer</h2>
                        {order.customer ? (
                            <p className="text-sm text-stone-700">
                                <Link to={`/admin/customers/${order.customer.id}`} className="font-medium text-stone-900 underline-offset-4 hover:underline">
                                    {order.customer.name}
                                </Link>
                                <br />
                                {order.customer.email}
                                {order.customer.phone && (
                                    <>
                                        <br />
                                        {order.customer.phone}
                                    </>
                                )}
                            </p>
                        ) : (
                            <p className="text-sm text-stone-500">Customer record unavailable.</p>
                        )}
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-3 font-semibold text-stone-900">Delivery</h2>
                        <AddressLines address={order.shipping_address} className="text-stone-700" />
                        {order.notes && <p className="mt-3 rounded-lg bg-stone-100 px-3 py-2 text-sm text-stone-700">Customer note: {order.notes}</p>}
                    </section>

                    <section className="card p-5">
                        <h2 className="mb-3 font-semibold text-stone-900">Payment</h2>
                        <p className="flex items-center gap-2 text-sm text-stone-700">
                            Cash on Delivery <OrderStatusBadge status={order.payment_status} />
                        </p>
                    </section>
                </aside>
            </div>

            <ConfirmationModal
                open={confirming === 'cancelled'}
                title="Cancel this order?"
                message="The items go back into stock and the order can never be reopened. The customer will see it as cancelled."
                confirmLabel="Cancel order"
                cancelLabel="Keep order"
                loading={busy === 'cancelled'}
                onConfirm={() => changeStatus('cancelled')}
                onCancel={() => setConfirming(null)}
            />
        </>
    );
}
