import { titleCase } from '../lib/format';

const STYLES = {
    pending: 'bg-amber-100 text-amber-900',
    confirmed: 'bg-sky-100 text-sky-900',
    processing: 'bg-indigo-100 text-indigo-900',
    shipped: 'bg-violet-100 text-violet-900',
    delivered: 'bg-emerald-100 text-emerald-900',
    cancelled: 'bg-stone-200 text-stone-700',
    // payment and product statuses share the badge
    paid: 'bg-emerald-100 text-emerald-900',
    unpaid: 'bg-amber-100 text-amber-900',
    refunded: 'bg-stone-200 text-stone-700',
    published: 'bg-emerald-100 text-emerald-900',
    draft: 'bg-amber-100 text-amber-900',
    archived: 'bg-stone-200 text-stone-700',
    active: 'bg-emerald-100 text-emerald-900',
    inactive: 'bg-stone-200 text-stone-700',
};

export default function OrderStatusBadge({ status, className = '' }) {
    return (
        <span className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ${STYLES[status] ?? 'bg-mist text-brown'} ${className}`}>
            {titleCase(status)}
        </span>
    );
}
