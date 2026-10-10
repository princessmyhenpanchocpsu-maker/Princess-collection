import { useState } from 'react';
import client from '../api/client';
import { errorMessage, fieldErrors } from '../lib/errors';
import Button from './Button';
import FormInput from './FormInput';

const EMPTY = {
    recipient_name: '',
    phone: '',
    address_line: '',
    barangay: '',
    city: '',
    province: '',
    postal_code: '',
    delivery_instructions: '',
    is_default: false,
};

// Creates a new address, or updates `address` when one is given. Calls onSaved with the saved record.
export default function AddressForm({ address, defaults = {}, onSaved, onCancel }) {
    const [form, setForm] = useState({ ...EMPTY, ...defaults, ...(address ?? {}), delivery_instructions: address?.delivery_instructions ?? '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);

    const field = (name) => ({
        value: form[name],
        onChange: (event) => setForm({ ...form, [name]: event.target.value }),
        error: errors[name],
    });

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage(null);

        try {
            const payload = { ...form, delivery_instructions: form.delivery_instructions.trim() || null };
            const { data } = address ? await client.put(`/addresses/${address.id}`, payload) : await client.post('/addresses', payload);
            onSaved(data.data);
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            setMessage(validation.address ?? (Object.keys(validation).length === 0 ? errorMessage(error) : null));
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            {message && (
                <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {message}
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <FormInput label="Recipient name" autoComplete="name" required {...field('recipient_name')} />
                <FormInput label="Mobile number" type="tel" autoComplete="tel" placeholder="09171234567" required {...field('phone')} />
            </div>
            <FormInput label="House no., street, building" autoComplete="address-line1" required {...field('address_line')} />
            <div className="grid gap-4 sm:grid-cols-2">
                <FormInput label="Barangay" required {...field('barangay')} />
                <FormInput label="City / Municipality" autoComplete="address-level2" required {...field('city')} />
                <FormInput label="Province" autoComplete="address-level1" required {...field('province')} />
                <FormInput label="Postal code" inputMode="numeric" autoComplete="postal-code" maxLength={4} required {...field('postal_code')} />
            </div>
            <FormInput as="textarea" rows={2} label="Delivery instructions" hint="Optional. Landmarks, gate codes, who to look for." maxLength={500} {...field('delivery_instructions')} />
            {!address?.is_default && (
                <label className="flex cursor-pointer items-center gap-2.5 text-sm text-brown">
                    <input
                        type="checkbox"
                        checked={form.is_default}
                        onChange={(event) => setForm({ ...form, is_default: event.target.checked })}
                        className="rounded border-brown/30 text-brown focus:ring-rose"
                    />
                    Use as my default address
                </label>
            )}
            <div className="flex flex-wrap justify-end gap-3 pt-2">
                {onCancel && (
                    <Button variant="secondary" onClick={onCancel} disabled={saving}>
                        Cancel
                    </Button>
                )}
                <Button type="submit" loading={saving}>
                    {address ? 'Save changes' : 'Save address'}
                </Button>
            </div>
        </form>
    );
}

export function AddressLines({ address, className = '' }) {
    return (
        <address className={`text-sm not-italic text-brown ${className}`}>
            <span className="font-medium text-ink">{address.recipient_name}</span>
            <br />
            {address.address_line}, {address.barangay}
            <br />
            {address.city}, {address.province} {address.postal_code}
            <br />
            {address.phone}
            {address.delivery_instructions && (
                <>
                    <br />
                    <span className="text-brown/70">Note: {address.delivery_instructions}</span>
                </>
            )}
        </address>
    );
}
