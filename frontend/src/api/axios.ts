import axios from 'axios';
import { toast } from 'sonner';
import { useAuthStore } from '../stores/authStore';

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
            if (status === 401 && !error.config?._quiet) {
                // Check if we are already on login to avoid loops
                if (window.location.pathname !== '/login') {
                    toast.error('Sessão expirada. Faça login novamente.');

                    // Reset auth state and redirect
                    const { logout } = useAuthStore.getState();
                    logout();
                    window.location.href = '/login';
                }
            }
            // Handle 422 - Validation Errors
            else if (status === 422) {
                const message = data.message || 'Dados inválidos.';
                // Only show errors if they exist as an object
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
