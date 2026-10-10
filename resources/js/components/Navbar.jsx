import { Crown, LayoutDashboard, LogOut, Menu, Package, Search, ShoppingBag, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useToast } from '../context/ToastContext';
import { useCategories, useStoreSettings } from '../hooks/useStoreData';

function SearchForm({ onDone, className = '' }) {
    const navigate = useNavigate();
    const [term, setTerm] = useState('');

    const submit = (event) => {
        event.preventDefault();
        navigate(term.trim() ? `/shop?search=${encodeURIComponent(term.trim())}` : '/shop');
        setTerm('');
        onDone?.();
    };

    return (
        <form role="search" onSubmit={submit} className={`relative ${className}`}>
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-brown/50" aria-hidden="true" />
            <input
                type="search"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder="Search products"
                aria-label="Search products"
                className="input rounded-full pl-10"
            />
        </form>
    );
}

function AccountMenu() {
    const { user, isAdmin, logout } = useAuth();
    const toast = useToast();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const container = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const close = (event) => !container.current?.contains(event.target) && setOpen(false);
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    if (!user) {
        return (
            <Link to="/login" className="flex h-10 items-center gap-2 rounded-full px-3 text-sm font-medium text-brown transition hover:bg-blush/60 hover:text-ink">
                <User className="h-5 w-5" strokeWidth={1.75} aria-hidden="true" />
                <span className="hidden sm:inline">Sign in</span>
            </Link>
        );
    }

    const item = 'flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-brown hover:bg-blush/50 hover:text-ink';
    const signOut = async () => {
        setOpen(false);
        await logout();
        toast.success('You have been signed out.');
        navigate('/');
    };

    return (
        <div ref={container} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="flex h-10 items-center gap-2 rounded-full px-3 text-sm font-medium text-brown transition hover:bg-blush/60 hover:text-ink"
            >
                <User className="h-5 w-5" strokeWidth={1.75} aria-hidden="true" />
                <span className="hidden max-w-28 truncate sm:inline">{user.name.split(' ')[0]}</span>
            </button>
            {open && (
                <div role="menu" className="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-brown/10 bg-white py-1 shadow-lg">
                    <p className="truncate border-b border-brown/10 px-4 py-2.5 text-xs text-brown/60">{user.email}</p>
                    {isAdmin && (
                        <Link to="/admin" role="menuitem" className={item} onClick={() => setOpen(false)}>
                            <LayoutDashboard className="h-4 w-4" aria-hidden="true" /> Admin dashboard
                        </Link>
                    )}
                    <Link to="/account" role="menuitem" className={item} onClick={() => setOpen(false)}>
                        <User className="h-4 w-4" aria-hidden="true" /> My account
                    </Link>
                    <Link to="/account/orders" role="menuitem" className={item} onClick={() => setOpen(false)}>
                        <Package className="h-4 w-4" aria-hidden="true" /> My orders
                    </Link>
                    <button type="button" role="menuitem" className={item} onClick={signOut}>
                        <LogOut className="h-4 w-4" aria-hidden="true" /> Sign out
                    </button>
                </div>
            )}
        </div>
    );
}

export default function Navbar() {
    const categories = useCategories();
    const settings = useStoreSettings();
    const { count } = useCart();
    const location = useLocation();
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        setMenuOpen(false);
    }, [location.pathname, location.search]);

    const link = ({ isActive }) =>
        `whitespace-nowrap border-b-2 py-1 text-sm transition ${isActive ? 'border-rose font-medium text-ink' : 'border-transparent text-brown hover:border-rose/50 hover:text-ink'}`;

    return (
        <header className="sticky top-0 z-30">
            {settings?.announcement_text && (
                <p className="bg-brown px-4 py-2 text-center text-xs font-medium tracking-wide text-cream">{settings.announcement_text}</p>
            )}
            <div className="border-b border-brown/10 bg-cream/95 backdrop-blur">
                <div className="container-page flex h-16 items-center gap-3">
                    <button
                        type="button"
                        className="-ml-2 flex h-10 w-10 items-center justify-center rounded-full text-brown hover:bg-blush/60 lg:hidden"
                        onClick={() => setMenuOpen((value) => !value)}
                        aria-expanded={menuOpen}
                        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                    >
                        {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                    </button>

                    <Link to="/" className="flex items-center gap-2 text-brown">
                        <Crown className="h-6 w-6 text-rose" strokeWidth={1.5} aria-hidden="true" />
                        <span className="font-display text-lg font-semibold leading-none sm:text-xl">Princess Collections</span>
                    </Link>

                    <nav aria-label="Main" className="ml-6 hidden items-center gap-6 lg:flex">
                        <NavLink to="/shop" className={link}>
                            Shop all
                        </NavLink>
                        {categories.map((category) => (
                            <NavLink key={category.id} to={`/category/${category.slug}`} className={link}>
                                {category.name}
                            </NavLink>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-1">
                        <SearchForm className="mr-2 hidden w-52 xl:block" />
                        <AccountMenu />
                        <Link
                            to="/cart"
                            className="relative flex h-10 w-10 items-center justify-center rounded-full text-brown transition hover:bg-blush/60 hover:text-ink"
                            aria-label={`Cart, ${count} ${count === 1 ? 'item' : 'items'}`}
                        >
                            <ShoppingBag className="h-5 w-5" strokeWidth={1.75} aria-hidden="true" />
                            {count > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brown px-1 text-[11px] font-semibold text-white">
                                    {count > 99 ? '99+' : count}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>

                {menuOpen && (
                    <div className="border-t border-brown/10 bg-cream lg:hidden">
                        <div className="container-page space-y-4 py-4">
                            <SearchForm onDone={() => setMenuOpen(false)} />
                            <nav aria-label="Mobile" className="flex flex-col">
                                <Link to="/shop" className="rounded-lg px-2 py-2.5 text-sm font-medium text-ink hover:bg-blush/50">
                                    Shop all
                                </Link>
                                {categories.map((category) => (
                                    <Link key={category.id} to={`/category/${category.slug}`} className="rounded-lg px-2 py-2.5 text-sm text-brown hover:bg-blush/50">
                                        {category.name}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}
