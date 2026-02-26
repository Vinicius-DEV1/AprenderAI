import { useQuery } from '@tanstack/react-query';
import api from '../api/axios';
import { useConfigStore } from '../stores/configStore';
import { useEffect } from 'react';

export function useConfig() {
    const setConfig = useConfigStore((state) => state.setConfig);

    const query = useQuery({
        queryKey: ['systemConfig'],
        queryFn: async () => {
            console.log('Fetching system config...');
            const response = await api.get('/api/v1/config');
            console.log('Config response:', response.data);
            return response.data.data;
        },
        staleTime: 1000 * 60 * 60, // 1 hour
    });

    useEffect(() => {
        if (query.error) {
            console.error('Config fetch error:', query.error);
        }
        if (query.data) {
            console.log('Setting config in store:', query.data);
            setConfig(query.data);
        }
    }, [query.data, query.error, setConfig]);

    return query;
}
