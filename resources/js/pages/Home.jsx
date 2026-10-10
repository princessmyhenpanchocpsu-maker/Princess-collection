import { ArrowRight, Banknote, RefreshCw, Truck } from 'lucide-react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import { ErrorState } from '../components/EmptyState';
import ProductGrid from '../components/ProductGrid';
import useApi from '../hooks/useApi';
import { useCategories, useStoreSettings } from '../hooks/useStoreData';
import { formatPeso } from '../lib/format';

function SectionHeading({ eyebrow, title, to, linkLabel }) {
    return (
        <div className="mb-8 flex items-end justify-between gap-4">
            <div>
                {eyebrow && <p className="text-xs font-semibold uppercase tracking-[0.2em] text-rose-dark">{eyebrow}</p>}
                <h2 className="mt-1 font-display text-2xl font-semibold text-brown sm:text-3xl">{title}</h2>
            </div>
            {to && (
                <Link to={to} className="group hidden shrink-0 items-center gap-1 text-sm font-medium text-brown hover:text-ink sm:flex">
                    {linkLabel}
                    <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                </Link>
            )}
        </div>
    );
}

export default function Home() {
    const categories = useCategories();
    const settings = useStoreSettings();
    const arrivals = useApi((signal) => client.get('/products', { params: { sort: 'newest', per_page: 4 }, signal }));
    const bestsellers = useApi((signal) => client.get('/products', { params: { sort: 'bestselling', has_sales: 1, per_page: 4 }, signal }));

    const heroImages = (arrivals.data?.data ?? []).filter((product) => product.image).slice(0, 2);
    const hasBestsellers = bestsellers.data?.data?.length > 0;

    return (
        <>
            <section className="bg-gradient-to-br from-blush/70 via-cream to-cream">
                <div className="container-page grid items-center gap-10 py-14 md:grid-cols-2 md:py-20">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.25em] text-rose-dark">New season, new favourites</p>
                        <h1 className="mt-4 font-display text-4xl font-semibold leading-tight text-brown sm:text-5xl lg:text-6xl">
                            Your Style,
                            <br />
                            Your Crown.
                        </h1>
                        <p className="mt-5 max-w-md text-base text-brown/85 sm:text-lg">
                            Tops, dresses and everyday essentials made to feel as good as they look — delivered to your door, pay on arrival.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link to="/shop" className="btn btn-primary px-7 py-3">
                                Shop the collection
                            </Link>
                            <a href="#new-arrivals" className="btn btn-secondary px-7 py-3">
                                See new arrivals
                            </a>
                        </div>
                    </div>
                    {heroImages.length > 0 && (
                        <div className="relative mx-auto grid w-full max-w-md grid-cols-2 items-start gap-4">
                            {heroImages.map((product, index) => (
                                <Link
                                    key={product.id}
                                    to={`/product/${product.slug}`}
                                    className={`overflow-hidden rounded-3xl bg-white shadow-soft transition hover:-translate-y-1 ${index === 1 ? 'mt-10' : ''}`}
                                >
                                    <img src={product.image.url} alt={product.image.alt_text ?? product.name} className="aspect-[4/5] w-full object-cover" />
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {categories.length > 0 && (
                <section className="container-page pt-16">
                    <SectionHeading eyebrow="Browse" title="Shop by category" />
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        {categories.map((category) => (
                            <Link key={category.id} to={`/category/${category.slug}`} className="group relative overflow-hidden rounded-2xl bg-blush/60 shadow-soft">
                                {category.image_url ? (
                                    <img src={category.image_url} alt="" loading="lazy" className="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105" />
                                ) : (
                                    <div className="aspect-[4/5] w-full bg-gradient-to-br from-blush to-rose/40" />
                                )}
                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-white via-white/90 to-transparent px-4 pb-4 pt-10">
                                    <h3 className="font-display text-base font-semibold text-brown sm:text-lg">{category.name}</h3>
                                    <p className="text-xs text-brown/70">
                                        {category.products_count} {category.products_count === 1 ? 'style' : 'styles'}
                                    </p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            <section id="new-arrivals" className="container-page scroll-mt-28 pt-16">
                <SectionHeading eyebrow="Just in" title="New arrivals" to="/shop?sort=newest" linkLabel="View all" />
                {arrivals.error ? (
                    <ErrorState error={arrivals.error} onRetry={arrivals.reload} title="We couldn’t load new arrivals" className="py-8" />
                ) : (
                    <ProductGrid products={arrivals.data?.data} loading={arrivals.loading} skeletonCount={4} />
                )}
            </section>

            {hasBestsellers && (
                <section className="container-page pt-16">
                    <SectionHeading eyebrow="Customer favourites" title="Bestsellers" to="/shop?sort=bestselling" linkLabel="View all" />
                    <ProductGrid products={bestsellers.data.data} />
                </section>
            )}

            <section className="container-page pt-16">
                <div className="grid gap-6 rounded-3xl bg-brown px-6 py-10 text-cream sm:grid-cols-3 sm:px-10">
                    {[
                        [Banknote, 'Cash on Delivery', 'Pay only when your order reaches you.'],
                        [
                            Truck,
                            settings?.free_shipping_threshold ? `Free shipping from ${formatPeso(settings.free_shipping_threshold)}` : 'Nationwide delivery',
                            settings ? `Flat ${formatPeso(settings.shipping_fee)} shipping on smaller orders.` : 'We deliver across the Philippines.',
                        ],
                        [RefreshCw, 'Easy order tracking', 'Follow every step from your account, and cancel before we pack it.'],
                    ].map(([Icon, title, text]) => (
                        <div key={title} className="flex gap-4">
                            <Icon className="mt-1 h-6 w-6 shrink-0 text-blush" strokeWidth={1.5} aria-hidden="true" />
                            <div>
                                <h3 className="font-display text-lg font-semibold">{title}</h3>
                                <p className="mt-1 text-sm text-cream/80">{text}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </>
    );
}
