import axios from 'axios';
import { toast } from 'sonner';
import { useAuthStore } from '../stores/authStore';

// BUG FIX: Flag de bootstrap para evitar que o interceptor 401
// dispare window.location.href durante a verificação inicial de sessão.
// Sem esta flag, o 401 no boot causava redirect ANTES de setUser(null),
// deixando isLoading=true para sempre (tela branca).
let _isBootstrapping = false;
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
                // Durante o bootstrap (verificação inicial de sessão),
                // apenas deixa o caller tratar o erro — NÃO redireciona via window.location,
                // pois o App.tsx já vai chamar setUser(null) e o PrivateRoute
                // vai redirecionar via React Router corretamente.
                if (_isBootstrapping) {
                    return Promise.reject(error);
                }
                // Pós-bootstrap: usuário estava logado mas sessão expirou
                if (window.location.pathname !== '/login') {
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
