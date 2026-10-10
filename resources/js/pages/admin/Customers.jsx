import { Link } from 'react-router-dom';
import client from '../../api/client';
import { DataTable, PageHeader, SearchInput } from '../../components/admin/AdminUI';
import OrderStatusBadge from '../../components/OrderStatusBadge';
import useApi from '../../hooks/useApi';
import useQueryParams from '../../hooks/useQueryParams';
import { formatDate, formatPeso } from '../../lib/format';

export default function Customers() {
    const [query, setQuery, key] = useQueryParams();
    const resource = useApi((signal) => client.get('/admin/customers', { params: query, signal }), [key]);

    return (
        <>
            <PageHeader title="Customers" description="Everyone who has registered an account." />
            <DataTable
                resource={resource}
                columns={['Customer', 'Phone', 'Joined', '>Orders', '>Total spent', 'Status']}
                emptyTitle="No customers found"
                emptyMessage="Customers appear here once they register."
                onPageChange={(page) => setQuery({ page })}
                toolbar={
                    <>
                        <SearchInput value={query.search} onChange={(search) => setQuery({ search })} placeholder="Search name, email or phone" className="w-full sm:w-72" />
                        <select value={query.is_active ?? ''} onChange={(event) => setQuery({ is_active: event.target.value })} className="input w-auto" aria-label="Filter by account status">
                            <option value="">All accounts</option>
                            <option value="1">Active</option>
                            <option value="0">Deactivated</option>
                        </select>
                    </>
                }
            >
                {(rows) =>
                    rows.map((customer) => (
                        <tr key={customer.id}>
                            <td>
                                <Link to={`/admin/customers/${customer.id}`} className="font-medium text-stone-900 underline-offset-4 hover:underline">
                                    {customer.name}
                                </Link>
                                <p className="text-xs text-stone-500">{customer.email}</p>
                            </td>
                            <td className="whitespace-nowrap">{customer.phone ?? '—'}</td>
                            <td className="whitespace-nowrap">{formatDate(customer.joined_at)}</td>
                            <td className="text-right tabular-nums">{customer.orders_count}</td>
                            <td className="text-right tabular-nums">{formatPeso(customer.total_spent)}</td>
                            <td>
                                <OrderStatusBadge status={customer.is_active ? 'active' : 'inactive'} />
                            </td>
                        </tr>
                    ))
                }
            </DataTable>
        </>
    );
}
