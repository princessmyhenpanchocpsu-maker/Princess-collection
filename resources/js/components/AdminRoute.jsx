import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import LoadingSpinner from './LoadingSpinner';

// Keeps non-admins out of the admin screens. The real protection is on the server:
// every /api/v1/admin route checks the role again, so hiding the UI is only a convenience.
export default function AdminRoute() {
    const { user, isAdmin, loading } = useAuth();
    const location = useLocation();

    if (loading) return <LoadingSpinner className="min-h-screen" />;
    if (!user) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    if (!isAdmin) return <Navigate to="/" replace />;

    return <Outlet />;
}
