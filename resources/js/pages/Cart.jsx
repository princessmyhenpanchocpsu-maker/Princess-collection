import { CircleAlert, ShoppingBag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import EmptyState from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';
import OrderSummary from '../components/OrderSummary';
import QuantitySelector from '../components/QuantitySelector';
import { useCart } from '../context/CartContext';
import { useToast } from '../context/ToastContext';
import { useStoreSettings } from '../hooks/useStoreData';
import { errorMessage, fieldErrors } from '../lib/errors';
import { formatPeso } from '../lib/format';

const ISSUES = {
    unavailable: () => 'This item is no longer available. Remove it to continue.',
    out_of_stock: () => 'This item is now out of stock. Remove it to continue.',
    insufficient_stock: (item) => `Only ${item.available_stock} left. Reduce the quantity to continue.`,
};

export default function Cart() {
    const { cart, loading, updateItem, removeItem } = useCart();
    const settings = useStoreSettings();
    const toast = useToast();
    const navigate = useNavigate();
    const [busy, setBusy] = useState(null);

    const run = async (itemId, action) => {
        setBusy(itemId);
        try {
            await action();
        } catch (error) {
            toast.error(fieldErrors(error).quantity ?? errorMessage(error));
        } finally {
            setBusy(null);
        }
    };

    if (!cart && loading) return <LoadingSpinner className="min-h-[50vh]" />;

    if (!cart || cart.items.length === 0) {
        return (
            <EmptyState
                icon={ShoppingBag}
                title="Your cart is empty"
                message="Find something you love and it will show up here."
                action={
                    <Link to="/shop" className="btn btn-primary">
                        Start shopping
                    </Link>
                }
                className="py-24"
            />
        );
    }

    const threshold = Number(settings?.free_shipping_threshold ?? 0);
    const remaining = threshold > 0 ? threshold - Number(cart.subtotal) : 0;

    return (
        <div className="container-page py-10">
            <h1 className="font-display text-3xl font-semibold text-brown">Your cart</h1>

            <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
                <ul className="card divide-y divide-brown/10 px-4 sm:px-6">
                    {cart.items.map((item) => {
                        const isBusy = busy === item.id;
                        const blocked = item.issue === 'unavailable' || item.issue === 'out_of_stock';

                        return (
                            <li key={item.id} className={`flex gap-4 py-5 ${isBusy ? 'opacity-60' : ''}`}>
                                <Link to={item.product.slug ? `/product/${item.product.slug}` : '#'} className="h-28 w-[5.5rem] shrink-0 overflow-hidden rounded-xl bg-blush/40">
                                    {item.product.image_url && <img src={item.product.image_url} alt="" className={`h-full w-full object-cover ${blocked ? 'grayscale' : ''}`} />}
                                </Link>
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="truncate text-sm font-medium text-ink sm:text-base">{item.product.name}</h2>
                                            <p className="mt-0.5 text-sm text-brown/75">
                                                {item.variant.size} · {item.variant.color}
                                            </p>
                                            <p className="mt-0.5 text-sm text-brown">{formatPeso(item.unit_price)} each</p>
                                        </div>
                                        <p className="shrink-0 text-sm font-semibold tabular-nums text-ink sm:text-base">{formatPeso(item.line_total)}</p>
                                    </div>

                                    {item.issue && (
                                        <p role="alert" className="mt-2 flex items-start gap-1.5 text-sm text-red-700">
                                            <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                            {ISSUES[item.issue](item)}
                                        </p>
                                    )}

                                    <div className="mt-auto flex items-center justify-between gap-3 pt-3">
                                        <QuantitySelector
                                            size="sm"
                                            value={item.quantity}
                                            max={Math.max(item.available_stock, 1)}
                                            disabled={isBusy || blocked}
                                            onChange={(quantity) => run(item.id, () => updateItem(item.id, quantity))}
                                        />
                                        <button
                                            type="button"
                                            disabled={isBusy}
                                            onClick={() => run(item.id, () => removeItem(item.id))}
                                            className="flex items-center gap-1.5 rounded-full px-2 py-1 text-sm text-brown/80 transition hover:bg-red-50 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>

                <aside className="card h-fit p-6 lg:sticky lg:top-32">
                    <h2 className="mb-4 font-display text-xl font-semibold text-brown">Order summary</h2>
                    <OrderSummary subtotal={cart.subtotal} shippingFee={cart.shipping_fee} total={cart.total} />
                    {remaining > 0 && (
                        <p className="mt-4 rounded-xl bg-blush/50 px-4 py-3 text-sm text-brown">
                            Add {formatPeso(remaining)} more to get <span className="font-medium text-ink">free shipping</span>.
                        </p>
                    )}
                    {!cart.can_checkout && (
                        <p className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Fix the highlighted items above before checking out.</p>
                    )}
                    <button type="button" className="btn btn-primary mt-5 w-full py-3" disabled={!cart.can_checkout} onClick={() => navigate('/checkout')}>
                        Proceed to checkout
                    </button>
                    <Link to="/shop" className="mt-4 block text-center text-sm text-brown underline-offset-4 hover:text-ink hover:underline">
                        Continue shopping
                    </Link>
                </aside>
            </div>
        </div>
    );
}
