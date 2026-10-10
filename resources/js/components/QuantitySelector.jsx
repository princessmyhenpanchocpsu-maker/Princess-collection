import { Minus, Plus } from 'lucide-react';

export default function QuantitySelector({ value, onChange, min = 1, max = 99, disabled = false, size = 'md' }) {
    const box = size === 'sm' ? 'h-8 w-8' : 'h-10 w-10';
    const button = `flex ${box} items-center justify-center text-brown transition hover:bg-blush/60 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent`;

    return (
        <div className="inline-flex items-center overflow-hidden rounded-full border border-brown/20 bg-white" role="group" aria-label="Quantity">
            <button type="button" className={button} onClick={() => onChange(value - 1)} disabled={disabled || value <= min} aria-label="Decrease quantity">
                <Minus className="h-4 w-4" />
            </button>
            <span className={`min-w-8 text-center text-sm font-medium tabular-nums ${disabled ? 'text-brown/50' : 'text-ink'}`} aria-live="polite">
                {value}
            </span>
            <button type="button" className={button} onClick={() => onChange(value + 1)} disabled={disabled || value >= max} aria-label="Increase quantity">
                <Plus className="h-4 w-4" />
            </button>
        </div>
    );
}
