import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../../api/axios';
import { X, AlertCircle, CheckCircle2, RefreshCcw, Coins } from 'lucide-react';
import { CompletedBatch } from './Types';

interface BatchDetailsModalProps {
    batch: CompletedBatch | null;
    onClose: () => void;
}

interface BatchDetails {
    batch: {
        batch_id: string;
        type: string;
        model: string;
        status: string;
        total_count: number;
        processed_count: number;
        error_count: number;
        input_tokens: number;
        output_tokens: number;
        created_at: string;
        updated_at: string;
    };
    items_summary: {
        total: number;
        success: number;
        failed: number;
        reverted: number;
        pending: number;
    };
    recent_errors: Array<{
        question_id: number;
        error_message: string;
        updated_at: string;
    }>;
}

const BatchDetailsModal: React.FC<BatchDetailsModalProps> = ({ batch, onClose }) => {
    if (!batch) return null;

    const { data: details, isLoading, isError } = useQuery<BatchDetails>({
        queryKey: ['batch-details', batch.id],
        queryFn: async () => {
             // We use the existing endpoint in AdminAIBatchAnalyticsController
             // Note: This endpoint expects a UUID, so normal Laravel batches (numeric ID) might return 404 here
             // We will handle it gracefully.
             const res = await api.get(`/api/v1/admin/triage/${batch.id}/details`);
             return res.data;
        },
        enabled: !!batch && typeof batch.id === 'string' && batch.name.startsWith('IA:'),
        retry: 1
    });

    const isAiBatch = typeof batch.id === 'string' && batch.name.startsWith('IA:');

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                            Detalhes do Lote
                            {details?.batch?.status === 'completed' || details?.batch?.status === 'success' ? (
                                <span className="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-black uppercase">Sucesso</span>
                            ) : details?.batch?.status === 'cancelled' ? (
                                <span className="bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full text-[10px] font-black uppercase">Cancelado</span>
                            ) : null}
                        </h2>
                        <p className="text-xs font-mono text-gray-500 mt-1">{batch.name} • {batch.id}</p>
                    </div>
                    <button onClick={onClose} className="p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 rounded-lg transition-colors">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-6 overflow-y-auto">
                    {!isAiBatch ? (
                        <div className="text-center py-8">
                            <p className="text-gray-500 mb-2">Este é um lote padrão do Laravel.</p>
                            <p className="text-sm text-gray-400">Detalhes avançados (tokens, custo) estão disponíveis apenas para lotes de Inteligência Artificial.</p>
                            <div className="mt-6 flex justify-center gap-4">
                                <div className="bg-gray-50 p-4 rounded-xl text-center min-w-[120px]">
                                    <div className="text-2xl font-black text-gray-800">{batch.total_jobs}</div>
                                    <div className="text-xs font-medium text-gray-500 uppercase tracking-widest mt-1">Total Jobs</div>
                                </div>
                                <div className="bg-red-50 p-4 rounded-xl text-center min-w-[120px]">
                                    <div className="text-2xl font-black text-red-600">{batch.failed_jobs}</div>
                                    <div className="text-xs font-medium text-red-500 uppercase tracking-widest mt-1">Falhas</div>
                                </div>
                            </div>
                        </div>
                    ) : isLoading ? (
                        <div className="flex justify-center py-12">
                            <RefreshCcw className="w-8 h-8 animate-spin text-gray-300" />
                        </div>
                    ) : isError || !details ? (
                        <div className="bg-red-50 p-6 rounded-xl border border-red-100 flex flex-col items-center">
                            <AlertCircle className="w-8 h-8 text-red-500 mb-3" />
                            <p className="font-semibold text-red-800">Erro ao carregar os detalhes.</p>
                            <p className="text-sm text-red-600 mt-1">O lote pode ter sido removido ou ser muito antigo.</p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Token Usage & Cost Estimator */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                    <div className="flex items-center gap-2 text-blue-600 mb-1">
                                        <CheckCircle2 className="w-4 h-4" />
                                        <span className="text-xs font-bold uppercase tracking-wider">Sucesso</span>
                                    </div>
                                    <div className="text-2xl font-black text-blue-900">{details?.items_summary?.success ?? 0}</div>
                                </div>
                                <div className="bg-red-50 p-4 rounded-xl border border-red-100">
                                    <div className="flex items-center gap-2 text-red-600 mb-1">
                                        <AlertCircle className="w-4 h-4" />
                                        <span className="text-xs font-bold uppercase tracking-wider">Erros</span>
                                    </div>
                                    <div className="text-2xl font-black text-red-900">{details?.items_summary?.failed ?? 0}</div>
                                </div>
                                <div className="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                                    <div className="flex items-center gap-2 text-indigo-600 mb-1">
                                        <Coins className="w-4 h-4" />
                                        <span className="text-xs font-bold uppercase tracking-wider">Tokens IN</span>
                                    </div>
                                    <div className="text-2xl font-black text-indigo-900">{details?.batch?.input_tokens?.toLocaleString() ?? 0}</div>
                                </div>
                                <div className="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                                    <div className="flex items-center gap-2 text-indigo-600 mb-1">
                                        <Coins className="w-4 h-4" />
                                        <span className="text-xs font-bold uppercase tracking-wider">Tokens OUT</span>
                                    </div>
                                    <div className="text-2xl font-black text-indigo-900">{details?.batch?.output_tokens?.toLocaleString() ?? 0}</div>
                                </div>
                            </div>

                            {/* Specific Errors List */}
                            {details.recent_errors && details.recent_errors.length > 0 && (
                                <div>
                                    <h3 className="text-sm font-bold text-gray-800 mb-3 border-b border-gray-100 pb-2">Questões com Falha</h3>
                                    <div className="bg-gray-50 rounded-xl border border-gray-100 overflow-hidden">
                                        <ul className="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                                            {details.recent_errors.map((err, idx) => (
                                                <li key={idx} className="p-3 hover:bg-white transition-colors">
                                                    <div className="flex justify-between items-start mb-1">
                                                        <span className="text-xs font-bold bg-gray-200 text-gray-700 px-2 py-0.5 rounded">
                                                            ID: {err.question_id}
                                                        </span>
                                                        <span className="text-[10px] text-gray-400">
                                                            {new Date(err.updated_at).toLocaleTimeString('pt-BR')}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs text-red-600 font-mono break-words bg-red-50 p-2 rounded truncate" title={err.error_message}>
                                                        {err.error_message}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
                
                <div className="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-300 transition-colors"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default BatchDetailsModal;
