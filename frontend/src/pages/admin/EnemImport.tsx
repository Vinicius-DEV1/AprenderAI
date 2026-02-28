import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';

import { motion, AnimatePresence } from 'framer-motion';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function EnemImport() {
    const queryClient = useQueryClient();
    const currentYear = new Date().getFullYear();

    const [yearInput, setYearInput] = useState('');
    const [showErrorModal, setShowErrorModal] = useState(false);
    const [showIgnoredModal, setShowIgnoredModal] = useState(false);
    const [currentErrors, setCurrentErrors] = useState<string[]>([]);
    const [currentIgnored, setCurrentIgnored] = useState<any[]>([]);

    const [activeBatchId, setActiveBatchId] = useState<string | null>(null);
    const [progress, setProgress] = useState(0);
    const [processed, setProcessed] = useState(0);
    const [total, setTotal] = useState(0);
    const [isFinished, setIsFinished] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-enem-import'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/enem');
            return res.data;
        }
    });

    useEffect(() => {
        if (data?.activeBatch && !data?.activeBatch?.finished) {
            setActiveBatchId(data.activeBatch.id);
            setProgress(data.activeBatch.progress || 0);
            setProcessed(data.activeBatch.processed || 0);
            setTotal(data.activeBatch.total || 0);
            setIsFinished(false);
        }
    }, [data]);

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        if (activeBatchId && !isFinished) {
            interval = setInterval(async () => {
                try {
                    const res = await api.get(`/api/v1/admin/enem/status?batch_id=${activeBatchId}`);
                    const statusData = res.data;

                    setProgress(statusData.progress || 0);
                    setProcessed(statusData.processed || 0);
                    setTotal(statusData.total || 0);

                    if (statusData.finished || statusData.progress === 100) {
                        setIsFinished(true);
                        clearInterval(interval);
                        setTimeout(() => {
                            setActiveBatchId(null);
                            queryClient.invalidateQueries({ queryKey: ['admin-enem-import'] });
                        }, 2000);
                    }
                } catch (e) {
                    // silent
                }
            }, 2000);
        }
        return () => clearInterval(interval);
    }, [activeBatchId, isFinished, queryClient]);

    const startImport = useMutation({
        mutationFn: async (year: string) => {
            const res = await api.post('/api/v1/admin/enem', { year: year || null });
            return res.data;
        },
        onSuccess: (resData) => {
            if (resData.activeBatch) {
                setActiveBatchId(resData.activeBatch.id);
                setProgress(resData.activeBatch.progress || 0);
                setProcessed(resData.activeBatch.processed || 0);
                setTotal(resData.activeBatch.total || 0);
                setIsFinished(false);
            } else {
                queryClient.invalidateQueries({ queryKey: ['admin-enem-import'] });
            }
        },
        onError: () => {
            toast.error('Falha ao iniciar importação.');
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        startImport.mutate(yearInput);
    };



    if (isLoading) return <AdminPageSkeleton />;

    const logs = data?.logs || { data: [], current_page: 1, last_page: 1 };

    return (
        <div className="container mx-auto py-12 px-4 sm:px-6 lg:px-8 max-w-7xl">
            <div className="mb-6 flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Importação ENEM Dev API</h1>
                    <p className="text-gray-600 text-sm mt-1">Ferramenta para reabastecimento automático via API Pública.</p>
                </div>
            </div>

            {/* Seção do Progresso Ativo */}
            <AnimatePresence>
                {activeBatchId && (
                    <motion.div
                        initial={{ opacity: 0, y: -20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        className="bg-white dark:bg-slate-900 p-8 rounded-3xl shadow-xl shadow-indigo-100 dark:shadow-none border border-indigo-100 dark:border-slate-800 mb-8 overflow-hidden relative"
                    >
                        <div className="relative z-10">
                            <div className="flex justify-between items-center mb-6">
                                <div className="flex items-center gap-4">
                                    <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-indigo-200 animate-pulse">
                                        ⚡
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-black text-slate-800 dark:text-slate-100">Sincronização Ativa</h2>
                                        <p className="text-indigo-600 dark:text-indigo-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                                            <span className="w-2 h-2 bg-indigo-600 rounded-full animate-ping"></span>
                                            Processando Lote ENEM API
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <span className="text-4xl font-black text-indigo-600">{progress}%</span>
                                </div>
                            </div>

                            <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-4 mb-4 p-1 overflow-hidden">
                                <motion.div
                                    initial={{ width: 0 }}
                                    animate={{ width: `${progress}%` }}
                                    transition={{ duration: 0.5, ease: "easeOut" }}
                                    className="h-full rounded-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 bg-[length:200%_100%] animate-[bg-move_3s_linear_infinite] relative"
                                >
                                    <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full animate-[shimmer_1.5s_infinite]"></div>
                                </motion.div>
                            </div>

                            <div className="flex justify-between text-xs font-black uppercase tracking-wider text-slate-400">
                                <div className="flex gap-6">
                                    <div className="flex flex-col">
                                        <span className="text-[9px] text-slate-400">STATUS</span>
                                        <span className="text-slate-600 dark:text-slate-300">IMPORTANDO ANOS</span>
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-[9px] text-slate-400">PROCESSADO</span>
                                        <span className="text-indigo-600">{processed} Anos de {total}</span>
                                    </div>
                                </div>
                                <div className="text-right flex flex-col items-end">
                                    <span className="text-[9px] text-slate-400">TEMPO ESTIMADO</span>
                                    <span className="text-slate-600 dark:text-slate-300">~2-5 MINUTOS</span>
                                </div>
                            </div>

                            {isFinished && (
                                <motion.div
                                    initial={{ opacity: 0, scale: 0.95 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    className="mt-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 rounded-2xl flex items-center gap-3 text-emerald-700 dark:text-emerald-400"
                                >
                                    <span className="text-xl">✅</span>
                                    <span className="text-xs font-bold uppercase tracking-wide">Importação concluído com sucesso! Atualizando tabelas...</span>
                                </motion.div>
                            )}
                        </div>

                        <style>{`
                            @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
                            @keyframes bg-move { 0% { background-position: 0% 0%; } 100% { background-position: -200% 0%; } }
                        `}</style>
                    </motion.div>
                )}
            </AnimatePresence>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Disparar Importação */}
                <div className="lg:col-span-1">
                    <div className="bg-white dark:bg-slate-900 p-6 rounded-3xl shadow-sm border border-gray-100 dark:border-slate-800">
                        <h2 className="text-lg font-black text-gray-800 dark:text-slate-100 mb-4 uppercase tracking-tight">Novo Acionamento</h2>

                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Ano Opcional</label>
                                <input
                                    type="number"
                                    min="2009"
                                    max={currentYear.toString()}
                                    placeholder="Ex: 2022"
                                    value={yearInput}
                                    onChange={(e) => setYearInput(e.target.value)}
                                    className="w-full rounded-xl border-gray-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 shadow-sm focus:ring-2 focus:ring-indigo-500 transition-all font-bold text-sm h-12"
                                    disabled={!!activeBatchId && !isFinished}
                                />
                                <p className="text-[10px] text-gray-500 mt-2 font-medium">Deixe em branco para importar TODOS os anos disponíveis (Atenção: muito demorado!).</p>
                            </div>

                            <button type="submit"
                                className={`w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs uppercase tracking-widest py-4 px-4 rounded-2xl transition-all shadow-lg shadow-indigo-100 dark:shadow-none flex items-center justify-center gap-3 ${!!activeBatchId && !isFinished ? 'opacity-50 cursor-not-allowed' : 'active:scale-95'}`}
                                disabled={!!activeBatchId && !isFinished}>
                                {startImport.isPending ? (
                                    <>
                                        <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                        ACIONANDO...
                                    </>
                                ) : (
                                    <>
                                        🚀 INICIAR INTEGRAÇÃO
                                    </>
                                )}
                            </button>

                            <div className="mt-4 p-4 bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-800 text-indigo-700 dark:text-indigo-400 text-[10px] rounded-2xl font-bold">
                                <strong>Transacional e Idempotente</strong>
                                <p className="mt-1 leading-relaxed opacity-80">A importação atualizará apenas registros que não foram importados ainda no banco de dados. Múltiplos acionamentos são seguros.</p>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Histórico e Logs */}
                <div className="lg:col-span-2">
                    <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
                        <div className="p-6 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
                            <h2 className="text-lg font-black text-gray-800 dark:text-slate-100 uppercase tracking-tight">Histórico</h2>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-500">
                                <thead className="text-[10px] font-black text-gray-400 uppercase bg-gray-50 dark:bg-slate-800/50 tracking-widest">
                                    <tr>
                                        <th className="px-6 py-4">Iniciado Em</th>
                                        <th className="px-6 py-4">Alvo</th>
                                        <th className="px-6 py-4">Inseridas</th>
                                        <th className="px-6 py-4">Ignoradas</th>
                                        <th className="px-6 py-4">Erros</th>
                                        <th className="px-6 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50 dark:divide-slate-800">
                                    {logs.data?.length > 0 ? logs.data.map((log: any) => (
                                        <tr key={log.id} className="bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                            <td className="px-6 py-4 text-xs font-bold text-gray-600 dark:text-slate-400">{new Date(log.created_at).toLocaleString('pt-BR')}</td>
                                            <td className="px-6 py-4 font-black text-xs text-slate-800 dark:text-slate-200">{log.year === 0 ? 'COMPLETO' : `ANO ${log.year}`}</td>
                                            <td className="px-6 py-4">
                                                <span className="text-emerald-600 font-black">+{log.inserted_count}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold">{log.ignored_count}</span>
                                                    {log.ignored_count > 0 && Array.isArray(log.ignored_details) && (
                                                        <button onClick={() => { setCurrentIgnored(log.ignored_details); setShowIgnoredModal(true); }} className="p-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg hover:bg-indigo-100 transition-colors" title="Ver Detalhes">🔍</button>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-rose-500 font-bold">{log.error_count}</span>
                                                    {log.error_count > 0 && Array.isArray(log.errors) && (
                                                        <button onClick={() => { setCurrentErrors(log.errors); setShowErrorModal(true); }} className="p-1.5 bg-rose-50 dark:bg-rose-900/20 rounded-lg hover:bg-rose-100 transition-colors" title="Ver Falhas">❌</button>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {log.status === 'completed' ? (
                                                    <span className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400 text-[9px] font-black uppercase px-3 py-1 rounded-lg">CONCLUÍDO</span>
                                                ) : log.status === 'failed' ? (
                                                    <span className="bg-rose-100 text-rose-800 dark:bg-rose-900/20 dark:text-rose-400 text-[9px] font-black uppercase px-3 py-1 rounded-lg">FALHOU</span>
                                                ) : (
                                                    <span className="bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400 text-[9px] font-black uppercase px-3 py-1 rounded-lg">EM ANDAMENTO</span>
                                                )}
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-8 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">Nenhum evento registrado</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {logs.last_page > 1 && (
                            <div className="p-4 border-t border-gray-100 dark:border-slate-800 flex justify-end">
                                <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Página {logs.current_page} de {logs.last_page}</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal de Erros */}
            <AnimatePresence>
                {showErrorModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
                            onClick={() => setShowErrorModal(false)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            className="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden relative z-10 border border-slate-200 dark:border-slate-800"
                        >
                            <div className="p-6">
                                <h3 className="text-lg font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight mb-4">Relatório de Diagnóstico</h3>
                                <div className="max-h-96 overflow-y-auto rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 custom-scrollbar">
                                    {currentErrors.map((err, idx) => (
                                        <div key={idx} className="mb-3 p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 text-xs font-bold text-rose-500 leading-relaxed shadow-sm italic">
                                            {err}
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="p-6 bg-slate-50 dark:bg-slate-800/30 flex justify-end">
                                <button type="button" className="px-6 py-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-900 transition-all shadow-lg shadow-slate-200" onClick={() => setShowErrorModal(false)}>
                                    Fechar
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>

            {/* Modal de Itens Ignorados */}
            <AnimatePresence>
                {showIgnoredModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-slate-900/80 backdrop-blur-md"
                            onClick={() => setShowIgnoredModal(false)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.9, y: 40 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.9, y: 40 }}
                            className="bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-2xl w-full max-w-4xl overflow-hidden relative z-10 border border-slate-200 dark:border-slate-800"
                        >
                            <div className="bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-600 bg-[length:200%_100%] animate-[bg-move_4s_linear_infinite] px-8 py-6">
                                <h3 className="text-xl font-black text-white flex items-center gap-4 uppercase tracking-tight">
                                    <span className="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">🕵️</span>
                                    Questões Ignoradas (Duplicatas)
                                </h3>
                            </div>
                            <div className="p-8">
                                <div className="overflow-y-auto max-h-[65vh] pr-4 custom-scrollbar space-y-6">
                                    {currentIgnored.map((item, idx) => (
                                        <div key={idx} className="bg-slate-50 dark:bg-slate-800/50 rounded-3xl p-6 border border-slate-100 dark:border-slate-700 shadow-sm relative group overflow-hidden">
                                            <div className="absolute top-0 right-0 p-4 opacity-10 font-black text-6xl select-none group-hover:opacity-20 transition-opacity">#{item.index}</div>

                                            <div className="flex flex-wrap gap-4 items-center mb-6 relative z-10">
                                                <div className="px-4 py-1.5 bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-indigo-100">ID {item.index}</div>
                                                <div className="px-4 py-1.5 bg-white dark:bg-slate-800 text-slate-400 text-[10px] font-black uppercase tracking-widest rounded-xl border border-slate-200 dark:border-slate-700">ANO {item.full_data?.year}</div>
                                                <div className="px-4 py-1.5 bg-orange-100 text-orange-700 text-[10px] font-black uppercase tracking-widest rounded-xl">DUPLICATA</div>
                                            </div>

                                            <div className="space-y-4 relative z-10">
                                                <div className="flex flex-wrap gap-6 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                    <div className="flex flex-col">
                                                        <span>Matéria</span>
                                                        <span className="text-slate-800 dark:text-slate-200">{item.full_data?.discipline}</span>
                                                    </div>
                                                    <div className="flex flex-col">
                                                        <span>Assunto</span>
                                                        <span className="text-slate-800 dark:text-slate-200">{item.full_data?.topic || 'N/A'}</span>
                                                    </div>
                                                </div>

                                                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-100 dark:border-slate-700">
                                                    <p className="text-sm font-bold text-slate-700 dark:text-slate-300 leading-relaxed italic line-clamp-3">
                                                        {item.full_data?.context?.replace(/!\[.*?\]\(.*?\)/g, '')}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="p-8 bg-slate-50 dark:bg-slate-800/30 flex justify-center sticky bottom-0 border-t border-slate-100 dark:border-slate-800">
                                <button type="button" className="px-12 py-4 bg-slate-900 text-white text-sm font-black uppercase tracking-[0.2em] rounded-2xl hover:scale-105 transition-all shadow-2xl shadow-slate-300" onClick={() => setShowIgnoredModal(false)}>
                                    FECHAR DOCUMENTO
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
}

