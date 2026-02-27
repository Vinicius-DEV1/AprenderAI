import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';

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
            alert('Falha ao iniciar importação.');
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        startImport.mutate(yearInput);
    };

    const extractImages = (text: string) => {
        if (!text) return [];
        const regex = /!\[.*?\]\((.*?)\)/g;
        const matches = [];
        let match;
        while ((match = regex.exec(text)) !== null) {
            matches.push(match[1]);
        }
        return matches;
    };

    if (isLoading) return <div className="p-8">Carregando painel API ENEM...</div>;

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
            {activeBatchId && (
                <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 mb-8">
                    <h2 className="text-lg font-semibold text-gray-800 mb-4">Lote em Andamento</h2>
                    <div className="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div className="bg-blue-600 h-4 rounded-full transition-all duration-500" style={{ width: `${progress}%` }}></div>
                    </div>
                    <div className="flex justify-between text-sm text-gray-600">
                        <span>Progresso: {progress}%</span>
                        <span>Sub-tarefas (Anos): {processed} de {total}</span>
                    </div>

                    {isFinished && (
                        <div className="mt-4 p-3 bg-green-50 border-l-4 border-green-500 text-green-700">
                            O processamento em lote foi concluído! Recarregando sistema...
                        </div>
                    )}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Disparar Importação */}
                <div className="lg:col-span-1">
                    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                        <h2 className="text-lg font-semibold text-gray-800 mb-4">Novo Acionamento</h2>

                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Ano Opcional</label>
                                <input
                                    type="number"
                                    min="2009"
                                    max={currentYear.toString()}
                                    placeholder="Ex: 2022"
                                    value={yearInput}
                                    onChange={(e) => setYearInput(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    disabled={!!activeBatchId && !isFinished}
                                />
                                <p className="text-xs text-gray-500 mt-1">Deixe em branco para importar TODOS os anos disponíveis (Atenção: muito demorado!).</p>
                            </div>

                            <button type="submit"
                                className={`w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors ${!!activeBatchId && !isFinished ? 'opacity-50 cursor-not-allowed' : ''}`}
                                disabled={!!activeBatchId && !isFinished}>
                                INICIAR INTEGRAÇÃO
                            </button>

                            <div className="mt-4 p-3 bg-blue-50 border border-blue-100 text-blue-700 text-sm rounded-lg">
                                <strong>Transacional e Idempotente</strong>
                                <p className="mt-1 text-xs">A importação atualizará apenas registros que não foram importados ainda no banco de dados. Múltiplos acionamentos são seguros.</p>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Histórico e Logs */}
                <div className="lg:col-span-2">
                    <div className="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 border-b border-gray-100">
                            <h2 className="text-lg font-semibold text-gray-800">Histórico de Importações</h2>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-500">
                                <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3">Iniciado Em</th>
                                        <th className="px-6 py-3">Alvo</th>
                                        <th className="px-6 py-3">Inseridas</th>
                                        <th className="px-6 py-3">Ignoradas</th>
                                        <th className="px-6 py-3">Erros</th>
                                        <th className="px-6 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data?.length > 0 ? logs.data.map((log: any) => (
                                        <tr key={log.id} className="border-b bg-white hover:bg-gray-50">
                                            <td className="px-6 py-4">{new Date(log.created_at).toLocaleString('pt-BR')}</td>
                                            <td className="px-6 py-4 font-medium">{log.year === 0 ? 'COMPLETO (TUDO)' : `Ano ${log.year}`}</td>
                                            <td className="px-6 py-4 text-green-600">{log.inserted_count}</td>
                                            <td className="px-6 py-4 text-gray-500">
                                                {log.ignored_count}
                                                {log.ignored_count > 0 && Array.isArray(log.ignored_details) && (
                                                    <button onClick={() => { setCurrentIgnored(log.ignored_details); setShowIgnoredModal(true); }} className="ml-2 text-xs text-blue-500 hover:underline" title="Ver Detalhes">🔍</button>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-red-500">
                                                {log.error_count}
                                                {log.error_count > 0 && Array.isArray(log.errors) && (
                                                    <button onClick={() => { setCurrentErrors(log.errors); setShowErrorModal(true); }} className="ml-2 text-xs text-blue-500 hover:underline">Ver Falhas</button>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                {log.status === 'completed' ? (
                                                    <span className="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Concluído</span>
                                                ) : log.status === 'failed' ? (
                                                    <span className="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">Falhou</span>
                                                ) : (
                                                    <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Em Progresso</span>
                                                )}
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-4 text-center text-gray-500">Nenhum evento de integração registrado no histórico.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {logs.last_page > 1 && (
                            <div className="p-4 border-t border-gray-100 flex justify-end">
                                <span className="text-xs text-gray-500">Paginação via API em andamento... (Página {logs.current_page} de {logs.last_page})</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal de Erros */}
            {showErrorModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto font-sans">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity z-40" onClick={() => setShowErrorModal(false)}>
                            <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                        <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">Relatório de Diagnóstico</h3>
                                <div className="mt-2 max-h-64 overflow-y-auto rounded bg-gray-100 p-2 text-sm font-mono text-red-600">
                                    {currentErrors.map((err, idx) => (
                                        <div key={idx} className="mb-2 p-1 border-b border-gray-200">{err}</div>
                                    ))}
                                </div>
                            </div>
                            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onClick={() => setShowErrorModal(false)}>
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Itens Ignorados */}
            {showIgnoredModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto font-sans">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity z-40" onClick={() => setShowIgnoredModal(false)}>
                            <div className="absolute inset-0 bg-gray-900 opacity-60 backdrop-blur-sm"></div>
                        </div>
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative z-50 border border-gray-100">
                            <div className="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4">
                                <h3 className="text-lg font-bold text-white flex items-center gap-2">
                                    <span>🕵️‍♂️</span> Itens Ignorados na Importação
                                </h3>
                            </div>
                            <div className="bg-white px-6 py-6 font-sans">
                                <div className="overflow-y-auto max-h-[75vh] pr-2 custom-scrollbar">
                                    <div className="space-y-6">
                                        {currentIgnored.map((item, idx) => (
                                            <div key={idx} className="bg-white border-2 border-gray-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-500 group">
                                                {/* Header: ID + Motivo */}
                                                <div className="bg-gray-50 px-6 py-4 flex items-center justify-between border-b border-gray-100">
                                                    <div className="flex items-center gap-4">
                                                        <div className="bg-blue-600 text-white text-[11px] font-black px-4 py-1.5 rounded-xl uppercase tracking-widest shadow-lg shadow-blue-200">
                                                            ID #{item.index}
                                                        </div>
                                                        <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-white rounded-lg border border-gray-200 shadow-sm">
                                                            <span className="text-gray-400 text-[10px] font-bold uppercase">Prova:</span>
                                                            <span className="text-gray-800 text-[10px] font-black uppercase">{item.full_data?.year}</span>
                                                        </div>
                                                    </div>

                                                    {item.reason === 'duplicate' && (
                                                        <span className="px-4 py-1.5 rounded-full bg-blue-50 text-blue-700 font-black text-[10px] uppercase border-2 border-blue-100">DUPLICATA</span>
                                                    )}
                                                    {item.reason === 'invalid_data' && (
                                                        <span className="px-4 py-1.5 rounded-full bg-orange-50 text-orange-700 font-black text-[10px] uppercase border-2 border-orange-100">DADOS INCOMPLETOS</span>
                                                    )}
                                                </div>

                                                <div className="p-6">
                                                    {/* Atributos */}
                                                    <div className="flex flex-wrap gap-4 mb-6 pb-6 border-b border-gray-50">
                                                        <div className="flex flex-col">
                                                            <span className="text-[9px] font-bold text-blue-500 uppercase tracking-widest">Disciplina</span>
                                                            <span className="text-xs font-black text-gray-800">{item.full_data?.discipline}</span>
                                                        </div>
                                                        <div className="flex flex-col">
                                                            <span className="text-[9px] font-bold text-indigo-500 uppercase tracking-widest">Assunto</span>
                                                            <span className="text-xs font-black text-gray-800">{item.full_data?.topic || 'Geral'}</span>
                                                        </div>
                                                        {item.full_data?.language && (
                                                            <div className="flex flex-col">
                                                                <span className="text-[9px] font-bold text-purple-500 uppercase tracking-widest">Língua</span>
                                                                <span className="text-xs font-black text-gray-800">{item.full_data?.language}</span>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Enunciado e Imagens */}
                                                    <div className="prose prose-sm max-w-none text-gray-800">
                                                        {/* Imagens do Enunciado */}
                                                        <div className="flex flex-wrap gap-4 mb-4">
                                                            {extractImages(item.full_data?.context).map((img, i) => (
                                                                <div key={i} className="relative group/img">
                                                                    <img src={img} className="max-h-64 rounded-xl border-2 border-gray-100 shadow-sm hover:scale-105 transition-transform duration-300" alt="Enunciado" />
                                                                    <div className="absolute top-2 right-2 bg-black/50 text-white text-[8px] font-bold px-2 py-1 rounded-md opacity-0 group-hover/img:opacity-100 transition-opacity">ENUNCIADO</div>
                                                                </div>
                                                            ))}
                                                        </div>

                                                        <div className="font-bold text-lg leading-relaxed mb-4">{item.full_data?.context?.replace(/!\[.*?\]\(.*?\)/g, '')}</div>

                                                        {item.full_data?.alternativesIntroduction && (
                                                            <div className="text-sm font-medium text-gray-600 bg-gray-50 p-3 rounded-xl border-l-4 border-gray-200 mb-6">
                                                                {item.full_data?.alternativesIntroduction}
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Alternativas */}
                                                    <div className="mt-8 space-y-3">
                                                        <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Alternativas</span>
                                                        {item.full_data?.alternatives?.map((alt: any, i: number) => (
                                                            <div key={i} className={`flex items-start gap-4 p-4 rounded-2xl border-2 transition-all duration-200 ${alt.isCorrect ? 'bg-green-50/50 border-green-200 shadow-sm' : 'bg-white border-gray-50 hover:border-gray-100'}`}>

                                                                <div className={`flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center font-black text-sm transition-colors ${alt.isCorrect ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'bg-gray-100 text-gray-500'}`}>
                                                                    <span>{alt.letter}</span>
                                                                </div>

                                                                <div className="flex-1">
                                                                    <div className="flex flex-col gap-3">
                                                                        <p className="text-sm font-bold text-gray-800 pt-1">{alt.text}</p>

                                                                        {alt.file && (
                                                                            <img src={alt.file} className="max-h-48 w-fit rounded-lg border border-gray-100 shadow-sm mt-2" alt="Alternativa" />
                                                                        )}
                                                                    </div>
                                                                </div>

                                                                {alt.isCorrect && (
                                                                    <div className="flex-shrink-0 text-green-600">
                                                                        <svg className="w-6 h-6 shadow-sm" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"></path></svg>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                            <div className="bg-gray-50 px-6 py-8 flex justify-center border-t border-gray-100">
                                <button type="button" className="group flex items-center gap-4 px-12 py-4 bg-gray-900 border-2 border-gray-900 rounded-3xl shadow-xl shadow-gray-200 text-sm font-black text-white hover:bg-black hover:scale-105 transition-all duration-300 focus:outline-none" onClick={() => setShowIgnoredModal(false)}>
                                    <span>FECHAR DOCUMENTO</span>
                                    <kbd className="hidden sm:inline-block px-2 py-0.5 text-[10px] font-bold bg-gray-700 text-gray-300 border border-gray-600 rounded-lg">ESC</kbd>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
