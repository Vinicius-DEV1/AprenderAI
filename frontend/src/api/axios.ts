import axios from 'axios';
import { toast } from 'sonner';

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

            if (status === 401 && !error.config?._quiet) {
                toast.error('Sessão expirada ou não autorizada. Faça login novamente.');
                // Optionally trigger a logout event here if needed
            } else if (status === 403) {
                toast.error(data.message || 'Acesso negado.');
            } else if (status >= 500) {
                toast.error('Erro interno no servidor. Nossa equipe já foi notificada.');
            }
        } else if (!error.response && error.message === 'Network Error') {
            toast.error('Falha na conexão de rede. Verifique sua internet.');
        }

        return Promise.reject(error);
    }
);

export default api;
