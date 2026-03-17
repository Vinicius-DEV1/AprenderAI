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
        
        // If the user was created very recently (last 5 minutes), 
        // they are likely in the registration flow and should see /welcome.
        const isFreshRegistration = user?.created_at && 
            (new Date().getTime() - new Date(user.created_at).getTime()) < 300_000;

        if (isFreshRegistration) {
            return <Navigate to={`/welcome${window.location.search}`} replace />;
        }

        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}
