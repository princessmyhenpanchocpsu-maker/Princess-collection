import { Loader2 } from 'lucide-react';

export default function Button({ variant = 'primary', loading = false, disabled, children, className = '', type = 'button', ...props }) {
    return (
        <button type={type} disabled={disabled || loading} className={`btn btn-${variant} ${className}`} {...props}>
            {loading && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
            {children}
        </button>
    );
}
