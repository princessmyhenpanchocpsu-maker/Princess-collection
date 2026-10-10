import { Archive, ArchiveRestore, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import client from '../../api/client';
import { DataTable, PageHeader, SearchInput } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import ConfirmationModal from '../../components/ConfirmationModal';
import FormInput from '../../components/FormInput';
import Modal from '../../components/Modal';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { clearStoreData } from '../../hooks/useStoreData';
import { errorMessage, fieldErrors } from '../../lib/errors';

function CategoryForm({ category, onSaved, onCancel }) {
    const [form, setForm] = useState({ name: category?.name ?? '', description: category?.description ?? '' });
    const [image, setImage] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage(null);

        // Multipart so the optional image travels with the fields. PHP only parses
        // multipart bodies on POST, so an update is sent as POST with a method override.
        const body = new FormData();
        body.append('name', form.name);
        body.append('description', form.description);
        if (image) body.append('image', image);
        if (category) body.append('_method', 'PUT');

        try {
            await client.post(category ? `/admin/categories/${category.slug}` : '/admin/categories', body);
            onSaved();
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) setMessage(errorMessage(error));
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            {message && (
                <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {message}
                </p>
            )}
            <FormInput label="Name" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} error={errors.name} />
            <FormInput as="textarea" rows={3} label="Description" maxLength={1000} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} error={errors.description} />
            <FormInput
                label="Image"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                hint={category?.image_url ? 'Choose a file only if you want to replace the current image.' : 'Optional. JPEG, PNG or WebP, up to 4 MB.'}
                onChange={(event) => setImage(event.target.files[0] ?? null)}
                error={errors.image}
            />
            <div className="flex justify-end gap-3 pt-2">
                <Button variant="secondary" onClick={onCancel} disabled={saving}>
                    Cancel
                </Button>
                <Button type="submit" loading={saving}>
                    {category ? 'Save changes' : 'Create category'}
                </Button>
            </div>
        </form>
    );
}

export default function Categories() {
    const toast = useToast();
    const [query, setQuery, key] = useQueryParams();
    const resource = useApi((signal) => client.get('/admin/categories', { params: query, signal }), [key]);
    const [editing, setEditing] = useState(null); // null | 'new' | category
    const [archiving, setArchiving] = useState(null);
    const [busy, setBusy] = useState(false);

    const refresh = () => {
        clearStoreData('categories');
        resource.reload();
    };

    const setActive = async (category, isActive) => {
        setBusy(true);
        try {
            if (isActive) await client.put(`/admin/categories/${category.slug}`, { name: category.name, description: category.description, is_active: true });
            else await client.delete(`/admin/categories/${category.slug}`);

            toast.success(isActive ? `${category.name} is visible in the store again.` : `${category.name} was archived.`);
            setArchiving(null);
            refresh();
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <PageHeader title="Categories" description="Group products for the store menu and filters.">
                <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    New category
                </button>
            </PageHeader>

            <DataTable
                resource={resource}
                columns={['Category', 'Products', 'Status', '>Actions']}
                emptyTitle="No categories found"
                onPageChange={(page) => setQuery({ page })}
                toolbar={<SearchInput value={query.search} onChange={(search) => setQuery({ search })} placeholder="Search categories" />}
            >
                {(rows) =>
                    rows.map((category) => (
                        <tr key={category.id}>
                            <td>
                                <div className="flex items-center gap-3">
                                    <div className="h-12 w-10 shrink-0 overflow-hidden rounded bg-stone-100">{category.image_url && <img src={category.image_url} alt="" className="h-full w-full object-cover" />}</div>
                                    <div className="min-w-0">
                                        <p className="font-medium text-stone-900">{category.name}</p>
                                        <p className="max-w-md truncate text-xs text-stone-500">{category.description}</p>
                                    </div>
                                </div>
                            </td>
                            <td className="tabular-nums">{category.products_count}</td>
                            <td>
                                <OrderStatusBadge status={category.is_active ? 'active' : 'archived'} />
                            </td>
                            <td>
                                <div className="flex justify-end gap-1">
                                    <button type="button" className="btn btn-ghost px-2 py-1.5" onClick={() => setEditing(category)} aria-label={`Edit ${category.name}`}>
                                        <Pencil className="h-4 w-4" />
                                    </button>
                                    {category.is_active ? (
                                        <button type="button" className="btn btn-ghost px-2 py-1.5 hover:text-red-700" onClick={() => setArchiving(category)} aria-label={`Archive ${category.name}`}>
                                            <Archive className="h-4 w-4" />
                                        </button>
                                    ) : (
                                        <button type="button" className="btn btn-ghost px-2 py-1.5" disabled={busy} onClick={() => setActive(category, true)} aria-label={`Restore ${category.name}`}>
                                            <ArchiveRestore className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))
                }
            </DataTable>

            <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title={editing === 'new' ? 'New category' : 'Edit category'}>
                {editing && (
                    <CategoryForm
                        category={editing === 'new' ? null : editing}
                        onCancel={() => setEditing(null)}
                        onSaved={() => {
                            setEditing(null);
                            toast.success('Category saved.');
                            refresh();
                        }}
                    />
                )}
            </Modal>

            <ConfirmationModal
                open={Boolean(archiving)}
                title={`Archive ${archiving?.name ?? 'category'}?`}
                message={`It will disappear from the store, along with its ${archiving?.products_count ?? 0} product(s), until you restore it. Nothing is deleted.`}
                confirmLabel="Archive category"
                cancelLabel="Cancel"
                loading={busy}
                onConfirm={() => setActive(archiving, false)}
                onCancel={() => setArchiving(null)}
            />
        </>
    );
}
