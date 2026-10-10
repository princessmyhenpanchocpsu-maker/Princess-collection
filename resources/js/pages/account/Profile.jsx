import { useState } from 'react';
import client from '../../api/client';
import Button from '../../components/Button';
import FormInput from '../../components/FormInput';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { errorMessage, fieldErrors } from '../../lib/errors';

export default function Profile() {
    const { user, setUser } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({ name: user.name, email: user.email, phone: user.phone ?? '' });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const field = (name) => ({ value: form[name], onChange: (event) => setForm({ ...form, [name]: event.target.value }), error: errors[name] });

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const { data } = await client.put('/user/profile', { ...form, phone: form.phone.trim() || null });
            setUser(data.data);
            toast.success('Profile updated.');
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) toast.error(errorMessage(error));
        } finally {
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="card max-w-xl space-y-4 p-6" noValidate>
            <h2 className="font-display text-xl font-semibold text-brown">Profile</h2>
            <FormInput label="Full name" autoComplete="name" required {...field('name')} />
            <FormInput label="Email address" type="email" autoComplete="email" required {...field('email')} />
            <FormInput label="Mobile number" type="tel" autoComplete="tel" placeholder="09171234567" {...field('phone')} />
            <Button type="submit" loading={saving}>
                Save changes
            </Button>
        </form>
    );
}
