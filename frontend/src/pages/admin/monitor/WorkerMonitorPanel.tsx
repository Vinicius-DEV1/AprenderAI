import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../../api/axios';
import { 
    RefreshCw, 
    Trash2, 
    Play, 
    AlertCircle, 
    Server, 
    Cpu, 
    Database, 
    Clock, 
    CheckCircle2, 
    XCircle,
    ChevronLeft,
    ChevronRight,
    Zap
} from 'lucide-react';
import { toast } from 'sonner';

interface Slot {
    slot: number;
    expires_in_seconds: number;
}

interface ConcurrencyGroup {
    max_allowed: number;
    active: number;
    slots: Slot[];
}

interface WorkerStats {
    queues: Record<string, { pending: number; processing: number; failed: number }>;
    concurrency: {
        ai_triage: ConcurrencyGroup;
        embeddings: ConcurrencyGroup;
        import: ConcurrencyGroup;
    };
    failed_jobs: number;
    redis_memory: string;
}

interface FailedJob {
    id: number;
    queue: string;
    payload?: string;
    exception: string;
    failed_at: string;
    command_name: string;
}

const WorkerMonitorPanel = () => {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [selectedQueue, setSelectedQueue] = useState<string | null>(null);

    // 1. Fetch Overview Stats
    const { data: stats, isLoading: statsLoading } = useQuery<WorkerStats>({
        queryKey: ['worker-monitor-overview'],
        queryFn: async () => {
            const res = await api.get('/api/v1/monitor/overview');
            return res.data.data;
        },
        refetchInterval: 5000
    });

    // 2. Fetch Failed Jobs
    const { data: failedJobsData, isLoading: failedLoading } = useQuery({
        queryKey: ['failed-jobs', page, selectedQueue],
        queryFn: async () => {
            const res = await api.get(`/api/v1/monitor/failed-jobs?page=${page}${selectedQueue ? `&queue=${selectedQueue}` : ''}`);
            return res.data.data;
        }
    });

    // 3. Mutations
    const retryAllMutation = useMutation({
        mutationFn: async () => {
            await api.post('/api/v1/admin/monitor/failed-jobs/retry-all');
        },
        onSuccess: () => {
            toast.success('Todos os jobs foram colocados na fila para re-tentativa');
            queryClient.invalidateQueries({ queryKey: ['failed-jobs'] });
            queryClient.invalidateQueries({ queryKey: ['worker-monitor-overview'] });
        },
        onError: () => {
            toast.error('Falha ao re-tentar jobs');
        }
    });

    const clearAllMutation = useMutation({
        mutationFn: async () => {
            await api.delete('/api/v1/admin/monitor/failed-jobs');
        },
        onSuccess: () => {
            toast.success('Logs de falha limpos com sucesso');
            queryClient.invalidateQueries({ queryKey: ['failed-jobs'] });
            queryClient.invalidateQueries({ queryKey: ['worker-monitor-overview'] });
        },
        onError: () => {
            toast.error('Falha ao limpar logs');
        }
    });

    const clearPendingTriageMutation = useMutation({
        mutationFn: async () => {
            await api.delete('/api/v1/admin/monitor/pending-triage');
        },
        onSuccess: () => {
            toast.success('Fila de triagem pendente limpa com sucesso');
            queryClient.invalidateQueries({ queryKey: ['worker-monitor-overview'] });
        },
        onError: () => {
            toast.error('Falha ao limpar fila de triagem');
        }
    });

    const retryOneMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/v1/monitor/failed-jobs/${id}/retry`),
        onSuccess: () => {
            toast.success('Job reintegrado à fila.');
            queryClient.invalidateQueries({ queryKey: ['failed-jobs'] });
        }
    });

    if (statsLoading && !stats) {
        return (
            <div className="flex items-center justify-center p-12">
                <RefreshCw className="w-8 h-8 animate-spin text-indigo-600" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                            <Server className="w-5 h-5" />
                        </div>
                        <span className="text-sm font-medium text-gray-500">Redis Memory</span>
                    </div>
                    <p className="text-2xl font-black text-gray-800">{stats?.redis_memory || '0 MB'}</p>
                </div>

                <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 bg-red-50 text-red-600 rounded-lg">
                            <AlertCircle className="w-5 h-5" />
                        </div>
                        <span className="text-sm font-medium text-gray-500">Failed Jobs</span>
                    </div>
                    <p className="text-2xl font-black text-red-600">{stats?.failed_jobs || 0}</p>
                </div>

                <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 bg-green-50 text-green-600 rounded-lg">
                            <Cpu className="w-5 h-5" />
                        </div>
                        <span className="text-sm font-medium text-gray-500">Active Workers</span>
                    </div>
                    <p className="text-2xl font-black text-gray-800">Cluster Default</p>
                </div>

                <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <Database className="w-5 h-5" />
                        </div>
                        <span className="text-sm font-medium text-gray-500">Total Queues</span>
                    </div>
                    <p className="text-2xl font-black text-gray-800">{Object.keys(stats?.queues || {}).length}</p>
                </div>
            </div>

            {/* Concurrency Slots Section */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {stats && Object.entries(stats.concurrency).map(([key, data]) => (
                    <div key={key} className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                            <h3 className="font-bold text-gray-700 uppercase text-xs tracking-wider">
                                {key.replace('_', ' ')} SLOTS
                            </h3>
                            <span className="text-[10px] font-black px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full">
                                {data.active} / {data.max_allowed}
                            </span>
                        </div>
                        <div className="p-5">
                            <div className="flex flex-wrap gap-2">
                                {Array.from({ length: data.max_allowed }).map((_, i) => {
                                    const activeSlot = data.slots.find(s => s.slot === i);
                                    return (
                                        <div 
                                            key={i}
                                            className={`w-10 h-10 rounded-xl flex items-center justify-center transition-all ${
                                                activeSlot 
                                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' 
                                                    : 'bg-gray-100 text-gray-400 border border-dashed border-gray-300'
                                            }`}
                                            title={activeSlot ? `Expira em ${activeSlot.expires_in_seconds}s` : 'Livre'}
                                        >
                                            {activeSlot ? <Zap className="w-4 h-4" /> : <Clock className="w-4 h-4 opacity-50" />}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Failed Jobs Table */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-lg overflow-hidden">
                <div className="p-6 border-b border-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-2">
                            <XCircle className="w-6 h-6 text-red-500" />
                            Failed Job Management
                        </h2>
                        <p className="text-sm text-gray-500">Inspect and recover jobs that failed during execution.</p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => {
                                if (window.confirm('Deseja limpar todos os jobs de triagem PENDENTES (que ainda não rodaram)?')) {
                                    clearPendingTriageMutation.mutate();
                                }
                            }}
                            disabled={clearPendingTriageMutation.isPending || (stats?.queues?.ai_triage?.pending || 0) === 0}
                            className="bg-orange-50 hover:bg-orange-100 text-orange-600 font-bold px-4 py-2 rounded-xl text-sm flex items-center gap-2 transition-all border border-orange-200 disabled:opacity-50"
                        >
                            <Trash2 className="w-4 h-4" />
                            Limpar Fila Triagem
                        </button>
                        <button 
                            onClick={() => retryAllMutation.mutate()}
                            disabled={retryAllMutation.isPending || !stats?.failed_jobs}
                            className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold px-4 py-2 rounded-xl text-sm flex items-center gap-2 transition-all shadow-md shadow-indigo-100"
                        >
                            <Play className="w-4 h-4" />
                            Retry All
                        </button>
                        <button 
                            onClick={() => {
                                if (window.confirm('CUIDADO: Isso irá apagar PERMANENTEMENTE todos os registros de falha. Continuar?')) {
                                    clearAllMutation.mutate();
                                }
                            }}
                            disabled={clearAllMutation.isPending || !stats?.failed_jobs}
                            className="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-4 py-2 rounded-xl text-sm flex items-center gap-2 transition-all border border-red-200"
                        >
                            <Trash2 className="w-4 h-4" />
                            Clear Failed Logs
                        </button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left">
                        <thead className="bg-gray-50 text-gray-500 text-[10px] uppercase font-black tracking-widest">
                            <tr>
                                <th className="px-6 py-4">Job Class</th>
                                <th className="px-6 py-4">Queue</th>
                                <th className="px-6 py-4">Reason</th>
                                <th className="px-6 py-4">Failed At</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {failedJobsData?.data.map((job: FailedJob) => (
                                <tr key={job.id} className="hover:bg-gray-50/50 transition-colors group">
                                    <td className="px-6 py-4">
                                        <div className="font-bold text-gray-800 text-sm">{job.command_name}</div>
                                        <div className="text-[10px] font-mono text-gray-400">ID: #{job.id}</div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-black uppercase">
                                            {job.queue}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 max-w-xs xl:max-w-md">
                                        <div className="text-xs text-red-600 font-medium truncate" title={job.exception}>
                                            {job.exception}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-gray-500">
                                        {new Date(job.failed_at).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button 
                                            onClick={() => retryOneMutation.mutate(job.id)}
                                            disabled={retryOneMutation.isPending}
                                            className="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                            title="Retry this job"
                                        >
                                            <RefreshCw className={`w-4 h-4 ${retryOneMutation.isPending ? 'animate-spin' : ''}`} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {failedJobsData?.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-12 text-center">
                                        <div className="flex flex-col items-center">
                                            <CheckCircle2 className="w-12 h-12 text-green-500 mb-4 opacity-20" />
                                            <p className="text-gray-500 font-medium">No failed jobs found. Everything is running smoothly!</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {failedJobsData && failedJobsData.last_page > 1 && (
                    <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500 font-medium">
                            Showing page {failedJobsData.current_page} of {failedJobsData.last_page}
                        </span>
                        <div className="flex gap-2">
                            <button 
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                disabled={page === 1}
                                className="p-2 bg-white border border-gray-200 rounded-lg disabled:opacity-30 hover:bg-gray-50 transition-colors"
                            >
                                <ChevronLeft className="w-4 h-4" />
                            </button>
                            <button 
                                onClick={() => setPage(p => Math.min(failedJobsData.last_page, p + 1))}
                                disabled={page === failedJobsData.last_page}
                                className="p-2 bg-white border border-gray-200 rounded-lg disabled:opacity-30 hover:bg-gray-50 transition-colors"
                            >
                                <ChevronRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default WorkerMonitorPanel;
