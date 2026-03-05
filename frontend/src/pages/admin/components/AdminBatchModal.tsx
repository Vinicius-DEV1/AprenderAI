import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../../api/axios';

interface BatchModalProps {
    isOpen: boolean;
    onClose: () => void;
    pendingCount: number;
    onBatchStarted: (batchId: string) => void;
}

export default function AdminBatchModal({ isOpen, onClose, pendingCount, onBatchStarted }: BatchModalProps) {
    const queryClient = useQueryClient();
    const [step, setStep] = useState<'config' | 'preview' | 'processing'>('config');
    const [quantity, setQuantity] = useState(10);
    const [type, setType] = useState('complete');
    const [model, setModel] = useState('gpt-4o');
    const [chunkSize, setChunkSize] = useState(20);
    const [delaySeconds, setDelaySeconds] = useState(15);
    const [reprocess, setReprocess] = useState(false);
    const [previewQuestions, setPreviewQuestions] = useState<any[]>([]);

    const [batchId, setBatchId] = useState<string | null>(null);
    const [progress, setProgress] = useState<any>(null);
    const [lastProgressRecord, setLastProgressRecord] = useState<{ processed: number; time: number } | null>(null);
    const [etaSeconds, setEtaSeconds] = useState<number | null>(null);

    // Persistência com Servidor (Recuperação no F5)
    useEffect(() => {
        if (isOpen && !batchId) {
            const checkActiveBatch = async () => {
                try {
                    const res = await api.get('/api/v1/admin/triage/active');
                    if (res.data.success && res.data.batch_id) {
                        setBatchId(res.data.batch_id);
                        setStep('processing');
                        onBatchStarted(res.data.batch_id); // Notify parent component if needed
                    }
                } catch (error) {
                    console.error('Erro ao buscar lote ativo:', error);
                }
            };
            checkActiveBatch();
        }
    }, [isOpen, batchId]);

    // Fetch dynamically configured models from API Keys vault
    const { data: availableModels = [] } = useQuery({
        queryKey: ['admin-api-keys-models'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-keys');
            // Coleta modelos cadastrados das chaves ativas
            const vault = res.data.vault || [];
            const models = new Set<string>();
            vault.forEach((k: any) => {
                if (k.preferred_model) models.add(k.preferred_model);
                if (k.model) models.add(k.model);
            });
            // Tenta também o capabilities_grid
            const grid = res.data.capabilities_grid || {};
            Object.values(grid).forEach((keys: any) => {
                keys.forEach((k: any) => {
                    if (k.preferred_model) models.add(k.preferred_model);
                    if (k.model) models.add(k.model);
                });
            });
            return Array.from(models);
        }
    });

    const noModelsConfigured = availableModels.length === 0;

    // Auto-select first model when available
    useEffect(() => {
        if (availableModels.length > 0 && !availableModels.includes(model)) {
            setModel(availableModels[0]);
        }
    }, [availableModels]);

    const previewMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/api/v1/admin/triage/preview', {
                quantity,
                type: type === 'complete' ? 'both' : type,
            });
            return res.data;
        },
        onSuccess: (data) => {
            setPreviewQuestions(data.questions);
            setStep('preview');
        }
    });

    const startMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/api/v1/admin/triage/start', {
                quantity: previewQuestions.length,
                type: type === 'complete' ? 'both' : type,
                model,
                chunk_size: chunkSize,
                delay_seconds: delaySeconds,
                reprocess,
                question_ids: previewQuestions.map(q => q.id)
            });
            return res.data;
        },
        onSuccess: (data) => {
            setBatchId(data.batch_id);
            setStep('processing');
            onBatchStarted(data.batch_id);
        }
    });

    // ───────────────────────────────────────────────────────
    // Polling do Status com React Query (Muito mais resiliente)
    // ───────────────────────────────────────────────────────
    const { data: queryProgress } = useQuery({
        queryKey: ['admin-batch-status', batchId],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/triage/${batchId}/status`);
            return res.data;
        },
        enabled: !!(batchId && step === 'processing'),
        refetchInterval: (queryData: any) => {
            if (!queryData) return 2000;
            if (['completed', 'failed', 'cancelled'].includes(queryData.status)) {
                return false;
            }
            return 2000;
        },
        retry: 5,
        retryDelay: 2000,
    });

    // Sincroniza o progress da query com o estado local para manter a lógica de cálculo de ETA
    useEffect(() => {
        if (queryProgress) {
            setProgress(queryProgress);

            // ETA Calculation
            if (queryProgress.status === 'processing' && queryProgress.total > 0 && queryProgress.processed > 0) {
                const now = Date.now();
                if (lastProgressRecord) {
                    if (queryProgress.processed > lastProgressRecord.processed) {
                        const itemsDelta = queryProgress.processed - lastProgressRecord.processed;
                        const timeDeltaSeconds = (now - lastProgressRecord.time) / 1000;
                        const secondsPerItem = timeDeltaSeconds / itemsDelta;
                        const remainingItems = queryProgress.total - (queryProgress.processed + queryProgress.errors);
                        setEtaSeconds(Math.round(remainingItems * secondsPerItem));
                        setLastProgressRecord({ processed: queryProgress.processed, time: now });
                    }
                } else {
                    setLastProgressRecord({ processed: queryProgress.processed, time: now });
                }
            }

            // Finalização Automática
            if (queryProgress.status === 'completed' && queryProgress.errors === 0) {
                const timer = setTimeout(() => {
                    handleFinalize();
                }, 3000);
                return () => clearTimeout(timer);
            }
        }
    }, [queryProgress]);

    const handleFinalize = () => {
        onClose();
        setBatchId(null);
        setProgress(null);
        setLastProgressRecord(null);
        setEtaSeconds(null);
        setStep('config');
    };

    const handleMinimize = () => {
        onClose(); // Just close visually, keep background
    };

    const handleCancelAndRevert = async () => {
        if (!window.confirm("Isso interromperá o lote imediatamente e reverterá as questões já modificadas de volta para o estado original. Tem certeza?")) {
            return;
        }

        try {
            await api.post(`/api/v1/admin/triage/${batchId}/cancel-and-revert`);
            alert("Lote cancelado e revertido com sucesso.");
            queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
            handleFinalize();
        } catch (error: any) {
            alert(error.response?.data?.message || 'Erro ao cancelar o lote.');
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4">
                <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onClick={batchId ? handleMinimize : onClose}></div>

                <div className="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]">

                    {/* Header */}
                    <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <span className="text-xl">{batchId ? '⏳' : '🤖'}</span>
                            <h3 className="text-xl font-black text-gray-900">
                                {batchId ? 'Processando Lote...' : step === 'config' ? 'Configurar Lote de IA' : 'Pré-visualização do Lote'}
                            </h3>
                        </div>
                        {batchId ? (
                            <button onClick={handleMinimize} className="text-gray-400 hover:text-gray-600 font-bold p-2 bg-white rounded-full border border-gray-200 shadow-sm" title="Minimizar para plano de fundo">➖ Ocultar (Segundo Plano)</button>
                        ) : (
                            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 font-bold">✕</button>
                        )}
                    </div>

                    {/* Body */}
                    <div className="p-6 overflow-y-auto flex-grow">
                        {batchId ? (
                            <div className="flex flex-col items-center justify-center py-10 space-y-6">
                                <div className={`text-6xl ${progress?.status === 'failed' || progress?.status === 'cancelled' ? '' : 'animate-bounce'}`}>
                                    {progress?.status === 'failed' ? '❌' : progress?.status === 'cancelled' ? '🛑' : '🚀'}
                                </div>
                                <div className="w-full max-w-md bg-gray-100 h-4 rounded-full overflow-hidden relative">
                                    {progress?.status === 'processing' && (
                                        <div className="absolute inset-0 bg-indigo-100 animate-pulse"></div>
                                    )}
                                    <div
                                        className={`absolute top-0 left-0 h-full transition-all duration-500 ease-out shadow-inner ${progress?.status === 'failed' ? 'bg-red-500' : progress?.status === 'cancelled' ? 'bg-amber-500' : 'bg-gradient-to-r from-indigo-500 to-purple-600'}`}
                                        style={{ width: `${progress && progress.total > 0 ? ((progress.processed + progress.errors) / progress.total) * 100 : 0}%` }}
                                    ></div>
                                </div>
                                <div className="text-center w-full">
                                    <p className={`font-black text-4xl mb-1 tracking-tight ${progress?.status === 'failed' ? 'text-red-600' : progress?.status === 'cancelled' ? 'text-amber-500' : 'text-gray-900'}`}>
                                        {progress ? `${progress.processed + progress.errors} / ${progress.total}` : 'Iniciando...'}
                                    </p>

                                    {progress?.status === 'processing' && etaSeconds !== null && (
                                        <p className="text-xs font-bold tracking-widest text-indigo-500 mb-3 animate-pulse">
                                            ⏳ FALTAM ~{etaSeconds > 60 ? `${Math.floor(etaSeconds / 60)}m ` : ''}{etaSeconds % 60}s
                                        </p>
                                    )}

                                    {/* Contador de Tokens */}
                                    {progress && progress.input_tokens !== undefined && progress.output_tokens !== undefined && (
                                        <div className="flex justify-center gap-3 mb-6">
                                            <div className="bg-blue-50 border border-blue-100 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm">
                                                <span className="text-[10px] font-black text-blue-400 uppercase tracking-wider">Input Tokens</span>
                                                <span className="text-sm font-black text-blue-600">{progress.input_tokens.toLocaleString()}</span>
                                            </div>
                                            <div className="bg-purple-50 border border-purple-100 px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm">
                                                <span className="text-[10px] font-black text-purple-400 uppercase tracking-wider">Output Tokens</span>
                                                <span className="text-sm font-black text-purple-600">{progress.output_tokens.toLocaleString()}</span>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex flex-col items-center gap-1">
                                        <p className={`text-xs font-bold uppercase tracking-widest ${progress?.status === 'failed' ? 'text-red-500' : 'text-indigo-500'}`}>
                                            {progress?.message || (batchId ? 'Conectando ao rastreador...' : 'Aguardando servidor...')}
                                        </p>
                                        {progress?.last_error && (
                                            <div className="mt-3 p-3 bg-red-50 border border-red-100 rounded-xl max-w-md mx-auto">
                                                <p className="text-[10px] text-red-600 font-bold leading-relaxed">
                                                    {progress.last_error}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : step === 'config' ? (
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">Quantidade Máxima (Disponível: {pendingCount})</label>
                                        <input
                                            type="number"
                                            value={quantity}
                                            onChange={(e) => setQuantity(parseInt(e.target.value))}
                                            max={pendingCount}
                                            className="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">Modelo de IA</label>
                                        {noModelsConfigured ? (
                                            <div className="w-full px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-xs font-bold flex items-center gap-2">
                                                ⚠️ Nenhum modelo configurado.{' '}
                                                <a href="/admin/api-keys" className="underline hover:text-amber-900">Cadastre uma chave de API</a>
                                            </div>
                                        ) : (
                                            <select
                                                value={model}
                                                onChange={(e) => setModel(e.target.value)}
                                                className="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold"
                                            >
                                                {availableModels.map((m) => (
                                                    <option key={m} value={m}>{m}</option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">Tamanho do Lote (Chunk)</label>
                                        <input
                                            type="number"
                                            value={chunkSize}
                                            onChange={(e) => setChunkSize(parseInt(e.target.value) || 5)}
                                            min={1}
                                            className="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold"
                                        />
                                        <p className="text-[10px] text-gray-400 mt-1 font-bold italic">Questões processadas por requisição API.</p>
                                    </div>
                                    <div className="flex items-center gap-3 pt-6">
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={reprocess}
                                                onChange={(e) => setReprocess(e.target.checked)}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                            <span className="ml-3 text-xs font-black uppercase text-gray-400">Forçar Sobrescrita</span>
                                        </label>
                                    </div>
                                    <div className="sm:col-span-2 space-y-2 mt-2">
                                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                            <span>⏱️ Intervalo entre Blocos (segundos)</span>
                                            <span className="normal-case font-bold text-[9px] text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">Seguro p/ Gemini Free: 15s+</span>
                                        </label>
                                        <div className="flex items-center gap-4 bg-gray-50/50 p-3 rounded-2xl border border-gray-100">
                                            <input
                                                type="range"
                                                min="0"
                                                max="60"
                                                step="5"
                                                value={delaySeconds}
                                                onChange={(e) => setDelaySeconds(parseInt(e.target.value))}
                                                className="flex-grow accent-indigo-600 h-1.5 bg-gray-200 rounded-full appearance-none cursor-pointer"
                                            />
                                            <div className="w-14 text-center py-1.5 bg-white border border-gray-100 rounded-xl font-black text-indigo-600 text-sm shadow-sm">
                                                {delaySeconds}s
                                            </div>
                                        </div>
                                        <p className="text-[9px] text-gray-400 font-bold italic leading-tight">
                                            Atrasa o início de cada bloco para evitar bloqueios de quota do Google.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-black uppercase text-gray-400 mb-4">Ações da IA</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {[
                                            { id: 'complete', label: '🚀 Completo', desc: 'Dificuldade, explicação e taxonomia' },
                                            { id: 'difficulty', label: '⚡ Apenas Dificuldade', desc: 'Gera nível e raciocínio' },
                                            { id: 'explanation', label: '📝 Apenas Explicação', desc: 'Gera texto explicativo' },
                                            { id: 'classification', label: '🏷️ Apenas Classificar', desc: 'Matéria e assunto' }
                                        ].map(opt => (
                                            <label
                                                key={opt.id}
                                                className={`relative flex cursor-pointer rounded-xl border p-4 shadow-sm transition-all ${type === opt.id ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-100 bg-white hover:border-gray-200'
                                                    }`}
                                            >
                                                <input type="radio" value={opt.id} checked={type === opt.id} onChange={() => setType(opt.id)} className="sr-only" />
                                                <div className="flex flex-col">
                                                    <span className="block text-sm font-black text-gray-900">{opt.label}</span>
                                                    <span className="block text-[10px] text-gray-500 mt-1 uppercase font-bold">{opt.desc}</span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col h-full">
                                <p className="text-sm text-gray-500 mb-4 font-medium italic">
                                    Revise as questões que serão processadas. Remova as que não deseja no lote.
                                </p>
                                <div className="border border-gray-100 rounded-xl overflow-hidden bg-white shadow-inner">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-gray-50 border-b border-gray-100 sticky top-0">
                                            <tr>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase w-16">ID</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase">Enunciado</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase w-32">Matéria</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase text-right w-24">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50 font-medium">
                                            {previewQuestions.map((q, idx) => (
                                                <tr key={q.id} className="hover:bg-red-50/30 transition-colors">
                                                    <td className="px-4 py-3 text-gray-400 font-mono">#{q.id}</td>
                                                    <td className="px-4 py-3 text-gray-700 max-w-xs truncate">{q.statement}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{q.subject}</span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <button
                                                            onClick={() => setPreviewQuestions(prev => prev.filter((_, i) => i !== idx))}
                                                            className="text-red-400 hover:text-red-600 text-[10px] font-black uppercase"
                                                        >
                                                            Remover
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    {batchId ? (
                        <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                            {(progress?.status === 'completed' || progress?.status === 'failed' || progress?.status === 'cancelled') ? (
                                <button
                                    onClick={handleFinalize}
                                    className="px-8 py-2.5 bg-gray-900 text-white rounded-xl font-bold text-sm hover:bg-black transition shadow-lg"
                                >
                                    Fechar Lote
                                </button>
                            ) : (
                                <button
                                    onClick={handleCancelAndRevert}
                                    className="px-8 py-2.5 bg-white border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-50 transition shadow-lg"
                                >
                                    🛑 Cancelar e Reverter
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                            <button
                                onClick={onClose}
                                className="px-6 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-gray-50 transition"
                            >
                                Cancelar
                            </button>

                            {step === 'config' ? (
                                <button
                                    onClick={() => previewMutation.mutate()}
                                    disabled={previewMutation.isPending || pendingCount === 0}
                                    className="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 disabled:opacity-50"
                                >
                                    {previewMutation.isPending ? 'Carregando...' : 'Ver Prévia →'}
                                </button>
                            ) : (
                                <div className="flex gap-3">
                                    <button
                                        onClick={() => setStep('config')}
                                        className="px-6 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl font-bold text-sm hover:bg-indigo-100 transition"
                                    >
                                        ← Voltar
                                    </button>
                                    <button
                                        onClick={() => startMutation.mutate()}
                                        disabled={startMutation.isPending || previewQuestions.length === 0}
                                        className="px-8 py-2.5 bg-green-600 text-white rounded-xl font-bold text-sm hover:bg-green-700 transition shadow-lg shadow-green-100 disabled:opacity-50"
                                    >
                                        {startMutation.isPending ? 'Iniciando...' : '🚀 Confirmar Lote'}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
