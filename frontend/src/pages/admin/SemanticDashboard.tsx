import React, { useState, useEffect, useRef } from 'react';
import api from '../../api/axios';
import { toast } from 'sonner';
import { clsx } from 'clsx';
import { 
    Activity,
    AlertCircle,
    ArrowRight,
    BarChart3,
    Box,
    CheckCircle2,
    ChevronDown,
    Clock,
    Database,
    Filter,
    Hash,
    Info,
    Layers,
    Lightbulb,
    Microscope,
    RefreshCw,
    Rocket,
    Save,
    Search,
    Server,
    Settings,
    Target,
    TrendingUp,
    Zap,
    Trash2,
    HelpCircle,
    X,
    ChevronUp,
    PlayCircle,
    Code,
    Eye
} from 'lucide-react';

// Components
import StatCard from './semantic/StatCard';
import PipelineStep from './semantic/PipelineStep';
import SearchRow from './semantic/SearchRow';
import CacheRow from './semantic/CacheRow';
import { IndexModal, IntentModal, ResetModal } from './semantic/SemanticModals';
import SearchResultItem from './semantic/SearchResultItem';

const TARGET_PIPELINE_VERSION = 'v8.1.0';

interface DashboardStats {
    overview: {
        mysql_published_questions: number;
        mysql_indexed_questions: number;
        mysql_total_subjects: number;
        mysql_indexed_subjects: number;
        mysql_total_topics: number;
        mysql_indexed_topics: number;
    };
    qdrant: {
        status: string;
        collections: Array<{ name: string; count: number }>;
        vectors_count: number;
        concepts_points: number;
        index_version_status?: {
            expected: string;
            questions: { status: string; expected: string; actual: string; message: string };
            concepts: { status: string; expected: string; actual: string; message: string };
        };
    };
    performance: {
        total_searches: number;
        l1_cache_hits: number;
        l2_cache_hits: number;
        total_cache_entries: number;
    };
    jobs: {
        pending: number;
        failed: number;
        recent_failures?: Array<{
            id: number;
            failed_at: string;
            payload: string;
            error_preview: string;
        }>;
        waiting_list?: Array<{
            job: string;
            id: string | number;
            timestamp: string;
            ago: string;
        }>;
        waiting_total?: number;
    };
    analytics: {
        total_ai_requests: number;
        success_rate: number;
        top_prompts: Array<{ prompt: string; total: number }>;
        chart_data: Array<{ date: string; count: number; success: number; failed: number }>;
    };
    recent_searches?: Array<{
        id: number;
        user_name: string;
        prompt: string;
        status: string;
        created_at: string;
        similarity_threshold: number;
        filters?: any;
        error?: string;
    }>;
    search_cache?: Array<{
        id: number;
        prompt_text: string;
        prompt_hash: string;
        filters_result: any;
        concept_ids: number[];
        last_used_at: string | null;
        created_at: string;
        vector_preview: number[];
    }>;
    top_concepts?: Array<{ name: string; count: number; type?: 'concept' | 'subject' | 'topic' }>;
    config: {
        vector_search_enabled: boolean | string;
        concept_detection_threshold: number;
        qdrant_candidate_limit: number;
        final_result_limit: number;
        rerank_weights: Record<string, number>;
        pipeline_version?: string;
        search_cache_enabled: boolean;
    };
}

