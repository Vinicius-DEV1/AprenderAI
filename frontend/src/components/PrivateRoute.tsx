import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

export default function PrivateRoute() {
    const { isAuthenticated, isLoading } = useAuthStore();

    if (isLoading) {
        return (
            <div className="container">
                <div className="card" style={{ textAlign: 'center' }}>
                    <p>Verificando sessão...</p>
                </div>
            </div>
        );
    }

    // Se não estiver logado, envia para a página de login
    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    // Se estiver logado, renderiza as rotas filhas
    return <Outlet />;
}
