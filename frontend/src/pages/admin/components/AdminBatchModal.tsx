import { useState, useEffect, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../../api/axios';
import { useUIStore } from '../../../stores/uiStore';
import AdminQuestionViewModal from './AdminQuestionViewModal';

interface BatchModalProps {
    isOpen: boolean;
    onClose: () => void;
    pendingCount: number;
    onBatchStarted: (batchId: string) => void;
}

export default function AdminBatchModal({ isOpen, onClose, pendingCount, onBatchStarted }: BatchModalProps) {
    const queryClient = useQueryClient();
    const ui = useUIStore();
    const [step, setStep] = useState<'config' | 'preview' | 'processing'>('config');
    const [quantity, setQuantity] = useState(10);
    const [type, setType] = useState('complete');
    const [chunkSize, setChunkSize] = useState(5);
    const [delaySeconds, setDelaySeconds] = useState(15);
    const [reprocess, setReprocess] = useState(false);
    const [previewQuestions, setPreviewQuestions] = useState<any[]>([]);

    const [batchId, setBatchId] = useState<string | null>(null);
    const [progress, setProgress] = useState<any>(null);
    const [lastProgressRecord, setLastProgressRecord] = useState<{ processed: number; time: number } | null>(null);
    const [etaSeconds, setEtaSeconds] = useState<number | null>(null);
    // Chunk phase live timers
    const [chunkElapsed, setChunkElapsed] = useState(0);
    const [delayCountdown, setDelayCountdown] = useState<number | null>(null);
    const chunkTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const delayTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const [viewQuestionId, setViewQuestionId] = useState<number | null>(null);
    const [isQuestionModalOpen, setIsQuestionModalOpen] = useState(false);

    const { data: batchDetails } = useQuery({
        queryKey: ['admin-batch-details', batchId],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/triage/${batchId}/details`);
            return res.data;
        },
        enabled: !!(batchId && progress?.status === 'completed'),
    });

    // Persistência com Servidor (Recuperação no F5)
    // Ao abrir o modal, prioriza o batchId salvo na sessão atual.
    // Só consulta o servidor se não há batchId na sessão — evita carregar lotes anteriores.
    useEffect(() => {
        if (isOpen && !batchId) {
            const sessionBatchId = sessionStorage.getItem('active_batch_id');
            if (sessionBatchId) {
                // Já temos um lote desta sessão — usa ele direto.
                setBatchId(sessionBatchId);
                setStep('processing');
                onBatchStarted(sessionBatchId);
            } else {
                // Sem lote na sessão: verifica se há algum em processamento no servidor.
                const checkActiveBatch = async () => {
                    try {
                        const res = await api.get('/api/v1/admin/triage/active');
                        if (res.data.success && res.data.batch_id && res.data.status === 'processing') {
                            // Só recupera automaticamente se ainda estiver em processamento E não tiver sido dispensado
                            if (!useUIStore.getState().dismissedBatches.includes(res.data.batch_id)) {
                                setBatchId(res.data.batch_id);
                                setStep('processing');
                                onBatchStarted(res.data.batch_id);
                                sessionStorage.setItem('active_batch_id', res.data.batch_id);
                            }
                        }
                    } catch (error) {
                        console.error('Erro ao buscar lote ativo:', error);
                    }
                };
                checkActiveBatch();
            }
        }
    }, [isOpen, batchId]);

    // Fetch triage-configured API keys (read-only — failover router selects automatically)
    const { data: triageKeysData } = useQuery({
        queryKey: ['admin-triage-active-keys'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/triage/active-keys');
            return res.data.keys as Array<{
                id: number; name: string; provider: string;
                model: string; status: string; is_primary: boolean;
            }>;
        },
        staleTime: 30000,
    });
    const triageKeys = triageKeysData || [];
    const primaryKey = triageKeys.find(k => k.is_primary) || triageKeys[0];

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
                chunk_size: chunkSize,
                delay_seconds: delaySeconds,
                reprocess,
                question_ids: previewQuestions.map(q => q.id)
            });
            return res.data;
        },
        onSuccess: (data) => {
            setBatchId(data.batch_id);
            // Salva o batchId na sessão atual para evitar carregar lotes anteriores.
            sessionStorage.setItem('active_batch_id', data.batch_id);
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

            // Chunk Phase Timers
            const chunkStatus = queryProgress.chunk_status;
            if (chunkStatus) {
                if (chunkStatus.phase === 'processing') {
                    // Clear any delay timer
                    if (delayTimerRef.current) { clearInterval(delayTimerRef.current); delayTimerRef.current = null; }
                    setDelayCountdown(null);
                    // Start or continue the chunk elapsed timer
                    if (!chunkTimerRef.current) {
                        const startedAt = chunkStatus.chunk_started_at ? new Date(chunkStatus.chunk_started_at).getTime() : Date.now();
                        setChunkElapsed(Math.floor((Date.now() - startedAt) / 1000));
                        chunkTimerRef.current = setInterval(() => {
                            setChunkElapsed(Math.floor((Date.now() - startedAt) / 1000));
                        }, 1000);
                    }
                } else if (chunkStatus.phase === 'delay') {
                    // Clear chunk timer
                    if (chunkTimerRef.current) { clearInterval(chunkTimerRef.current); chunkTimerRef.current = null; }
                    // Start or update delay countdown
                    const endsAt = chunkStatus.delay_ends_at ? new Date(chunkStatus.delay_ends_at).getTime() : Date.now();
                    const remaining = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
                    setDelayCountdown(remaining);
                    if (!delayTimerRef.current) {
                        delayTimerRef.current = setInterval(() => {
                            const rem = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
                            setDelayCountdown(rem);
                            if (rem <= 0 && delayTimerRef.current) { clearInterval(delayTimerRef.current); delayTimerRef.current = null; }
                        }, 1000);
                    }
                } else {
                    // idle/skipped — clear everything
                    if (chunkTimerRef.current) { clearInterval(chunkTimerRef.current); chunkTimerRef.current = null; }
                    if (delayTimerRef.current) { clearInterval(delayTimerRef.current); delayTimerRef.current = null; }
                    setDelayCountdown(null);
                }
            }
        }
    }, [queryProgress]);

    const handleFinalize = () => {
        // Limpa o batchId da sessão ao finalizar/fechar o modal.
        sessionStorage.removeItem('active_batch_id');
        if (batchId) {
            ui.dismissBatch(batchId);
        }
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
                <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onClick={() => {
                    const isTerminal = progress?.status === 'completed' || progress?.status === 'failed' || progress?.status === 'cancelled';
                    if (isTerminal || !batchId) {
                        handleFinalize(); // Limpa sessionStorage e reseta tudo
                    } else {
                        handleMinimize(); // Lote ativo: apenas oculta visualmente
                    }
                }}></div>

                <div className="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]">

                    {/* Header */}
                    <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <span className="text-xl">
                                {batchId
                                    ? (progress?.status === 'completed' ? '✅' : progress?.status === 'failed' ? '❌' : progress?.status === 'cancelled' ? '🛑' : progress?.status === 'retrying' ? '🔄' : '⏳')
                                    : '🤖'
                                }
                            </span>
                            <h3 className="text-xl font-black text-gray-900">
                                {batchId
                                    ? (progress?.status === 'completed' ? 'Lote Concluído!' : progress?.status === 'failed' ? 'Lote com Falha' : progress?.status === 'cancelled' ? 'Lote Cancelado' : progress?.status === 'retrying' ? 'Re-tentativa Automática' : 'Processando Lote...')
                                    : step === 'config' ? 'Configurar Lote de IA' : 'Pré-visualização do Lote'
                                }
                            </h3>
                        </div>
                        {batchId ? (
                            <button
                                onClick={() => {
                                    const isTerminal = progress?.status === 'completed' || progress?.status === 'failed' || progress?.status === 'cancelled';
                                    if (isTerminal) handleFinalize();
                                    else handleMinimize();
                                }}
                                className="text-gray-400 hover:text-gray-600 font-bold p-2 bg-white rounded-full border border-gray-200 shadow-sm"
                                title={progress?.status === 'processing' ? "Minimizar para plano de fundo" : "Fechar"}
                            >
                                {progress?.status === 'processing' || progress?.status === 'retrying' ? '➖ Ocultar (Segundo Plano)' : '✕ Fechar'}
                            </button>
                        ) : (
                            <button onClick={handleFinalize} className="text-gray-400 hover:text-gray-600 font-bold">✕</button>
                        )}
                    </div>

                    {/* Body */}
                    <div className="p-6 overflow-y-auto flex-grow">
                        {batchId ? (
                            <div className="flex flex-col items-center justify-center py-10 space-y-6">
                                <div className={`text-6xl ${progress?.status === 'completed' ? '' :
                                    progress?.status === 'failed' || progress?.status === 'cancelled' ? '' : 'animate-bounce'
                                    }`}>
                                    {progress?.status === 'failed' ? '❌' :
                                        progress?.status === 'cancelled' ? '🛑' :
                                            progress?.status === 'completed' ? '✅' :
                                                progress?.status === 'retrying' ? '🔄' : '🚀'}
                                </div>
                                <div className="w-full max-w-md bg-gray-100 h-4 rounded-full overflow-hidden relative">
                                    {progress?.status === 'processing' && (
                                        <div className="absolute inset-0 bg-indigo-100 animate-pulse"></div>
                                    )}
                                    <div
                                        className={`absolute top-0 left-0 h-full transition-all duration-500 ease-out shadow-inner ${progress?.status === 'failed' ? 'bg-red-500' :
                                            progress?.status === 'cancelled' ? 'bg-amber-500' :
                                                progress?.status === 'retrying' ? 'bg-indigo-400 animate-pulse' :
                                                    progress?.status === 'completed' ? 'bg-gradient-to-r from-green-400 to-emerald-600' :
                                                        'bg-gradient-to-r from-indigo-500 to-purple-600'
                                            }`}
                                        style={{ width: `${progress && progress.total > 0 ? ((progress.processed + progress.errors) / progress.total) * 100 : 0}%` }}
                                    ></div>
                                </div>
                                <div className="text-center w-full">
                                    <p className={`font-black text-4xl mb-1 tracking-tight ${progress?.status === 'failed' ? 'text-red-600' : progress?.status === 'cancelled' ? 'text-amber-500' : 'text-gray-900'}`}>
                                        {progress
                                            ? `${progress.processed + progress.errors} / ${progress.total}`
                                            : 'Iniciando...'}
                                    </p>

                                    {progress?.status === 'processing' && etaSeconds !== null && (
                                        <p className="text-xs font-bold tracking-widest text-indigo-500 mb-3 animate-pulse">
                                            ⏳ FALTAM ~{etaSeconds > 60 ? `${Math.floor(etaSeconds / 60)}m ` : ''}{etaSeconds % 60}s
                                        </p>
                                    )}

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
                                    {progress?.status === 'completed' && progress.stats && (
                                        <div className="flex flex-wrap justify-center gap-3 mb-6 animate-in fade-in slide-in-from-top-4 duration-1000">
                                            {progress.stats.difficulty > 0 && (
                                                <div className="bg-amber-50 border border-amber-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">⚡</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-amber-400 uppercase tracking-widest leading-none mb-1">Dificuldade</span>
                                                        <span className="text-base font-black text-amber-600">{progress.stats.difficulty} <span className="text-[10px] opacity-70">setadas</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.explanation > 0 && (
                                                <div className="bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">📝</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-emerald-400 uppercase tracking-widest leading-none mb-1">Explicação</span>
                                                        <span className="text-base font-black text-emerald-600">{progress.stats.explanation} <span className="text-[10px] opacity-70">geradas</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.subjects > 0 && (
                                                <div className="bg-blue-50 border border-blue-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">📚</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-blue-400 uppercase tracking-widest leading-none mb-1">Disciplinas</span>
                                                        <span className="text-base font-black text-blue-600">{progress.stats.subjects} <span className="text-[10px] opacity-70">vínculos</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.topics > 0 && (
                                                <div className="bg-indigo-50 border border-indigo-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">🏷️</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-indigo-400 uppercase tracking-widest leading-none mb-1">Assuntos</span>
                                                        <span className="text-base font-black text-indigo-600">{progress.stats.topics} <span className="text-[10px] opacity-70">vínculos</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.sent_to_review > 0 && (
                                                <div className="bg-red-50 border border-red-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">🚫</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-red-500 uppercase tracking-widest leading-none mb-1">P/ Revisão</span>
                                                        <span className="text-base font-black text-red-700">{progress.stats.sent_to_review} <span className="text-[10px] opacity-70">retidas</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.low_quality > 0 && (
                                                <div className="bg-pink-50 border border-pink-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">👎</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-pink-500 uppercase tracking-widest leading-none mb-1">Baixa Qualid.</span>
                                                        <span className="text-base font-black text-pink-700">{progress.stats.low_quality} <span className="text-[10px] opacity-70">detectadas</span></span>
                                                    </div>
                                                </div>
                                            )}
                                            {progress.stats.approved > 0 && (
                                                <div className="bg-green-50 border border-green-100 px-4 py-2 rounded-2xl flex items-center gap-3 shadow-sm hover:scale-105 transition-transform cursor-default">
                                                    <span className="text-2xl">✅</span>
                                                    <div className="flex flex-col items-start">
                                                        <span className="text-[9px] font-black text-green-500 uppercase tracking-widest leading-none mb-1">Aprovadas</span>
                                                        <span className="text-base font-black text-green-700">{progress.stats.approved} <span className="text-[10px] opacity-70">liberadas</span></span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Detalhamento das Questões Processadas */}
                                    {progress?.status === 'completed' && batchDetails?.items && batchDetails.items.length > 0 && (
                                        <div className="mt-8 w-full border-t border-gray-100 pt-8">
                                            <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center justify-center gap-2">
                                                <span className="w-8 h-px bg-gray-100"></span>
                                                Detalhamento das Alterações
                                                <span className="w-8 h-px bg-gray-100"></span>
                                            </h4>

                                            <div className="grid grid-cols-1 gap-4 max-w-3xl mx-auto">
                                                {batchDetails.items.map((item: any) => (
                                                    <div key={item.id} className="bg-white border border-gray-100 rounded-3xl p-5 shadow-sm hover:shadow-md transition group">
                                                        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                                            <div className="flex-1">
                                                                <div className="flex items-center gap-2 mb-2">
                                                                    <span className="px-2 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-black uppercase tracking-widest">#{item.question_id}</span>
                                                                    <span className="text-xs font-bold text-gray-400 line-clamp-1 italic">"{item.statement}"</span>
                                                                </div>

                                                                <div className="flex flex-wrap gap-4 mt-3">
                                                                    {/* Dificuldade Diff */}
                                                                    {item.before?.difficulty !== item.after?.difficulty && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Dificuldade</span>
                                                                            <div className="flex items-center gap-1.5 bg-amber-50 px-2 py-1 rounded-lg border border-amber-100">
                                                                                <span className="text-[10px] font-bold text-amber-400 line-through opacity-50 uppercase">{item.before?.difficulty || 'N/A'}</span>
                                                                                <span className="text-amber-400 text-xs">→</span>
                                                                                <span className="text-[11px] font-black text-amber-600 uppercase">{item.after?.difficulty}</span>
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {/* Explicação Indicator */}
                                                                    {item.after?.explanation && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Explicação</span>
                                                                            <div className="flex items-center gap-1 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100">
                                                                                <span className="text-[10px] font-bold text-emerald-600">✅ Gerada</span>
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {/* Triage Quality Score */}
                                                                    {item.after?.quality_score !== undefined && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Qualidade</span>
                                                                            <div className={`flex items-center gap-1 px-2 py-1 rounded-lg border ${item.after.quality_score >= 90 ? 'bg-green-50 border-green-100 text-green-700' :
                                                                                item.after.quality_score >= 60 ? 'bg-amber-50 border-amber-100 text-amber-700' :
                                                                                    'bg-red-50 border-red-100 text-red-700'
                                                                                }`}>
                                                                                <span className="text-[10px] font-black">{item.after.quality_score}</span>
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {/* Issues detected by AI */}
                                                                    {item.after?.issues && item.after.issues.length > 0 && (
                                                                        <div className="flex flex-col">
                                                                            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Problemas</span>
                                                                            <div className="flex flex-wrap gap-1">
                                                                                {item.after.issues.map((issue: string) => (
                                                                                    <span key={issue} className="px-1.5 py-0.5 bg-red-100 text-red-600 rounded text-[9px] font-black uppercase tracking-tight">
                                                                                        {issue}
                                                                                    </span>
                                                                                ))}
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {/* Matérias Indicator */}
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
                                                                className="px-4 py-3 bg-indigo-50 text-indigo-600 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition shadow-sm group-hover:scale-105 w-full md:w-auto text-center"
                                                            >
                                                                Ver Questão 👁️
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex flex-col items-center gap-1">
                                        <p className={`text-xs font-bold uppercase tracking-widest ${progress?.status === 'failed' ? 'text-red-500' : progress?.status === 'completed' ? 'text-green-600' : 'text-indigo-500'}`}>
                                            {progress?.message || (batchId ? 'Conectando ao rastreador...' : 'Aguardando servidor...')}
                                        </p>

                                        {/* Detalhes de Erro e Warning */}
                                        {(progress?.last_error || (progress?.errors_log && progress.errors_log.length > 0)) && (
                                            <div className="mt-4 w-full max-w-lg">
                                                <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden text-left">
                                                    <div className="px-4 py-2 bg-gray-50 border-b border-gray-100 flex justify-between items-center font-black text-[9px] uppercase tracking-widest text-gray-400">
                                                        <span>Registro de Incidentes</span>
                                                        <span className="text-red-400">{progress?.errors || 0} erros detectados</span>
                                                    </div>
                                                    <div className="max-h-40 overflow-y-auto p-4 space-y-2">
                                                        {progress?.last_error && !progress.errors_log?.some((l: any) => l.error === progress.last_error) && (
                                                            <p className="text-[10px] text-red-600 font-bold leading-relaxed flex gap-2">
                                                                <span className="flex-shrink-0">🚫</span>
                                                                {progress.last_error}
                                                            </p>
                                                        )}
                                                        {progress.errors_log?.slice().reverse().map((log: any, idx: number) => (
                                                            <p key={idx} className={`text-[10px] font-bold leading-relaxed flex gap-2 ${log.type === 'fatal' ? 'text-red-600' : 'text-amber-600'}`}>
                                                                <span className="flex-shrink-0">{log.type === 'fatal' ? '🚫' : '⚠️'}</span>
                                                                <span className="opacity-50 font-mono text-[9px] flex-shrink-0">{log.time.split(' ')[1]}</span>
                                                                {log.error}
                                                            </p>
                                                        ))}
                                                        {(!progress?.last_error && (!progress.errors_log || progress.errors_log.length === 0)) && (
                                                            <p className="text-[10px] text-gray-400 font-medium text-center py-4">Nenhum erro registrado até o momento.</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                        {/* Chunk Phase Status Panel */}
                                        {progress?.status === 'processing' && progress?.chunk_status && (
                                            <div className="mt-3 mx-auto max-w-xs w-full">
                                                {progress.chunk_status.phase === 'processing' && (
                                                    <div className="bg-indigo-50 border border-indigo-100 rounded-2xl px-5 py-3 flex items-center justify-between gap-3">
                                                        <div className="flex items-center gap-2 text-indigo-600">
                                                            <span className="animate-spin text-base">⚙️</span>
                                                            <span className="text-xs font-black">Chunk {progress.chunk_status.chunk_index}</span>
                                                        </div>
                                                        <span className="text-xs font-mono font-black text-indigo-700 bg-white px-3 py-1 rounded-full border border-indigo-100 shadow-sm">
                                                            ⏱ {chunkElapsed}s
                                                        </span>
                                                    </div>
                                                )}
                                                {progress.chunk_status.phase === 'delay' && (
                                                    <div className="bg-amber-50 border border-amber-100 rounded-2xl px-5 py-3 flex items-center justify-between gap-3">
                                                        <div className="flex flex-col">
                                                            <span className="text-xs font-black text-amber-600">⏳ Aguardando próximo lote</span>
                                                            <span className="text-[10px] text-amber-400 font-bold">Chunk {progress.chunk_status.chunk_index + 1} em breve...</span>
                                                        </div>
                                                        <span className="text-lg font-mono font-black text-amber-700 bg-white px-4 py-1 rounded-full border border-amber-100 shadow-sm min-w-[3.5rem] text-center">
                                                            {delayCountdown ?? 0}s
                                                        </span>
                                                    </div>
                                                )}
                                                {/* Active key info */}
                                                {progress.active_key && (
                                                    <div className="mt-2 flex items-center justify-center gap-2 text-[10px] text-gray-400 font-bold">
                                                        <span>🔑 {progress.active_key.name}</span>
                                                        <span className="bg-gray-100 px-2 py-0.5 rounded-full">{progress.active_key.model || progress.active_key.provider}</span>
                                                    </div>
                                                )}
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
                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">⚡ Roteamento de IA (Triagem)</label>
                                        {triageKeys.length === 0 ? (
                                            <div className="w-full px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-xs font-bold flex items-center gap-2">
                                                ⚠️ Nenhuma chave configurada para Triagem.
                                                <a href="/admin/api-keys" className="underline hover:text-amber-900">Cadastrar agora</a>
                                            </div>
                                        ) : (
                                            <div className="bg-indigo-50 border border-indigo-100 rounded-xl p-4 space-y-2">
                                                {primaryKey && (
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-xs font-black text-indigo-700">
                                                            🔑 {primaryKey.name}
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-[10px] font-bold text-gray-500 bg-white px-2 py-0.5 rounded-full border border-gray-100">{primaryKey.model || primaryKey.provider}</span>
                                                            <span className={`text-[9px] font-black uppercase px-2 py-0.5 rounded-full ${primaryKey.status === 'online' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}`}>{primaryKey.status}</span>
                                                        </div>
                                                    </div>
                                                )}
                                                {triageKeys.length > 1 && (
                                                    <p className="text-[10px] text-indigo-400 font-bold">
                                                        + {triageKeys.length - 1} chave{triageKeys.length > 2 ? 's' : ''} de failover configurada{triageKeys.length > 2 ? 's' : ''}
                                                    </p>
                                                )}
                                                <p className="text-[9px] text-indigo-300 font-bold italic">Selecionada automaticamente pelo roteador de failover. Configure em <a href="/admin/api-keys" className="underline">Chaves de API</a>.</p>
                                            </div>
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
                                <div className="flex items-center gap-4">
                                    <button
                                        onClick={handleFinalize}
                                        className="px-4 py-2 text-gray-400 hover:text-gray-600 text-[10px] font-black uppercase tracking-widest"
                                        title="Use apenas se o lote travar no servidor"
                                    >
                                        Ignorar e Sair (Emergência)
                                    </button>
                                    <button
                                        onClick={handleCancelAndRevert}
                                        className="px-8 py-2.5 bg-white border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-50 transition shadow-lg"
                                    >
                                        🛑 Cancelar e Reverter
                                    </button>
                                </div>
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

            <AdminQuestionViewModal
                isOpen={isQuestionModalOpen}
                onClose={() => setIsQuestionModalOpen(false)}
                questionId={viewQuestionId}
            />
        </div>
    );
}
