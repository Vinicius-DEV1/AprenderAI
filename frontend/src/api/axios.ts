import axios from 'axios';
import { toast } from 'sonner';
import { useAuthStore } from '../stores/authStore';

// BUG FIX: Flag de bootstrap para evitar que o interceptor 401
// dispare window.location.href durante a verificação inicial de sessão ou config.
// Começa como true para cobrir o primeiro render de App.tsx (useConfig).
let _isBootstrapping = true;
export const setBootstrapping = (value: boolean) => { _isBootstrapping = value; };

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000'),
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Global Error Handling
        if (error.response) {
            const { status, data } = error.response;

            // Handle 401 - Unauthorized/Session Expired
            if (status === 401) {
                // Durante o bootstrap (verificação inicial), não redireciona.
                if (_isBootstrapping) {
                    return Promise.reject(error);
                }

                // Normaliza path para evitar loops (ex: /login/ com barra no final)
                const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
                const authPaths = ['/login', '/register', '/forgot-password', '/reset-password'];

                if (!authPaths.includes(currentPath)) {
                    toast.error('Sessão expirada. Faça login novamente.');
                    const { logout } = useAuthStore.getState();
                    logout();
                    window.location.href = '/login';
                }
            }
            // Handle 422 - Validation Errors
            else if (status === 422) {
                const message = data.message || 'Dados inválidos.';
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                toast.error(`${message} ${errors}`);
            }
            // Handle 403 - Forbidden
            else if (status === 403) {
                toast.error(data.message || 'Acesso negado.');
            }
            // Handle 500+ - Server Errors
            else if (status >= 500) {
                toast.error(data.message || 'Erro interno no servidor. Nossa equipe já foi notificada.');
            }
        } else if (!error.response && error.message === 'Network Error') {
            toast.error('Falha na conexão de rede. Verifique sua internet.');
        }

        return Promise.reject(error);
    }
);

export default api;
