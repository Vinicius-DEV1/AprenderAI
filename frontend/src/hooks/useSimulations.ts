import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getSimulations, getSimulation, createSimulation, submitSimulationAnswers } from '../api/simulations';

export function useSimulations(page = 1) {
    return useQuery({
        queryKey: ['simulations', { page }],
        queryFn: () => getSimulations(page),
    });
}

export function useSimulation(id: string | number, includeAnswers = false) {
    return useQuery({
        queryKey: ['simulation', id, { includeAnswers }],
        queryFn: () => getSimulation(id, includeAnswers),
        enabled: !!id,
    });
}

export function useCreateSimulation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createSimulation,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['simulations'] });
        },
    });
}

export function useSubmitSimulation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, data }: { id: string | number; data: any }) => submitSimulationAnswers(id, data),
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ['simulations'] });
            queryClient.invalidateQueries({ queryKey: ['simulation', variables.id] });
        },
    });
}
