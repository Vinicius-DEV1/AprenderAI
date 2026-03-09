import { useState, FormEvent, useEffect, useRef, useCallback } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    createEssayDraft, startTopicGeneration, getTopicStatus,
    submitEssay, getEssayThemes, getEssayRule, getEssays, updateEssayDraft
} from '../../api/essays';

// ─── Types ────────────────────────────────────────────────────────────────────
interface EssayTheme {
    id: number;
    title: string;
    year: number | null;
    institution: string | null;
    type: string;
}

interface WritingRule {
    min_chars: number;
    max_chars: number;
    max_lines: number;
}

// ─── Theme Search Modal ────────────────────────────────────────────────────────
function ThemeSearchModal({
    essayType,
    onSelect,
    onClose,
}: {
    essayType: string;
    onSelect: (theme: EssayTheme) => void;
    onClose: () => void;
}) {
    const [keyword, setKeyword] = useState('');
    const [debouncedKeyword, setDebouncedKeyword] = useState('');
    const [page, setPage] = useState(1);

    // Debounce keyword input
    useEffect(() => {
        const t = setTimeout(() => {
            setDebouncedKeyword(keyword);
            setPage(1);
        }, 400);
        return () => clearTimeout(t);
    }, [keyword]);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['essay-themes', essayType, debouncedKeyword, page],
        queryFn: () => getEssayThemes({ type: essayType, keyword: debouncedKeyword || undefined, page }),
        placeholderData: (prev) => prev,
    });

    const themes: EssayTheme[] = data?.data ?? [];
    const meta = data?.meta;

    return (
        // Backdrop
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            {/* Modal card */}
            <div
                className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                        🔍 Pesquisar Temas de Redação
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                        aria-label="Fechar"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Search input */}
                <div className="px-6 py-3 border-b border-gray-100 dark:border-gray-700">
                    <input
                        type="text"
                        value={keyword}
                        onChange={(e) => setKeyword(e.target.value)}
                        placeholder="Buscar por palavra-chave no tema..."
                        autoFocus
                        className="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                    />
                </div>

                {/* Results */}
                <div className="flex-1 overflow-y-auto px-6 py-3 space-y-2">
                    {isLoading && (
                        <div className="flex justify-center py-10">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
                        </div>
                    )}
                    {isError && (
                        <p className="text-center text-red-500 py-8 text-sm">
                            Erro ao buscar temas. Tente novamente.
                        </p>
                    )}
                    {!isLoading && !isError && themes.length === 0 && (
                        <div className="text-center py-10">
                            <p className="text-gray-500 dark:text-gray-400 text-sm">
                                {debouncedKeyword
                                    ? `Nenhum tema encontrado para "${debouncedKeyword}".`
                                    : 'Nenhum tema de redação disponível ainda.'}
                            </p>
                        </div>
                    )}
                    {themes.map((theme) => (
                        <button
                            key={theme.id}
                            onClick={() => onSelect(theme)}
                            className="w-full text-left px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors group"
                        >
                            <p className="text-sm font-semibold text-gray-800 dark:text-gray-100 group-hover:text-blue-700 dark:group-hover:text-blue-300 line-clamp-2">
                                {theme.title}
                            </p>
                            <div className="flex gap-3 mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                {theme.year && <span>📅 {theme.year}</span>}
                                {theme.institution && <span>🏛 {theme.institution}</span>}
                                <span className={`px-1.5 py-0.5 rounded uppercase font-bold text-[10px] ${theme.type === 'enem' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300'}`}>
                                    {theme.type}
                                </span>
                            </div>
                        </button>
                    ))}
                </div>

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between px-6 py-3 border-t border-gray-100 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                        <span>Página {meta.current_page} de {meta.last_page} ({meta.total} temas)</span>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                disabled={meta.current_page <= 1}
                                className="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                ← Anterior
                            </button>
                            <button
                                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                                disabled={meta.current_page >= meta.last_page}
                                className="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                Próximo →
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────
interface EssayWriteProps {
    isSimulationMode?: boolean;
    simulationEssayId?: number;
    /** When set, the writer resumes an existing draft instead of creating a new one. */
    resumeEssayId?: number;
    initialTheme?: string;
    initialThemeDescription?: string;
    initialType?: string;
    initialContent?: string;
    onBack?: () => void;
    onSubmitSimulationEssay?: (formData: FormData) => void;
    onDraftUpdate?: (content: string) => void;
}

