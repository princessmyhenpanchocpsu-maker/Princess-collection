import { Loader2 } from 'lucide-react';

export default function LoadingSpinner({ label = 'Loading…', className = 'py-16' }) {
    return (
        <div role="status" className={`flex flex-col items-center justify-center gap-3 text-brown/70 ${className}`}>
            <Loader2 className="h-7 w-7 animate-spin text-rose" aria-hidden="true" />
            <span className="text-sm">{label}</span>
        </div>
    );
}
