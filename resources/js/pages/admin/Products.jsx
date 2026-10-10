import { Archive, ImageOff, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../../api/client';
import { DataTable, PageHeader, SearchInput } from '../../components/admin/AdminUI';
import ConfirmationModal from '../../components/ConfirmationModal';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { errorMessage } from '../../lib/errors';
import { formatPeso } from '../../lib/format';

export default function Products() {
    const toast = useToast();
    const [query, setQuery, key] = useQueryParams();
    const resource = useApi((signal) => client.get('/admin/products', { params: query, signal }), [key]);
    const categories = useApi((signal) => client.get('/admin/categories', { params: { per_page: 100 }, signal })).data?.data ?? [];
    const [archiving, setArchiving] = useState(null);
    const [busy, setBusy] = useState(false);

    const archive = async () => {
        setBusy(true);
        try {
            await client.delete(`/admin/products/${archiving.slug}`);
            toast.success(`${archiving.name} was archived.`);
            setArchiving(null);
            resource.reload();
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <PageHeader title="Products" description="Create, edit and archive what you sell.">
                <Link to="/admin/products/new" className="btn btn-primary">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    New product
                </Link>
            </PageHeader>

            <DataTable
                resource={resource}
                columns={['Product', 'Category', 'Price', 'Stock', 'Status', '>Actions']}
                emptyTitle="No products found"
                emptyMessage="Try a different search or filter, or add a new product."
                onPageChange={(page) => setQuery({ page })}
                toolbar={
                    <>
                        <SearchInput value={query.search} onChange={(search) => setQuery({ search })} placeholder="Search name or SKU" />
                        <select value={query.status ?? ''} onChange={(event) => setQuery({ status: event.target.value })} className="input w-auto" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                        <select value={query.category_id ?? ''} onChange={(event) => setQuery({ category_id: event.target.value })} className="input w-auto" aria-label="Filter by category">
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </>
                }
            >
                {(rows) =>
                    rows.map((product) => (
                        <tr key={product.id}>
                            <td>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded bg-stone-100 text-stone-400">
                                        {product.image ? <img src={product.image.url} alt="" className="h-full w-full object-cover" /> : <ImageOff className="h-4 w-4" aria-hidden="true" />}
                                    </div>
                                    <div className="min-w-0">
                                        <Link to={`/admin/products/${product.slug}/edit`} className="font-medium text-stone-900 underline-offset-4 hover:underline">
                                            {product.name}
                                        </Link>
                                        <p className="text-xs text-stone-500">
                                            {product.sizes.length} sizes · {product.colors.length} colours
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>{product.category?.name}</td>
                            <td className="whitespace-nowrap tabular-nums">{formatPeso(product.base_price)}</td>
                            <td className="tabular-nums">{product.total_stock === 0 ? <span className="font-medium text-red-700">Out of stock</span> : product.total_stock}</td>
                            <td>
                                <OrderStatusBadge status={product.status} />
                            </td>
                            <td>
                                <div className="flex justify-end gap-1">
                                    <Link to={`/admin/products/${product.slug}/edit`} className="btn btn-ghost px-2 py-1.5" aria-label={`Edit ${product.name}`}>
                                        <Pencil className="h-4 w-4" />
                                    </Link>
                                    {product.status !== 'archived' && (
                                        <button type="button" className="btn btn-ghost px-2 py-1.5 hover:text-red-700" onClick={() => setArchiving(product)} aria-label={`Archive ${product.name}`}>
                                            <Archive className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))
                }
            </DataTable>

            <ConfirmationModal
                open={Boolean(archiving)}
                title={`Archive ${archiving?.name ?? 'product'}?`}
                message="Customers will no longer see or be able to buy it. Past orders are not affected, and you can restore it later by changing its status."
                confirmLabel="Archive product"
                cancelLabel="Cancel"
                loading={busy}
                onConfirm={archive}
                onCancel={() => setArchiving(null)}
            />
        </>
    );
}
