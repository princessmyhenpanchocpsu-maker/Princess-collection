import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import Button from '../components/Button';
import FormInput from '../components/FormInput';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { errorMessage, fieldErrors } from '../lib/errors';
import { AuthCard, destinationFor } from './Login';

export default function Register() {
    const { user, loading, register } = useAuth();
    const navigate = useNavigate();
    const toast = useToast();
    const { state } = useLocation();

    const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    if (!loading && user && !submitting) return <Navigate to={destinationFor(user, state?.from)} replace />;

    const update = (field) => (event) => setForm({ ...form, [field]: event.target.value });

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage(null);

        try {
            const created = await register({ ...form, phone: form.phone.trim() || null });
            toast.success(`Welcome, ${created.name.split(' ')[0]}! Your account is ready.`);
            navigate(destinationFor(created, state?.from), { replace: true });
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) setMessage(errorMessage(error, 'We couldn’t create your account. Please try again.'));
            setSubmitting(false);
        }
    };

    return (
        <AuthCard
            title="Create your account"
            subtitle="Save your addresses, track orders and check out faster."
            footer={
                <>
                    Already have an account?{' '}
                    <Link to="/login" state={state} className="link">
                        Sign in
                    </Link>
                </>
            }
        >
            {message && (
                <p role="alert" className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {message}
                </p>
            )}
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormInput label="Full name" autoComplete="name" required value={form.name} onChange={update('name')} error={errors.name} />
                <FormInput label="Email address" type="email" autoComplete="email" required value={form.email} onChange={update('email')} error={errors.email} />
                <FormInput
                    label="Mobile number"
                    type="tel"
                    autoComplete="tel"
                    placeholder="09171234567"
                    hint="Optional. Used by the courier on delivery."
                    value={form.phone}
                    onChange={update('phone')}
                    error={errors.phone}
                />
                <FormInput
                    label="Password"
                    type="password"
                    autoComplete="new-password"
                    required
                    hint="At least 8 characters, with a letter and a number."
                    value={form.password}
                    onChange={update('password')}
                    error={errors.password}
                />
                <FormInput
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.password_confirmation}
                    onChange={update('password_confirmation')}
                    error={errors.password_confirmation}
                />
                <Button type="submit" loading={submitting} className="w-full py-3">
                    Create account
                </Button>
            </form>
        </AuthCard>
    );
}
