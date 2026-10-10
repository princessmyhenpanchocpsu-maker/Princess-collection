import { Banknote, MapPin, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import client from '../api/client';
import AddressForm, { AddressLines } from '../components/AddressForm';
import Button from '../components/Button';
import { ErrorState } from '../components/EmptyState';
import FormInput from '../components/FormInput';
import LoadingSpinner from '../components/LoadingSpinner';
import Modal from '../components/Modal';
import OrderSummary, { LineItem } from '../components/OrderSummary';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useToast } from '../context/ToastContext';
import useApi from '../hooks/useApi';
import { errorMessage, fieldErrors } from '../lib/errors';
import { uuid } from '../lib/format';

export default function Checkout() {
    const { user } = useAuth();
    const { cart, loading: cartLoading, refresh } = useCart();
    const toast = useToast();
    const navigate = useNavigate();
    const addresses = useApi((signal) => client.get('/addresses', { signal }));

    // One key per visit to this page. A double-click, a retry after a timeout, or a resubmitted
    // request all carry the same key, so the server creates at most one order.
    const idempotencyKey = useRef(uuid());

    const [addressId, setAddressId] = useState(null);
    const [notes, setNotes] = useState('');
    const [adding, setAdding] = useState(false);
    const [placing, setPlacing] = useState(false);
    const [placed, setPlaced] = useState(false);
    const [problems, setProblems] = useState(null);

    const list = addresses.data?.data ?? [];

    useEffect(() => {
        if (addressId === null && list.length > 0) setAddressId((list.find((address) => address.is_default) ?? list[0]).id);
    }, [list, addressId]);

    if (placed) return null;
    if ((!cart && cartLoading) || addresses.loading) return <LoadingSpinner className="min-h-[50vh]" />;
    if (!cart || cart.items.length === 0) return <Navigate to="/cart" replace />;
    if (addresses.error) return <ErrorState error={addresses.error} onRetry={addresses.reload} className="py-24" />;

    const placeOrder = async () => {
        setPlacing(true);
        setProblems(null);

        try {
            const { data } = await client.post(
                '/checkout',
                { address_id: addressId, payment_method: 'cod', notes: notes.trim() || null },
                { headers: { 'Idempotency-Key': idempotencyKey.current } },
            );

            setPlaced(true);
            await refresh();
            navigate(`/orders/${data.data.order_number}`, { replace: true, state: { justPlaced: true } });
        } catch (error) {
            const validation = fieldErrors(error);
            const itemProblems = Object.entries(validation).filter(([field]) => field.startsWith('items.')).map(([, message]) => message);

            setProblems({
                message: validation.cart ?? validation.address_id ?? (itemProblems.length ? error.response.data.message : errorMessage(error, 'We couldn’t place your order. Please try again.')),
                items: itemProblems,
            });

            // Stock or availability changed: show the customer the up-to-date cart.
            if (itemProblems.length) refresh();
            setPlacing(false);
        }
    };

    return (
        <div className="container-page py-10">
            <h1 className="font-display text-3xl font-semibold text-brown">Checkout</h1>

            <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_24rem]">
                <div className="space-y-6">
                    <section className="card p-6">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="flex items-center gap-2 font-display text-xl font-semibold text-brown">
                                <MapPin className="h-5 w-5 text-rose-dark" aria-hidden="true" />
                                Delivery address
                            </h2>
                            {list.length > 0 && (
                                <button type="button" className="btn btn-ghost" onClick={() => setAdding(true)}>
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    New address
                                </button>
                            )}
                        </div>

                        {list.length === 0 ? (
                            <>
                                <p className="mb-5 text-sm text-brown/80">Add the address where you’d like this order delivered.</p>
                                <AddressForm
                                    defaults={{ recipient_name: user.name, phone: user.phone ?? '' }}
                                    onSaved={(address) => {
                                        addresses.setData({ ...addresses.data, data: [address] });
                                        setAddressId(address.id);
                                    }}
                                />
                            </>
                        ) : (
                            <div role="radiogroup" aria-label="Delivery address" className="grid gap-3 sm:grid-cols-2">
                                {list.map((address) => {
                                    const selected = address.id === addressId;

                                    return (
                                        <label
                                            key={address.id}
                                            className={`relative cursor-pointer rounded-xl border p-4 transition ${selected ? 'border-brown bg-blush/30' : 'border-brown/15 hover:border-rose'}`}
                                        >
                                            <input type="radio" name="address" className="sr-only" checked={selected} onChange={() => setAddressId(address.id)} />
                                            <AddressLines address={address} />
                                            {address.is_default && <span className="mt-2 inline-block rounded-full bg-white px-2 py-0.5 text-xs font-medium text-brown">Default</span>}
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    <section className="card p-6">
                        <h2 className="mb-4 flex items-center gap-2 font-display text-xl font-semibold text-brown">
                            <Banknote className="h-5 w-5 text-rose-dark" aria-hidden="true" />
                            Payment
                        </h2>
                        <div className="rounded-xl border border-brown bg-blush/30 p-4">
                            <p className="text-sm font-medium text-ink">Cash on Delivery</p>
                            <p className="mt-1 text-sm text-brown/80">Pay the courier in cash when your order arrives. Please prepare the exact amount if you can.</p>
                        </div>
                    </section>

                    <section className="card p-6">
                        <FormInput
                            as="textarea"
                            rows={3}
                            label="Order notes"
                            hint="Optional. Anything we should know about this order."
                            maxLength={500}
                            value={notes}
                            onChange={(event) => setNotes(event.target.value)}
                        />
                    </section>
                </div>

                <aside className="card h-fit p-6 lg:sticky lg:top-32">
                    <h2 className="mb-4 font-display text-xl font-semibold text-brown">Your order</h2>
                    <div className="mb-5 max-h-80 space-y-4 overflow-y-auto pr-1">
                        {cart.items.map((item) => (
                            <LineItem
                                key={item.id}
                                imageUrl={item.product.image_url}
                                name={item.product.name}
                                meta={`${item.variant.size} · ${item.variant.color}`}
                                quantity={item.quantity}
                                price={item.line_total}
                            />
                        ))}
                    </div>
                    <OrderSummary subtotal={cart.subtotal} shippingFee={cart.shipping_fee} total={cart.total} />

                    {problems && (
                        <div role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p className="font-medium">{problems.message}</p>
                            {problems.items.length > 0 && (
                                <>
                                    <ul className="mt-2 list-disc space-y-1 pl-5">
                                        {problems.items.map((problem) => (
                                            <li key={problem}>{problem}</li>
                                        ))}
                                    </ul>
                                    <Link to="/cart" className="mt-2 inline-block font-medium underline underline-offset-4">
                                        Review your cart
                                    </Link>
                                </>
                            )}
                        </div>
                    )}

                    <Button onClick={placeOrder} loading={placing} disabled={!addressId || !cart.can_checkout} className="mt-5 w-full py-3">
                        Place order
                    </Button>
                    {!addressId && <p className="mt-3 text-center text-xs text-brown/70">Save a delivery address to place your order.</p>}
                    <p className="mt-3 text-center text-xs text-brown/60">You can cancel from your account any time before we start packing.</p>
                </aside>
            </div>

            <Modal open={adding} onClose={() => setAdding(false)} title="New delivery address">
                <AddressForm
                    onCancel={() => setAdding(false)}
                    onSaved={(address) => {
                        addresses.reload();
                        setAddressId(address.id);
                        setAdding(false);
                        toast.success('Address saved.');
                    }}
                />
            </Modal>
        </div>
    );
}
