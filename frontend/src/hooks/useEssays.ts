import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getEssays, getEssay, createEssay } from '../api/essays';

export function useEssays(page = 1) {
    return useQuery({
        queryKey: ['essays', { page }],
        queryFn: () => getEssays(page),
    });
}

export function useEssay(id: string | number) {
    return useQuery({
        queryKey: ['essay', id],
        queryFn: () => getEssay(id),
        enabled: !!id,
    });
}

export function useCreateEssay() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createEssay,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['essays'] });
        },
    });
}
