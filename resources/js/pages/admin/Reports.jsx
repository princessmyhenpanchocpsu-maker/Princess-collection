import client from '../../api/client';
import { PageHeader, StatTile } from '../../components/admin/AdminUI';
import { ErrorState } from '../../components/EmptyState';
import LoadingSpinner from '../../components/LoadingSpinner';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { fieldErrors } from '../../lib/errors';
import { formatDate, formatPeso } from '../../lib/format';

const GROUPS = [
    ['daily', 'Daily'],
    ['weekly', 'Weekly'],
    ['monthly', 'Monthly'],
];

function periodLabel(period, group) {
    const date = new Date(`${period}T00:00:00`);

    if (group === 'monthly') return new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric' }).format(date);
    if (group === 'weekly') return `Week of ${formatDate(date)}`;

    return new Intl.DateTimeFormat('en-PH', { weekday: 'short', dateStyle: undefined, month: 'short', day: 'numeric', year: 'numeric' }).format(date);
}

export default function Reports() {
    const [query, setQuery, key] = useQueryParams();
    const { data, loading, error, reload } = useApi((signal) => client.get('/admin/reports/sales', { params: query, signal }), [key]);
    const report = data?.data;
    const group = query.group ?? 'daily';
    const invalid = error?.response?.status === 422 ? Object.values(fieldErrors(error))[0] : null;

    return (
        <>
            <PageHeader title="Sales reports" description="Orders that were not cancelled, by the date they were placed." />

            <div className="card mb-6 flex flex-wrap items-end gap-4 p-4">
                <div role="group" aria-label="Group by" className="flex overflow-hidden rounded-lg border border-stone-300">
                    {GROUPS.map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={group === value}
                            onClick={() => setQuery({ group: value })}
                            className={`px-4 py-2 text-sm ${group === value ? 'bg-stone-900 font-medium text-white' : 'bg-white text-stone-600 hover:bg-stone-50'}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <label className="text-sm text-stone-600">
                    <span className="mb-1 block text-xs font-medium">From</span>
                    <input type="date" value={query.date_from ?? report?.date_from ?? ''} max={query.date_to ?? report?.date_to} onChange={(event) => setQuery({ date_from: event.target.value })} className="input w-auto" />
                </label>
                <label className="text-sm text-stone-600">
                    <span className="mb-1 block text-xs font-medium">To</span>
                    <input type="date" value={query.date_to ?? report?.date_to ?? ''} min={query.date_from ?? report?.date_from} onChange={(event) => setQuery({ date_to: event.target.value })} className="input w-auto" />
                </label>
                {(query.date_from || query.date_to) && (
                    <button type="button" className="btn btn-ghost" onClick={() => setQuery({ date_from: '', date_to: '' })}>
                        Last 30 days
                    </button>
                )}
            </div>

            {invalid ? (
                <p role="alert" className="card p-5 text-sm text-red-700">
                    {invalid}
                </p>
            ) : error ? (
                <ErrorState error={error} onRetry={reload} className="py-16" />
            ) : !report ? (
                <LoadingSpinner className="py-16" />
            ) : (
                <div className={loading ? 'opacity-60' : ''}>
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                        <StatTile label="Total sales" value={formatPeso(report.summary.total)} hint="Including shipping fees" />
                        <StatTile label="Orders" value={report.summary.orders} />
                        <StatTile label="Items sold" value={report.summary.items_sold} />
                        <StatTile label="Average order" value={formatPeso(report.summary.average_order_value)} />
                        <StatTile label="Cancelled orders" value={report.summary.cancelled_orders} hint="Not counted in sales" />
                    </div>

                    <div className="mt-6 grid gap-6 xl:grid-cols-3">
                        <section className="card overflow-hidden xl:col-span-2">
                            <h2 className="border-b border-stone-200 px-5 py-3.5 font-semibold text-stone-900">
                                {GROUPS.find(([value]) => value === report.group)[1]} sales, {formatDate(report.date_from)} – {formatDate(report.date_to)}
                            </h2>
                            {report.rows.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-stone-500">No sales in this period.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Period</th>
                                                <th scope="col" className="text-right">Orders</th>
                                                <th scope="col" className="text-right">Items</th>
                                                <th scope="col" className="text-right">Products</th>
                                                <th scope="col" className="text-right">Shipping</th>
                                                <th scope="col" className="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {report.rows.map((row) => (
                                                <tr key={row.period}>
                                                    <td className="whitespace-nowrap">{periodLabel(row.period, report.group)}</td>
                                                    <td className="text-right tabular-nums">{row.orders}</td>
                                                    <td className="text-right tabular-nums">{row.items_sold}</td>
                                                    <td className="text-right tabular-nums">{formatPeso(row.subtotal)}</td>
                                                    <td className="text-right tabular-nums">{formatPeso(row.shipping)}</td>
                                                    <td className="text-right font-medium tabular-nums text-stone-900">{formatPeso(row.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t-2 border-stone-200 bg-stone-50 font-semibold text-stone-900">
                                                <td className="px-4 py-3">Total</td>
                                                <td className="px-4 py-3 text-right tabular-nums">{report.summary.orders}</td>
                                                <td className="px-4 py-3 text-right tabular-nums">{report.summary.items_sold}</td>
                                                <td className="px-4 py-3 text-right tabular-nums">{formatPeso(report.summary.subtotal)}</td>
                                                <td className="px-4 py-3 text-right tabular-nums">{formatPeso(report.summary.shipping)}</td>
                                                <td className="px-4 py-3 text-right tabular-nums">{formatPeso(report.summary.total)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}
                        </section>

                        <section className="card h-fit overflow-hidden">
                            <h2 className="border-b border-stone-200 px-5 py-3.5 font-semibold text-stone-900">Bestsellers in this period</h2>
                            {report.bestsellers.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-stone-500">No sales in this period.</p>
                            ) : (
                                <ol className="divide-y divide-stone-100">
                                    {report.bestsellers.map((product, index) => (
                                        <li key={product.name} className="flex items-center gap-3 px-5 py-3 text-sm">
                                            <span className="w-5 text-stone-400">{index + 1}</span>
                                            <span className="min-w-0 flex-1 truncate text-stone-800">{product.name}</span>
                                            <span className="whitespace-nowrap text-right tabular-nums text-stone-500">
                                                {product.units_sold} sold · {formatPeso(product.revenue)}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </div>
                </div>
            )}
        </>
    );
}
