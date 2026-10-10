import { Link } from 'react-router-dom';

export default function NotFound() {
    return (
        <div className="flex min-h-[60vh] flex-col items-center justify-center px-6 py-16 text-center">
            <p className="font-display text-6xl font-semibold text-rose">404</p>
            <h1 className="mt-4 font-display text-2xl text-brown">This page can’t be found</h1>
            <p className="mt-2 text-brown/80">The link may be broken or the page may have moved.</p>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
                <Link to="/" className="btn btn-primary">
                    Back to home
                </Link>
                <Link to="/shop" className="btn btn-secondary">
                    Browse the shop
                </Link>
            </div>
        </div>
    );
}
