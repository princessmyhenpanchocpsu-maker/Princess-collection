import { ArrowLeft, ArrowRight, Plus, Trash2, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import client from '../../api/client';
import { PageHeader } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import ConfirmationModal from '../../components/ConfirmationModal';
import { ErrorState } from '../../components/EmptyState';
import FormInput from '../../components/FormInput';
import LoadingSpinner from '../../components/LoadingSpinner';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import { errorMessage, fieldErrors } from '../../lib/errors';
import { SIZE_ORDER } from '../../lib/format';

const blankVariant = (overrides = {}) => ({
    key: Math.random().toString(36).slice(2),
    id: null,
    size: 'M',
    color: '',
    sku: '',
    price_override: '',
    stock_quantity: 0,
    low_stock_threshold: 5,
    is_active: true,
    ...overrides,
});

function Images({ product, onChange }) {
    const toast = useToast();
    const input = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [errors, setErrors] = useState([]);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);
    const base = `/admin/products/${product.slug}/images`;

    const upload = async (event) => {
        const files = [...event.target.files];
        event.target.value = '';
        if (files.length === 0) return;

        const body = new FormData();
        files.forEach((file) => body.append('images[]', file));

        setUploading(true);
        setErrors([]);
        try {
            const { data } = await client.post(base, body);
            onChange(data.data);
            toast.success(files.length === 1 ? 'Image uploaded.' : `${files.length} images uploaded.`);
        } catch (error) {
            const messages = Object.values(fieldErrors(error));
            setErrors(messages.length ? messages : [errorMessage(error)]);
        } finally {
            setUploading(false);
        }
    };

    const move = async (index, direction) => {
        const order = product.images.map((image) => image.id);
        [order[index], order[index + direction]] = [order[index + direction], order[index]];

        try {
            const { data } = await client.patch(`${base}/reorder`, { order });
            onChange(data.data);
        } catch (error) {
            toast.error(errorMessage(error));
        }
    };

    const remove = async () => {
        setBusy(true);
        try {
            await client.delete(`${base}/${deleting.id}`);
            onChange(product.images.filter((image) => image.id !== deleting.id));
            setDeleting(null);
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className="card p-5">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-stone-900">Images</h2>
                    <p className="text-sm text-stone-500">JPEG, PNG or WebP, up to 4 MB each. The first image is the main photo.</p>
                </div>
                <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={upload} />
                <Button variant="secondary" loading={uploading} onClick={() => input.current.click()}>
                    <Upload className="h-4 w-4" aria-hidden="true" />
                    Upload images
                </Button>
            </div>

            {errors.length > 0 && (
                <ul role="alert" className="mb-4 space-y-1 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}

            {product.images.length === 0 ? (
                <p className="rounded-lg border border-dashed border-stone-300 px-4 py-8 text-center text-sm text-stone-500">No images yet. Products without a photo show a placeholder in the store.</p>
            ) : (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    {product.images.map((image, index) => (
                        <li key={image.id} className="overflow-hidden rounded-lg border border-stone-200 bg-white">
                            <img src={image.url} alt={image.alt_text ?? ''} className="aspect-[4/5] w-full object-cover" />
                            <div className="flex items-center justify-between border-t border-stone-200 px-1 py-1">
                                <button type="button" className="rounded p-1 text-stone-500 hover:bg-stone-100 disabled:opacity-30" disabled={index === 0} onClick={() => move(index, -1)} aria-label="Move earlier">
                                    <ArrowLeft className="h-4 w-4" />
                                </button>
                                <button type="button" className="rounded p-1 text-stone-500 hover:bg-red-50 hover:text-red-700" onClick={() => setDeleting(image)} aria-label="Delete image">
                                    <Trash2 className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    className="rounded p-1 text-stone-500 hover:bg-stone-100 disabled:opacity-30"
                                    disabled={index === product.images.length - 1}
                                    onClick={() => move(index, 1)}
                                    aria-label="Move later"
                                >
                                    <ArrowRight className="h-4 w-4" />
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmationModal
                open={Boolean(deleting)}
                title="Delete this image?"
                message="The file is removed permanently and cannot be recovered."
                confirmLabel="Delete image"
                cancelLabel="Cancel"
                loading={busy}
                onConfirm={remove}
                onCancel={() => setDeleting(null)}
            />
        </section>
    );
}

export default function ProductForm() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const toast = useToast();
    const isNew = !slug;

    const categories = useApi((signal) => client.get('/admin/categories', { params: { per_page: 100 }, signal })).data?.data ?? [];
    const existing = useApi((signal) => (isNew ? Promise.resolve({ data: null }) : client.get(`/admin/products/${slug}`, { signal })), [slug]);

    const [form, setForm] = useState({ name: '', category_id: '', description: '', base_price: '', status: 'draft' });
    const [variants, setVariants] = useState([blankVariant()]);
    const [images, setImages] = useState([]);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);
    const [bulk, setBulk] = useState({ sizes: [...SIZE_ORDER], colors: '' });

    const product = existing.data?.data;

    useEffect(() => {
        if (!product) return;

        setForm({
            name: product.name,
            category_id: product.category_id,
            description: product.description ?? '',
            base_price: product.base_price,
            status: product.status,
        });
        setVariants(product.variants.map((variant) => blankVariant({ ...variant, key: String(variant.id), price_override: variant.price_override ?? '' })));
        setImages(product.images);
    }, [product]);

    if (!isNew && existing.loading && !product) return <LoadingSpinner className="py-24" />;
    if (!isNew && existing.error) return <ErrorState error={existing.error} onRetry={existing.reload} title="We couldn’t load this product" className="py-24" />;

    const field = (name) => ({ value: form[name], onChange: (event) => setForm({ ...form, [name]: event.target.value }), error: errors[name] });
    const setVariant = (key, changes) => setVariants((list) => list.map((variant) => (variant.key === key ? { ...variant, ...changes } : variant)));

    const generate = () => {
        const colors = bulk.colors.split(',').map((color) => color.trim()).filter(Boolean);
        const taken = new Set(variants.map((variant) => `${variant.size}|${variant.color.trim().toLowerCase()}`));
        const added = [];

        colors.forEach((color) =>
            bulk.sizes.forEach((size) => {
                const combo = `${size}|${color.toLowerCase()}`;
                if (!taken.has(combo)) {
                    taken.add(combo);
                    added.push(blankVariant({ size, color }));
                }
            }),
        );

        // Drop the untouched starter row once real rows exist.
        setVariants((list) => [...list.filter((variant) => variant.id || variant.color.trim()), ...added]);
        setBulk({ ...bulk, colors: '' });
    };

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage(null);

        const payload = {
            ...form,
            variants: variants.map((variant) => ({
                ...(variant.id ? { id: variant.id } : { stock_quantity: Number(variant.stock_quantity) || 0 }),
                size: variant.size,
                color: variant.color,
                sku: variant.sku || null,
                price_override: variant.price_override === '' ? null : variant.price_override,
                low_stock_threshold: variant.low_stock_threshold === '' ? null : Number(variant.low_stock_threshold),
                is_active: variant.is_active,
            })),
        };

        try {
            const { data } = isNew ? await client.post('/admin/products', payload) : await client.put(`/admin/products/${slug}`, payload);
            toast.success(isNew ? 'Product created. You can add images now.' : 'Product saved.');

            if (isNew || data.data.slug !== slug) navigate(`/admin/products/${data.data.slug}/edit`, { replace: true });
            else existing.setData(data);
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            setMessage(Object.keys(validation).length ? 'Please fix the highlighted fields.' : errorMessage(error));
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } finally {
            setSaving(false);
        }
    };

    const cell = 'input px-2 py-1.5';

    return (
        <form onSubmit={submit} noValidate>
            <PageHeader title={isNew ? 'New product' : `Edit ${product?.name ?? 'product'}`}>
                <Link to="/admin/products" className="btn btn-secondary">
                    Back to products
                </Link>
                <Button type="submit" loading={saving}>
                    {isNew ? 'Create product' : 'Save changes'}
                </Button>
            </PageHeader>

            {message && (
                <p role="alert" className="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {message}
                </p>
            )}

            <div className="space-y-6">
                <section className="card grid gap-4 p-5 lg:grid-cols-2">
                    <FormInput label="Product name" required {...field('name')} className="lg:col-span-2" />
                    <FormInput as="select" label="Category" required {...field('category_id')}>
                        <option value="">Choose a category</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                                {category.is_active ? '' : ' (archived)'}
                            </option>
                        ))}
                    </FormInput>
                    <FormInput label="Base price (₱)" type="number" min="0" step="0.01" required {...field('base_price')} />
                    <FormInput as="select" label="Status" hint="Only published products appear in the store." {...field('status')}>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </FormInput>
                    <FormInput as="textarea" rows={4} label="Description" maxLength={5000} {...field('description')} className="lg:col-span-2" />
                </section>

                <section className="card p-5">
                    <h2 className="font-semibold text-stone-900">Variants</h2>
                    <p className="text-sm text-stone-500">
                        One row per size and colour. Leave SKU empty to generate one, and price empty to use the base price.
                        {!isNew && ' To change stock on an existing variant, use Inventory.'}
                    </p>
                    {errors.variants && <p className="mt-2 text-sm text-red-600">{errors.variants}</p>}

                    <div className="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-stone-50 p-3">
                        <fieldset>
                            <legend className="mb-1 text-xs font-medium text-stone-600">Sizes</legend>
                            <div className="flex gap-1">
                                {SIZE_ORDER.map((size) => {
                                    const on = bulk.sizes.includes(size);

                                    return (
                                        <button
                                            key={size}
                                            type="button"
                                            aria-pressed={on}
                                            onClick={() => setBulk({ ...bulk, sizes: on ? bulk.sizes.filter((item) => item !== size) : [...bulk.sizes, size] })}
                                            className={`h-9 min-w-9 rounded-lg border px-2 text-sm ${on ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 bg-white text-stone-600'}`}
                                        >
                                            {size}
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                        <label className="min-w-48 flex-1">
                            <span className="mb-1 block text-xs font-medium text-stone-600">Colours, separated by commas</span>
                            <input className="input" placeholder="Rose, Cream, Black" value={bulk.colors} onChange={(event) => setBulk({ ...bulk, colors: event.target.value })} />
                        </label>
                        <Button variant="secondary" onClick={generate} disabled={!bulk.colors.trim() || bulk.sizes.length === 0}>
                            Add combinations
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Size</th>
                                    <th scope="col">Colour</th>
                                    <th scope="col">SKU</th>
                                    <th scope="col">Price override (₱)</th>
                                    <th scope="col">{isNew ? 'Opening stock' : 'Stock'}</th>
                                    <th scope="col">Low-stock alert at</th>
                                    <th scope="col">Active</th>
                                    <th scope="col" />
                                </tr>
                            </thead>
                            <tbody>
                                {variants.map((variant, index) => {
                                    const rowError = ['size', 'color', 'sku', 'price_override', 'stock_quantity', 'low_stock_threshold', 'id'].map((name) => errors[`variants.${index}.${name}`]).find(Boolean);

                                    return (
                                        <tr key={variant.key} className={rowError ? 'bg-red-50/60' : ''}>
                                            <td>
                                                <select className={`${cell} w-20`} value={variant.size} onChange={(event) => setVariant(variant.key, { size: event.target.value })} aria-label="Size">
                                                    {[...new Set([...SIZE_ORDER, variant.size])].map((size) => (
                                                        <option key={size}>{size}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td>
                                                <input className={`${cell} w-32`} value={variant.color} onChange={(event) => setVariant(variant.key, { color: event.target.value })} aria-label="Colour" />
                                            </td>
                                            <td>
                                                <input className={`${cell} w-40`} value={variant.sku} placeholder="Auto" onChange={(event) => setVariant(variant.key, { sku: event.target.value })} aria-label="SKU" />
                                            </td>
                                            <td>
                                                <input
                                                    className={`${cell} w-28`}
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder={form.base_price || 'Base'}
                                                    value={variant.price_override}
                                                    onChange={(event) => setVariant(variant.key, { price_override: event.target.value })}
                                                    aria-label="Price override"
                                                />
                                            </td>
                                            <td>
                                                {variant.id ? (
                                                    <span className="tabular-nums">{variant.stock_quantity}</span>
                                                ) : (
                                                    <input
                                                        className={`${cell} w-24`}
                                                        type="number"
                                                        min="0"
                                                        value={variant.stock_quantity}
                                                        onChange={(event) => setVariant(variant.key, { stock_quantity: event.target.value })}
                                                        aria-label="Opening stock"
                                                    />
                                                )}
                                            </td>
                                            <td>
                                                <input
                                                    className={`${cell} w-20`}
                                                    type="number"
                                                    min="0"
                                                    value={variant.low_stock_threshold}
                                                    onChange={(event) => setVariant(variant.key, { low_stock_threshold: event.target.value })}
                                                    aria-label="Low-stock threshold"
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    checked={variant.is_active}
                                                    onChange={(event) => setVariant(variant.key, { is_active: event.target.checked })}
                                                    className="rounded border-stone-300 text-stone-900 focus:ring-rose"
                                                    aria-label="Active"
                                                />
                                            </td>
                                            <td className="text-right">
                                                {!variant.id && variants.length > 1 && (
                                                    <button type="button" className="rounded p-1 text-stone-500 hover:bg-stone-100" onClick={() => setVariants((list) => list.filter((item) => item.key !== variant.key))} aria-label="Remove row">
                                                        <X className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {rowError && <p className="mt-1 whitespace-nowrap text-left text-xs text-red-600">{rowError}</p>}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <button type="button" className="btn btn-ghost mt-3" onClick={() => setVariants((list) => [...list, blankVariant()])}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Add a row
                    </button>
                </section>

                {isNew ? (
                    <p className="card p-5 text-sm text-stone-500">Save the product first, then you can upload its images.</p>
                ) : (
                    product && <Images product={{ ...product, images }} onChange={setImages} />
                )}
            </div>
        </form>
    );
}
