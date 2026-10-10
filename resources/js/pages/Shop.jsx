import { SearchX, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import client from '../api/client';
import { ColorSwatch } from '../components/ColorSelector';
import EmptyState, { ErrorState } from '../components/EmptyState';
import Modal from '../components/Modal';
import Pagination from '../components/Pagination';
import ProductGrid from '../components/ProductGrid';
import useApi from '../hooks/useApi';
import { useCategories } from '../hooks/useStoreData';

const SORTS = [
    ['newest', 'Newest'],
    ['bestselling', 'Bestselling'],
    ['price_asc', 'Price: low to high'],
    ['price_desc', 'Price: high to low'],
    ['name', 'Name: A to Z'],
];

const FILTER_KEYS = ['search', 'category', 'size', 'color', 'min_price', 'max_price', 'in_stock'];

function FilterGroup({ title, children }) {
    return (
        <fieldset className="border-b border-brown/10 py-5 first:pt-0 last:border-0">
            <legend className="mb-3 text-sm font-semibold text-ink">{title}</legend>
            {children}
        </fieldset>
    );
}

function Filters({ params, setParam, options, categories, fixedCategory }) {
    // Price is typed, so it is applied on blur / Enter rather than on every keystroke.
    const [price, setPrice] = useState({ min: params.get('min_price') ?? '', max: params.get('max_price') ?? '' });

    useEffect(() => {
        setPrice({ min: params.get('min_price') ?? '', max: params.get('max_price') ?? '' });
    }, [params]);

    const applyPrice = () => {
        const unchanged = price.min === (params.get('min_price') ?? '') && price.max === (params.get('max_price') ?? '');

        if (!unchanged) setParam({ min_price: price.min, max_price: price.max });
    };

    const chip = (active) =>
        `rounded-full border px-3 py-1.5 text-sm transition ${active ? 'border-brown bg-brown text-white' : 'border-brown/20 bg-white text-brown hover:border-rose'}`;

    return (
        <div>
            {!fixedCategory && (
                <FilterGroup title="Category">
                    <div className="space-y-2">
                        {[{ slug: '', name: 'All categories' }, ...categories].map((category) => (
                            <label key={category.slug || 'all'} className="flex cursor-pointer items-center gap-2.5 text-sm text-brown">
                                <input
                                    type="radio"
                                    name="category"
                                    checked={(params.get('category') ?? '') === category.slug}
                                    onChange={() => setParam('category', category.slug)}
                                    className="border-brown/30 text-brown focus:ring-rose"
                                />
                                {category.name}
                            </label>
                        ))}
                    </div>
                </FilterGroup>
            )}

            {options?.sizes?.length > 0 && (
                <FilterGroup title="Size">
                    <div className="flex flex-wrap gap-2">
                        {options.sizes.map((size) => {
                            const active = params.get('size') === size;

                            return (
                                <button key={size} type="button" aria-pressed={active} className={chip(active)} onClick={() => setParam('size', active ? '' : size)}>
                                    {size}
                                </button>
                            );
                        })}
                    </div>
                </FilterGroup>
            )}

            {options?.colors?.length > 0 && (
                <FilterGroup title="Colour">
                    <div className="flex flex-wrap gap-2">
                        {options.colors.map((color) => {
                            const active = params.get('color') === color;

                            return (
                                <button
                                    key={color}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => setParam('color', active ? '' : color)}
                                    className={`flex items-center gap-1.5 rounded-full border py-1 pl-1.5 pr-3 text-sm transition ${
                                        active ? 'border-brown bg-blush/60 font-medium text-ink' : 'border-brown/20 bg-white text-brown hover:border-rose'
                                    }`}
                                >
                                    <ColorSwatch color={color} className="h-5 w-5" />
                                    {color}
                                </button>
                            );
                        })}
                    </div>
                </FilterGroup>
            )}

            <FilterGroup title="Price (₱)">
                <form
                    className="flex items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyPrice();
                    }}
                >
                    <input
                        type="number"
                        min="0"
                        inputMode="numeric"
                        placeholder={options?.price?.min ? Math.floor(options.price.min) : 'Min'}
                        aria-label="Minimum price"
                        value={price.min}
                        onChange={(event) => setPrice({ ...price, min: event.target.value })}
                        onBlur={applyPrice}
                        className="input"
                    />
                    <span className="text-brown/50">–</span>
                    <input
                        type="number"
                        min="0"
                        inputMode="numeric"
                        placeholder={options?.price?.max ? Math.ceil(options.price.max) : 'Max'}
                        aria-label="Maximum price"
                        value={price.max}
                        onChange={(event) => setPrice({ ...price, max: event.target.value })}
                        onBlur={applyPrice}
                        className="input"
                    />
                    <button type="submit" className="sr-only">
                        Apply price
                    </button>
                </form>
            </FilterGroup>

            <FilterGroup title="Availability">
                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-brown">
                    <input
                        type="checkbox"
                        checked={params.get('in_stock') === '1'}
                        onChange={(event) => setParam('in_stock', event.target.checked ? '1' : '')}
                        className="rounded border-brown/30 text-brown focus:ring-rose"
                    />
                    In stock only
                </label>
            </FilterGroup>
        </div>
    );
}

