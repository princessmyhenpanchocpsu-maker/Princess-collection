import { CircleAlert, RotateCw } from 'lucide-react';
import { errorMessage } from '../lib/errors';

export default function EmptyState({ icon: Icon, title, message, action, className = 'py-16' }) {
    return (
        <div className={`flex flex-col items-center justify-center px-4 text-center ${className}`}>
            {Icon && (
                <span className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blush/60 text-brown">
                    <Icon className="h-6 w-6" strokeWidth={1.5} aria-hidden="true" />
                </span>
            )}
            <h2 className="font-display text-xl text-brown">{title}</h2>
            {message && <p className="mt-2 max-w-md text-sm text-brown/75">{message}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

// The error counterpart: says what failed and offers a retry.
export function ErrorState({ error, onRetry, title = 'We couldn’t load this', className }) {
    return (
        <EmptyState
            icon={CircleAlert}
            title={title}
            message={errorMessage(error)}
            className={className}
            action={
                onRetry && (
                    <button type="button" onClick={onRetry} className="btn btn-secondary">
                        <RotateCw className="h-4 w-4" aria-hidden="true" />
                        Try again
                    </button>
                )
            }
        />
    );
}
