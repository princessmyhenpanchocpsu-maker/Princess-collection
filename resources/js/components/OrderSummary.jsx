import { formatPeso } from '../lib/format';

// Totals block shared by the cart, checkout and order pages. All figures come from the server.
export default function OrderSummary({ subtotal, shippingFee, discount, total, children }) {
    const free = Number(shippingFee) === 0;

    return (
        <dl className="space-y-2.5 text-sm">
            <div className="flex justify-between">
                <dt className="text-brown">Subtotal</dt>
                <dd className="tabular-nums text-ink">{formatPeso(subtotal)}</dd>
            </div>
            <div className="flex justify-between">
                <dt className="text-brown">Shipping</dt>
                <dd className={`tabular-nums ${free ? 'font-medium text-emerald-700' : 'text-ink'}`}>{free ? 'Free' : formatPeso(shippingFee)}</dd>
            </div>
            {Number(discount) > 0 && (
                <div className="flex justify-between">
                    <dt className="text-brown">Discount</dt>
                    <dd className="tabular-nums text-ink">−{formatPeso(discount)}</dd>
                </div>
            )}
            {children}
            <div className="flex justify-between border-t border-brown/10 pt-3 text-base font-semibold">
                <dt className="text-ink">Total</dt>
                <dd className="tabular-nums text-ink">{formatPeso(total)}</dd>
            </div>
        </dl>
    );
}

export function LineItem({ imageUrl, name, meta, quantity, price, to, children }) {
    const Wrapper = to ? 'a' : 'div';

    return (
        <div className="flex gap-3">
            <Wrapper {...(to ? { href: to } : {})} className="h-20 w-16 shrink-0 overflow-hidden rounded-lg bg-blush/40">
                {imageUrl && <img src={imageUrl} alt="" loading="lazy" className="h-full w-full object-cover" />}
            </Wrapper>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-ink">{name}</p>
                <p className="text-xs text-brown/70">{meta}</p>
                {quantity != null && <p className="mt-1 text-xs text-brown">Qty {quantity}</p>}
                {children}
            </div>
            <p className="shrink-0 text-sm font-medium tabular-nums text-ink">{formatPeso(price)}</p>
        </div>
    );
}
