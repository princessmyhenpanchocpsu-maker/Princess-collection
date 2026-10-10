import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import LoadingSpinner from './LoadingSpinner';

// Sends guests to the login page and brings them back afterwards.
// This only decides what to render; the API enforces access on every request.
export default function ProtectedRoute() {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) return <LoadingSpinner className="min-h-[50vh]" />;
    if (!user) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;

    return <Outlet />;
}
