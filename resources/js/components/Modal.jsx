import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';

export default function Modal({ open, onClose, title, children, footer, size = 'max-w-lg' }) {
    const panel = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const previouslyFocused = document.activeElement;
        const onKey = (event) => event.key === 'Escape' && onClose();

        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        panel.current?.focus();

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
            previouslyFocused?.focus?.();
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div className="absolute inset-0 bg-ink/40" onClick={onClose} aria-hidden="true" />
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                aria-label={title}
                tabIndex={-1}
                className={`relative flex max-h-[90vh] w-full ${size} flex-col rounded-t-2xl bg-white shadow-xl outline-none sm:rounded-2xl`}
            >
                <div className="flex items-center justify-between gap-4 border-b border-brown/10 px-5 py-4">
                    <h2 className="text-lg font-semibold text-ink">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-full p-1.5 text-brown/60 hover:bg-mist hover:text-ink" aria-label="Close">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex flex-wrap justify-end gap-3 border-t border-brown/10 px-5 py-4">{footer}</div>}
            </div>
        </div>
    );
}
