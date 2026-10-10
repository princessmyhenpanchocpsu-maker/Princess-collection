import { formatPeso } from '../lib/format';

// Shows a single price, or "From ₱X" when variants are priced differently.
export default function PriceDisplay({ price, maxPrice, className = '' }) {
    const hasRange = maxPrice != null && Number(maxPrice) > Number(price);

    return (
        <span className={`font-medium text-ink ${className}`}>
            {hasRange && <span className="mr-1 text-[0.85em] font-normal text-brown/70">From</span>}
            {formatPeso(price)}
        </span>
    );
}
