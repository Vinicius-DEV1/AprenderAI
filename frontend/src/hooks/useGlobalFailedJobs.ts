import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../api/axios';

interface FailedJobsCountResponse {
    failed_count: number;
}

export function useGlobalFailedJobs() {
    const previousCountRef = useRef<number | null>(null);

    const { data } = useQuery<FailedJobsCountResponse>({
        queryKey: ['global-failed-jobs-count'],
        queryFn: async () => {
             // Reusing the queue/batch info endpoint if possible, but creating a lightweight one is better.
             // Here we use the existing failed-jobs endpoint to just get the count.
             const res = await api.get('/api/v1/admin/monitor/queues');
             return { failed_count: res.data?.failed?.length || 0 };
        },
        refetchInterval: 30000, // Poll every 30 seconds
        refetchOnWindowFocus: false,
    });

    useEffect(() => {
        if (data && previousCountRef.current !== null) {
            const currentCount = data.failed_count;
            const previousCount = previousCountRef.current;
            
            // Trigger toast if failed jobs increased
            if (currentCount > previousCount) {
                const diff = currentCount - previousCount;
                toast.error(`Atenção: Houve um pico de falhas!`, {
                    description: `Foram detectados ${diff} novo(s) job(s) com erro. Total atual: ${currentCount}.`,
                    action: {
                        label: 'Ver Monitor',
                        onClick: () => {
                            window.location.href = '/admin/monitor';
                        }
                    },
                    duration: 10000, // Show for 10 seconds
                    id: 'failed-jobs-spike' // Prevent duplicate toasts stacking
                });
            }
        }
        
        // Always update the ref after checking
        if (data) {
            previousCountRef.current = data.failed_count;
        }
    }, [data]);
}
