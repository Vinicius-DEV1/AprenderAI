import { useQuery } from '@tanstack/react-query';
import api from '../api/axios';
import { useConfigStore } from '../stores/configStore';
import { useEffect } from 'react';

export function useConfig() {
    const setConfig = useConfigStore((state) => state.setConfig);

    const query = useQuery({
        queryKey: ['systemConfig'],
        queryFn: async () => {
            const response = await api.get('/api/v1/config', { _quiet: true } as any);
            // Defesa extra: garante que `data.data` existe antes de retornar
            if (!response.data || !response.data.data) {
                throw new Error(`["systemConfig"] data is undefined`);
            }
            return response.data.data;
        },
        staleTime: 1000 * 60 * 60, // 1 hour
        retry: 2, // retry up to 2 times if config API fails temporarily
    });

    useEffect(() => {
        if (query.error) {
            console.error('Config fetch error:', query.error);
        }
        if (query.data) {
            setConfig(query.data);
        }
    }, [query.data, query.error, setConfig]);

    return query;
}
