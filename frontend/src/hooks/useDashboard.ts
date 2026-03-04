import { useQuery } from '@tanstack/react-query';
import { getDashboardData } from '../api/dashboard';

export function useDashboard(userId?: number) {
    return useQuery({
        queryKey: ['dashboard', userId],
        queryFn: getDashboardData,
        enabled: !!userId,
    });
}