export default function EssayWrite({
    isSimulationMode = false,
    simulationEssayId,
    resumeEssayId,
    initialTheme,
    initialThemeDescription,
    initialType,
    initialContent,
    onBack,
    onSubmitSimulationEssay,
    onDraftUpdate
}: EssayWriteProps) {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    // Wizard State
    // Bypass step 1 and 2 if simulation mode or resuming a draft
    const isResumingDraft = !!resumeEssayId;
    const [step, setStep] = useState<1 | 2 | 3>((isSimulationMode || isResumingDraft) ? 3 : 1);
    const [essayId, setEssayId] = useState<number | null>(
        resumeEssayId ?? (isSimulationMode ? (simulationEssayId || null) : null)
    );

    // Step 1 State
    const [type, setType] = useState(initialType || 'enem');
    const [timeLimit, setTimeLimit] = useState(60);

    // Step 2 State
    const [theme, setTheme] = useState(initialTheme || '');
    const [themeDescription, setThemeDescription] = useState(initialThemeDescription || '');
    const [isGenerating, setIsGenerating] = useState(false);
    const [regenCount, setRegenCount] = useState(0);
    const [showThemeModal, setShowThemeModal] = useState(false);

    // Step 3 State
    const [inputType, setInputType] = useState<'text' | 'image'>('text');
    const [content, setContent] = useState(initialContent || '');
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [charWarning, setCharWarning] = useState('');
    const [lineWarning, setLineWarning] = useState('');
    const charWarningTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lineWarningTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Auto-save State
    const [autoSaveStatus, setAutoSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const autoSaveTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    // Track the last saved content to avoid redundant saves
    const lastSavedContent = useRef(initialContent || '');

    // WritingRule (fetched on entering step 3)
    const [rule, setRule] = useState<WritingRule>({ min_chars: 1500, max_chars: 3000, max_lines: 30 });

    // Shared State
    const [error, setError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const charCount = content.length;
    const wordCount = content.trim() === '' ? 0 : content.trim().split(/\s+/).filter(w => w.length > 0).length;
    // Unused charsRemaining removed to prevent build warning
    const isNearLimit = charCount >= rule.max_chars * 0.9;

    // Timer
    const [remainingSeconds, setRemainingSeconds] = useState(() => {
        // Recover from localStorage if we already started the timer for this essay
        const id = resumeEssayId ?? (isSimulationMode ? simulationEssayId : null);
        if (id) {
            const stored = localStorage.getItem(`essay_timer_start_${id}`);
            if (stored) {
                const startTs = parseInt(stored, 10);
                const elapsed = Math.floor((Date.now() - startTs) / 1000);
                const remaining = Math.max(0, timeLimit * 60 - elapsed);
                return remaining;
            }
        }
        return timeLimit * 60;
    });

    // ── Essay Limit (how many submissions remain this month) ────────────────
    const { data: essayMeta } = useQuery({
        queryKey: ['essays-meta'],
        queryFn: () => getEssays(1),
        staleTime: 60_000,
    });
    const essayLimit: { remaining: number; total: number } | undefined = essayMeta?.meta?.essayLimit;

    // ── Fetch WritingRule when entering step 3 ──────────────────────────────
    useEffect(() => {
        if (step === 3) {
            getEssayRule(type).then((r) => setRule(r)).catch(() => {
                // Keep sensible defaults on error
            });
        }
    }, [step, type]);

    // ── Mutations ──────────────────────────────────────────────────────────
    const draftMutation = useMutation({
        mutationFn: () => createEssayDraft({ type, time_limit: timeLimit }),
        onSuccess: (data) => {
            setEssayId(data.data.id);
            setError(null);
            // If theme was already selected or we are startting generation, flow continues
        },
        onError: (err: any) => {
            const code = err.response?.data?.code;
            if (code === 'QUOTA_EXCEEDED') {
                setError('Você atingiu seu limite mensal de redações.');
            } else {
                setError('Falha interna ao criar rascunho. Tente novamente.');
            }
        },
    });

    const generateMutation = useMutation({
        mutationFn: async () => {
            let currentEssayId = essayId;
            if (!currentEssayId) {
                const draft = await createEssayDraft({ type, time_limit: timeLimit });
                currentEssayId = draft.data.id;
                setEssayId(currentEssayId);
            }
            return startTopicGeneration(currentEssayId!);
        },
        onSuccess: () => {
            setIsGenerating(true);
            setError(null);
            pollTopicStatus();
        },
        onError: (err: any) => setError(err.response?.data?.message || 'Erro ao iniciar geração.'),
    });

    // Auto generate theme if needed on simulation mode
    useEffect(() => {
        if (isSimulationMode && essayId && !initialTheme && !isGenerating && !theme) {
            generateMutation.mutate();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isSimulationMode, essayId, initialTheme]);

    const pollTopicStatus = useCallback(() => {
        const interval = setInterval(async () => {
            try {
                const data = await getTopicStatus(essayId!);
                if (data.topic_description) {
                    clearInterval(interval);
                    setTheme(data.title || data.topic_description);
                    setThemeDescription(data.topic_description);
                    setRegenCount(data.topic_regen_count);
                    setIsGenerating(false);
                } else if (data.status === 'error') {
                    clearInterval(interval);
                    setIsGenerating(false);

                    if (data.error_details) {
                        setError(`${data.error_details.message || 'A IA falhou ao gerar o tema.'} (ID: ${data.error_details.request_id || 'N/A'}) - Tente novamente ou pesquise um tema existente.`);
                    } else {
                        setError('A IA falhou ao gerar o tema. Tente novamente ou pesquise um tema existente.');
                    }
                }
            } catch (err) {
                // Keep polling unless fatal
            }
        }, 3000);
    }, [essayId]);

    const submitMutation = useMutation({
        mutationFn: (data: FormData) => submitEssay(essayId!, data),
        onSuccess: (data) => {
            // Clear the persisted timer for this essay
            if (essayId) localStorage.removeItem(`essay_timer_start_${essayId}`);
            queryClient.invalidateQueries({ queryKey: ['essays'] });
            queryClient.invalidateQueries({ queryKey: ['essays-meta'] }); // Refetch limit
            navigate(data?.data?.id ? `/redacoes/correcao/${data.data.id}` : '/redacoes');
        },
        onError: (err: any) => {
            const code = err.response?.data?.code;
            if (code === 'QUOTA_EXCEEDED') {
                setError('Você atingiu o limite mensal de redações.');
            } else {
                setError(err.response?.data?.message || 'Falha ao enviar a redação.');
            }
        },
    });

    // ── Step Handlers ─────────────────────────────────────────────────────
    const handleStep1Submit = (e: FormEvent) => {
        e.preventDefault();
        setRemainingSeconds(timeLimit * 60);
        setStep(2); // Just move to step 2, don't create draft yet
    };

    const handleStep2Submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!theme.trim()) {
            setError('Defina um tema antes de continuar.');
            return;
        }

        setError(null);

        // If no essayId yet (and not resuming), create the draft now before moving to Step 3
        if (!essayId && !isResumingDraft) {
            try {
                const data = await createEssayDraft({ type, time_limit: timeLimit });
                setEssayId(data.data.id);
            } catch (err: any) {
                const code = err.response?.data?.code;
                if (code === 'QUOTA_EXCEEDED') {
                    setError('Você atingiu seu limite mensal de redações.');
                } else {
                    setError('Falha interna ao criar rascunho. Tente novamente.');
                }
                return;
            }
        }

        setStep(3);
    };

    const showLineWarning = (msg: string) => {
        setLineWarning(msg);
        if (lineWarningTimeout.current) clearTimeout(lineWarningTimeout.current);
        lineWarningTimeout.current = setTimeout(() => setLineWarning(''), 3000);
    };

    const showCharWarning = (msg: string) => {
        setCharWarning(msg);
        if (charWarningTimeout.current) clearTimeout(charWarningTimeout.current);
        charWarningTimeout.current = setTimeout(() => setCharWarning(''), 3000);
    };

    const handleContentChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        let text = e.target.value;

        // Truncate lines exceeding max_lines (handles paste)
        const lines = text.split('\n');
        if (lines.length > rule.max_lines) {
            text = lines.slice(0, rule.max_lines).join('\n');
            showLineWarning(`Máximo de ${rule.max_lines} linhas atingido.`);
        }

        // maxlength is enforced by the attribute, but guard here too
        if (text.length > rule.max_chars) {
            text = text.slice(0, rule.max_chars);
            showCharWarning(`Máximo de ${rule.max_chars} caracteres atingido.`);
        }

        setContent(text);
        if (onDraftUpdate) {
            onDraftUpdate(text);
        }

        // Trigger Auto-save
        if (essayId && inputType === 'text') {
            setAutoSaveStatus('idle'); // clear saved status so the user knows it changed
            if (autoSaveTimeout.current) clearTimeout(autoSaveTimeout.current);
            autoSaveTimeout.current = setTimeout(async () => {
                if (text !== lastSavedContent.current) {
                    setAutoSaveStatus('saving');
                    try {
                        await updateEssayDraft(essayId, text);
                        lastSavedContent.current = text;
                        setAutoSaveStatus('saved');
                    } catch (err) {
                        setAutoSaveStatus('error');
                    }
                }
            }, 2000); // 2 seconds debounce
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter') {
            const lines = (content || '').split('\n').length;
            if (lines >= rule.max_lines) {
                e.preventDefault();
                showLineWarning(`Máximo de ${rule.max_lines} linhas atingido.`);
            }
        }
        if (content.length >= rule.max_chars && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            showCharWarning(`Máximo de ${rule.max_chars} caracteres atingido.`);
        }
    };

    const handleStep3Submit = (e: FormEvent) => {
        e.preventDefault();
        setError(null);

        const formData = new FormData();
        formData.append('input_type', inputType);
        if (theme) {
            formData.append('theme', theme); // Changed from theme.title to theme as theme is a string
        }

        if (inputType === 'text') {
            if (content.trim().length < rule.min_chars) {
                setError(`Escreva ao menos ${rule.min_chars} caracteres (mínimo exigido).`);
                return;
            }
            if (content.trim().length > rule.max_chars) {
                setError(`Sua redação excede o limite de ${rule.max_chars} caracteres.`);
                return;
            }
            formData.append('content', content);
        } else {
            if (!imageFile) {
                setError('Selecione uma imagem da sua redação.');
                return;
            }
            formData.append('image', imageFile);
        }

        if (!isSimulationMode && essayLimit && essayLimit.remaining <= 0) {
            setError('Você atingiu o limite mensal de redações.');
            return;
        }

        if (window.confirm(isSimulationMode ? 'Este texto será usado como sua redação. Tem certeza?' : 'Tem certeza que deseja enviar sua redação para correção?')) {
            if (isSimulationMode && onSubmitSimulationEssay) {
                onSubmitSimulationEssay(formData);
            } else {
                submitMutation.mutate(formData);
            }
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 8 * 1024 * 1024) {
                setError('Imagem muito grande (máx 8MB).');
                return;
            }
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setImagePreview(reader.result as string);
            reader.readAsDataURL(file);
            setError(null);
        }
    };

    // ── Timer Effect ──────────────────────────────────────────────────────
    // Persist start timestamp to localStorage the first time we enter step 3.
    useEffect(() => {
        if (step === 3 && essayId) {
            const storageKey = `essay_timer_start_${essayId}`;
            if (!localStorage.getItem(storageKey)) {
                localStorage.setItem(storageKey, String(Date.now() - (timeLimit * 60 - remainingSeconds) * 1000));
            }
        }
    }, [step, essayId]);

    useEffect(() => {
        if (step === 3 && remainingSeconds > 0) {
            const timer = setInterval(() => {
                setRemainingSeconds(prev => prev > 0 ? prev - 1 : 0);
            }, 1000);
            return () => clearInterval(timer);
        }
    }, [step, remainingSeconds]);

    const h = Math.floor(remainingSeconds / 3600).toString().padStart(2, '0');
    const m = Math.floor((remainingSeconds % 3600) / 60).toString().padStart(2, '0');
    const s = (remainingSeconds % 60).toString().padStart(2, '0');
    const timerDisplay = `${h}:${m}:${s}`;
    const timerCritical = remainingSeconds <= 300;

    // Se estiver em modo simulado e não tiver tema (esperando gerar)
    if (isSimulationMode && isGenerating) {
        return (
            <div className="flex flex-col items-center justify-center space-y-4 py-20 min-h-[400px]">
                <div className="animate-spin rounded-full h-14 w-14 border-b-2 border-blue-600" />
                <p className="font-semibold text-gray-600 dark:text-gray-300 transform animate-pulse transition-all">Sorteando tema exclusivo da redação...</p>
                <p className="text-sm text-gray-400">Isso pode levar alguns segundos.</p>
                {error && <p className="text-red-500 mt-4 text-sm font-bold bg-white p-3 rounded">{error}</p>}
            </div>
        );
    }

    // ── Render ────────────────────────────────────────────────────────────
    return (
        <div className="py-12">
            {showThemeModal && (
                <ThemeSearchModal
                    essayType={type}
                    onSelect={(t) => {
                        setTheme(t.title);
                        setThemeDescription(t.title);
                        setShowThemeModal(false);
                        setError(null);
                    }}
                    onClose={() => setShowThemeModal(false)}
                />
            )}

            <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 -mt-[25px]">

                {!isSimulationMode && (
                    <div className="mb-6 flex justify-between items-center">
                        <Link to="/redacoes" className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Voltar
                        </Link>
                        {step === 3 && (
                            <div className={`px-4 py-2 rounded-full font-mono font-bold shadow-sm border transition-colors ${timerCritical ? 'bg-red-50 border-red-200 text-red-600 animate-pulse dark:bg-red-900/30 dark:border-red-700 dark:text-red-400' : 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300'}`}>
                                {timerDisplay}
                                {timerCritical && <span className="ml-2 text-xs">⚠ Menos de 5 min!</span>}
                            </div>
                        )}
                    </div>
                )}

                <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    {/* Steps Indicator */}
                    {!isSimulationMode && (
                        <div className="border-b border-gray-200 dark:border-gray-700 p-4">
                            <div className="flex items-center justify-center space-x-8">
                                <div className={`font-bold ${step >= 1 ? 'text-blue-600' : 'text-gray-400'}`}>1. Tipo e Tempo</div>
                                <div className={`w-12 h-0.5 ${step >= 2 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`} />
                                <div className={`font-bold ${step >= 2 ? 'text-blue-600' : 'text-gray-400'}`}>2. Tema</div>
                                <div className={`w-12 h-0.5 ${step >= 3 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`} />
                                <div className={`font-bold ${step >= 3 ? 'text-blue-600' : 'text-gray-400'}`}>3. Escrita</div>
                            </div>
                        </div>
                    )}

                    <div className="p-6 text-gray-900 dark:text-gray-100">
                        {error && (
                            <div className="bg-red-100 dark:bg-red-900 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-md mb-6">
                                <span className="text-sm font-medium">{error}</span>
                            </div>
                        )}

                        {/* ── STEP 1: Type & Time ── */}
                        {step === 1 && (
                            <form onSubmit={handleStep1Submit} className="space-y-6">
                                <h3 className="text-lg font-medium">Escolha o formato</h3>
                                <div>
                                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Tipo de Redação</label>
                                    <select
                                        value={type}
                                        onChange={(e) => setType(e.target.value)}
                                        className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        disabled={draftMutation.isPending}
                                    >
                                        <option value="enem">ENEM</option>
                                        <option value="concurso">Concurso Público</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Tempo Disponível</label>
                                    <select
                                        value={timeLimit}
                                        onChange={(e) => setTimeLimit(Number(e.target.value))}
                                        className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        disabled={draftMutation.isPending}
                                    >
                                        <option value={30}>30 minutos</option>
                                        <option value={45}>45 minutos</option>
                                        <option value={60}>60 minutos (1 hora)</option>
                                        <option value={90}>90 minutos (1h 30m)</option>
                                        <option value={120}>120 minutos (2 horas)</option>
                                    </select>
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" disabled={draftMutation.isPending} className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50 font-bold">
                                        {draftMutation.isPending ? 'Criando...' : 'Continuar →'}
                                    </button>
                                </div>
                            </form>
                        )}

                        {/* ── STEP 2: Theme ── */}
                        {step === 2 && (
                            <form onSubmit={handleStep2Submit} className="space-y-6">
                                <div>
                                    <h3 className="text-lg font-semibold">Defina o Tema da Redação</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Escolha como deseja obter o tema.{' '}
                                        {essayLimit !== undefined && (
                                            <span className={`font-semibold ${essayLimit.remaining <= 1 ? 'text-red-500' : 'text-blue-600 dark:text-blue-400'}`}>
                                                Você possui {essayLimit.remaining} envio{essayLimit.remaining !== 1 ? 's' : ''} de redação restante{essayLimit.remaining !== 1 ? 's' : ''} este mês.
                                            </span>
                                        )}
                                    </p>
                                </div>

                                {isGenerating ? (
                                    <div className="flex flex-col items-center justify-center space-y-4 py-12">
                                        <div className="animate-spin rounded-full h-14 w-14 border-b-2 border-blue-600" />
                                        <p className="font-semibold text-gray-600 dark:text-gray-300">Xavier está criando um tema exclusivo para você...</p>
                                        <p className="text-sm text-gray-400">Isso pode levar alguns segundos.</p>
                                    </div>
                                ) : (
                                    <>
                                        {/* Theme selected card */}
                                        {theme ? (
                                            <div className="p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg space-y-2">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-xs font-bold uppercase tracking-wide text-blue-500 dark:text-blue-400 mb-1">Tema selecionado</p>
                                                        <p className="font-semibold text-gray-900 dark:text-gray-100">{theme}</p>
                                                        {themeDescription && themeDescription !== theme && (
                                                            <p className="text-sm text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">{themeDescription}</p>
                                                        )}
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setTheme(''); setThemeDescription(''); }}
                                                        className="flex-shrink-0 text-xs text-red-500 hover:text-red-700 font-semibold"
                                                    >
                                                        Trocar
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            /* CTA buttons — vertical, centered, hierarchical */
                                            <div className="flex flex-col items-center gap-4 w-full">
                                                {/* Primary: Generate with Xavier (larger, full attention) */}
                                                <button
                                                    type="button"
                                                    onClick={() => generateMutation.mutate()}
                                                    disabled={generateMutation.isPending || regenCount >= 3}
                                                    className="w-full max-w-lg flex flex-col items-center justify-center gap-3 py-8 px-6 rounded-xl border-2 border-blue-500 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <span className="text-4xl">✨</span>
                                                    <div className="text-center">
                                                        <p className="font-bold text-lg">Gerar tema exclusivo com Xavier</p>
                                                        <p className="text-sm text-blue-100 mt-1">
                                                            Xavier cria um tema inédito, personalizado para você
                                                        </p>
                                                        {regenCount >= 3 && (
                                                            <p className="text-xs text-yellow-300 mt-2 font-semibold">⚠ Limite de gerações de tema atingido</p>
                                                        )}
                                                    </div>
                                                </button>

                                                {/* Divider */}
                                                <div className="flex items-center gap-3 w-full max-w-lg text-gray-400 text-xs">
                                                    <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
                                                    <span className="font-medium uppercase tracking-wide">ou</span>
                                                    <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
                                                </div>

                                                {/* Secondary: Search existing (smaller, subdued) */}
                                                <button
                                                    type="button"
                                                    onClick={() => setShowThemeModal(true)}
                                                    className="w-full max-w-sm flex flex-col items-center justify-center gap-2 py-5 px-6 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700/60 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-600 dark:text-gray-300 shadow-sm transition-all"
                                                >
                                                    <span className="text-2xl">🔍</span>
                                                    <div className="text-center">
                                                        <p className="font-semibold text-sm">Pesquisar temas já existentes</p>
                                                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                            Escolha um tema do banco de redações
                                                        </p>
                                                    </div>
                                                </button>
                                            </div>
                                        )}

                                        {/* Actions row */}
                                        <div className="flex justify-between items-center pt-2">
                                            <button
                                                type="button"
                                                onClick={() => setStep(1)}
                                                className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 font-medium"
                                            >
                                                ← Voltar
                                            </button>
                                            <button
                                                type="submit"
                                                disabled={!theme.trim()}
                                                className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-bold disabled:opacity-40 disabled:cursor-not-allowed"
                                            >
                                                Continuar →
                                            </button>
                                        </div>
                                    </>
                                )}
                            </form>
                        )}

                        {/* ── STEP 3: Write ── */}
                        {step === 3 && (
                            <form onSubmit={handleStep3Submit} className="space-y-6">

                                {/* Theme display (collapsible) */}
                                <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md text-sm">
                                    <span className="font-bold">Tema:</span>{' '}
                                    <span>{theme}</span>
                                    {themeDescription && themeDescription !== theme && (
                                        <p className="text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">{themeDescription}</p>
                                    )}
                                </div>

                                {/* WritingRule card */}
                                <div className={`p-4 rounded-lg border shadow-sm ${type === 'enem' ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800' : 'bg-orange-50 dark:bg-orange-900/30 border-orange-200 dark:border-orange-800'}`}>
                                    <h3 className={`font-bold mb-2 flex items-center text-base ${type === 'enem' ? 'text-blue-800 dark:text-blue-300' : 'text-orange-800 dark:text-orange-300'}`}>
                                        {type === 'enem' ? '📘 ENEM' : '📙 Concurso Público'}
                                    </h3>
                                    <div className={`text-sm space-y-0.5 ${type === 'enem' ? 'text-blue-700 dark:text-blue-400' : 'text-orange-700 dark:text-orange-400'}`}>
                                        <p>Mínimo: <strong>{rule.min_chars.toLocaleString('pt-BR')}</strong> caracteres</p>
                                        <p>Máximo: <strong>{rule.max_chars.toLocaleString('pt-BR')}</strong> caracteres</p>
                                        <p>Máximo: <strong>{rule.max_lines}</strong> linhas</p>
                                    </div>
                                </div>

                                {/* Input Type Toggle (radio buttons) */}
                                <div className="bg-gray-50 dark:bg-gray-800 p-2 rounded-lg inline-flex">
                                    {(['text', 'image'] as const).map((val) => (
                                        <label
                                            key={val}
                                            className={`cursor-pointer px-5 py-2 rounded-md font-semibold text-sm transition-colors ${inputType === val ? 'bg-blue-600 text-white shadow' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'}`}
                                        >
                                            <input
                                                type="radio"
                                                className="hidden"
                                                value={val}
                                                checked={inputType === val}
                                                onChange={() => setInputType(val)}
                                            />
                                            {val === 'text' ? 'Digitar texto' : 'Enviar imagem (OCR)'}
                                        </label>
                                    ))}
                                </div>

                                {/* Text input */}
                                {inputType === 'text' ? (
                                    <div>
                                        <textarea
                                            value={content}
                                            onChange={handleContentChange}
                                            onKeyDown={handleKeyDown}
                                            rows={25}
                                            maxLength={rule.max_chars}
                                            className="lined-paper w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none font-serif text-lg p-6 disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                                            placeholder="Escreva sua redação aqui..."
                                            disabled={submitMutation.isPending}
                                        />

                                        {/* Warnings */}
                                        <div className="min-h-[20px] mt-1">
                                            {charWarning && (
                                                <span className="text-red-500 font-bold text-sm bg-red-50 dark:bg-red-900/40 px-2 py-1 rounded mr-3">
                                                    {charWarning}
                                                </span>
                                            )}
                                            {lineWarning && (
                                                <span className="text-red-500 font-bold text-sm bg-red-50 dark:bg-red-900/40 px-2 py-1 rounded">
                                                    {lineWarning}
                                                </span>
                                            )}
                                        </div>

                                        {/* Chars / words counter */}
                                        <div
                                            className={`flex justify-between items-center mt-2 px-3 py-2 rounded-md border text-sm font-medium transition-colors ${isNearLimit ? 'text-red-600 dark:text-red-400 font-bold border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20' : 'text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800'}`}
                                        >
                                            <div className="flex gap-4">
                                                <span>Caracteres: <strong>{charCount.toLocaleString('pt-BR')}</strong> / {rule.max_chars.toLocaleString('pt-BR')}</span>
                                                <span>Palavras: <strong>{wordCount}</strong></span>
                                            </div>

                                            {/* Auto-save Indicator */}
                                            {essayId && (
                                                <div className="text-xs flex items-center gap-1.5 opacity-80">
                                                    {autoSaveStatus === 'saving' && (
                                                        <>
                                                            <div className="animate-spin h-3 w-3 border-b-2 border-indigo-500 rounded-full" />
                                                            <span className="text-indigo-600 dark:text-indigo-400">Salvando rascunho...</span>
                                                        </>
                                                    )}
                                                    {autoSaveStatus === 'saved' && (
                                                        <>
                                                            <span className="text-emerald-500">✔</span>
                                                            <span className="text-emerald-600 dark:text-emerald-400">Rascunho salvo</span>
                                                        </>
                                                    )}
                                                    {autoSaveStatus === 'error' && (
                                                        <>
                                                            <span className="text-red-500">⚠️</span>
                                                            <span className="text-red-600 dark:text-red-400">Falha ao salvar</span>
                                                        </>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    /* Image upload */
                                    <div className="p-6 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-center">
                                        <input
                                            type="file"
                                            ref={fileInputRef}
                                            onChange={handleFileChange}
                                            accept="image/jpeg,image/png,image/webp"
                                            className="hidden"
                                            id="image-upload"
                                            disabled={submitMutation.isPending}
                                        />
                                        {imagePreview ? (
                                            <div className="mt-2">
                                                <img src={imagePreview} alt="Preview" className="max-h-96 mx-auto rounded shadow-sm" />
                                                <button
                                                    type="button"
                                                    onClick={() => { setImageFile(null); setImagePreview(null); if (fileInputRef.current) fileInputRef.current.value = ''; }}
                                                    className="mt-3 text-sm text-red-600 hover:text-red-800 font-medium"
                                                >
                                                    Remover imagem
                                                </button>
                                            </div>
                                        ) : (
                                            <label htmlFor="image-upload" className="cursor-pointer block py-8">
                                                <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28H8z" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                </svg>
                                                <p className="text-sm font-medium text-indigo-600 dark:text-indigo-400">Clique para selecionar a foto da sua redação</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">JPG, PNG ou WebP — máximo 8MB</p>
                                            </label>
                                        )}
                                    </div>
                                )}

                                {/* Submit */}
                                <div className="flex justify-between items-center mt-6">
                                    {isSimulationMode && onBack ? (
                                        <button
                                            type="button"
                                            onClick={onBack}
                                            disabled={submitMutation.isPending}
                                            className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 font-medium"
                                        >
                                            ← Voltar para Questões
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setStep(2)}
                                            disabled={submitMutation.isPending}
                                            className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 font-medium"
                                        >
                                            ← Voltar ao Tema
                                        </button>
                                    )}
                                    <button
                                        type="submit"
                                        disabled={submitMutation.isPending}
                                        className="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 font-bold text-lg disabled:opacity-50 flex items-center gap-2"
                                    >
                                        {submitMutation.isPending && (
                                            <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                        )}
                                        {submitMutation.isPending ? 'Enviando...' : 'Enviar para Correção ✓'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
