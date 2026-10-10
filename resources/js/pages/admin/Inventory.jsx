import { History, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../../api/client';
import { DataTable, PageHeader, SearchInput } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import FormInput from '../../components/FormInput';
import Modal from '../../components/Modal';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { errorMessage, fieldErrors } from '../../lib/errors';
import { formatDate, titleCase } from '../../lib/format';

function StockLevel({ variant }) {
    if (variant.stock_quantity === 0) return <span className="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-900">Out of stock</span>;
    if (variant.is_low_stock) return <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">Low stock</span>;

    return <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900">In stock</span>;
}

function AdjustForm({ variant, onDone, onCancel }) {
    const [form, setForm] = useState({ type: 'stock_in', quantity: '', reason: '' });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const quantity = Number(form.quantity) || 0;
    const change = form.type === 'stock_in' ? Math.abs(quantity) : quantity;
    const result = variant.stock_quantity + change;

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            await client.post('/admin/inventory/adjustments', { product_variant_id: variant.id, type: form.type, quantity: change, reason: form.reason });
            onDone();
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(Object.keys(validation).length ? validation : { reason: errorMessage(error) });
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <p className="text-sm text-stone-600">
                <span className="font-medium text-stone-900">{variant.product?.name}</span> — {variant.size} · {variant.color} ({variant.sku})
                <br />
                Current stock: <span className="font-medium tabular-nums text-stone-900">{variant.stock_quantity}</span>
            </p>
            <FormInput as="select" label="Type" value={form.type} onChange={(event) => setForm({ ...form, type: event.target.value })} error={errors.type}>
                <option value="stock_in">Stock in — received new stock</option>
                <option value="adjustment">Adjustment — correct the count up or down</option>
            </FormInput>
            <FormInput
                label="Quantity"
                type="number"
                required
                min={form.type === 'stock_in' ? 1 : undefined}
                hint={form.type === 'stock_in' ? 'How many units arrived.' : 'Use a negative number to remove stock, e.g. -2 for damaged items.'}
                value={form.quantity}
                onChange={(event) => setForm({ ...form, quantity: event.target.value })}
                error={errors.quantity}
            />
            <FormInput label="Reason" required maxLength={255} placeholder="e.g. Supplier delivery, stocktake, damaged" value={form.reason} onChange={(event) => setForm({ ...form, reason: event.target.value })} error={errors.reason} />
            {change !== 0 && (
                <p className={`rounded-lg px-4 py-3 text-sm ${result < 0 ? 'bg-red-50 text-red-700' : 'bg-stone-100 text-stone-700'}`}>
                    {result < 0 ? 'Stock cannot go below zero.' : `Stock will change from ${variant.stock_quantity} to ${result}.`}
                </p>
            )}
            <div className="flex justify-end gap-3 pt-2">
                <Button variant="secondary" onClick={onCancel} disabled={saving}>
                    Cancel
                </Button>
                <Button type="submit" loading={saving} disabled={change === 0 || result < 0}>
                    Save adjustment
                </Button>
            </div>
        </form>
    );
}

function Movements({ query, setQuery, queryKey }) {
    const resource = useApi((signal) => client.get('/admin/inventory/movements', { params: { type: query.type, page: query.page }, signal }), [queryKey]);

    return (
        <DataTable
            resource={resource}
            columns={['Date', 'Product', 'Type', '>Change', 'Reason', 'By']}
            emptyTitle="No stock movements yet"
            onPageChange={(page) => setQuery({ page })}
            toolbar={
                <select value={query.type ?? ''} onChange={(event) => setQuery({ type: event.target.value })} className="input w-auto" aria-label="Filter by type">
                    <option value="">All movement types</option>
                    {['stock_in', 'adjustment', 'sale', 'cancellation_restock'].map((type) => (
                        <option key={type} value={type}>
                            {titleCase(type)}
                        </option>
                    ))}
                </select>
            }
        >
            {(rows) =>
                rows.map((movement) => (
                    <tr key={movement.id}>
                        <td className="whitespace-nowrap">{formatDate(movement.created_at, { time: true })}</td>
                        <td>
                            <p className="text-stone-900">{movement.variant?.product_name ?? '—'}</p>
                            <p className="text-xs text-stone-500">
                                {movement.variant ? `${movement.variant.size} · ${movement.variant.color} · ${movement.variant.sku}` : ''}
                            </p>
                        </td>
                        <td className="whitespace-nowrap">{titleCase(movement.type)}</td>
                        <td className="text-right font-medium tabular-nums text-stone-900">{movement.quantity_change > 0 ? `+${movement.quantity_change}` : movement.quantity_change}</td>
                        <td>
                            {movement.order_number ? (
                                <Link to={`/admin/orders/${movement.order_number}`} className="underline-offset-4 hover:underline">
                                    {movement.reason}
                                </Link>
                            ) : (
                                movement.reason
                            )}
                        </td>
                        <td className="whitespace-nowrap">{movement.user?.name ?? 'System'}</td>
                    </tr>
                ))
            }
        </DataTable>
    );
}

