import { toast } from 'sonner';
import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';

export default function QuestionHistory() {
    const [page, setPage] = useState(1);
    const [detailsModalOpen, setDetailsModalOpen] = useState(false);
    const [errorsModalOpen, setErrorsModalOpen] = useState(false);

    const [selectedBatchData, setSelectedBatchData] = useState<any>(null);
    const [selectedErrors, setSelectedErrors] = useState<any[]>([]);

    const { data, refetch } = useQuery({
        queryKey: ['admin-questions-history', page],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/questions-batch/history?page=${page}`);
            return res.data;
        }
    });

    const actionMutation = useMutation({
        mutationFn: async ({ action, id }: { action: string, id: string | number }) => {
            if (action === 'undoBatch') return api.post(`/api/v1/admin/questions-batch/undo-batch/${id}`);
            if (action === 'retryBatch') return api.post(`/api/v1/admin/questions-batch/retry/${id}`);
            if (action === 'undoItem') return api.post(`/api/v1/admin/questions-batch/undo-item/${id}`);
            if (action === 'details') return api.get(`/api/v1/admin/questions-batch/details/${id}`);
            return null;
        },
        onSuccess: (res, variables) => {
            if (variables.action === 'details') {
                setSelectedBatchData(res?.data);
                setDetailsModalOpen(true);
            } else if (variables.action === 'undoItem') {
                // Update item locally if needed or refresh details
                if (selectedBatchData) {
                    const updatedItems = selectedBatchData.items.map((i: any) =>
                        i.id === variables.id ? { ...i, status: 'reverted' } : i
                    );
                    setSelectedBatchData({ ...selectedBatchData, items: updatedItems });
                }
            } else {
                refetch(); // Refresh main list for batch actions
            }
        },
        onError: () => {
            toast.error('Erro ao processar a requisição.');
        }
    });

    const loadDetails = (batchId: string) => actionMutation.mutate({ action: 'details', id: batchId });
    const undoBatch = (batchId: string) => {
        if (window.confirm('Reverter TODAS as questões deste lote ao estado anterior? Essa ação não pode ser desfeita.')) {
            actionMutation.mutate({ action: 'undoBatch', id: batchId });
        }
    };
    const retryBatch = (batchId: string) => {
        if (window.confirm('Reprocessar as questões que falharam neste lote?')) {
            actionMutation.mutate({ action: 'retryBatch', id: batchId });
        }
    };
    const undoItem = (itemId: number) => {
        if (window.confirm('Reverter esta questão ao estado anterior?')) {
            actionMutation.mutate({ action: 'undoItem', id: itemId });
        }
    };

    const showErrors = (errors: any[]) => {
        setSelectedErrors(errors || []);
        setErrorsModalOpen(true);
    };

    const batches = data?.data || [];

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Histórico de Triagem IA</h1>
                    <p className="text-gray-600">Monitore lotes processados, veja detalhes e reverta alterações.</p>
                </div>
                <Link to="/admin/questions" className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all font-medium">
                    Voltar ao Banco
                </Link>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-50 text-gray-600 text-sm uppercase font-semibold">
                        <tr>
                            <th className="px-6 py-4 border-b">Data</th>
                            <th className="px-6 py-4 border-b">Modelo</th>
                            <th className="px-6 py-4 border-b">Tipo</th>
                            <th className="px-6 py-4 border-b text-center">Progresso</th>
                            <th className="px-6 py-4 border-b">Status</th>
                            <th className="px-6 py-4 border-b text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {batches.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                                    Nenhum lote de processamento encontrado.
                                </td>
                            </tr>
                        )}
                        {batches.map((batch: any) => {
                            const dateObj = new Date(batch.created_at);
                            const updatedObj = new Date(batch.updated_at);
                            const isOldProcessing = batch.status === 'processing' && (new Date().getTime() - updatedObj.getTime() > 15 * 60000);
                            const perc = batch.total_count > 0 ? Math.round(((batch.processed_count + batch.error_count) / batch.total_count) * 100) : 0;

                            return (
                                <tr key={batch.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="text-sm font-medium text-gray-900">{dateObj.toLocaleDateString('pt-BR')}</div>
                                        <div className="text-xs text-gray-500">{dateObj.toLocaleTimeString('pt-BR')}</div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                            {batch.model}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {batch.type.charAt(0).toUpperCase() + batch.type.slice(1)}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-col items-center">
                                            <div className="w-full bg-gray-200 rounded-full h-1.5 mb-1">
                                                <div className="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style={{ width: `${perc}%` }}></div>
                                            </div>
                                            <span className="text-[10px] text-gray-500">{batch.processed_count + batch.error_count}/{batch.total_count} ({perc}%)</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                        {batch.status === 'processing' && isOldProcessing && <span className="flex items-center gap-1.5 text-orange-600 font-medium text-sm">⚠️ Provável Falha</span>}
                                        {batch.status === 'processing' && !isOldProcessing && <span className="flex items-center gap-1.5 text-blue-600 font-medium text-sm"><span className="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>Processando</span>}
                                        {batch.status === 'completed' && <span className="flex items-center gap-1.5 text-green-600 font-medium text-sm">✅ Concluído</span>}
                                        {batch.status === 'failed' && <span className="flex items-center gap-1.5 text-red-600 font-medium text-sm">❌ Falhou</span>}
                                        {batch.status === 'reverted' && <span className="flex items-center gap-1.5 text-amber-600 font-medium text-sm">↩️ Revertido</span>}
                                        {batch.status === 'cancelled' && <span className="flex items-center gap-1.5 text-gray-500 font-medium text-sm">🚫 Cancelado</span>}
                                        {['processing', 'completed', 'failed', 'reverted', 'cancelled'].indexOf(batch.status) === -1 && <span className="text-gray-400 text-sm">{batch.status}</span>}
                                    </td>
                                    <td className="px-6 py-4 text-right space-x-2">
                                        <button onClick={() => loadDetails(batch.batch_id)} className="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                            Ver Detalhes
                                        </button>

                                        {batch.status === 'completed' && (
                                            <button onClick={() => undoBatch(batch.batch_id)} className="text-amber-600 hover:text-amber-900 font-medium text-sm">
                                                Desfazer Lote
                                            </button>
                                        )}

                                        {(batch.status === 'failed' || isOldProcessing) && (
                                            <button onClick={() => retryBatch(batch.batch_id)} className="text-blue-600 hover:text-blue-900 font-medium text-sm">
                                                Tentar Novamente
                                            </button>
                                        )}

                                        {batch.status === 'processing' && !isOldProcessing && (
                                            <button onClick={() => { }} className="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                                Monitorar
                                            </button>
                                        )}

                                        {(batch.error_count > 0 || batch.status === 'failed') && (
                                            <button onClick={() => showErrors(batch.errors_log)} className="text-red-600 hover:text-red-900 font-medium text-sm">
                                                Ver Erros
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                {/* Basic Pagination Handling if API returns meta */}
                {data?.last_page > 1 && (
                    <div className="px-6 py-4 border-t border-gray-100 flex gap-2">
                        <button disabled={page === 1} onClick={() => setPage(page - 1)} className="px-3 py-1 bg-gray-100 rounded disabled:opacity-50">Anterior</button>
                        <span className="px-3 py-1">Página {page} de {data.last_page}</span>
                        <button disabled={page === data.last_page} onClick={() => setPage(page + 1)} className="px-3 py-1 bg-gray-100 rounded disabled:opacity-50">Próxima</button>
                    </div>
                )}
            </div>

            {/* Details Modal */}
            {detailsModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center min-h-screen p-4">
                    <div className="fixed inset-0 bg-black/50 transition-opacity" onClick={() => setDetailsModalOpen(false)}></div>
                    <div className="relative bg-white rounded-xl shadow-xl max-w-4xl w-full p-6" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-xl font-bold text-gray-800">Detalhes do Lote</h3>
                            <button onClick={() => setDetailsModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                                ❌
                            </button>
                        </div>

                        {selectedBatchData?.batch && (
                            <div className="mb-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                                <span className="font-medium">Tipo:</span> {selectedBatchData.batch.type} •
                                <span className="font-medium ml-2">Modelo:</span> {selectedBatchData.batch.model} •
                                <span className="font-medium ml-2">Data:</span> {new Date(selectedBatchData.batch.created_at).toLocaleString()}
                            </div>
                        )}

                        <div className="max-h-[60vh] overflow-y-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th className="px-4 py-2">Questão</th>
                                        <th className="px-4 py-2">Status</th>
                                        <th className="px-4 py-2">Antes → Depois</th>
                                        <th className="px-4 py-2 text-right">Ação</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(selectedBatchData?.items || []).map((item: any) => (
                                        <tr key={item.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-gray-900">#{item.question_id}</div>
                                                <div className="text-xs text-gray-500 truncate max-w-xs">{item.statement}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${item.status === 'processed' ? 'bg-green-50 text-green-700' :
                                                        item.status === 'failed' ? 'bg-red-50 text-red-700' :
                                                            item.status === 'reverted' ? 'bg-amber-50 text-amber-700' :
                                                                'bg-gray-50 text-gray-600'
                                                    }`}>{item.status}</span>
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                {item.before && item.after ? (
                                                    <div className="space-y-1">
                                                        <div className="flex gap-2">
                                                            <span className="text-red-500 line-through">{item.before.difficulty || '—'}</span>
                                                            <span>→</span>
                                                            <span className="text-green-600 font-medium">{item.after.difficulty || '—'}</span>
                                                        </div>
                                                        <div className="text-gray-500">
                                                            {(item.before.explanation ? 'Com' : 'Sem')} explicação → {(item.after.explanation ? 'Com' : 'Sem')} explicação
                                                        </div>
                                                    </div>
                                                ) : <span className="text-gray-400">—</span>}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {item.status === 'processed' && (
                                                    <button onClick={() => undoItem(item.id)} className="text-amber-600 hover:text-amber-800 text-xs font-medium">
                                                        ↩ Desfazer
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button onClick={() => setDetailsModalOpen(false)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">Fechar</button>
                        </div>
                    </div>
                </div>
            )}

            {/* Errors Modal */}
            {errorsModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center min-h-screen p-4">
                    <div className="fixed inset-0 bg-black/50 transition-opacity" onClick={() => setErrorsModalOpen(false)}></div>
                    <div className="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6" onClick={e => e.stopPropagation()}>
                        <h3 className="text-xl font-bold text-gray-800 mb-4">Log de Erros do Lote</h3>
                        <div className="max-h-96 overflow-y-auto space-y-2">
                            {selectedErrors.length === 0 ? (
                                <p className="text-center text-gray-500 py-4">Nenhum detalhe de erro disponível.</p>
                            ) : selectedErrors.map((err: any, idx: number) => (
                                <div key={idx} className={`p-3 rounded-lg border ${err.type === 'fatal' ? 'bg-red-50 border-red-200' : 'bg-orange-50 border-orange-200'}`}>
                                    <div className="flex justify-between items-start mb-1">
                                        <span className={`text-[10px] font-bold uppercase ${err.type === 'fatal' ? 'text-red-700' : 'text-orange-700'}`}>{err.type}</span>
                                        <span className="text-[10px] text-gray-500">{err.time}</span>
                                    </div>
                                    <p className="text-xs text-gray-800 break-words">{err.error}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-6 flex justify-end">
                            <button onClick={() => setErrorsModalOpen(false)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">Fechar</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
