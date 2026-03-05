import axios from 'axios';
import { toast } from 'sonner';
import { useAuthStore } from '../stores/authStore';

// BUG FIX: Flag de bootstrap para evitar que o interceptor 401
// dispare window.location.href durante a verificação inicial de sessão ou config.
// Começa como true para cobrir o primeiro render de App.tsx (useConfig).
let _isBootstrapping = true;
export const setBootstrapping = (value: boolean) => { _isBootstrapping = value; };

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : '/'),
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// -------------------------------------------------------
// Interceptor 1: Auto-retry on 419 (CSRF mismatch)
// Re-fetches the CSRF cookie and retries the request once.
// Must be registered BEFORE the main error interceptor.
// -------------------------------------------------------
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;
        if (error.response?.status === 419 && !originalRequest._csrfRetried) {
            originalRequest._csrfRetried = true;
            try {
                await api.get('/sanctum/csrf-cookie');
                return api(originalRequest);
            } catch {
                // If CSRF refresh also fails, fall through to the main interceptor
            }
        }
        return Promise.reject(error);
    }
);

// -------------------------------------------------------
// Interceptor 2: Global error handling (401, 422, 403, 500+)
// -------------------------------------------------------
api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Global Error Handling
        if (error.response) {
            const { status, data } = error.response;

            // Handle 401 (Unauthorized) or 419 (CSRF/Session Expired)
            if (status === 401 || status === 419) {
                // Durante o bootstrap (verificação inicial), não redireciona.
                if (_isBootstrapping) {
                    return Promise.reject(error);
                }

                // Normaliza path para evitar loops (ex: /login/ com barra no final)
                const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
                const publicPaths = ['/', '/login', '/register', '/forgot-password', '/reset-password', '/privacidade', '/uso-justo', '/500', '/verify-email'];

                if (!publicPaths.includes(currentPath)) {
                    const message = status === 419 ? 'Página expirada por inatividade. Recarregando...' : 'Sessão expirada. Faça login novamente.';
                    toast.error(message);

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
        } else if (!error.response) {
            // Log para ajudar a debugar problemas silenciosos de rede ou CORS
            console.error('API Connection Error:', {
                message: error.message,
                config: error.config?.url,
                method: error.config?.method
            });

            if (error.message === 'Network Error') {
                const isLoginPage = window.location.pathname === '/login' || window.location.pathname === '/login/';
                if (!isLoginPage) {
                    toast.error('Falha na conexão de rede. Verifique sua internet.');
                }
            }
        }

        return Promise.reject(error);
    }
);

export default api;
