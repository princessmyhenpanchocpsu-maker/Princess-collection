import { Check } from 'lucide-react';
import { colorHex } from '../lib/format';

// Dark swatches need a light tick, light ones a dark tick.
function isDark(hex) {
    const [r, g, b] = [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16));

    return (r * 299 + g * 587 + b * 114) / 1000 < 150;
}

export function ColorSwatch({ color, className = 'h-4 w-4' }) {
    const hex = colorHex(color);

    return (
        <span
            title={color}
            className={`inline-block shrink-0 rounded-full border border-brown/25 ${className}`}
            style={{ background: hex ?? 'repeating-linear-gradient(45deg, #e7e5e4 0 4px, #fafaf9 4px 8px)' }}
        />
    );
}

export default function ColorSelector({ colors, value, onChange }) {
    return (
        <div role="radiogroup" aria-label="Colour" className="flex flex-wrap gap-2">
            {colors.map((color) => {
                const selected = color === value;
                const hex = colorHex(color);

                return (
                    <button
                        key={color}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(color)}
                        className={`flex items-center gap-2 rounded-full border py-1.5 pl-1.5 pr-3.5 text-sm transition ${
                            selected ? 'border-brown bg-blush/50 font-medium text-ink' : 'border-brown/20 bg-white text-brown hover:border-rose'
                        }`}
                    >
                        <span
                            className="flex h-7 w-7 items-center justify-center rounded-full border border-brown/25"
                            style={{ background: hex ?? 'repeating-linear-gradient(45deg, #e7e5e4 0 4px, #fafaf9 4px 8px)' }}
                        >
                            {selected && <Check className={`h-4 w-4 ${hex && isDark(hex) ? 'text-white' : 'text-ink'}`} strokeWidth={3} aria-hidden="true" />}
                        </span>
                        {color}
                    </button>
                );
            })}
        </div>
    );
}
