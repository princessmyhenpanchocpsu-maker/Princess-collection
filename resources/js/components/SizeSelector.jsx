import { sortSizes } from '../lib/format';

/**
 * `sizes` lists every size the product comes in; `available` is the subset that can be bought right now.
 */
export default function SizeSelector({ sizes, available = sizes, value, onChange }) {
    return (
        <div role="radiogroup" aria-label="Size" className="flex flex-wrap gap-2">
            {sortSizes(sizes).map((size) => {
                const selected = size === value;
                const enabled = available.includes(size);

                return (
                    <button
                        key={size}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={!enabled}
                        onClick={() => onChange(size)}
                        title={enabled ? undefined : 'Out of stock'}
                        className={`flex h-10 min-w-12 items-center justify-center rounded-full border px-3 text-sm font-medium transition ${
                            selected
                                ? 'border-brown bg-brown text-white'
                                : enabled
                                  ? 'border-brown/20 bg-white text-ink hover:border-rose'
                                  : 'cursor-not-allowed border-brown/10 bg-mist text-brown/40 line-through'
                        }`}
                    >
                        {size}
                    </button>
                );
            })}
        </div>
    );
}
