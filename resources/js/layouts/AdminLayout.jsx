import { BarChart3, Boxes, Crown, ExternalLink, FolderTree, LayoutDashboard, LogOut, Menu, Settings, ShoppingCart, Shirt, Users, X } from 'lucide-react';
import { Suspense, useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import LoadingSpinner from '../components/LoadingSpinner';
import { useAuth } from '../context/AuthContext';

const LINKS = [
    ['/admin', 'Dashboard', LayoutDashboard, true],
    ['/admin/orders', 'Orders', ShoppingCart],
    ['/admin/products', 'Products', Shirt],
    ['/admin/categories', 'Categories', FolderTree],
    ['/admin/inventory', 'Inventory', Boxes],
    ['/admin/customers', 'Customers', Users],
    ['/admin/reports', 'Sales reports', BarChart3],
    ['/admin/settings', 'Settings', Settings],
];

export default function AdminLayout() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const { pathname } = useLocation();
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setOpen(false);
        window.scrollTo(0, 0);
    }, [pathname]);

    const signOut = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <div className="admin min-h-screen bg-mist text-stone-800">
            {open && <div className="fixed inset-0 z-30 bg-stone-900/40 lg:hidden" onClick={() => setOpen(false)} aria-hidden="true" />}

            <aside className={`fixed inset-y-0 left-0 z-40 flex w-60 flex-col border-r border-stone-200 bg-white transition-transform lg:translate-x-0 ${open ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-14 items-center justify-between border-b border-stone-200 px-4">
                    <Link to="/admin" className="flex items-center gap-2 font-semibold text-stone-900">
                        <Crown className="h-5 w-5 text-rose-dark" aria-hidden="true" />
                        Princess Admin
                    </Link>
                    <button type="button" className="rounded p-1 text-stone-500 hover:bg-stone-100 lg:hidden" onClick={() => setOpen(false)} aria-label="Close menu">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <nav aria-label="Admin" className="flex-1 space-y-0.5 overflow-y-auto p-3">
                    {LINKS.map(([to, label, Icon, end]) => (
                        <NavLink
                            key={to}
                            to={to}
                            end={Boolean(end)}
                            className={({ isActive }) =>
                                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${isActive ? 'bg-stone-900 font-medium text-white' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'}`
                            }
                        >
                            <Icon className="h-4 w-4" aria-hidden="true" />
                            {label}
                        </NavLink>
                    ))}
                </nav>
                <div className="border-t border-stone-200 p-3">
                    <Link to="/" className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-stone-600 hover:bg-stone-100 hover:text-stone-900">
                        <ExternalLink className="h-4 w-4" aria-hidden="true" />
                        View store
                    </Link>
                    <button type="button" onClick={signOut} className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-stone-600 hover:bg-stone-100 hover:text-stone-900">
                        <LogOut className="h-4 w-4" aria-hidden="true" />
                        Sign out
                    </button>
                </div>
            </aside>

            <div className="lg:pl-60">
                <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-stone-200 bg-white px-4 sm:px-6">
                    <button type="button" className="rounded p-1.5 text-stone-600 hover:bg-stone-100 lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu">
                        <Menu className="h-5 w-5" />
                    </button>
                    <p className="ml-auto truncate text-sm text-stone-500">
                        Signed in as <span className="font-medium text-stone-800">{user.name}</span>
                    </p>
                </header>
                <main className="mx-auto max-w-7xl p-4 sm:p-6">
                    {/* Keeps the sidebar on screen while a page's code loads for the first time. */}
                    <Suspense fallback={<LoadingSpinner className="py-24" />}>
                        <Outlet />
                    </Suspense>
                </main>
            </div>
        </div>
    );
}
