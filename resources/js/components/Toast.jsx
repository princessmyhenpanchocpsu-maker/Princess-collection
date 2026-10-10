import { CheckCircle2, CircleAlert, X } from 'lucide-react';

export default function Toast({ type, message, onDismiss }) {
    const isError = type === 'error';
    const Icon = isError ? CircleAlert : CheckCircle2;

    return (
        <div
            role={isError ? 'alert' : 'status'}
            className="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-brown/10 bg-white px-4 py-3 text-sm text-ink shadow-lg"
        >
            <Icon className={`mt-0.5 h-5 w-5 shrink-0 ${isError ? 'text-red-600' : 'text-emerald-600'}`} aria-hidden="true" />
            <p className="flex-1">{message}</p>
            <button type="button" onClick={onDismiss} className="rounded p-0.5 text-brown/50 hover:text-ink" aria-label="Dismiss">
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
