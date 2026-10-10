import { MapPin, Package, User } from 'lucide-react';
import { NavLink, Outlet } from 'react-router-dom';

const LINKS = [
    ['/account', 'Profile', User, true],
    ['/account/orders', 'Orders', Package, false],
    ['/account/addresses', 'Addresses', MapPin, false],
];

export default function AccountLayout() {
    return (
        <div className="container-page py-10">
            <h1 className="font-display text-3xl font-semibold text-brown">My account</h1>
            <div className="mt-8 grid gap-8 md:grid-cols-[13rem_1fr]">
                <nav aria-label="Account" className="flex gap-2 overflow-x-auto md:flex-col">
                    {LINKS.map(([to, label, Icon, end]) => (
                        <NavLink
                            key={to}
                            to={to}
                            end={end}
                            className={({ isActive }) =>
                                `flex items-center gap-2.5 whitespace-nowrap rounded-full px-4 py-2.5 text-sm transition ${isActive ? 'bg-brown font-medium text-white' : 'text-brown hover:bg-blush/60'}`
                            }
                        >
                            <Icon className="h-4 w-4" aria-hidden="true" />
                            {label}
                        </NavLink>
                    ))}
                </nav>
                <div className="min-w-0">
                    <Outlet />
                </div>
            </div>
        </div>
    );
}
