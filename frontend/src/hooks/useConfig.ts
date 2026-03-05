import { useQuery } from '@tanstack/react-query';
import api from '../api/axios';
import { useConfigStore } from '../stores/configStore';
import { useEffect } from 'react';

export function useConfig() {
    const setConfig = useConfigStore((state) => state.setConfig);

    const query = useQuery({
        queryKey: ['systemConfig'],
        queryFn: async () => {
            try {
                const response = await api.get('/api/v1/config', { _quiet: true } as any);
                // Valida estrutura da resposta antes de usar
                const data = response?.data?.data;
                if (!data || typeof data !== 'object') {
                    console.warn('[useConfig] API returned unexpected structure:', response?.data);
                    return null; // retorna null, não lança erro — bootstrap não trava
                }
                return data;
            } catch (err: any) {
                // Loga o erro mas NÃO propaga — o bootstrap continua com defaults
                console.error('[useConfig] Failed to load config from API:', err?.message ?? err);
                return null;
            }
        },
        staleTime: 1000 * 60 * 60, // 1 hour
        retry: 1,
    });

    useEffect(() => {
        if (query.data) {
            setConfig(query.data);
        }
        // Não loga erro aqui — já foi logado no queryFn
    }, [query.data, setConfig]);

    return query;
}
