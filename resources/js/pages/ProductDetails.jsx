import { Banknote, Check, ImageOff, SearchX, ShoppingBag, Truck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import client from '../api/client';
import Button from '../components/Button';
import ColorSelector from '../components/ColorSelector';
import EmptyState, { ErrorState } from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';
import ProductGrid from '../components/ProductGrid';
import QuantitySelector from '../components/QuantitySelector';
import SizeSelector from '../components/SizeSelector';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useToast } from '../context/ToastContext';
import useApi from '../hooks/useApi';
import { errorMessage, fieldErrors } from '../lib/errors';
import { formatPeso, sortSizes } from '../lib/format';

function StockMessage({ variant }) {
    if (!variant) return <p className="text-sm text-brown/70">Choose a colour and size to see availability.</p>;
    if (!variant.in_stock) return <p className="text-sm font-medium text-red-700">Out of stock in this size and colour.</p>;
    if (variant.is_low_stock) return <p className="text-sm font-medium text-amber-800">Only {variant.stock_quantity} left in stock.</p>;

    return (
        <p className="flex items-center gap-1.5 text-sm font-medium text-emerald-700">
            <Check className="h-4 w-4" aria-hidden="true" /> In stock ({variant.stock_quantity} available)
        </p>
    );
}

export default function ProductDetails() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const location = useLocation();
    const { user } = useAuth();
    const { addItem, cart } = useCart();
    const toast = useToast();

    const { data, loading, error, reload } = useApi((signal) => client.get(`/products/${slug}`, { signal }), [slug]);
    const product = data?.data;

    const [color, setColor] = useState(null);
    const [size, setSize] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [imageIndex, setImageIndex] = useState(0);
    const [adding, setAdding] = useState(false);
    const [formError, setFormError] = useState(null);

    const variants = product?.variants ?? [];
    const colors = useMemo(() => [...new Set(variants.map((variant) => variant.color))], [variants]);
    const sizes = useMemo(() => sortSizes([...new Set(variants.map((variant) => variant.size))]), [variants]);

    // Start on the first colour that has anything in stock.
    useEffect(() => {
        if (!product) return;

        const firstAvailable = product.variants.find((variant) => variant.in_stock) ?? product.variants[0];
        setColor(firstAvailable?.color ?? null);
        setSize(null);
        setQuantity(1);
        setImageIndex(0);
        setFormError(null);
    }, [product]);

    const sizesForColor = variants.filter((variant) => variant.color === color);
    const availableSizes = sizesForColor.filter((variant) => variant.in_stock).map((variant) => variant.size);
    const selected = variants.find((variant) => variant.color === color && variant.size === size) ?? null;

    // Units of this variant already sitting in the cart count against what can still be added.
    const inCart = cart?.items.find((item) => item.variant.id === selected?.id)?.quantity ?? 0;
    const maxQuantity = selected ? Math.max(0, selected.stock_quantity - inCart) : 1;

    useEffect(() => {
        setQuantity((current) => Math.min(Math.max(1, current), Math.max(1, maxQuantity)));
    }, [maxQuantity]);

    const chooseColor = (next) => {
        setColor(next);
        setFormError(null);
        // Keep the size if this colour has it in stock; otherwise ask again.
        const stillAvailable = variants.some((variant) => variant.color === next && variant.size === size && variant.in_stock);
        if (!stillAvailable) setSize(null);

        // Show the photo for this colour when the gallery has one.
        const match = product.images.findIndex((image) => image.alt_text?.toLowerCase().includes(` in ${next.toLowerCase()}`));
        if (match !== -1) setImageIndex(match);
    };

    const addToCart = async () => {
        if (!selected) {
            setFormError('Please choose a size first.');

            return;
        }

        if (!user) {
            navigate('/login', { state: { from: location.pathname, notice: 'Sign in to add items to your cart.' } });

            return;
        }

        setAdding(true);
        setFormError(null);
        try {
            await addItem(selected.id, quantity);
            toast.success(`${product.name} (${selected.size}, ${selected.color}) added to your cart.`);
            setQuantity(1);
        } catch (addError) {
            const errors = fieldErrors(addError);
            setFormError(errors.quantity ?? errors.product_variant_id ?? errorMessage(addError));
        } finally {
            setAdding(false);
        }
    };

    if (loading) return <LoadingSpinner className="min-h-[60vh]" />;

    if (error?.response?.status === 404) {
        return (
            <EmptyState
                icon={SearchX}
                title="This product isn’t available"
                message="It may have sold out for good or been removed from the store."
                action={
                    <Link to="/shop" className="btn btn-primary">
                        Continue shopping
                    </Link>
                }
                className="py-24"
            />
        );
    }

    if (error) return <ErrorState error={error} onRetry={reload} className="py-24" />;

    const image = product.images[imageIndex];
    const displayPrice = selected?.price ?? product.price;
    const showRange = !selected && Number(product.max_price) > Number(product.price);

    return (
        <div className="container-page py-8">
            <nav aria-label="Breadcrumb" className="mb-6 text-xs text-brown/60">
                <Link to="/" className="hover:text-ink">
                    Home
                </Link>
                <span className="mx-1.5">/</span>
                <Link to={`/category/${product.category.slug}`} className="hover:text-ink">
                    {product.category.name}
                </Link>
                <span className="mx-1.5">/</span>
                <span className="text-brown">{product.name}</span>
            </nav>

            <div className="grid gap-8 md:grid-cols-2 lg:gap-14">
                <div>
                    <div className="aspect-[4/5] overflow-hidden rounded-3xl bg-white shadow-soft">
                        {image ? (
                            <img src={image.url} alt={image.alt_text ?? product.name} className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center bg-blush/40 text-brown/40">
                                <ImageOff className="h-10 w-10" strokeWidth={1.5} aria-hidden="true" />
                            </div>
                        )}
                    </div>
                    {product.images.length > 1 && (
                        <div className="mt-3 flex gap-3 overflow-x-auto pb-1">
                            {product.images.map((thumb, index) => (
                                <button
                                    key={thumb.id}
                                    type="button"
                                    onClick={() => setImageIndex(index)}
                                    aria-label={`Show image ${index + 1} of ${product.images.length}`}
                                    aria-current={index === imageIndex}
                                    className={`h-20 w-16 shrink-0 overflow-hidden rounded-xl border-2 bg-white transition ${index === imageIndex ? 'border-brown' : 'border-transparent opacity-70 hover:opacity-100'}`}
                                >
                                    <img src={thumb.url} alt="" className="h-full w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-rose-dark">{product.category.name}</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold text-brown sm:text-4xl">{product.name}</h1>
                    <p className="mt-3 text-2xl font-medium text-ink">
                        {showRange && <span className="mr-1.5 text-base font-normal text-brown/70">From</span>}
                        {formatPeso(displayPrice)}
                    </p>

                    {variants.length === 0 ? (
                        <p className="mt-6 rounded-xl bg-mist px-4 py-3 text-sm text-brown">This product is not available to order right now.</p>
                    ) : (
                        <div className="mt-7 space-y-6">
                            <div>
                                <h2 className="mb-2.5 text-sm font-medium text-brown">
                                    Colour{color && <span className="font-normal text-brown/70">: {color}</span>}
                                </h2>
                                <ColorSelector colors={colors} value={color} onChange={chooseColor} />
                            </div>

                            <div>
                                <h2 className="mb-2.5 text-sm font-medium text-brown">
                                    Size{size && <span className="font-normal text-brown/70">: {size}</span>}
                                </h2>
                                <SizeSelector
                                    sizes={sizes}
                                    available={availableSizes}
                                    value={size}
                                    onChange={(next) => {
                                        setSize(next);
                                        setFormError(null);
                                    }}
                                />
                            </div>

                            <StockMessage variant={selected} />

                            <div className="flex flex-wrap items-center gap-4">
                                <QuantitySelector value={quantity} onChange={setQuantity} max={Math.max(1, maxQuantity)} disabled={!selected?.in_stock || maxQuantity === 0} />
                                <Button onClick={addToCart} loading={adding} disabled={Boolean(selected) && (!selected.in_stock || maxQuantity === 0)} className="flex-1 px-8 py-3 sm:flex-none">
                                    <ShoppingBag className="h-4 w-4" aria-hidden="true" />
                                    {selected && !selected.in_stock ? 'Out of stock' : 'Add to cart'}
                                </Button>
                            </div>

                            {inCart > 0 && (
                                <p className="text-sm text-brown/80">
                                    You have {inCart} of this in your{' '}
                                    <Link to="/cart" className="link">
                                        cart
                                    </Link>
                                    {maxQuantity === 0 && ' — that is all we have in stock'}.
                                </p>
                            )}

                            {formError && (
                                <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {formError}
                                </p>
                            )}
                        </div>
                    )}

                    {product.description && (
                        <div className="mt-8 border-t border-brown/10 pt-6">
                            <h2 className="text-sm font-semibold text-ink">Description</h2>
                            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-brown/90">{product.description}</p>
                        </div>
                    )}

                    <ul className="mt-6 space-y-2 border-t border-brown/10 pt-6 text-sm text-brown/85">
                        <li className="flex items-center gap-2.5">
                            <Banknote className="h-4 w-4 text-rose-dark" aria-hidden="true" /> Cash on Delivery
                        </li>
                        <li className="flex items-center gap-2.5">
                            <Truck className="h-4 w-4 text-rose-dark" aria-hidden="true" /> Delivery across the Philippines
                        </li>
                    </ul>
                </div>
            </div>

            {product.related?.length > 0 && (
                <section className="mt-20">
                    <h2 className="mb-8 font-display text-2xl font-semibold text-brown">You may also like</h2>
                    <ProductGrid products={product.related} />
                </section>
            )}
        </div>
    );
}