export default function Inventory() {
    const toast = useToast();
    const [query, setQuery, key] = useQueryParams();
    const showHistory = query.view === 'history';
    const resource = useApi(
        (signal) => (showHistory ? Promise.resolve({ data: null }) : client.get('/admin/inventory', { params: { search: query.search, stock: query.stock, page: query.page }, signal })),
        [key],
    );
    const [adjusting, setAdjusting] = useState(null);

    const tab = (active) => `flex items-center gap-2 border-b-2 px-1 pb-2.5 text-sm ${active ? 'border-stone-900 font-medium text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800'}`;

    return (
        <>
            <PageHeader title="Inventory" description="Stock for every size and colour. Every change is recorded." />

            <div className="mb-4 flex gap-6 border-b border-stone-200">
                <button type="button" className={tab(!showHistory)} onClick={() => setQuery({ view: '', type: '' })}>
                    <SlidersHorizontal className="h-4 w-4" aria-hidden="true" />
                    Stock levels
                </button>
                <button type="button" className={tab(showHistory)} onClick={() => setQuery({ view: 'history', search: '', stock: '' })}>
                    <History className="h-4 w-4" aria-hidden="true" />
                    Movement history
                </button>
            </div>

            {showHistory ? (
                <Movements query={query} setQuery={setQuery} queryKey={key} />
            ) : (
                <DataTable
                    resource={resource}
                    columns={['Product', 'Variant', 'SKU', '>In stock', '>Alert at', 'Level', '>Actions']}
                    emptyTitle="No variants match"
                    onPageChange={(page) => setQuery({ page })}
                    toolbar={
                        <>
                            <SearchInput value={query.search} onChange={(search) => setQuery({ search })} placeholder="Search product or SKU" />
                            <select value={query.stock ?? ''} onChange={(event) => setQuery({ stock: event.target.value })} className="input w-auto" aria-label="Filter by stock level">
                                <option value="">All stock levels</option>
                                <option value="low">Low or out of stock</option>
                                <option value="out">Out of stock only</option>
                            </select>
                        </>
                    }
                >
                    {(rows) =>
                        rows.map((variant) => (
                            <tr key={variant.id} className={variant.is_active ? '' : 'opacity-60'}>
                                <td>
                                    <Link to={`/admin/products/${variant.product?.slug}/edit`} className="font-medium text-stone-900 underline-offset-4 hover:underline">
                                        {variant.product?.name}
                                    </Link>
                                </td>
                                <td className="whitespace-nowrap">
                                    {variant.size} · {variant.color}
                                    {!variant.is_active && <span className="ml-2 text-xs text-stone-500">(inactive)</span>}
                                </td>
                                <td className="whitespace-nowrap font-mono text-xs">{variant.sku}</td>
                                <td className="text-right font-medium tabular-nums text-stone-900">{variant.stock_quantity}</td>
                                <td className="text-right tabular-nums">{variant.low_stock_threshold}</td>
                                <td>
                                    <StockLevel variant={variant} />
                                </td>
                                <td className="text-right">
                                    <button type="button" className="btn btn-secondary px-3 py-1.5" onClick={() => setAdjusting(variant)}>
                                        Adjust
                                    </button>
                                </td>
                            </tr>
                        ))
                    }
                </DataTable>
            )}

            <Modal open={Boolean(adjusting)} onClose={() => setAdjusting(null)} title="Adjust stock">
                {adjusting && (
                    <AdjustForm
                        variant={adjusting}
                        onCancel={() => setAdjusting(null)}
                        onDone={() => {
                            setAdjusting(null);
                            toast.success('Stock updated.');
                            resource.reload();
                        }}
                    />
                )}
            </Modal>
        </>
    );
}
