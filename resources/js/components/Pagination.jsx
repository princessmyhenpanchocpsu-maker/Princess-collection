import { ChevronLeft, ChevronRight } from 'lucide-react';

// `meta` is Laravel's paginator meta: { current_page, last_page, from, to, total }.
export default function Pagination({ meta, onPageChange, className = '' }) {
    if (!meta || meta.last_page <= 1) return null;

    const { current_page: current, last_page: last } = meta;
    const pages = [...new Set([1, current - 1, current, current + 1, last])].filter((page) => page >= 1 && page <= last);

    const button = 'flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm transition disabled:cursor-not-allowed disabled:opacity-40';

    return (
        <nav aria-label="Pagination" className={`flex flex-wrap items-center justify-between gap-3 ${className}`}>
            <p className="text-sm text-brown/70">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <div className="flex items-center gap-1">
                <button type="button" className={`${button} text-brown hover:bg-blush/60`} disabled={current === 1} onClick={() => onPageChange(current - 1)} aria-label="Previous page">
                    <ChevronLeft className="h-4 w-4" />
                </button>
                {pages.map((page, index) => (
                    <span key={page} className="flex items-center gap-1">
                        {index > 0 && page - pages[index - 1] > 1 && <span className="px-1 text-brown/50">…</span>}
                        <button
                            type="button"
                            onClick={() => onPageChange(page)}
                            aria-current={page === current ? 'page' : undefined}
                            className={`${button} ${page === current ? 'bg-brown font-medium text-white' : 'text-brown hover:bg-blush/60'}`}
                        >
                            {page}
                        </button>
                    </span>
                ))}
                <button type="button" className={`${button} text-brown hover:bg-blush/60`} disabled={current === last} onClick={() => onPageChange(current + 1)} aria-label="Next page">
                    <ChevronRight className="h-4 w-4" />
                </button>
            </div>
        </nav>
    );
}
