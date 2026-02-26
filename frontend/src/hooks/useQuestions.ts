import { useQuery } from '@tanstack/react-query';
import { getQuestions, getQuestion } from '../api/questions';

export function useQuestions(params?: Record<string, any>) {
    return useQuery({
        queryKey: ['questions', params],
        queryFn: () => getQuestions(params),
    });
}

export function useQuestion(id: string | number) {
    return useQuery({
        queryKey: ['question', id],
        queryFn: () => getQuestion(id),
        enabled: !!id,
    });
}