export default function Shop() {
    const { slug: categorySlug } = useParams();
    const [params, setParams] = useSearchParams();
    const categories = useCategories();
    const [drawerOpen, setDrawerOpen] = useState(false);

    const query = Object.fromEntries(params.entries());
    const endpoint = categorySlug ? `/categories/${categorySlug}/products` : '/products';

    const { data, loading, error, reload } = useApi(
        (signal) => client.get(endpoint, { params: { ...query, per_page: 12 }, signal }),
        [endpoint, params.toString()],
    );
    const options = useApi((signal) => client.get('/products/filters', { signal })).data?.data;

    // Changing any filter or the sort returns to page 1.
    const setParam = (key, value) => {
        const next = new URLSearchParams(params);
        const changes = typeof key === 'object' ? key : { [key]: value };

        Object.entries(changes).forEach(([name, newValue]) => (newValue === '' || newValue == null ? next.delete(name) : next.set(name, newValue)));
        next.delete('page');
        setParams(next, { replace: true });
    };

    const setPage = (page) => {
        const next = new URLSearchParams(params);
        next.set('page', page);
        setParams(next);
        window.scrollTo({ top: 0 });
    };

    const activeFilters = FILTER_KEYS.filter((key) => params.has(key) && !(categorySlug && key === 'category'));
    const clearFilters = () => setParams(params.has('sort') ? { sort: params.get('sort') } : {}, { replace: true });

    const category = data?.category ?? categories.find((item) => item.slug === categorySlug);
    const notFound = categorySlug && error?.response?.status === 404;
    const products = data?.data ?? [];
    const filterProps = { params, setParam, options, categories, fixedCategory: Boolean(categorySlug) };

    if (notFound) {
        return (
            <EmptyState
                icon={SearchX}
                title="This category isn’t available"
                message="It may have been renamed or removed."
                action={
                    <Link to="/shop" className="btn btn-primary">
                        Browse all products
                    </Link>
                }
                className="py-24"
            />
        );
    }

    return (
        <div className="container-page py-10">
            <header className="mb-8">
                <nav aria-label="Breadcrumb" className="mb-2 text-xs text-brown/60">
                    <Link to="/" className="hover:text-ink">
                        Home
                    </Link>
                    <span className="mx-1.5">/</span>
                    {categorySlug ? (
                        <>
                            <Link to="/shop" className="hover:text-ink">
                                Shop
                            </Link>
                            <span className="mx-1.5">/</span>
                            <span className="text-brown">{category?.name ?? '…'}</span>
                        </>
                    ) : (
                        <span className="text-brown">Shop</span>
                    )}
                </nav>
                <h1 className="font-display text-3xl font-semibold text-brown sm:text-4xl">
                    {categorySlug ? (category?.name ?? 'Category') : params.get('search') ? `Results for “${params.get('search')}”` : 'Shop all'}
                </h1>
                {categorySlug && category?.description && <p className="mt-2 max-w-2xl text-brown/80">{category.description}</p>}
            </header>

            <div className="lg:grid lg:grid-cols-[15rem_1fr] lg:gap-10">
                <aside className="hidden lg:block" aria-label="Filters">
                    <Filters {...filterProps} />
                </aside>

                <div>
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <button type="button" className="btn btn-secondary lg:hidden" onClick={() => setDrawerOpen(true)}>
                            <SlidersHorizontal className="h-4 w-4" aria-hidden="true" />
                            Filters{activeFilters.length > 0 && ` (${activeFilters.length})`}
                        </button>
                        <p className="text-sm text-brown/70" aria-live="polite">
                            {loading ? 'Loading…' : `${data?.meta?.total ?? 0} ${data?.meta?.total === 1 ? 'product' : 'products'}`}
                        </p>
                        {activeFilters.length > 0 && (
                            <button type="button" onClick={clearFilters} className="flex items-center gap-1 text-sm text-brown underline-offset-4 hover:text-ink hover:underline">
                                <X className="h-3.5 w-3.5" aria-hidden="true" />
                                Clear filters
                            </button>
                        )}
                        <label className="ml-auto flex items-center gap-2 text-sm text-brown">
                            <span className="hidden sm:inline">Sort by</span>
                            <select value={params.get('sort') ?? 'newest'} onChange={(event) => setParam('sort', event.target.value)} className="input w-auto rounded-full py-2 pr-9" aria-label="Sort products">
                                {SORTS.map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

                    {error ? (
                        <ErrorState error={error} onRetry={reload} title="We couldn’t load the products" />
                    ) : !loading && products.length === 0 ? (
                        <EmptyState
                            icon={SearchX}
                            title="No products match"
                            message={activeFilters.length > 0 ? 'Try removing a filter or searching for something else.' : 'There is nothing here yet. Please check back soon.'}
                            action={
                                activeFilters.length > 0 && (
                                    <button type="button" className="btn btn-primary" onClick={clearFilters}>
                                        Clear filters
                                    </button>
                                )
                            }
                        />
                    ) : (
                        <>
                            <ProductGrid products={products} loading={loading} columns={3} skeletonCount={6} />
                            <Pagination meta={data?.meta} onPageChange={setPage} className="mt-10" />
                        </>
                    )}
                </div>
            </div>

            <Modal
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                title="Filters"
                footer={
                    <>
                        <button type="button" className="btn btn-secondary" onClick={clearFilters}>
                            Clear all
                        </button>
                        <button type="button" className="btn btn-primary" onClick={() => setDrawerOpen(false)}>
                            Show {data?.meta?.total ?? 0} products
                        </button>
                    </>
                }
            >
                <Filters {...filterProps} />
            </Modal>
        </div>
    );
}
