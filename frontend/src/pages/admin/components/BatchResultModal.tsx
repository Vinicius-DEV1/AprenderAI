import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../../api/axios';
import AdminQuestionViewModal from './AdminQuestionViewModal';
import { renderMd } from '../../../utils/markdown';

interface BatchResultModalProps {
    isOpen: boolean;
    onClose: () => void;
    batchId: string | null;
}

export default function BatchResultModal({ isOpen, onClose, batchId }: BatchResultModalProps) {
    const [viewQuestionId, setViewQuestionId] = useState<number | null>(null);
    const [isQuestionModalOpen, setIsQuestionModalOpen] = useState(false);

    const { data: batchDetails, isLoading } = useQuery({
        queryKey: ['admin-batch-history-details', batchId],
        queryFn: async () => {
             const res = await api.get(`/api/v1/admin/questions-batch/details/${batchId}`);
             return res.data;
        },
        enabled: isOpen && !!batchId,
    });

    if (!isOpen) return null;

    const progress = batchDetails?.batch ? {
        status: batchDetails.batch.status,
        processed: batchDetails.batch.processed_items,
        total: batchDetails.batch.total_items,
        errors: batchDetails.batch.failed_items,
        input_tokens: batchDetails.batch.usage_stats?.input_tokens,
        output_tokens: batchDetails.batch.usage_stats?.output_tokens,
        estimated_cost: batchDetails.batch.usage_stats?.estimated_cost,
        stats: batchDetails.batch.result_summary,
        initial_chunks_processed: batchDetails.batch.chunk_stats?.initial_processed,
        retry_chunks_processed: batchDetails.batch.chunk_stats?.retry_processed,
        retries_count: batchDetails.batch.chunk_stats?.retries_count,
        retries_success: batchDetails.batch.chunk_stats?.retries_success,
        retries_failed: batchDetails.batch.chunk_stats?.retries_failed,
        retry_chunks_total: batchDetails.batch.chunk_stats?.retry_total ?? 0,
        errors_log: batchDetails.recent_errors
    } : null;

    return (
        <div className="fixed inset-0 z-[60] overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onClick={onClose} />

                {/* This element is to trick the browser into centering the modal contents. */}
                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full">
                    {/* Header */}
                    <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <span className="text-xl">
                                {isLoading ? '⏳' : progress?.status === 'completed' ? '✅' : progress?.status === 'failed' ? '❌' : progress?.status === 'cancelled' ? '🛑' : '🤖'}
                            </span>
                            <h3 className="text-xl font-black text-gray-900">
                                Relatório do Lote: {batchId}
                            </h3>
                        </div>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 font-bold p-2 bg-white rounded-full border border-gray-200 shadow-sm" title="Fechar">✕ Fechar</button>
                    </div>

                    {/* Body */}
                    <div className="p-6 overflow-y-auto max-h-[80vh]">
                        {isLoading ? (
                            <div className="flex flex-col items-center justify-center py-20">
                                <span className="text-4xl animate-spin mb-4">⚙️</span>
                                <p className="text-gray-500 font-bold text-sm tracking-widest uppercase">Carregando detalhes do lote...</p>
                            </div>
                        ) : batchDetails ? (
                            <div className="flex flex-col items-center py-6 space-y-6">
                                <div className="text-6xl">
                                    {progress?.status === 'failed' ? '❌' : progress?.status === 'cancelled' ? '🛑' : '✅'}
                                </div>
                                <div className="w-full max-w-md bg-gray-100 h-4 rounded-full overflow-hidden relative">
                                    <div
                                        className={`absolute top-0 left-0 h-full transition-all duration-500 ease-out shadow-inner ${
                                            progress?.status === 'failed' ? 'bg-red-500' :
                                            progress?.status === 'cancelled' ? 'bg-amber-500' :
                                            'bg-gradient-to-r from-green-400 to-emerald-600'
                                        }`}
                                        style={{ width: `${progress && progress?.total > 0 ? ((progress?.processed + progress?.errors) / progress?.total) * 100 : 0}%` }}
                                    />
                                </div>
                                <div className="text-center w-full">
                                    <p className={`font-black text-4xl mb-1 tracking-tight ${progress?.status === 'failed' ? 'text-red-600' : progress?.status === 'cancelled' ? 'text-amber-500' : 'text-gray-900'}`}>
                                        {progress ? `${progress.processed + progress.errors} / ${progress.total}` : ''}
                                    </p>

                                    {/* Contador de Tokens e Custo */}
                                    {progress && (progress.input_tokens !== undefined || progress.estimated_cost !== undefined) && (
                                        <div className="flex flex-wrap justify-center gap-3 mb-6">
                                            {progress.input_tokens !== undefined && (
                                                <div className="bg-blue-50 border border-blue-100 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm">
                                                    <span className="text-[10px] font-black text-blue-400 uppercase tracking-wider">Input Tokens</span>
                                                    <span className="text-sm font-black text-blue-600">{progress.input_tokens.toLocaleString()}</span>
                                                </div>
                                            )}
                                            {progress.output_tokens !== undefined && (
                                                <div className="bg-purple-50 border border-purple-100 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm">
                                                    <span className="text-[10px] font-black text-purple-400 uppercase tracking-wider">Output Tokens</span>
                                                    <span className="text-sm font-black text-purple-600">{progress.output_tokens.toLocaleString()}</span>
                                                </div>
                                            )}
                                            {progress.estimated_cost !== undefined && (
                                                <div className="bg-green-50 border border-green-100 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm">
                                                    <span className="text-[10px] font-black text-green-500 uppercase tracking-wider">Custo Est.</span>
                                                    <span className="text-base font-black text-green-600">
                                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 4 }).format(progress.estimated_cost)}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Resumo de Parâmetros Atualizados */}
                                    {progress?.stats && (
                                        <div className="flex flex-wrap justify-center gap-3 mb-6">
                                            {progress.stats.difficulty > 0 && (
                                                <div className="bg-amber-50 border border-amber-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">⚡</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-amber-400 uppercase tracking-widest leading-none mb-1">Dificuldade</span>
                                                        <span className="text-base font-black text-amber-600">{progress.stats.difficulty}</span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.explanation > 0 && (
                                                <div className="bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">📝</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-emerald-400 uppercase tracking-widest leading-none mb-1">Explicação</span>
                                                        <span className="text-base font-black text-emerald-600">{progress.stats.explanation}</span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.subjects > 0 && (
                                                <div className="bg-blue-50 border border-blue-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">📚</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-blue-400 uppercase tracking-widest leading-none mb-1">Disciplinas</span>
                                                        <span className="text-base font-black text-blue-600">{progress.stats.subjects}</span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.topics > 0 && (
                                                <div className="bg-indigo-50 border border-indigo-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">🏷️</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-indigo-400 uppercase tracking-widest leading-none mb-1">Assuntos</span>
                                                        <span className="text-base font-black text-indigo-600">{progress.stats.topics}</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* API Usage Stats */}
                                    {progress?.stats?.api_usage && Object.keys(progress.stats.api_usage).length > 0 && (
                                        <div className="w-full max-w-xl mx-auto bg-slate-50 border border-slate-100 rounded-3xl p-6 mb-8 mt-4">
                                            <h5 className="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-3">Distribuição de Chamadas por Chave</h5>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                {Object.entries(progress.stats.api_usage).map(([keyName, count]) => (
                                                    <div key={keyName} className="flex items-center justify-between bg-white rounded-xl px-4 py-2 border border-slate-100 shadow-sm transition-hover hover:border-indigo-200 group">
                                                        <span className="text-xs font-bold text-slate-600 truncate mr-2 flex-1">🔑 {keyName}</span>
                                                        <span className="text-sm font-black text-indigo-600 bg-indigo-50 group-hover:bg-indigo-600 group-hover:text-white transition-colors px-3 py-0.5 rounded-full min-w-[3.5rem] text-center shadow-sm">
                                                            {count as number} <span className="text-[8px] opacity-70 uppercase ml-0.5">reqs</span>
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Detalhamento das Questões Processadas */}
                                    {batchDetails?.items && batchDetails.items.length > 0 && (
                                        <div className="mt-8 w-full border-t border-gray-100 pt-8">
                                            <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center justify-center gap-2">
                                                <span className="w-8 h-px bg-gray-100"></span>
                                                Detalhamento das Alterações
                                                <span className="w-8 h-px bg-gray-100"></span>
                                            </h4>

                                            <div className="grid grid-cols-1 gap-4 max-w-3xl mx-auto">
                                                {batchDetails.items.map((item: any) => (
                                                    <div key={item.id} className="bg-white border border-gray-100 rounded-3xl p-5 shadow-sm hover:shadow-md transition">
                                                        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                                            <div className="flex-1 text-left">
                                                                <div className="flex items-center gap-2 mb-2">
                                                                    <span className="px-2 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-black uppercase tracking-widest">#{item.question_id}</span>
                                                                    <div 
                                                                        className="text-xs font-bold text-gray-400 line-clamp-1 italic markdown-content-preview inline-block max-w-[400px]"
                                                                        dangerouslySetInnerHTML={renderMd(item.statement || '')}
                                                                    />
                                                                </div>

                                                                <div className="flex flex-wrap gap-4 mt-3">
                                                                    {item.after?.difficulty && item.before?.difficulty !== item.after?.difficulty && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Dificuldade</span>
                                                                            <div className="flex items-center gap-1.5 bg-amber-50 px-2 py-1 rounded-lg border border-amber-100">
                                                                                <span className="text-[10px] font-bold text-amber-400 opacity-50 uppercase">{item.before?.difficulty || 'N/A'}</span>
                                                                                <span className="text-amber-400 text-xs">→</span>
                                                                                <span className="text-[11px] font-black text-amber-600 uppercase">{item.after.difficulty}</span>
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {item.after?.explanation && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Explicação</span>
                                                                            <div className="flex items-center gap-1 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100">
                                                                                <span className="text-[10px] font-bold text-emerald-600">✅ Gerada</span>
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {(item.after?.subjects?.length || 0) > (item.before?.subjects?.length || 0) && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Vínculos</span>
                                                                            <div className="flex items-center gap-1 bg-blue-50 px-2 py-1 rounded-lg border border-blue-100">
                                                                                <span className="text-[10px] font-bold text-blue-600">+{item.after.subjects.length - (item.before?.subjects?.length || 0)} Matérias</span>
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            <button
                                                                onClick={() => {
                                                                    setViewQuestionId(item.question_id);
                                                                    setIsQuestionModalOpen(true);
                                                                }}
                                                                className="px-4 py-3 bg-indigo-50 text-indigo-600 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition shadow-sm w-full md:w-auto text-center"
                                                            >
                                                                Ver Questão 👁️
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>

            {isQuestionModalOpen && viewQuestionId && (
                <AdminQuestionViewModal
                    isOpen={isQuestionModalOpen}
                    onClose={() => setIsQuestionModalOpen(false)}
                    questionId={viewQuestionId}
                    onNext={() => {}} // Disabled read-only mode navigation
                    onPrev={() => {}}
                />
            )}
        </div>
    );
}

