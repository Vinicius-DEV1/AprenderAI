import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

export default function PrivateRoute() {
    const { isAuthenticated, user, isLoading, logout } = useAuthStore();

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    // Se estiver em um estado inconsistente (logado mas sem dados de usuário), limpa e redireciona
    if (isAuthenticated && !user) {
        logout();
        return <Navigate to="/login" replace />;
    }

    // Se não estiver logado, envia para a página de login
    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    // Se estiver logado, renderiza as rotas filhas
    return <Outlet />;
}
