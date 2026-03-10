import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

/**
 * GuestRoute - Guards routes that should only be visible to unauthenticated users.
 * Redirects logged-in users directly to /dashboard.
 */
export default function GuestRoute() {
    const { isAuthenticated, isLoading } = useAuthStore();

    // During the bootstrap check, don't redirect yet — wait for auth to resolve.
    if (isLoading) {
        return null;
    }

    if (isAuthenticated) {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}
