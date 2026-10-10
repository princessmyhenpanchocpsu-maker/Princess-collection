import { useEffect, useState } from 'react';
import client from '../../api/client';
import { PageHeader } from '../../components/admin/AdminUI';
import Button from '../../components/Button';
import { ErrorState } from '../../components/EmptyState';
import FormInput from '../../components/FormInput';
import LoadingSpinner from '../../components/LoadingSpinner';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import { clearStoreData } from '../../hooks/useStoreData';
import { errorMessage, fieldErrors } from '../../lib/errors';

export default function Settings() {
    const toast = useToast();
    const { data, loading, error, reload } = useApi((signal) => client.get('/admin/settings', { signal }));
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (data) {
            setForm({
                shipping_fee: data.data.shipping_fee ?? '',
                free_shipping_threshold: data.data.free_shipping_threshold ?? '',
                announcement_text: data.data.announcement_text ?? '',
            });
        }
    }, [data]);

    if (error) return <ErrorState error={error} onRetry={reload} className="py-24" />;
    if (loading || !form) return <LoadingSpinner className="py-24" />;

    const field = (name) => ({ value: form[name], onChange: (event) => setForm({ ...form, [name]: event.target.value }), error: errors[name] });

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            await client.put('/admin/settings', {
                shipping_fee: form.shipping_fee,
                free_shipping_threshold: form.free_shipping_threshold === '' ? null : form.free_shipping_threshold,
                announcement_text: form.announcement_text.trim() || null,
            });
            clearStoreData('settings');
            toast.success('Settings saved. New carts and orders use them straight away.');
        } catch (failure) {
            const validation = fieldErrors(failure);
            setErrors(validation);
            if (Object.keys(validation).length === 0) toast.error(errorMessage(failure));
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <PageHeader title="Settings" description="Store-wide options. Orders already placed keep the fees they were charged." />
            <form onSubmit={submit} className="card max-w-2xl space-y-5 p-5" noValidate>
                <h2 className="font-semibold text-stone-900">Shipping</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput label="Shipping fee (₱)" type="number" min="0" step="0.01" required {...field('shipping_fee')} />
                    <FormInput label="Free shipping from (₱)" type="number" min="0" step="0.01" hint="Leave empty to always charge the shipping fee." {...field('free_shipping_threshold')} />
                </div>

                <h2 className="border-t border-stone-200 pt-5 font-semibold text-stone-900">Storefront</h2>
                <FormInput label="Announcement bar text" maxLength={200} hint="Shown at the top of every store page. Leave empty to hide the bar." {...field('announcement_text')} />

                <h2 className="border-t border-stone-200 pt-5 font-semibold text-stone-900">Payment</h2>
                <p className="text-sm text-stone-600">Cash on Delivery is the only payment method in this version.</p>

                <Button type="submit" loading={saving}>
                    Save settings
                </Button>
            </form>
        </>
    );
}
