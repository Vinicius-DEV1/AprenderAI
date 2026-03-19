import axios from 'axios';
import { toast } from 'sonner';
import { useAuthStore } from '../stores/authStore';

// BUG FIX: Flag de bootstrap para evitar que o interceptor 401
// dispare window.location.href durante a verificação inicial de sessão ou config.
// Começa como true para cobrir o primeiro render de App.tsx (useConfig).
let _isBootstrapping = true;
export const setBootstrapping = (value: boolean) => { _isBootstrapping = value; };

// -----------------------------------------------------------------------
// DEPLOY MODE — Bloqueia toasts e logout forçado durante deploy/restart.
// Ativado quando o interceptor detecta erros 5xx ou Network Error EM SÉRIE
// (não em um único erro isolado).
// Desativado pelo useDeployDetection quando o servidor volta.
// -----------------------------------------------------------------------
let _isDeployMode = false;
let _onDeployDetected: (() => void) | null = null;

/** Registra o callback que aciona o DeployOverlay. Chamado em App.tsx. */
export const setDeployModeCallback = (cb: () => void) => {
    _onDeployDetected = cb;
};

/** Reseta o modo deploy quando o servidor recupera (chamado pelo hook). */
export const resetDeployMode = () => {
    _isDeployMode = false;
    _consecutiveServerErrors = 0;
    (window as any).__IS_DEPLOY_MODE__ = false;
};

// -----------------------------------------------------------------------
// THRESHOLD — Só ativa o deploy mode após N erros consecutivos de servidor.
// FIX: Antes, um ÚNICO 500 ou Network Error já ativava a tela. Isso causava
//      o overlay aparecer por erros pontuais, endpoints lentos ou quando o
//      backend estava simplesmente offline em dev local.
// -----------------------------------------------------------------------
const DEPLOY_ERROR_THRESHOLD = 2;
let _consecutiveServerErrors = 0;

// Em ambiente local (dev), não ativamos deploy mode para Network Errors
// porque o backend pode simplesmente não estar rodando — isso é esperado.
// Em produção (IS_PROD=true), o backend DEVE estar sempre online.
const IS_PROD = import.meta.env.PROD;

/** Aciona o modo deploy de forma idempotente (apenas quando threshold atingido). */
function activateDeployMode() {
    if (_isDeployMode) return;
    _isDeployMode = true;
    (window as any).__IS_DEPLOY_MODE__ = true;
    _onDeployDetected?.();
}

/**
 * Incrementa o contador de erros consecutivos de servidor.
 * Só dispara o overlay quando DEPLOY_ERROR_THRESHOLD for atingido.
 * FIX: Em vez de ativar imediatamente no primeiro erro, aguardamos N erros.
 */
function recordServerError() {
    _consecutiveServerErrors++;
    if (_consecutiveServerErrors >= DEPLOY_ERROR_THRESHOLD) {
        activateDeployMode();
    }
}

/**
 * Reseta o contador de erros quando uma requisição bem-sucedida chega.
 * FIX: Garante que erros isolados não se "acumulem" mesmo após recovery.
 */
function resetErrorCount() {
    if (_consecutiveServerErrors > 0) {
        _consecutiveServerErrors = 0;
    }
}

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || (IS_PROD ? '' : '/'),
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// -------------------------------------------------------
// Interceptor 0: Reseta contador de erros em sucesso
// FIX: Este interceptor faltava — sem ele, o contador nunca zerava.
// -------------------------------------------------------
api.interceptors.response.use(
    (response) => {
        resetErrorCount();
        return response;
    },
    (error) => Promise.reject(error) // passa para o próximo interceptor
);

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
        if (error.response) {
            const { status, data } = error.response;

            // Handle 401 (Unauthorized) or 419 (CSRF/Session Expired)
            if (status === 401 || status === 419) {
                // Durante o bootstrap (verificação inicial), não redireciona.
                if (_isBootstrapping) {
                    return Promise.reject(error);
                }

                // Se já estivermos em modo deploy, apenas congela.
                if (_isDeployMode) {
                    return new Promise(() => {});
                }

                // ESTRATÉGIA ANTI-LOGOUT FALSO:
                // Antes de deslogar o usuário em um 401/419, verificamos se o servidor está saudável.
                return (async () => {
                    try {
                        const healthRes = await fetch('/api/health', { method: 'GET', cache: 'no-store' });
                        if (!healthRes.ok) {
                            // Health retornou não-OK (degraded/503): é um deploy em andamento.
                            // FIX: Aplicamos threshold mesmo aqui (não ativa no primeiro erro).
                            if (IS_PROD) {
                                recordServerError();
                                if (_isDeployMode) return new Promise(() => {});
                            }
                        }
                    } catch {
                        // Health call falhou completamente (servidor offline).
                        // FIX: Só ativa deploy mode em produção — em dev isso é esperado.
                        if (IS_PROD) {
                            recordServerError();
                            if (_isDeployMode) return new Promise(() => {});
                        }
                    }

                    // Servidor saudável mas retornou 401/419 real → logout correto.
                    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
                    const publicPaths = ['/', '/login', '/register', '/forgot-password', '/reset-password', '/privacidade', '/uso-justo', '/500', '/verify-email'];

                    if (!publicPaths.includes(currentPath)) {
                        const message = status === 419
                            ? 'Página expirada por inatividade. Recarregando...'
                            : 'Sessão expirada. Faça login novamente.';
                        toast.error(message);

                        const { logout } = useAuthStore.getState();
                        logout();
                        window.location.href = '/login';
                    }

                    return Promise.reject(error);
                })();
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
                // FIX DEFINITIVO: deploy mode só faz sentido em produção.
                // Em dev local o backend pode estar com DB/Redis offline — isso
                // é esperado e NÃO deve travar toda a UI com a tela de deploy.
                //
                // CAUSA RAIZ DO BUG: este bloco não tinha IS_PROD guard.
                // O useConfig tem retry:0 mas mesmo 1 chamada 500 + 1 retry
                // já atingia o threshold=2, ativando o overlay imediatamente.
                if (IS_PROD) {
                    recordServerError();

                    if (_isDeployMode) {
                        // Congela a promise para impedir toasts duplicados.
                        return new Promise(() => {});
                    }
                } else {
                    // Em dev: mostra toast de erro simples, sem travar a UI.
                    toast.error(data?.message || 'Erro no servidor. Verifique se o backend está rodando.');
                }
            }
        } else if (!error.response) {
            // Network Error / servidor completamente offline
            console.error('API Connection Error:', {
                message: error.message,
                config: error.config?.url,
                method: error.config?.method,
            });

            if (error.message === 'Network Error') {
                // FIX PRINCIPAL: Antes ativava o overlay para qualquer Network Error,
                // inclusive em dev local onde o backend simplesmente não está rodando.
                //
                // Novo comportamento:
                //  - Em DEV (IS_PROD=false): NÃO ativa deploy mode. Apenas registra o erro.
                //  - Em PROD: ativa após DEPLOY_ERROR_THRESHOLD erros consecutivos,
                //    e apenas fora de páginas públicas.
                if (IS_PROD) {
                    const isPublicPage = ['/', '/login', '/register', '/forgot-password', '/reset-password'].includes(
                        window.location.pathname.replace(/\/$/, '') || '/'
                    );
                    if (!isPublicPage) {
                        recordServerError();
                        if (_isDeployMode) {
                            return new Promise(() => {});
                        }
                    }
                }
            }
        }

        return Promise.reject(error);
    }
);

export default api;
