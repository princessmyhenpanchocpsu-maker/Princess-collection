import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import Button from '../components/Button';
import FormInput from '../components/FormInput';
import { useAuth } from '../context/AuthContext';
import { errorMessage, fieldErrors } from '../lib/errors';

// Where to go after signing in: back to the page that asked for it, or a sensible home for the role.
export function destinationFor(user, from) {
    if (from && from !== '/login' && from !== '/register') return from;

    return user.role === 'admin' ? '/admin' : '/';
}

export function AuthCard({ title, subtitle, children, footer }) {
    return (
        <div className="container-page flex justify-center py-12 sm:py-16">
            <div className="w-full max-w-md">
                <div className="card p-6 sm:p-8">
                    <h1 className="font-display text-2xl font-semibold text-brown">{title}</h1>
                    {subtitle && <p className="mt-1.5 text-sm text-brown/75">{subtitle}</p>}
                    <div className="mt-6">{children}</div>
                </div>
                <p className="mt-5 text-center text-sm text-brown/80">{footer}</p>
            </div>
        </div>
    );
}

export default function Login() {
    const { user, loading, login } = useAuth();
    const navigate = useNavigate();
    const { state } = useLocation();

    const [form, setForm] = useState({ email: '', password: '' });
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
            const signedIn = await login(form);
            navigate(destinationFor(signedIn, state?.from), { replace: true });
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) setMessage(errorMessage(error, 'We couldn’t sign you in. Please try again.'));
            setSubmitting(false);
        }
    };

    return (
        <AuthCard
            title="Welcome back"
            subtitle="Sign in to see your cart, orders and saved addresses."
            footer={
                <>
                    New here?{' '}
                    <Link to="/register" state={state} className="link">
                        Create an account
                    </Link>
                </>
            }
        >
            {state?.notice && !message && <p className="mb-4 rounded-xl bg-blush/50 px-4 py-3 text-sm text-brown">{state.notice}</p>}
            {message && (
                <p role="alert" className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {message}
                </p>
            )}
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormInput label="Email address" type="email" autoComplete="email" required value={form.email} onChange={update('email')} error={errors.email} />
                <FormInput label="Password" type="password" autoComplete="current-password" required value={form.password} onChange={update('password')} error={errors.password} />
                <Button type="submit" loading={submitting} className="w-full py-3">
                    Sign in
                </Button>
            </form>
        </AuthCard>
    );
}
