import { useState, useEffect, useRef, useCallback } from 'react';

export type DeployState = 'idle' | 'deploying' | 'recovering';

interface UseDeployDetectionOptions {
    /** Chamado quando o servidor voltar ao normal. Ideal p/ refetch silencioso de dados. */
    onRecovered?: () => void;
}

/**
 * useDeployDetection
 *
 * Detecta quando o backend ficou indisponível (deploy, restart) e:
 *  - Entra em modo "deploying" sem redirecionar nem deslogar o usuário
 *  - Faz poll de /api/health a cada 3s até o servidor voltar
 *  - Emite "recovering" por 1.5s (animação de callback) e depois "idle"
 *
 * Uso em App.tsx:
 *   const { deployState, triggerDeploy, resetDeploy } = useDeployDetection({ onRecovered })
 */
export function useDeployDetection({ onRecovered }: UseDeployDetectionOptions = {}) {
    const [deployState, setDeployState] = useState<DeployState>('idle');
    const pollingRef   = useRef<ReturnType<typeof setInterval> | null>(null);
    const isActiveRef  = useRef(false);

    // Para o poll e libera o intervalo
    const stopPolling = useCallback(() => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    }, []);

    // Inicia o poll de /api/health
    const startPolling = useCallback(() => {
        if (pollingRef.current) return; // já está passando

        pollingRef.current = setInterval(async () => {
            try {
                const res = await fetch('/api/health', {
                    cache:       'no-store',
                    credentials: 'include',
                });
                if (res.ok) {
                    // Servidor voltou — transição suave
                    stopPolling();
                    setDeployState('recovering');
                    setTimeout(() => {
                        isActiveRef.current = false;
                        setDeployState('idle');
                        onRecovered?.();
                    }, 1500);
                }
            } catch {
                // Servidor ainda offline — próximo tick
            }
        }, 3000);
    }, [onRecovered, stopPolling]);

    /**
     * Ativa o modo de deploy.
     * Chamado pelo axios interceptor quando detecta 5xx / network error.
     * Idempotente: múltiplas chamadas não multiplicam o overlay.
     */
    const triggerDeploy = useCallback(() => {
        if (isActiveRef.current) return;
        isActiveRef.current = true;
        setDeployState('deploying');
        startPolling();
    }, [startPolling]);

    /** Reseta manualmente (usado em test/debug) */
    const resetDeploy = useCallback(() => {
        stopPolling();
        isActiveRef.current = false;
        setDeployState('idle');
    }, [stopPolling]);

    // Limpeza ao desmontar
    useEffect(() => {
        return () => stopPolling();
    }, [stopPolling]);

    return {
        deployState,
        triggerDeploy,
        resetDeploy,
        isDeploying: deployState !== 'idle',
    };
}
