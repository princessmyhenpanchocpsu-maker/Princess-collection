import { useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import Footer from '../components/Footer';
import Navbar from '../components/Navbar';

export default function StoreLayout() {
    const { pathname } = useLocation();

    // A new page should start at the top, like a normal page load.
    useEffect(() => {
        window.scrollTo(0, 0);
    }, [pathname]);

    return (
        <div className="flex min-h-screen flex-col">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm">
                Skip to content
            </a>
            <Navbar />
            <main id="main" className="flex-1">
                <Outlet />
            </main>
            <Footer />
        </div>
    );
}
