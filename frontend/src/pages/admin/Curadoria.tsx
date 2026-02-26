import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';

export default function Curadoria() {
    const queryClient = useQueryClient();
    const [triageOpen, setTriageOpen] = useState(false);
    const [triageLoading, setTriageLoading] = useState(false);
    const [triageBatchId, setTriageBatchId] = useState<string | null>(null);
    const [triageProgress, setTriageProgress] = useState<any>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-curadoria'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/curadoria');
            return res.data;
        }
    });

    const startTriage = useMutation({
        mutationFn: async (params: any) => {
            const res = await api.post('/api/v1/admin/triage/start', params);
            return res.data;
        },
        onSuccess: (data) => {
            setTriageBatchId(data.batch_id);
            setTriageLoading(true);
            pollTriage(data.batch_id);
        }
    });

    const pollTriage = (batchId: string) => {
        const interval = setInterval(async () => {
            try {
                const res = await api.get(`/api/v1/admin/triage/${batchId}/status`);
                setTriageProgress(res.data);
                if (res.data.status === 'completed' || res.data.status === 'failed') {
                    clearInterval(interval);
                    setTriageLoading(false);
                    queryClient.invalidateQueries({ queryKey: ['admin-curadoria'] });
                }
            } catch (e) {
                clearInterval(interval);
                setTriageLoading(false);
            }
        }, 3000);
    };

    if (isLoading) return <div className="p-8">Carregando portal de curadoria...</div>;
    if (!data) return <div className="p-8 text-red-500">Erro ao carregar dados.</div>;

    const { stats, recent_imports, recent_batches } = data;

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto">
            <header className="mb-10 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-white mb-2">Portal de Curadoria 🔍</h1>
                    <p className="text-gray-600 dark:text-slate-400">Gerencie a qualidade e a triagem do banco de questões.</p>
                </div>
                <button
                    onClick={() => setTriageOpen(true)}
                    className="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-lg flex items-center gap-2"
                >
                    <span>🤖</span> Iniciar Triagem IA
                </button>
            </header>

            {/* Triage Status Overlay */}
            {triageLoading && triageProgress && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl p-8 max-w-md w-full shadow-2xl border border-indigo-500/30">
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-xl font-bold">Processando Lote IA</h3>
                            <span className="animate-pulse text-indigo-500 font-black">●</span>
                        </div>
                        <div className="space-y-4">
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">Progresso</span>
                                <span className="font-bold">{triageProgress.processed} / {triageProgress.total}</span>
                            </div>
                            <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
                                <div
                                    className="bg-indigo-600 h-full transition-all duration-500"
                                    style={{ width: `${(triageProgress.processed / triageProgress.total) * 100}%` }}
                                ></div>
                            </div>
                            <p className="text-center text-xs text-slate-400">{triageProgress.message || 'Sincronizando com os agentes...'}</p>
                            {triageProgress.status === 'completed' && (
                                <button
                                    onClick={() => { setTriageLoading(false); setTriageProgress(null); }}
                                    className="w-full py-3 bg-green-600 text-white font-bold rounded-xl mt-4"
                                >
                                    Concluir
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Configuração de Triagem */}
            {triageOpen && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl p-8 max-w-lg w-full shadow-2xl">
                        <h3 className="text-2xl font-bold mb-4">Nova Triagem IA 🤖</h3>
                        <p className="text-slate-500 text-sm mb-6">Selecione o tipo de processamento que deseja realizar nas questões pendentes.</p>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-400 mb-2">Quantidade de Questões</label>
                                <select id="triage-qty" className="w-full p-3 border rounded-xl dark:bg-slate-800 dark:border-slate-700">
                                    <option value="10">10 questões</option>
                                    <option value="50">50 questões</option>
                                    <option value="100">100 questões</option>
                                    <option value="500">500 questões</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-bold uppercase text-slate-400 mb-2">Tipo de Processamento</label>
                                <select id="triage-type" className="w-full p-3 border rounded-xl dark:bg-slate-800 dark:border-slate-700">
                                    <option value="both">Completo (Classificar + Explicar)</option>
                                    <option value="classification">Apenas Classificação (Assuntos)</option>
                                    <option value="explanation">Apenas Explicação/Dificuldade</option>
                                </select>
                            </div>
                        </div>

                        <div className="flex gap-4 mt-10">
                            <button
                                onClick={() => setTriageOpen(false)}
                                className="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-xl hover:bg-slate-200"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={() => {
                                    const qty = (document.getElementById('triage-qty') as HTMLSelectElement).value;
                                    const type = (document.getElementById('triage-type') as HTMLSelectElement).value;
                                    startTriage.mutate({ quantity: parseInt(qty), type });
                                    setTriageOpen(false);
                                }}
                                className="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700"
                            >
                                Iniciar Agora
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <p className="text-slate-400 text-xs font-bold uppercase mb-2">Aguardando Revisão</p>
                    <h3 className="text-3xl font-bold text-orange-500">{stats.pending_import}</h3>
                    <p className="text-xs text-slate-500 mt-2">Questões importadas via ZIP</p>
                </div>
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <p className="text-slate-400 text-xs font-bold uppercase mb-2">Pendentes de Triagem</p>
                    <h3 className="text-3xl font-bold text-blue-500">{stats.pending_triage}</h3>
                    <p className="text-xs text-slate-500 mt-2">Sem explicação ou assuntos</p>
                </div>
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <p className="text-slate-400 text-xs font-bold uppercase mb-2">Lotes Processados</p>
                    <h3 className="text-3xl font-bold text-indigo-500">{stats.total_batches}</h3>
                    <p className="text-xs text-slate-500 mt-2">Histórico total de triagens IA</p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Recent Imports */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
                    <h3 className="font-bold mb-6 flex justify-between items-center">
                        📦 Importações Recentes (ZIP)
                        <button className="text-xs text-blue-600 hover:underline">Ver todas</button>
                    </h3>
                    <div className="space-y-4">
                        {recent_imports.map((imp: any) => (
                            <div key={imp.id} className="flex justify-between items-center p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-xl transition-colors">
                                <div>
                                    <p className="text-sm font-semibold">{imp.filename}</p>
                                    <p className="text-xs text-slate-400">Por {imp.uploader?.name} • {new Date(imp.created_at).toLocaleDateString()}</p>
                                </div>
                                <span className="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Concluído</span>
                            </div>
                        ))}
                        {recent_imports.length === 0 && <p className="text-center py-6 text-slate-400 italic">Nenhuma importação encontrada.</p>}
                    </div>
                </div>

                {/* AI Batches */}
                <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
                    <h3 className="font-bold mb-6 flex justify-between items-center">
                        🤖 Lotes de Triagem IA
                        <button className="text-xs text-blue-600 hover:underline">Ver todos</button>
                    </h3>
                    <div className="space-y-4">
                        {recent_batches.map((batch: any) => (
                            <div key={batch.id} className="flex justify-between items-center p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-xl transition-colors">
                                <div>
                                    <p className="text-sm font-semibold">Lote #{batch.batch_id.substring(0, 8)}</p>
                                    <p className="text-xs text-slate-400">{batch.total_count} questões • {new Date(batch.created_at).toLocaleDateString()}</p>
                                </div>
                                <span className={`px-2 py-1 text-[10px] font-bold rounded uppercase ${batch.status === 'completed' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700'}`}>
                                    {batch.status === 'completed' ? 'Finalizado' : batch.status === 'processing' ? 'Processando' : batch.status}
                                </span>
                            </div>
                        ))}
                        {recent_batches.length === 0 && <p className="text-center py-6 text-slate-400 italic">Nenhum lote processado ainda.</p>}
                    </div>
                </div>
            </div>

            <div className="mt-10 p-6 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl text-white flex justify-between items-center">
                <div>
                    <h3 className="text-xl font-bold mb-1">Deseja importar novas questões?</h3>
                    <p className="text-blue-100 text-sm opacity-80">Carregue um arquivo ZIP seguindo o padrão de nomenclatura.</p>
                </div>
                <button className="px-6 py-3 bg-white text-indigo-700 font-bold rounded-xl hover:bg-blue-50 transition-colors">
                    Fazer Upload
                </button>
            </div>
        </div>
    );
}