const SemanticDashboard = () => {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [configLoading, setConfigLoading] = useState(false);
    const [testLoading, setTestLoading] = useState(false);
    const [reindexing, setReindexing] = useState(false);
    const [reindexingIntents, setReindexingIntents] = useState(false);
    const [clearingCache, setClearingCache] = useState(false);
    const [clearingQueue, setClearingQueue] = useState(false);
    const [resetting, setResetting] = useState(false);
    const [isResetModalOpen, setIsResetModalOpen] = useState(false);
    const [resetConfirmText, setResetConfirmText] = useState('');
    const [wakingUp, setWakingUp] = useState(false);
    const [actionsMenuOpen, setActionsMenuOpen] = useState(false);
    const actionsMenuRef = useRef<HTMLDivElement>(null);

    // Config form
    const [configState, setConfigState] = useState({
        vector_search_enabled: true,
        concept_detection_threshold: 0.45,
        rerank_weights: {
            vector: 0.60,
            popularity: 0.15,
            quality: 0.15,
            recency: 0.10
        },
        search_cache_enabled: true,
    });

    // Index Modal (Questions)
    const [isIndexModalOpen, setIsIndexModalOpen] = useState(false);
    const [indexBatchLimit, setIndexBatchLimit] = useState(500);
    const [indexForce, setIndexForce] = useState(false);

    // Intent Index Modal (Subjects/Topics)
    const [isIntentModalOpen, setIsIntentModalOpen] = useState(false);
    const [intentBatchLimit, setIntentBatchLimit] = useState(1000);
    const [intentForce, setIntentForce] = useState(false);

    // Test search
    const [searchPrompt, setSearchPrompt] = useState('');
    const [searchResults, setSearchResults] = useState<any>(null);

    const parsePipelineSteps = (logs: string[]) => {
        const steps = {
            lexical: { positive: [] as string[], negative: [] as string[] },
            intent: [] as { type: string, value: string }[],
            expansion: [] as string[],
            temporal: null as { operator: string, year: number } | null,
            difficulty: null as string | null,
            organizations: [] as string[],
            institutions: [] as string[],
            vector: { statement: false, concept: false, explanation: false },
            rerank: [] as string[]
        };

        if (!logs) return steps;

        logs.forEach(log => {
            if (log.includes(" - Positive:")) {
                const match = log.match(/- Positive: '(.*)'/);
                if (match) steps.lexical.positive = match[1].split(' ').filter(t => t);
            }
            if (log.includes(" - Negative:")) {
                const match = log.match(/- Negative: '(.*)'/);
                if (match) steps.lexical.negative = match[1].split(' ').filter(t => t);
            }
            if (log.includes("INTENT DETECTED:")) {
                const parts = log.split("INTENT DETECTED: ")[1].split(" ");
                steps.intent.push({ type: parts[0], value: parts.slice(1).join(" ") });
            }
            if (log.includes("EXCLUSION DETECTED:")) {
                const parts = log.split("EXCLUSION DETECTED: ")[1].split(" ");
                steps.intent.push({ type: 'EXCLUIR ' + parts[0], value: parts.slice(1).join(" ") });
            }
            if (log.includes("DIFFICULTY DETECTED:")) {
                steps.difficulty = log.split("DIFFICULTY DETECTED: ")[1];
            }
            if (log.includes("TEMPORAL OPERATOR:")) {
                const parts = log.split("TEMPORAL OPERATOR: ")[1].split(" ");
                steps.temporal = { operator: parts[0], year: parseInt(parts[1]) };
            }
            if (log.includes("ORG DETECTED:")) {
                steps.organizations.push(log.split("ORG DETECTED: ")[1]);
            }
            if (log.includes("INST DETECTED:")) {
                steps.institutions.push(log.split("INST DETECTED: ")[1]);
            }
        });

        return steps;
    };

    const handleReplay = (prompt: string) => {
        setSearchPrompt(prompt);
        const element = document.getElementById('maestro-search-input');
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
            element.focus();
        }
        toast.info(`Prompt "${prompt}" carregado no Maestro.`);
    };

    const loadStats = async () => {
        try {
            isBusyRef.current = true;
            setLoading(true);
            const res = await api.get('/api/v1/admin/semantic');
            setStats(res.data);
            setConfigState({
                vector_search_enabled: res.data.config.vector_search_enabled == 1 || res.data.config.vector_search_enabled == true,
                concept_detection_threshold: parseFloat(res.data.config.concept_detection_threshold) || 0.45,
                rerank_weights: {
                    vector: res.data.config.rerank_weights?.vector || 0.60,
                    popularity: res.data.config.rerank_weights?.popularity || 0.15,
                    quality: res.data.config.rerank_weights?.quality || 0.15,
                    recency: res.data.config.rerank_weights?.recency || 0.10
                },
                search_cache_enabled: res.data.config.search_cache_enabled || false
            });
        } catch (error) {
            toast.error('Erro ao carregar os dados do dashboard.');
        } finally {
            setLoading(false);
            isBusyRef.current = false;
        }
    };

    // Ref to track if any background operation is running (avoids stale closure in setInterval)
    const isBusyRef = React.useRef(false);

    useEffect(() => {
        loadStats();

        // Auto-refresh every 10 seconds.
        // Uses a ref instead of state to avoid stale closure — the interval always reads the latest value.
        const interval = setInterval(() => {
            if (!isBusyRef.current) {
                loadStats();
            }
        }, 10000);

        // Close actions menu on outside click
        const handleClickOutside = (e: MouseEvent) => {
            if (actionsMenuRef.current && !actionsMenuRef.current.contains(e.target as Node)) {
                setActionsMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () => { clearInterval(interval); document.removeEventListener('mousedown', handleClickOutside); };
    }, []);

    const handleSaveConfig = async () => {
        try {
            setConfigLoading(true);
            await api.post('/api/v1/admin/semantic/config', configState);
            toast.success('Configuração atualizada. Pode levar alguns segundos para refletir globalmente.');
            await loadStats();
        } catch (error) {
            toast.error('Erro ao salvar as configurações.');
        } finally {
            setConfigLoading(false);
        }
    };

    const handleTestSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchPrompt) return;

        try {
            setTestLoading(true);
            const res = await api.post('/api/v1/admin/semantic/test-search', { prompt: searchPrompt });
            setSearchResults(res.data);
            toast.success('Busca concluída!');
        } catch (error) {
            toast.error('Erro ao executar a busca de teste.');
            setSearchResults(null);
        } finally {
            setTestLoading(false);
        }
    };

    const handleReindex = async () => {
        try {
            isBusyRef.current = true;
            setReindexing(true);
            const res = await api.post('/api/v1/admin/semantic/reindex', {
                limit: indexBatchLimit,
                force: indexForce
            });
            toast.success(res.data.message || 'Indexação de questões iniciada!');
            setIsIndexModalOpen(false);
            loadStats();
        } catch (error) {
            toast.error('Erro ao disparar indexação de questões.');
        } finally {
            setReindexing(false);
            isBusyRef.current = false;
        }
    };

    const handleReindexIntents = async () => {
        try {
            isBusyRef.current = true;
            setReindexingIntents(true);
            const res = await api.post('/api/v1/admin/semantic/reindex-concepts', {
                limit: intentBatchLimit,
                force: intentForce
            });
            toast.success(res.data.message || 'Indexação de intenções iniciada!');
            setIsIntentModalOpen(false);
            loadStats();
        } catch (error) {
            toast.error('Erro ao disparar indexação de intenções.');
        } finally {
            setReindexingIntents(false);
            isBusyRef.current = false;
        }
    };

    const handleClearCache = async () => {
        if (!confirm('Isso apagará todo o histórico de buscas otimizadas (L1/L2 Cache). Próximas buscas podem demorar mais para processar. Deseja continuar?')) {
            return;
        }

        try {
            setClearingCache(true);
            const res = await api.post('/api/v1/admin/semantic/clear-cache');
            toast.success(res.data.message);
            loadStats();
        } catch (error) {
            toast.error('Erro ao limpar o cache.');
        } finally {
            setClearingCache(false);
        }
    };

    const handleClearQueue = async () => {
        if (!confirm('Isso removerá TODOS os jobs pendentes da fila de embeddings. Use se a fila estiver travada ou com muitos erros. Deseja continuar?')) {
            return;
        }

        try {
            setClearingQueue(true);
            const res = await api.post('/api/v1/admin/semantic/clear-queue');
            toast.success(res.data.message);
            loadStats();
        } catch (error) {
            toast.error('Erro ao limpar a fila de jobs.');
        } finally {
            setClearingQueue(false);
        }
    };

    const handleResetEmbeddings = async () => {
        if (resetConfirmText !== 'RESET') return;
        try {
            setResetting(true);
            const res = await api.post('/api/v1/admin/semantic/reset-embeddings', { confirm: 'RESET' });
            toast.success(res.data.message);
            setIsResetModalOpen(false);
            setResetConfirmText('');
            loadStats();
        } catch (error) {
            toast.error('Erro ao executar reset completo.');
        } finally {
            setResetting(false);
        }
    };

    if (loading && !stats) {
        return <div className="p-8 flex justify-center"><RefreshCw className="w-8 h-8 animate-spin text-indigo-500" /></div>;
    }

    return (
        <>
            <div className="p-6 max-w-7xl mx-auto space-y-8">
                    {/* Header: Title + Pipeline Badge + Action Buttons */}
                    <div className="flex flex-wrap justify-between items-center gap-3">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                <Database className="w-6 h-6 text-indigo-500" />
                                Xavier Semantic Engine
                            </h1>
                            <p className="text-slate-500 dark:text-slate-400 mt-1 text-sm">
                                Monitoramento, configuração e testes do motor de busca vetorial.
                            </p>
                        </div>

                        <div className="flex items-center gap-2 flex-wrap">
                            {/* Version outdated inline indicator */}
                            {stats?.qdrant?.index_version_status &&
                                (stats.qdrant.index_version_status.questions.status === 'outdated' ||
                                    stats.qdrant.index_version_status.concepts.status === 'outdated') && (
                                    <span className="hidden lg:flex items-center gap-1.5 bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 px-3 py-1.5 rounded-lg border border-rose-200 dark:border-rose-800 text-xs font-bold animate-pulse">
                                        <AlertCircle className="w-3.5 h-3.5" />
                                        Re-indexação Necessária
                                    </span>
                                )}

                            {/* Refresh */}
                            <button
                                onClick={loadStats}
                                className="btn btn-secondary flex items-center gap-1.5 text-sm"
                                title="Atualizar Dados"
                            >
                                <RefreshCw className={clsx("w-4 h-4", loading && "animate-spin")} />
                                <span className="hidden sm:inline">Atualizar</span>
                            </button>

                            {/* ── Primary indexer buttons ── */}
                            <button
                                onClick={() => setIsIndexModalOpen(true)}
                                disabled={reindexing}
                                className="btn btn-primary bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                            >
                                {reindexing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Server className="w-4 h-4" />}
                                Indexar Questões
                            </button>
                            <button
                                onClick={() => setIsIntentModalOpen(true)}
                                className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-lg shadow-purple-500/25 transition-all active:scale-95"
                            >
                                {reindexingIntents ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Lightbulb className="w-4 h-4" />}
                                Indexar Matérias/Assuntos
                            </button>

                            {/* ── Secondary/dangerous actions dropdown ── */}
                            <div className="relative" ref={actionsMenuRef}>
                                <button
                                    onClick={() => setActionsMenuOpen(v => !v)}
                                    className="btn btn-secondary flex items-center gap-1.5 text-sm"
                                    title="Outras Ações"
                                >
                                    <Settings className="w-4 h-4" />
                                    <span className="hidden sm:inline">Ações</span>
                                    <ChevronDown className={clsx("w-3.5 h-3.5 transition-transform", actionsMenuOpen && "rotate-180")} />
                                </button>

                                {actionsMenuOpen && (
                                    <div className="absolute right-0 top-full mt-2 w-52 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-40 overflow-hidden">
                                        <div className="p-1">
                                            <button
                                                onClick={() => { setActionsMenuOpen(false); handleClearCache(); }}
                                                disabled={clearingCache}
                                                className="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors text-left"
                                            >
                                                {clearingCache ? <RefreshCw className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
                                                Limpar Cache de Busca
                                            </button>
                                            <button
                                                onClick={() => { setActionsMenuOpen(false); handleClearQueue(); }}
                                                disabled={clearingQueue}
                                                className="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left"
                                            >
                                                {clearingQueue ? <Trash2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
                                                Limpar Fila de Jobs
                                            </button>
                                            <div className="h-px bg-slate-100 dark:bg-slate-700 my-1" />
                                            <button
                                                onClick={() => { setActionsMenuOpen(false); setIsResetModalOpen(true); }}
                                                className="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-bold text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors text-left"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                                Reset Completo ⚠️
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                {/* OVERVIEW STATS */}
                {stats && (
                    <div className="space-y-6">
                        {/* MONITORING CENTER HEADER */}
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                            <h2 className="text-sm font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                <Activity className="w-4 h-4" /> Centro de Monitoramento
                            </h2>
                            <div className="flex items-center gap-4 text-[10px] font-bold">
                                <span className="flex items-center gap-1.5 text-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 px-2 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                    <Layers className="w-3 h-3" /> Pipeline: {stats.config.pipeline_version || 'v7'}
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {/* INFRA GROUP */}
                            <StatCard
                                title="Saúde do Qdrant"
                                value={stats.qdrant.status.toUpperCase()}
                                subtitle={`${stats.qdrant.questions_points.toLocaleString()} pontos vetoriais`}
                                icon={stats.qdrant.status === 'online' ? CheckCircle2 : AlertCircle}
                                colorClass={stats.qdrant.status === 'online' ? "bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30" : "bg-red-100 text-red-600 dark:bg-red-900/30"}
                                helpText="O Qdrant é o nosso banco de dados vetorial. Ele armazena o 'conhecimento' matemático das questões para permitir buscas por contexto."
                            />
                            <StatCard
                                title="Questões no Xavier"
                                value={`${stats.overview.mysql_indexed_questions.toLocaleString()} / ${stats.overview.mysql_published_questions.toLocaleString()}`}
                                subtitle={`${stats.overview.mysql_published_questions - stats.overview.mysql_indexed_questions} questões pendentes`}
                                icon={Box}
                                colorClass="bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                                helpText="Representa o percentual da sua base de questões que já está 'consciente' para a IA. Questões não indexadas só aparecem por busca de texto simples."
                            />
                            <StatCard
                                title="Matérias/Disciplinas"
                                value={`${stats.overview.mysql_indexed_subjects} / ${stats.overview.mysql_total_subjects}`}
                                subtitle={`${stats.overview.mysql_total_subjects - stats.overview.mysql_indexed_subjects} pendentes`}
                                icon={Database}
                                colorClass="bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                                helpText="Matérias/Disciplinas detectadas no Qdrant atuam como filtros rígidos na busca semântica."
                            />
                            <StatCard
                                title="Assuntos"
                                value={`${stats.overview.mysql_indexed_topics} / ${stats.overview.mysql_total_topics}`}
                                subtitle={`${stats.overview.mysql_total_topics - stats.overview.mysql_indexed_topics} pendentes`}
                                icon={Hash}
                                colorClass="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                                helpText="Assuntos (Topics) vinculados às questões. Permitem maior precisão no re-ranking."
                            />
                            <StatCard
                                title="Fila de Embedding"
                                value={stats.jobs.pending}
                                subtitle={`${stats.jobs.failed} falhas | ${stats.jobs.waiting_list?.length || 0} em espera`}
                                icon={stats.jobs.failed > 0 ? AlertCircle : Clock}
                                colorClass={stats.jobs.failed > 0 ? "bg-amber-100 text-amber-600 dark:bg-amber-900/30" : "bg-blue-100 text-blue-600 dark:bg-blue-900/30"}
                                helpText="Mostra quantos processos de 'transformar texto em vetor' estão aguardando no servidor. Falhas geralmente ocorrem por limite de cota da IA."
                            />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* PERFORMANCE GROUP */}
                            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm overflow-hidden relative">
                                <div className="absolute top-0 right-0 p-4 opacity-10">
                                    <TrendingUp className="w-12 h-12" />
                                </div>
                                <h3 className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Taxa de Sucesso (IA)</h3>
                                <div className="flex items-baseline gap-2">
                                    <span className="text-3xl font-black text-emerald-600 dark:text-emerald-400">{stats.analytics.success_rate}%</span>
                                    <span className="text-[10px] text-slate-400 font-medium">em {stats.analytics.total_ai_requests} buscas</span>
                                </div>
                                <div className="mt-4 w-full bg-slate-100 dark:bg-slate-700 h-1 rounded-full overflow-hidden">
                                    <div className="bg-emerald-500 h-full" style={{ width: `${stats.analytics.success_rate}%` }}></div>
                                </div>
                            </div>

                            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm overflow-hidden relative">
                                <div className="absolute top-0 right-0 p-4 opacity-10">
                                    <Zap className="w-12 h-12" />
                                </div>
                                <h3 className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Eficiência de Cache</h3>
                                <div className="flex items-baseline gap-2">
                                    <span className="text-3xl font-black text-amber-600 dark:text-amber-400">
                                        {((stats.performance.l1_cache_hits + stats.performance.l2_cache_hits) / (stats.performance.total_searches || 1) * 100).toFixed(1)}%
                                    </span>
                                    <span className="text-[10px] text-slate-400 font-medium">Economia de Tokens</span>
                                </div>
                                <p className="text-[10px] text-slate-500 mt-2 font-mono">
                                    Hit L1: {stats.performance.l1_cache_hits} | Hit L2: {stats.performance.l2_cache_hits}
                                </p>
                            </div>

                            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                                <h3 className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <BarChart3 className="w-4 h-4 text-indigo-500" /> Volume de Buscas (7 dias)
                                </h3>
                                <div className="flex items-end gap-1 h-14">
                                    {stats.analytics.chart_data.map((day, idx) => {
                                        const maxCount = Math.max(...stats.analytics.chart_data.map(d => d.count), 1);
                                        const heightPct = (day.count / maxCount) * 100;
                                        return (
                                            <div key={idx} className="flex-1 flex flex-col items-center gap-1" title={`${day.date}: ${day.count} buscas`}>
                                                <div
                                                    className="w-full rounded-t-sm bg-indigo-500/20 hover:bg-indigo-500 transition-all cursor-help"
                                                    style={{ height: `${Math.max(heightPct, 5)}%` }}
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* QDRANT INDEX HEALTH ALERTS */}
                {stats?.qdrant?.index_version_status && (
                    (stats.qdrant.index_version_status.questions.status === 'outdated' ||
                        stats.qdrant.index_version_status.concepts.status === 'outdated' ||
                        stats.qdrant.index_version_status.questions.status === 'empty' ||
                        stats.qdrant.index_version_status.concepts.status === 'empty'
                    ) && (
                        <div className="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 rounded-xl p-4 shadow-sm">
                            <h3 className="text-rose-800 dark:text-rose-400 font-bold mb-2 flex items-center gap-2">
                                <AlertCircle className="w-5 h-5" />
                                Atenção: Atualização de Banco Vetorial Necessária
                            </h3>
                            <p className="text-sm text-rose-700 dark:text-rose-300 mb-3">
                                O formato dos dados salvos no Qdrant está desatualizado (Versão esperada: <span className="font-mono bg-rose-100 dark:bg-rose-900/50 px-1 rounded">{stats.qdrant.index_version_status.expected}</span>).
                            </p>
                            <div className="flex gap-4 text-xs">
                                {stats.qdrant.index_version_status.questions.status !== 'ok' && (
                                    <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                        <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <strong>Questões:</strong> {stats.qdrant.index_version_status.questions.message}
                                    </div>
                                )}
                                {stats.qdrant.index_version_status.concepts.status !== 'ok' && (
                                    <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                        <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <strong>Conceitos:</strong> {stats.qdrant.index_version_status.concepts.message}
                                    </div>
                                )}
                            </div>
                        </div>
                    )
                )}

                {/* V8 UPGRADE BANNER — shown when some questions use old pipeline */}
                {stats && stats.config.pipeline_version === TARGET_PIPELINE_VERSION &&
                    stats.overview.mysql_indexed_questions > 0 &&
                    stats.qdrant.index_version_status?.questions.status === 'outdated' && (
                        <div className="bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl p-5 shadow-xl shadow-indigo-500/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <div className="p-2 bg-white/20 rounded-xl shrink-0">
                                    <Rocket className="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-base flex items-center gap-2">
                                        Upgrade Disponível: Pipeline {TARGET_PIPELINE_VERSION}
                                        <span className="bg-white/20 text-[10px] font-black px-2 py-0.5 rounded-full tracking-wider">NOVO</span>
                                    </h3>
                                    <p className="text-sm text-indigo-200 mt-1">
                                        5 vetores por questão + payload rico (subject_ids[], topic_ids[], has_explanation, word_count…). As questões já indexadas usam o formato antigo — Re-indexação com force necessária.
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => {
                                    setIndexForce(true);
                                    setIndexBatchLimit(stats?.overview.mysql_published_questions || 9999);
                                    setIsIndexModalOpen(true);
                                }}
                                className="shrink-0 bg-white text-indigo-700 hover:bg-indigo-50 font-bold px-5 py-2.5 rounded-xl text-sm shadow-lg transition-all active:scale-95 flex items-center gap-2"
                            >
                                <Zap className="w-4 h-4" />
                                Forçar Re-indexação (v8)
                            </button>
                        </div>
                    )}

                {/* MAESTRO WAITING ROOM (CONGESTION MONITOR) */}
                {stats && stats.jobs.waiting_list && stats.jobs.waiting_list.length > 0 && (
                    <div className="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/10 dark:to-orange-900/10 border border-amber-200 dark:border-amber-800/50 rounded-2xl p-6 shadow-lg shadow-amber-500/5 relative overflow-hidden group">
                        <div className="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-opacity">
                            <Clock className="w-24 h-24 rotate-12" />
                        </div>

                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 className="text-xl font-black text-amber-800 dark:text-amber-400 flex items-center gap-2">
                                    <Activity className="w-6 h-6 animate-pulse" />
                                    Maestro Waiting Room
                                </h3>
                                <p className="text-sm text-amber-700 dark:text-amber-500 mt-1 max-w-2xl font-medium">
                                    Shhh! Estes jobs estão "descansando" por 5 minutos antes da próxima tentativa.
                                    <span className="hidden sm:inline"> Isso acontece quando as chaves de API atingem o limite de cota ou estão todas ocupadas. <strong>Zero dados perdidos.</strong></span>
                                </p>
                            </div>
                            <div className="bg-amber-100 dark:bg-amber-900/40 px-4 py-2 rounded-xl border border-amber-200 dark:border-amber-700 font-black text-amber-700 dark:text-amber-300 flex items-center gap-3 shadow-inner">
                                <span className="text-2xl">{stats.jobs.waiting_total || stats.jobs.waiting_list.length}</span>
                                <span className="text-[10px] uppercase tracking-widest leading-tight">Jobs em<br />Recuperação</span>
                            </div>
                        </div>

                        {stats.jobs.waiting_total > stats.jobs.waiting_list.length && (
                             <div className="mb-4 text-[10px] font-bold text-amber-600 bg-amber-100/30 px-3 py-1 rounded border border-amber-200/50 w-fit">
                                 Exibindo apenas os primeiros 50 jobs de um total de {stats.jobs.waiting_total}.
                             </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            {stats.jobs.waiting_list.map((item, idx) => (
                                <div key={idx} className="bg-white/60 dark:bg-slate-800/60 backdrop-blur-sm p-3 rounded-xl border border-white dark:border-slate-700 shadow-sm flex flex-col hover:scale-105 transition-transform cursor-help group/item" title={`Registrado em: ${item.timestamp}`}>
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-[10px] font-black uppercase text-indigo-500 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded">
                                            {item.job.replace('Job', '')}
                                        </span>
                                        <Clock className="w-3 h-3 text-amber-500 group-hover/item:animate-spin" />
                                    </div>
                                    <div className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 truncate">
                                        ID: {item.id}
                                    </div>
                                    <div className="mt-auto flex items-center justify-between">
                                        <span className="text-[9px] text-slate-400 font-medium">Tentou há {item.ago}</span>
                                        <div className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2 text-[10px] text-amber-600 dark:text-amber-500 font-bold bg-amber-100/50 dark:bg-amber-900/20 w-fit px-3 py-1.5 rounded-lg border border-amber-200/50 dark:border-amber-800">
                                <Zap className="w-3 h-3" />
                                O sistema retentará automaticamente assim que as chaves esfriarem.
                            </div>
                            <button
                                onClick={async () => {
                                    setWakingUp(true);
                                    try {
                                        await api.post('/api/v1/admin/semantic/clear-congestion');
                                        toast.success('Jobs acordados! O sistema vai retentar imediatamente.');
                                        loadStats();
                                    } catch { toast.error('Falha ao acordar os jobs.'); }
                                    finally { setWakingUp(false); }
                                }}
                                disabled={wakingUp}
                                className="flex items-center gap-2 text-[10px] font-bold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white px-3 py-1.5 rounded-lg border border-amber-600 transition-all shadow-sm"
                            >
                                {wakingUp ? <RefreshCw className="w-3 h-3 animate-spin" /> : <Zap className="w-3 h-3" />}
                                Acordar Jobs Agora
                            </button>
                        </div>
                    </div>
                )}

                {/* CONCEPT CLOUD ROW */}
                {stats && stats.top_concepts && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-6">
                            <div className="flex items-center gap-2">
                                <Box className="w-5 h-5 text-indigo-500" />
                                <h2 className="text-lg font-bold text-slate-800 dark:text-white">Nuvem de Matérias & Assuntos (Qdrant)</h2>
                            </div>
                            <div className="flex gap-4 text-xs font-medium">
                                <span className="flex items-center gap-1.5 text-purple-600"><div className="w-2.5 h-2.5 rounded-full bg-purple-100 dark:bg-purple-900/40 border border-purple-200 dark:border-purple-700"></div> Matérias</span>
                                <span className="flex items-center gap-1.5 text-blue-600"><div className="w-2.5 h-2.5 rounded-full bg-blue-100 dark:bg-blue-900/40 border border-blue-200 dark:border-blue-700"></div> Assuntos</span>
                            </div>
                        </div>

                        <div className="flex flex-wrap justify-center items-center gap-x-4 gap-y-6 p-4 bg-slate-50/50 dark:bg-slate-900/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
                            {stats.top_concepts?.map((concept, i) => {
                                // Calculate a relative size based on count
                                // We use a log scale or simple thresholds to avoid massive extremes
                                const maxCount = Math.max(...(stats.top_concepts?.map(c => c.count) || []), 1);
                                const ratio = concept.count / maxCount;

                                let sizeClass = "text-xs";
                                if (ratio > 0.8) sizeClass = "text-2xl font-black";
                                else if (ratio > 0.5) sizeClass = "text-xl font-extrabold";
                                else if (ratio > 0.2) sizeClass = "text-lg font-bold";
                                else if (ratio > 0.05) sizeClass = "text-sm font-semibold";

                                return (
                                    <div
                                        key={i}
                                        className={clsx(
                                            "px-4 py-2 rounded-2xl flex items-center gap-2 transition-all hover:scale-110 hover:shadow-lg cursor-default border group",
                                            sizeClass,
                                            concept.type === 'subject'
                                                ? "bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-700 shadow-purple-100/50"
                                                : concept.type === 'topic'
                                                    ? "bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700 shadow-blue-100/50"
                                                    : "bg-white text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 shadow-sm"
                                        )}
                                        title={`${concept.type === 'subject' ? 'Disciplina' : concept.type === 'topic' ? 'Tópico' : 'Conceito'}: ${concept.count} questões`}
                                    >
                                        {concept.type === 'subject' && <Database className="w-3 h-3 group-hover:animate-bounce" />}
                                        {concept.type === 'topic' && <Hash className="w-3 h-3 group-hover:animate-rotate" />}
                                        {concept.name}
                                        <span className={clsx(
                                            "text-[10px] px-1.5 py-0.5 rounded-full font-black ml-1",
                                            concept.count > 100
                                                ? "bg-indigo-600 text-white"
                                                : "bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400"
                                        )}>
                                            {concept.count > 1000 ? `${(concept.count / 1000).toFixed(1)}k` : concept.count}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-900/40 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                            <div className="space-y-1">
                                <p className="text-slate-500 text-[10px] uppercase font-bold flex items-center gap-1.5">
                                    <Database className="w-3.5 h-3.5 text-purple-500" />
                                    <span className="text-purple-600 dark:text-purple-400">Roxo: Matérias/Disciplinas (Hard Filter)</span>
                                </p>
                                <p className="text-[10px] text-slate-400 leading-tight">A IA detectou uma matéria específica. A busca é travada APENAS nessas matérias.</p>
                            </div>
                            <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-4">
                                <p className="text-slate-500 text-[10px] uppercase font-bold flex items-center gap-1.5">
                                    <Hash className="w-3.5 h-3.5 text-blue-500" />
                                    <span className="text-blue-600 dark:text-blue-400">Azul: Assuntos (Aumento Precisão)</span>
                                </p>
                                <p className="text-[10px] text-slate-400 leading-tight">Expansão semântica para tópicos relacionados. Melhora a nota de questões que batem com esses temas.</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* SETTINGS AREA */}
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                            <Settings className="w-5 h-5 text-slate-400" />
                            Configurações em Tempo Real
                        </h2>
                        <p className="text-xs text-slate-500 mb-6">
                            As alterações aqui são salvas diretamente no banco de dados e entram em vigor imediatamente na próxima busca.
                        </p>

                        <div className="space-y-5">
                            <div>
                                <label className="flex items-center gap-3 cursor-pointer">
                                    <div className="relative">
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={configState.vector_search_enabled}
                                            onChange={(e) => setConfigState({ ...configState, vector_search_enabled: e.target.checked })}
                                        />
                                        <div className={clsx("block w-14 h-8 rounded-full transition-colors", configState.vector_search_enabled ? "bg-emerald-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                        <div className={clsx("dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform", configState.vector_search_enabled && "transform translate-x-6")}></div>
                                    </div>
                                    <div className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                        <div>
                                            <span className="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-1">
                                                Motor Semântico
                                                <div className="cursor-help text-slate-400" title="Quando ativado, o Xavier utiliza o Qdrant (Vetores) para entender o significado das perguntas, não apenas as palavras exatas.">
                                                    <HelpCircle className="w-3.5 h-3.5" />
                                                </div>
                                            </span>
                                            <p className="text-[10px] text-slate-500">Uso de Embeddings (Cérebro IA)</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <hr className="border-slate-100 dark:border-slate-700" />

                            <div>
                                <label className="flex items-center gap-3 cursor-pointer">
                                    <div className="relative">
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={configState.search_cache_enabled}
                                            onChange={(e) => setConfigState({ ...configState, search_cache_enabled: e.target.checked })}
                                        />
                                        <div className={clsx("block w-14 h-8 rounded-full transition-colors", configState.search_cache_enabled ? "bg-indigo-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                        <div className={clsx("dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform", configState.search_cache_enabled && "transform translate-x-6")}></div>
                                    </div>
                                    <div className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                        <div>
                                            <span className="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-1">
                                                Cache de Busca (L2)
                                                <div className="cursor-help text-slate-400" title="Quando ativado, economiza custos e melhora a velocidade ao reutilizar resultados de buscas idênticas ou muito próximas.">
                                                    <HelpCircle className="w-3.5 h-3.5" />
                                                </div>
                                            </span>
                                            <p className="text-[10px] text-slate-500">Persistência em MySQL (Xavier Cache)</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <hr className="border-slate-100 dark:border-slate-700" />

                            <div>
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    Tolerância Semântica (Concept Threshold)
                                </label>
                                <div className="flex items-center gap-4">
                                    <input
                                        type="range"
                                        min="0.20" max="0.95" step="0.01"
                                        value={configState.concept_detection_threshold}
                                        onChange={(e) => setConfigState({ ...configState, concept_detection_threshold: parseFloat(e.target.value) })}
                                        className="w-full accent-indigo-600"
                                    />
                                    <span className="w-12 text-center text-sm font-mono text-slate-600 dark:text-slate-400">
                                        {configState.concept_detection_threshold.toFixed(2)}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 mt-2">
                                    Define o Score mínimo para a IA associar automaticamente um conceito à busca. Valores baixos = maior expansão (pode trazer ruído).
                                </p>
                            </div>

                            <button
                                onClick={handleSaveConfig}
                                disabled={configLoading}
                                className="w-full btn btn-primary bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center gap-2 py-2"
                            >
                                {configLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                                Salvar no Banco (Runtime)
                            </button>
                        </div>
                    </div>

                    {stats && stats.config.pipeline_version && (
                        <div className="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/50 rounded-xl p-6 shadow-sm flex items-start gap-4">
                            <div className="p-3 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-lg">
                                <Database className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-800 dark:text-white">Pipeline Ativo</h3>
                                <div className="mt-1 flex items-center gap-2">
                                    <span className="font-mono text-lg font-bold text-indigo-700 dark:text-indigo-400">
                                        {stats.config.pipeline_version}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 mt-2">
                                    Versão de indexação atual. Útil para debugar se as questões já adotaram novos pesos.
                                </p>
                            </div>
                        </div>
                    )}

                    {stats && stats.jobs.recent_failures && stats.jobs.recent_failures.length > 0 && (
                        <div className="bg-white dark:bg-slate-800 rounded-xl border border-red-200 dark:border-red-900/50 p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-red-600 dark:text-red-400 mb-4 flex items-center gap-2">
                                <AlertCircle className="w-5 h-5" />
                                Últimas Falhas (Embeddings)
                            </h2>
                            <div className="space-y-3">
                                {stats.jobs.recent_failures.map((job) => (
                                    <div key={job.id} className="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-900/30 text-sm">
                                        <div className="flex justify-between items-start mb-1 text-xs">
                                            <span className="font-medium text-red-800 dark:text-red-300">#{job.id} - {job.payload}</span>
                                            <span className="text-red-500 whitespace-nowrap ml-2">{new Date(job.failed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                        </div>
                                        <p className="text-red-600 dark:text-red-400 font-mono text-[10px] break-all line-clamp-2" title={job.error_preview}>
                                            {job.error_preview}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* PRIORITIES & SEARCH TESTER */}
                <div className="space-y-6">
                    {stats && (
                        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                            <h3 className="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center justify-between">
                                <span className="flex items-center gap-2">
                                    <BarChart3 className="w-4 h-4 text-indigo-500" />
                                    Prioridades de Ranking (Regras de Pesos)
                                </span>
                                <span className="text-[10px] bg-slate-100 dark:bg-slate-700 font-mono px-2 py-0.5 rounded">Soma: {(configState.rerank_weights.vector + configState.rerank_weights.popularity + configState.rerank_weights.quality + configState.rerank_weights.recency).toFixed(2)}</span>
                            </h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                {[
                                    { key: 'vector', label: 'Similaridade Vetorial', sub: 'Contexto/IA (Cérebro)', color: 'accent-indigo-600', help: 'O quanto o significado da questão (vetores) vale na nota final. É o coração da busca semântica.' },
                                    { key: 'popularity', label: 'Popularidade', sub: 'Resolvidas/Acessos', color: 'accent-sky-500', help: 'Dá um bônus para questões que outros alunos acessam ou resolvem com frequência.' },
                                    { key: 'quality', label: 'Qualidade Pedagógica', sub: 'Banca e complexidade', color: 'accent-amber-500', help: 'Peso para questões de bancas renomadas ou com enunciados classificados como alta qualidade.' },
                                    { key: 'recency', label: 'Recência (Ano)', sub: 'Favorece o atual', color: 'accent-emerald-500', help: 'Dá preferência para questões mais novas (ex: 2024 sobre 2010), mantendo a base atualizada.' }
                                ].map(w => (
                                    <div key={w.key} className="bg-slate-50/50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <div className="flex justify-between items-center mb-1">
                                            <div>
                                                <span className="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                                    {w.label}
                                                    <div className="cursor-help text-slate-400" title={w.help}>
                                                        <HelpCircle className="w-3 h-3" />
                                                    </div>
                                                </span>
                                                <p className="text-[10px] text-slate-500">{w.sub}</p>
                                            </div>
                                            <span className="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">{(configState.rerank_weights as any)[w.key].toFixed(2)}</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="0" max="1" step="0.01"
                                            value={(configState.rerank_weights as any)[w.key]}
                                            onChange={(e) => setConfigState({
                                                ...configState,
                                                rerank_weights: { ...configState.rerank_weights, [w.key]: parseFloat(e.target.value) }
                                            })}
                                            className={clsx("w-full h-1.5 rounded-lg cursor-pointer", w.color)}
                                        />
                                    </div>
                                ))}
                            </div>
                            <p className="text-[10px] text-slate-400 mt-4 leading-relaxed italic">
                                * Se a soma for {'>'} 1.0, o sistema normaliza automaticamente. Recomendamos manter a soma em 1.0.
                            </p>

                            {/* Fixed boosts - User Profile */}
                            <div className="mt-5 border-t border-slate-100 dark:border-slate-700 pt-4">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-3">Bônus do Perfil do Usuário (Fixos)</p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div className="bg-indigo-50/50 dark:bg-indigo-900/20 p-3 rounded-xl border border-indigo-100 dark:border-indigo-800">
                                        <div className="flex justify-between items-center mb-1">
                                            <span className="text-xs font-semibold text-indigo-700 dark:text-indigo-400 flex items-center gap-1">
                                                🧠 Proficiência (Tema Fraco)
                                            </span>
                                            <span className="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-300">+0.25</span>
                                        </div>
                                        <p className="text-[10px] text-slate-500">Questões de temas que o usuário erra com frequência recebem este bônus para forçar prática/crescimento.</p>
                                    </div>
                                    <div className="bg-emerald-50/50 dark:bg-emerald-900/20 p-3 rounded-xl border border-emerald-100 dark:border-emerald-800">
                                        <div className="flex justify-between items-center mb-1">
                                            <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                                ✅ Proficiência (Tema Forte)
                                            </span>
                                            <span className="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-300">+0.05</span>
                                        </div>
                                        <p className="text-[10px] text-slate-500">Temas que o usuário domina recebem um bônus mínimo para manutenção/revisão leve.</p>
                                    </div>
                                    <div className="bg-amber-50/50 dark:bg-amber-900/20 p-3 rounded-xl border border-amber-100 dark:border-amber-800">
                                        <div className="flex justify-between items-center mb-1">
                                            <span className="text-xs font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1">
                                                🎯 Intenção (Matéria)
                                            </span>
                                            <span className="text-xs font-mono font-bold text-amber-600 dark:text-amber-300">+0.30</span>
                                        </div>
                                        <p className="text-[10px] text-slate-500">Quando a busca detecta semanticamente a matéria certa, questões dessa matéria recebem este bônus.</p>
                                    </div>
                                    <div className="bg-purple-50/50 dark:bg-purple-900/20 p-3 rounded-xl border border-purple-100 dark:border-purple-800">
                                        <div className="flex justify-between items-center mb-1">
                                            <span className="text-xs font-semibold text-purple-700 dark:text-purple-400 flex items-center gap-1">
                                                📌 Intenção (Assunto/Tópico)
                                            </span>
                                            <span className="text-xs font-mono font-bold text-purple-600 dark:text-purple-300">+0.15</span>
                                        </div>
                                        <p className="text-[10px] text-slate-500">Bônus para questões cujo tópico específico foi detectado na intenção da busca semântica.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* SEARCH TESTER (BOTTOM - FULL WIDTH) */}
                <div className="space-y-6">
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm flex flex-col h-full">
                        <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                            <Zap className="w-5 h-5 text-amber-500" />
                            Painel do Maestro (Xavier 2.0)
                        </h2>

                        {/* Warning: Qdrant empty */}
                        {stats && stats.qdrant.questions_points === 0 && (
                            <div className="mb-4 flex items-start gap-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-4">
                                <AlertCircle className="w-5 h-5 text-rose-500 mt-0.5 shrink-0" />
                                <div>
                                    <p className="text-sm font-bold text-rose-700 dark:text-rose-400">Qdrant vazio — buscas retornarão zero resultados!</p>
                                    <p className="text-xs text-rose-600 dark:text-rose-500 mt-1">O banco vetorial não possui questões indexadas. Use o botão <strong>"Indexar Questões"</strong> acima para iniciar a indexação.</p>
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleTestSearch} className="mb-6 flex gap-3 items-stretch w-full">
                            <div className="flex-1 relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                                <input
                                    id="maestro-search-input"
                                    type="text"
                                    value={searchPrompt}
                                    onChange={(e) => setSearchPrompt(e.target.value)}
                                    placeholder="Simule uma busca... ex: física menos mecânica"
                                    className="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 backdrop-blur-sm outline-none focus:ring-2 focus:ring-indigo-500 shadow-inner transition-all text-slate-800 dark:text-white"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={testLoading || !searchPrompt}
                                className="bg-gradient-to-br from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 disabled:from-slate-400 disabled:to-slate-500 text-white min-w-[60px] flex items-center justify-center rounded-2xl shadow-lg shadow-indigo-500/25 transition-all active:scale-95"
                            >
                                {testLoading ? <RefreshCw className="w-6 h-6 animate-spin" /> : <ArrowRight className="w-6 h-6" />}
                            </button>
                        </form>

                        {searchResults ? (
                            <div className="flex-1 flex flex-col lg:flex-row gap-6 overflow-hidden">
                                {/* LEFT: MAESTRO X-RAY */}
                                <div className="w-full lg:w-96 shrink-0 space-y-4 overflow-y-auto pr-2 custom-scrollbar border-r border-slate-100 dark:border-slate-700/50">
                                    <div className="flex items-center gap-2 mb-4">
                                        <Microscope className="w-4 h-4 text-indigo-500" />
                                        <h3 className="text-xs font-black uppercase tracking-widest text-slate-500">Pipeline Maestro</h3>
                                    </div>

                                    {/* Step 1: Lexical */}
                                    <PipelineStep
                                        icon={Filter}
                                        title="Análise Léxica"
                                        isActive={true}
                                        description="O Xavier separa o que você QUER (+) do que você NÃO QUER (-) na busca."
                                        helpText="Primeira camada: a IA limpa o seu texto e identifica palavras de negação (ex: 'menos', 'não') para criar filtros rígidos."
                                    >
                                        <div className="flex flex-wrap gap-1.5">
                                            {parsePipelineSteps(searchResults.logs).lexical.positive.map(t => (
                                                <span key={t} className="text-[10px] font-bold bg-blue-50 dark:bg-blue-900/30 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100 dark:border-blue-800">
                                                    +{t}
                                                </span>
                                            ))}
                                            {parsePipelineSteps(searchResults.logs).lexical.negative.map(t => (
                                                <span key={t} className="text-[10px] font-bold bg-rose-50 dark:bg-rose-900/30 text-rose-600 px-2 py-0.5 rounded-full border border-rose-100 dark:border-rose-800">
                                                    -{t}
                                                </span>
                                            ))}
                                            {parsePipelineSteps(searchResults.logs).difficulty && (
                                                <span className="text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 px-2 py-0.5 rounded-full border border-amber-100 dark:border-amber-800">
                                                    Dificuldade: {parsePipelineSteps(searchResults.logs).difficulty}
                                                </span>
                                            )}
                                            {parsePipelineSteps(searchResults.logs).temporal && (
                                                <span className="text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 px-2 py-0.5 rounded-full border border-emerald-100 dark:border-emerald-800">
                                                    Ano: {parsePipelineSteps(searchResults.logs).temporal?.operator} {parsePipelineSteps(searchResults.logs).temporal?.year}
                                                </span>
                                            )}
                                            {parsePipelineSteps(searchResults.logs).organizations.map((org, idx) => (
                                                <span key={idx} className="text-[10px] font-bold bg-purple-50 dark:bg-purple-900/30 text-purple-600 px-2 py-0.5 rounded-full border border-purple-100 dark:border-purple-800">
                                                    Banca: {org}
                                                </span>
                                            ))}
                                            {parsePipelineSteps(searchResults.logs).institutions.map((inst, idx) => (
                                                <span key={idx} className="text-[10px] font-bold bg-blue-50 dark:bg-blue-900/30 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100 dark:border-blue-800">
                                                    Instituição: {inst}
                                                </span>
                                            ))}
                                            {parsePipelineSteps(searchResults.logs).lexical.positive.length === 0 &&
                                                parsePipelineSteps(searchResults.logs).lexical.negative.length === 0 &&
                                                !parsePipelineSteps(searchResults.logs).difficulty &&
                                                !parsePipelineSteps(searchResults.logs).temporal && (
                                                    <span className="text-[10px] text-slate-400">Nenhum termo processado.</span>
                                                )}
                                        </div>
                                    </PipelineStep>

                                    {/* Step 2: Intent */}
                                    <PipelineStep
                                        icon={Target}
                                        title="Detecção de Intenção"
                                        isActive={true}
                                        description="A IA tenta adivinhar a matéria ou assunto técnico."
                                        helpText="Segunda camada: cruzamos seu texto com nossa base de Disciplinas e Tópicos. Se houver match forte (>0.45), a busca é filtrada automaticamente."
                                    >
                                        <div className="space-y-1">
                                            {parsePipelineSteps(searchResults.logs).intent.map((i, idx) => (
                                                <div key={idx} className={clsx(
                                                    "flex items-center justify-between text-[10px] p-1.5 rounded-lg border",
                                                    i.type.includes('EXCLUIR')
                                                        ? "bg-rose-50/50 border-rose-100 dark:bg-rose-900/20 dark:border-rose-800 text-rose-700 dark:text-rose-400"
                                                        : "bg-indigo-50/50 border-indigo-100 dark:bg-indigo-900/20 dark:border-indigo-800 text-indigo-700 dark:text-indigo-400"
                                                )}>
                                                    <span className="font-bold shrink-0">{i.type}:</span>
                                                    <span className="truncate ml-2 text-right">{i.value}</span>
                                                </div>
                                            ))}
                                            {parsePipelineSteps(searchResults.logs).intent.length === 0 && (
                                                <p className="text-[10px] text-slate-400 italic">Busca puramente semântica.</p>
                                            )}
                                        </div>
                                    </PipelineStep>

                                    <PipelineStep
                                        icon={Layers}
                                        title="Busca Vetorial (5 Vetores)"
                                        isActive={true}
                                        status={`${searchResults.results?.length || 0} candidatos`}
                                        description="Captura paralela de contexto nos 5 facets semânticos da questão."
                                        helpText="Terceira camada: o Qdrant busca em paralelo nos 5 vetores (statement, concept, explanation, alternatives, skills) e funde com Reciprocal Rank Fusion."
                                    >
                                        <div className="grid grid-cols-5 gap-1">
                                            {[
                                                { label: 'Stmt', color: 'emerald' },
                                                { label: 'Cncpt', color: 'emerald' },
                                                { label: 'Expl', color: 'emerald' },
                                                { label: 'Alts', color: 'blue' },
                                                { label: 'Skills', color: 'violet' },
                                            ].map(slot => (
                                                <div key={slot.label} className={clsx(
                                                    "flex flex-col items-center p-1.5 rounded-lg border",
                                                    slot.color === 'blue' ? "bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800" :
                                                    slot.color === 'violet' ? "bg-violet-50 dark:bg-violet-900/20 border-violet-100 dark:border-violet-800" :
                                                    "bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800"
                                                )}>
                                                    <div className={clsx("w-1.5 h-1.5 rounded-full mb-1",
                                                        slot.color === 'blue' ? "bg-blue-500" : slot.color === 'violet' ? "bg-violet-500" : "bg-emerald-500"
                                                    )} />
                                                    <span className={clsx("text-[8px] uppercase font-bold",
                                                        slot.color === 'blue' ? "text-blue-700 dark:text-blue-400" : slot.color === 'violet' ? "text-violet-700 dark:text-violet-400" : "text-emerald-700 dark:text-emerald-400"
                                                    )}>{slot.label}</span>
                                                </div>
                                            ))}
                                        </div>
                                        <p className="text-[9px] text-slate-400 mt-2">🔵 V2: Alts + Skills são novos no v8_qdrant_native</p>
                                    </PipelineStep>

                                    <div className="p-4 bg-slate-900 rounded-2xl border border-slate-800 shadow-inner">
                                        <h4 className="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                                            <Clock className="w-3 h-3" /> Latência API
                                        </h4>
                                        <div className="flex justify-between items-end">
                                            <span className="text-2xl font-black text-white">{searchResults.latency_ms}ms</span>
                                            <span className="text-[10px] text-slate-500 mb-1">Qdrant+Logic</span>
                                        </div>
                                    </div>
                                </div>

                                {/* RIGHT: RESULTS */}
                                <div className="flex-1 flex flex-col gap-4 overflow-hidden border-l border-slate-200 dark:border-slate-700 pl-0 lg:pl-6">
                                    <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                                        <h4 className="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">
                                            Resultados Ranqueados
                                        </h4>
                                        <span className="text-xs font-mono text-slate-400">{searchResults.results?.length} itens</span>
                                    </div>

                                    <div className="overflow-y-auto pr-2 custom-scrollbar space-y-4 pb-20">
                                        {searchResults.results?.map((res: any) => (
                                            <SearchResultItem 
                                                key={res.question_id} 
                                                res={res} 
                                                targetPipelineVersion={TARGET_PIPELINE_VERSION} 
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex-1 flex flex-col items-center justify-center text-slate-400 py-12">
                                <Search className="w-12 h-12 mb-3 text-slate-300 dark:text-slate-600" />
                                <p>Execute uma busca para inspecionar os logs do pipeline e os scores.</p>
                            </div>
                        )}
                    </div>

                    {/* RECENT SEARCHES PANEL */}
                    {stats && stats.recent_searches && (
                        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm overflow-hidden">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Activity className="w-5 h-5 text-emerald-500" />
                                    Buscas Recentes (Ao Vivo)
                                </div>
                                <span className={clsx(
                                    "text-[10px] font-bold px-2 py-0.5 rounded-full border",
                                    "bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:border-indigo-800"
                                )}>
                                    Últimas 10
                                </span>
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm whitespace-nowrap border-separate border-spacing-0">
                                    <thead>
                                        <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Data</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Usuário</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Prompt Buscado</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700 text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-700 dark:text-slate-200">
                                        {stats.recent_searches.map((search) => (
                                            <SearchRow key={search.id} search={search} onReplay={handleReplay} />
                                        ))}
                                        {stats.recent_searches.length === 0 && (
                                            <tr>
                                                <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                                                    Nenhuma busca registrada no banco ainda.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* SEARCH CACHE PANEL (L2) */}
                    {stats && stats.search_cache && (
                        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm overflow-hidden">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Database className="w-5 h-5 text-indigo-500" />
                                    Cache Semântico (L2)
                                </div>
                                <div className="flex items-center gap-2">
                                     <button 
                                        onClick={async () => {
                                            setClearingCache(true);
                                            try {
                                                await api.post('/api/v1/admin/semantic/clear-cache');
                                                toast.success('Cache semântico limpo com sucesso.');
                                                loadStats();
                                            } catch (e) {
                                                toast.error('Erro ao limpar cache.');
                                            } finally {
                                                setClearingCache(false);
                                            }
                                        }}
                                        disabled={clearingCache}
                                        className="text-[10px] font-bold text-slate-400 hover:text-red-500 flex items-center gap-1 transition-colors"
                                    >
                                        <Trash2 className="w-3.5 h-3.5" />
                                        LIMPAR CACHE
                                    </button>
                                </div>
                            </h2>
                            <p className="text-xs text-slate-500 mb-6 flex items-center gap-1.5">
                                <Info className="w-3.5 h-3.5" />
                                O cache L2 armazena os embeddings e resultados finais para acelerar buscas repetidas ou semanticamente próximas.
                            </p>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm whitespace-nowrap border-separate border-spacing-0">
                                    <thead>
                                        <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Hash (Key)</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Prompt Original</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700">Último Uso</th>
                                            <th className="font-bold text-[10px] uppercase tracking-widest px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700 text-right">Payload</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-700 dark:text-slate-200">
                                        {stats.search_cache.map((item) => (
                                            <CacheRow key={item.id} item={item} />
                                        ))}
                                        {stats.search_cache.length === 0 && (
                                            <tr>
                                                <td colSpan={4} className="px-4 py-8 text-center text-slate-500 italic">
                                                    Cache semântico está limpo no momento.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                </div>
            </div>


            {/* MODALS */}
            <IndexModal
                isOpen={isIndexModalOpen}
                onClose={() => setIsIndexModalOpen(false)}
                stats={stats}
                indexBatchLimit={indexBatchLimit}
                setIndexBatchLimit={setIndexBatchLimit}
                indexForce={indexForce}
                setIndexForce={setIndexForce}
                reindexing={reindexing}
                onReindex={handleReindex}
                targetPipelineVersion={TARGET_PIPELINE_VERSION}
            />

            <IntentModal
                isOpen={isIntentModalOpen}
                onClose={() => setIsIntentModalOpen(false)}
                stats={stats}
                intentBatchLimit={intentBatchLimit}
                setIntentBatchLimit={setIntentBatchLimit}
                intentForce={intentForce}
                setIntentForce={setIntentForce}
                reindexingIntents={reindexingIntents}
                onReindexIntents={handleReindexIntents}
                targetPipelineVersion={TARGET_PIPELINE_VERSION}
            />

            <ResetModal
                isOpen={isResetModalOpen}
                onClose={() => {
                    setIsResetModalOpen(false);
                    setResetConfirmText('');
                }}
                resetting={resetting}
                resetConfirmText={resetConfirmText}
                setResetConfirmText={setResetConfirmText}
                onReset={handleResetEmbeddings}
            />
        </>
    );
};

export default SemanticDashboard;
