import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import EmptyState, { ErrorState } from '../EmptyState';
import LoadingSpinner from '../LoadingSpinner';
import Pagination from '../Pagination';

export function PageHeader({ title, description, children }) {
    return (
        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 className="text-2xl font-semibold text-stone-900">{title}</h1>
                {description && <p className="mt-1 text-sm text-stone-500">{description}</p>}
            </div>
            {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
        </div>
    );
}

// A headline number. The value stays in text colour; the label says what it is.
export function StatTile({ label, value, hint, icon: Icon, to, Link }) {
    const body = (
        <>
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-stone-500">{label}</p>
                {Icon && <Icon className="h-4 w-4 text-stone-400" aria-hidden="true" />}
            </div>
            <p className="mt-2 text-2xl font-semibold tabular-nums text-stone-900">{value}</p>
            {hint && <p className="mt-1 text-xs text-stone-500">{hint}</p>}
        </>
    );

    return to && Link ? (
        <Link to={to} className="card block p-5 transition hover:border-stone-400">
            {body}
        </Link>
    ) : (
        <div className="card p-5">{body}</div>
    );
}

// Text search that waits for a pause in typing before reporting the value.
export function SearchInput({ value, onChange, placeholder = 'Search', className = 'w-full sm:w-64' }) {
    const [term, setTerm] = useState(value ?? '');

    useEffect(() => {
        setTerm(value ?? '');
    }, [value]);

    useEffect(() => {
        if (term === (value ?? '')) return undefined;

        const timer = setTimeout(() => onChange(term), 350);

        return () => clearTimeout(timer);
    }, [term, value, onChange]);

    return (
        <div className={`relative ${className}`}>
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" aria-hidden="true" />
            <input type="search" value={term} onChange={(event) => setTerm(event.target.value)} placeholder={placeholder} aria-label={placeholder} className="input pl-9" />
        </div>
    );
}

/**
 * Card wrapping a paginated admin table, with loading, empty and error states.
 * `resource` is a useApi() result whose body is { data: [...], meta }.
 */
export function DataTable({ resource, columns, emptyTitle = 'Nothing to show', emptyMessage, onPageChange, toolbar, children }) {
    const { data, loading, error, reload } = resource;
    const rows = data?.data ?? [];

    return (
        <div className="card overflow-hidden">
            {toolbar && <div className="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">{toolbar}</div>}
            {error ? (
                <ErrorState error={error} onRetry={reload} className="py-12" />
            ) : loading && !data ? (
                <LoadingSpinner className="py-12" />
            ) : rows.length === 0 ? (
                <EmptyState title={emptyTitle} message={emptyMessage} className="py-12" />
            ) : (
                <div className={`overflow-x-auto ${loading ? 'opacity-60' : ''}`}>
                    <table className="admin-table">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th key={column} scope="col" className={column.startsWith('>') ? 'text-right' : ''}>
                                        {column.replace(/^>/, '')}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>{children(rows)}</tbody>
                    </table>
                </div>
            )}
            {data?.meta && onPageChange && <Pagination meta={data.meta} onPageChange={onPageChange} className="border-t border-stone-200 px-4 py-3" />}
        </div>
    );
}
