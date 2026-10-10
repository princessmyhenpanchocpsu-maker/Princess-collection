import { Banknote, Crown, Truck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useCategories, useStoreSettings } from '../hooks/useStoreData';
import { formatPeso } from '../lib/format';

export default function Footer() {
    const categories = useCategories();
    const settings = useStoreSettings();
    const link = 'text-sm text-brown/80 transition hover:text-ink';

    return (
        <footer className="mt-20 border-t border-brown/10 bg-white">
            <div className="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div className="sm:col-span-2 lg:col-span-1">
                    <Link to="/" className="flex items-center gap-2 text-brown">
                        <Crown className="h-6 w-6 text-rose" strokeWidth={1.5} aria-hidden="true" />
                        <span className="font-display text-lg font-semibold">Princess Collections</span>
                    </Link>
                    <p className="mt-3 max-w-xs text-sm text-brown/80">Your Style, Your Crown. Women’s clothing chosen for comfort, confidence and everyday elegance.</p>
                </div>

                <nav aria-label="Shop">
                    <h2 className="text-sm font-semibold text-ink">Shop</h2>
                    <ul className="mt-3 space-y-2">
                        <li>
                            <Link to="/shop" className={link}>
                                All products
                            </Link>
                        </li>
                        {categories.map((category) => (
                            <li key={category.id}>
                                <Link to={`/category/${category.slug}`} className={link}>
                                    {category.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <nav aria-label="Account">
                    <h2 className="text-sm font-semibold text-ink">Your account</h2>
                    <ul className="mt-3 space-y-2">
                        {[
                            ['/account', 'Profile'],
                            ['/account/orders', 'Orders and tracking'],
                            ['/account/addresses', 'Addresses'],
                            ['/cart', 'Cart'],
                        ].map(([to, label]) => (
                            <li key={to}>
                                <Link to={to} className={link}>
                                    {label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div>
                    <h2 className="text-sm font-semibold text-ink">Delivery and payment</h2>
                    <ul className="mt-3 space-y-3 text-sm text-brown/80">
                        <li className="flex gap-2">
                            <Banknote className="mt-0.5 h-4 w-4 shrink-0 text-rose-dark" aria-hidden="true" />
                            Cash on Delivery — pay when your order arrives.
                        </li>
                        {settings && (
                            <li className="flex gap-2">
                                <Truck className="mt-0.5 h-4 w-4 shrink-0 text-rose-dark" aria-hidden="true" />
                                {settings.free_shipping_threshold
                                    ? `${formatPeso(settings.shipping_fee)} shipping, free on orders ${formatPeso(settings.free_shipping_threshold)} and up.`
                                    : `Flat ${formatPeso(settings.shipping_fee)} shipping fee.`}
                            </li>
                        )}
                    </ul>
                </div>
            </div>
        </footer>
    );
}
