import { CheckCircle2, Circle, PartyPopper, SearchX, Truck } from 'lucide-react';
import { useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import client from '../api/client';
import { AddressLines } from '../components/AddressForm';
import ConfirmationModal from '../components/ConfirmationModal';
import EmptyState, { ErrorState } from '../components/EmptyState';
import FormInput from '../components/FormInput';
import LoadingSpinner from '../components/LoadingSpinner';
import OrderStatusBadge from '../components/OrderStatusBadge';
import OrderSummary, { LineItem } from '../components/OrderSummary';
import { useToast } from '../context/ToastContext';
import useApi from '../hooks/useApi';
import { errorMessage, fieldErrors } from '../lib/errors';
import { formatDate, formatPeso, titleCase } from '../lib/format';

const STEPS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

function Progress({ status }) {
    if (status === 'cancelled') return <p className="rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700">This order was cancelled.</p>;

    const current = STEPS.indexOf(status);

    return (
        <ol className="grid grid-cols-5 gap-1">
            {STEPS.map((step, index) => {
                const done = index <= current;
                const Icon = done ? CheckCircle2 : Circle;

                return (
                    <li key={step} className="flex flex-col items-center text-center" aria-current={index === current ? 'step' : undefined}>
                        <Icon className={`h-6 w-6 ${done ? 'text-emerald-600' : 'text-brown/25'}`} aria-hidden="true" />
                        <span className={`mt-1.5 text-[11px] sm:text-xs ${done ? 'font-medium text-ink' : 'text-brown/50'}`}>{titleCase(step)}</span>
                    </li>
                );
            })}
        </ol>
    );
}

export default function OrderDetails() {
    const { orderNumber } = useParams();
    const { state } = useLocation();
    const toast = useToast();
    const { data, loading, error, reload, setData } = useApi((signal) => client.get(`/orders/${orderNumber}`, { signal }), [orderNumber]);
    const [cancelling, setCancelling] = useState(false);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [cancelError, setCancelError] = useState(null);

    if (loading && !data) return <LoadingSpinner className="min-h-[50vh]" />;
    if (error?.response?.status === 404) {
        return (
            <EmptyState
                icon={SearchX}
                title="Order not found"
                message="Check the order number, or look it up in your order history."
                action={
                    <Link to="/account/orders" className="btn btn-primary">
                        View my orders
                    </Link>
                }
                className="py-24"
            />
        );
    }
    if (error) return <ErrorState error={error} onRetry={reload} className="py-24" />;

    const order = data.data;

    const cancel = async () => {
        setBusy(true);
        setCancelError(null);
        try {
            const { data: updated } = await client.post(`/orders/${order.order_number}/cancel`, { reason: reason.trim() || null });
            setData(updated);
            setCancelling(false);
            toast.success('Your order has been cancelled.');
        } catch (failure) {
            setCancelError(fieldErrors(failure).status ?? errorMessage(failure));
            reload();
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="container-page py-10">
            {state?.justPlaced && (
                <div className="mb-8 flex items-start gap-3 rounded-2xl bg-emerald-50 px-5 py-4 text-emerald-900">
                    <PartyPopper className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    <div>
                        <p className="font-medium">Thank you! Your order has been placed.</p>
                        <p className="mt-0.5 text-sm">Please prepare {formatPeso(order.total_amount)} for the courier. We’ll update the status here as it moves along.</p>
                    </div>
                </div>
            )}

            <nav aria-label="Breadcrumb" className="mb-3 text-xs text-brown/60">
                <Link to="/account/orders" className="hover:text-ink">
                    My orders
                </Link>
                <span className="mx-1.5">/</span>
                <span className="text-brown">{order.order_number}</span>
            </nav>
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="font-display text-3xl font-semibold text-brown">Order {order.order_number}</h1>
                <OrderStatusBadge status={order.status} />
            </div>
            <p className="mt-1 text-sm text-brown/70">Placed on {formatDate(order.placed_at, { time: true })}</p>

            <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
                <div className="space-y-6">
                    <section className="card p-6">
                        <h2 className="mb-5 font-display text-xl font-semibold text-brown">Tracking</h2>
                        <Progress status={order.status} />
                        {order.tracking_number && (
                            <p className="mt-5 flex items-center gap-2 rounded-xl bg-blush/40 px-4 py-3 text-sm text-brown">
                                <Truck className="h-4 w-4" aria-hidden="true" />
                                Courier tracking number: <span className="font-semibold text-ink">{order.tracking_number}</span>
                            </p>
                        )}
                    </section>

                    <section className="card p-6">
                        <h2 className="mb-5 font-display text-xl font-semibold text-brown">Items</h2>
                        <div className="space-y-5">
                            {order.items.map((item) => (
                                <LineItem
                                    key={item.id}
                                    imageUrl={item.image_url}
                                    name={item.product_name}
                                    to={item.product_slug ? `/product/${item.product_slug}` : undefined}
                                    meta={`${item.size} · ${item.color} · ${formatPeso(item.unit_price)} each`}
                                    quantity={item.quantity}
                                    price={item.line_total}
                                />
                            ))}
                        </div>
                    </section>

                    <section className="card p-6">
                        <h2 className="mb-5 font-display text-xl font-semibold text-brown">History</h2>
                        <ol className="space-y-4">
                            {[...order.status_history].reverse().map((entry) => (
                                <li key={entry.id} className="flex gap-3 text-sm">
                                    <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-rose" aria-hidden="true" />
                                    <div>
                                        <p className="font-medium text-ink">{titleCase(entry.new_status)}</p>
                                        {entry.note && <p className="text-brown/80">{entry.note}</p>}
                                        <p className="text-xs text-brown/60">{formatDate(entry.created_at, { time: true })}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>

                <aside className="space-y-6">
                    <section className="card p-6">
                        <h2 className="mb-4 font-display text-xl font-semibold text-brown">Summary</h2>
                        <OrderSummary subtotal={order.subtotal} shippingFee={order.shipping_fee} discount={order.discount_amount} total={order.total_amount} />
                        <p className="mt-4 text-sm text-brown/80">
                            Cash on Delivery · <OrderStatusBadge status={order.payment_status} />
                        </p>
                    </section>
                    <section className="card p-6">
                        <h2 className="mb-3 font-display text-xl font-semibold text-brown">Delivering to</h2>
                        <AddressLines address={order.shipping_address} />
                        {order.notes && <p className="mt-3 text-sm text-brown/80">Your note: {order.notes}</p>}
                    </section>
                    {order.can_cancel && (
                        <button type="button" className="btn btn-secondary w-full border-red-200 text-red-700 hover:border-red-400 hover:text-red-800" onClick={() => setCancelling(true)}>
                            Cancel order
                        </button>
                    )}
                </aside>
            </div>

            <ConfirmationModal
                open={cancelling}
                title="Cancel this order?"
                message="The items go back on the shelf and this can’t be undone. You can place a new order any time."
                confirmLabel="Yes, cancel order"
                loading={busy}
                onConfirm={cancel}
                onCancel={() => setCancelling(false)}
            >
                <FormInput className="mt-4" label="Reason (optional)" maxLength={255} value={reason} onChange={(event) => setReason(event.target.value)} />
                {cancelError && (
                    <p role="alert" className="mt-3 text-sm text-red-700">
                        {cancelError}
                    </p>
                )}
            </ConfirmationModal>
        </div>
    );
}
