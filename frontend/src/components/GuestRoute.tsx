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
        const user = useAuthStore.getState().user;
        
        // Use the explicit flag from the backend to decide if the user 
        // should be sent to the onboarding flow (/welcome)
        if (user?.is_new_user) {
            return <Navigate to={`/welcome${window.location.search}`} replace />;
        }

        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}
