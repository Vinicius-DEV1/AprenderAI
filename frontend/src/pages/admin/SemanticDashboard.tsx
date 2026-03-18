import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import { toast } from 'sonner';
import { 
    Database, Activity, Search, RefreshCw, Settings, Save, Server, 
    Box, FileJson, CheckCircle2, AlertCircle, PlayCircle, ChevronDown, Clock, Lightbulb,
    Trash2, BarChart3, TrendingUp, Zap, Hash
} from 'lucide-react';
import { clsx } from 'clsx';

interface DashboardStats {
    overview: {
        mysql_published_questions: number;
        mysql_indexed_questions: number;
        mysql_total_vectors: number;
        mysql_total_concepts: number;
        mysql_indexed_concepts: number;
        mysql_total_subjects: number;
        mysql_indexed_subjects: number;
        mysql_total_topics: number;
        mysql_indexed_topics: number;
    };
    qdrant: {
        status: string;
        questions_points: number;
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
    }>;
    top_concepts?: Array<{ name: string; count: number; type?: 'concept' | 'subject' | 'topic' }>;
    config: {
        vector_search_enabled: boolean | string;
        concept_detection_threshold: number;
        qdrant_candidate_limit: number;
        final_result_limit: number;
        rerank_weights: Record<string, number>;
        pipeline_version?: string;
    };
}

const StatCard = ({ title, value, subtitle, icon: Icon, colorClass }: any) => (
    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 flex flex-col items-start shadow-sm hover:shadow transition-shadow">
        <div className={clsx("p-3 rounded-lg mb-4", colorClass)}>
            <Icon className="w-6 h-6" />
        </div>
        <div className="flex flex-col">
            <h3 className="text-slate-500 dark:text-slate-400 text-sm font-medium mb-1">{title}</h3>
            <span className="text-2xl font-bold text-slate-800 dark:text-white">{value}</span>
            {subtitle && <span className="text-xs text-slate-400 mt-1">{subtitle}</span>}
        </div>
    </div>
);

const SemanticDashboard = () => {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [configLoading, setConfigLoading] = useState(false);
    const [testLoading, setTestLoading] = useState(false);
    const [reindexing, setReindexing] = useState(false);
    const [reindexingConcepts, setReindexingConcepts] = useState(false);
    const [clearingCache, setClearingCache] = useState(false);
    const [resetting, setResetting] = useState(false);
    const [isResetModalOpen, setIsResetModalOpen] = useState(false);
    const [resetConfirmText, setResetConfirmText] = useState('');
    
    // Config form
    const [configState, setConfigState] = useState({
        vector_search_enabled: true,
        concept_detection_threshold: 0.45,
    });

    // Index Modal (Questions)
    const [isIndexModalOpen, setIsIndexModalOpen] = useState(false);
    const [indexBatchLimit, setIndexBatchLimit] = useState(50);
    const [indexForce, setIndexForce] = useState(false);

    // Concept Index Modal
    const [isConceptModalOpen, setIsConceptModalOpen] = useState(false);
    const [conceptBatchLimit, setConceptBatchLimit] = useState(100);
    const [conceptForce, setConceptForce] = useState(false);

    // Test search
    const [searchPrompt, setSearchPrompt] = useState('');
    const [searchResults, setSearchResults] = useState<any>(null);

    const loadStats = async () => {
        try {
            setLoading(true);
            const res = await api.get('/api/v1/admin/semantic');
            setStats(res.data);
            setConfigState({
                vector_search_enabled: res.data.config.vector_search_enabled == 1 || res.data.config.vector_search_enabled == true,
                concept_detection_threshold: parseFloat(res.data.config.concept_detection_threshold) || 0.45,
            });
        } catch (error) {
            toast.error('Erro ao carregar os dados do dashboard.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadStats();

        // Auto-refresh every 10 seconds to show indexing progress
        const interval = setInterval(() => {
            // Only refresh if not already loading and not in the middle of a test search
            if (!loading && !testLoading) {
                loadStats();
            }
        }, 10000);

        return () => clearInterval(interval);
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
        }
    };

    const handleReindexConcepts = async () => {
        try {
            setReindexingConcepts(true);
            const res = await api.post('/api/v1/admin/semantic/reindex-concepts', {
                limit: conceptBatchLimit,
                force: conceptForce
            });
            toast.success(res.data.message || 'Indexação de conceitos iniciada!');
            setIsConceptModalOpen(false);
            loadStats();
        } catch (error) {
            toast.error('Erro ao disparar indexação de conceitos.');
        } finally {
            setReindexingConcepts(false);
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
        <div className="p-6 max-w-7xl mx-auto space-y-8">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <Database className="w-6 h-6 text-indigo-500" />
                        Xavier Semantic Engine
                    </h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Monitoramento, configuração e testes do motor de busca vetorial.
                    </p>
                </div>
                
                {stats?.qdrant?.index_version_status && (
                    (stats.qdrant.index_version_status.questions.status === 'outdated' || 
                     stats.qdrant.index_version_status.concepts.status === 'outdated') && (
                        <div className="hidden lg:flex items-center gap-2 bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 px-4 py-2 rounded-lg border border-rose-200 dark:border-rose-800 animate-pulse">
                            <AlertCircle className="w-5 h-5" />
                            <span className="text-sm font-medium">Re-indexação Necessária</span>
                        </div>
                    )
                )}

                <div className="flex gap-3">
                    <button 
                        onClick={loadStats} 
                        className="btn btn-secondary flex items-center gap-2"
                        title="Atualizar Dados"
                    >
                        <RefreshCw className={clsx("w-4 h-4", loading && "animate-spin")} />
                    </button>
                    <button 
                        onClick={handleClearCache}
                        disabled={clearingCache}
                        className="btn btn-secondary text-red-600 border-red-200 hover:bg-red-50 flex items-center gap-2"
                        title="Limpar Cache de Busca (Não apaga vetores)"
                    >
                        {clearingCache ? <RefreshCw className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
                        Limpar Cache
                    </button>
                    <button 
                        onClick={() => setIsResetModalOpen(true)}
                        className="btn btn-secondary text-rose-700 bg-rose-100 border-rose-200 hover:bg-rose-200 flex items-center gap-2"
                        title="Reset Completo (APAGA TUDO E RE-INDEXA)"
                    >
                        <Trash2 className="w-4 h-4" />
                        Reset Completo
                    </button>
                    <button 
                        onClick={() => setIsIndexModalOpen(true)}
                        disabled={reindexing}
                        className="btn btn-primary bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-2"
                    >
                        {reindexing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Server className="w-4 h-4" />}
                        Indexar Questões
                    </button>
                    <button 
                        onClick={() => setIsConceptModalOpen(true)}
                        className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-lg shadow-purple-500/25 transition-all active:scale-95"
                    >
                        {reindexingConcepts ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Lightbulb className="w-4 h-4" />}
                        Indexar Intenções
                    </button>
                </div>
            </div>

            {/* OVERVIEW STATS */}
            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                    <StatCard 
                        title="Status Qdrant" 
                        value={stats.qdrant.status.toUpperCase()} 
                        subtitle={`${stats.qdrant.questions_points} pts em Questions`}
                        icon={stats.qdrant.status === 'online' ? CheckCircle2 : AlertCircle}
                        colorClass={stats.qdrant.status === 'online' ? "bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30" : "bg-red-100 text-red-600 dark:bg-red-900/30"}
                    />
                    <StatCard 
                        title="Questões Vetorizadas" 
                        value={`${stats.overview.mysql_indexed_questions} / ${stats.overview.mysql_published_questions}`} 
                        subtitle={`Total Vetores: ${stats.overview.mysql_total_vectors}`}
                        icon={Box}
                        colorClass="bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                    />
                    <StatCard 
                        title="Buscas & Cache (L1+L2)" 
                        value={`${((stats.performance.l1_cache_hits + stats.performance.l2_cache_hits) / (stats.performance.total_searches || 1) * 100).toFixed(1)}%`} 
                        subtitle={`L1: ${stats.performance.l1_cache_hits} | L2: ${stats.performance.l2_cache_hits}`}
                        icon={Activity}
                        colorClass="bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                    />
                    <StatCard 
                        title="Conceitos Vetorizados" 
                        value={`${stats.overview.mysql_indexed_concepts} / ${stats.overview.mysql_total_concepts}`} 
                        subtitle={`Qdrant: ${stats.qdrant.concepts_points} pontos`}
                        icon={Lightbulb}
                        colorClass="bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                    />
                    <StatCard 
                        title="Status de Fila (Embeddings)" 
                        value={`${stats.jobs.pending}`} 
                        subtitle={`${stats.jobs.failed} falhas registradas`}
                        icon={stats.jobs.failed > 0 ? AlertCircle : CheckCircle2}
                        colorClass={stats.jobs.failed > 0 ? "bg-red-100 text-red-600 dark:bg-red-900/30" : "bg-blue-100 text-blue-600 dark:bg-blue-900/30"}
                    />
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
                            O formato dos dados salvos no Qdrant está desatualizado ou vazio em relação ao código atual do Painel 
                            (Versão esperada: <span className="font-mono bg-rose-100 dark:bg-rose-900/50 px-1 rounded">{stats.qdrant.index_version_status.expected}</span>). 
                            A busca semântica pode falhar ou retornar resultados de baixa precisão até que as coleções sejam re-indexadas.
                        </p>
                        <div className="space-y-2 text-sm">
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

            {/* ANALYTICS ROW  — absorvido do antigo Xavier Insights */}
            {stats && stats.analytics && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Success Rate Card */}
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                            <TrendingUp className="w-4 h-4 text-emerald-500" />
                            Taxa de Sucesso
                        </h3>
                        <div className="flex items-end gap-3">
                            <span className="text-4xl font-bold text-emerald-600 dark:text-emerald-400">
                                {stats.analytics.success_rate}%
                            </span>
                            <span className="text-xs text-slate-400 mb-1">
                                de {stats.analytics.total_ai_requests} buscas
                            </span>
                        </div>
                    </div>

                    {/* Top Searched Terms */}
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                            <Zap className="w-4 h-4 text-amber-500" />
                            Top 5 Termos Buscados
                        </h3>
                        <div className="space-y-2">
                            {stats.analytics.top_prompts.length > 0 ? stats.analytics.top_prompts.map((item, idx) => (
                                <div key={idx} className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <span className="text-xs font-bold text-slate-400 w-4 shrink-0">#{idx + 1}</span>
                                        <span className="text-sm text-slate-700 dark:text-slate-300 truncate" title={item.prompt}>
                                            {item.prompt}
                                        </span>
                                    </div>
                                    <span className="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded shrink-0">
                                        {item.total}x
                                    </span>
                                </div>
                            )) : (
                                <p className="text-xs text-slate-400">Nenhuma busca registrada.</p>
                            )}
                        </div>
                    </div>

                    {/* 7-Day Chart */}
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                            <BarChart3 className="w-4 h-4 text-indigo-500" />
                            Volume de Buscas (7 dias)
                        </h3>
                        {stats.analytics.chart_data.length > 0 ? (
                            <div className="flex items-end gap-1 h-24">
                                {stats.analytics.chart_data.map((day, idx) => {
                                    const maxCount = Math.max(...stats.analytics.chart_data.map(d => d.count), 1);
                                    const heightPct = (day.count / maxCount) * 100;
                                    return (
                                        <div key={idx} className="flex-1 flex flex-col items-center gap-1" title={`${day.date}: ${day.count} buscas (${day.success} ok, ${day.failed} falhas)`}>
                                            <div className="w-full flex flex-col justify-end" style={{ height: '80px' }}>
                                                <div 
                                                    className="w-full rounded-t bg-gradient-to-t from-indigo-600 to-indigo-400 dark:from-indigo-500 dark:to-indigo-300 transition-all hover:opacity-80"
                                                    style={{ height: `${Math.max(heightPct, 4)}%` }}
                                                />
                                            </div>
                                            <span className="text-[9px] text-slate-400 font-mono">
                                                {day.date.slice(-2)}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="h-24 flex items-center justify-center text-xs text-slate-400 italic">Sem dados nos últimos 7 dias</div>
                        )}
                    </div>
                </div>
            )}

            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard 
                        title="Taxa de Sucesso (IA)"
                        value={`${stats.analytics.success_rate}%`}
                        subtitle={`${stats.analytics.total_ai_requests} buscas totais`}
                        icon={TrendingUp}
                        colorClass="bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400"
                    />

                    <StatCard 
                        title="Cache Semântico"
                        value={stats.performance.total_cache_entries}
                        subtitle={`${stats.performance.l2_cache_hits} hits acumulados`}
                        icon={Zap}
                        colorClass="bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400"
                    />

                    <StatCard 
                        title="Disciplinas Indexadas"
                        value={`${stats.overview.mysql_indexed_subjects} / ${stats.overview.mysql_total_subjects}`}
                        subtitle="Vetorizadas para intenção"
                        icon={Database}
                        colorClass="bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400"
                    />

                    <StatCard 
                        title="Tópicos Indexados"
                        value={`${stats.overview.mysql_indexed_topics} / ${stats.overview.mysql_total_topics}`}
                        subtitle="Aumento de precisão"
                        icon={Hash}
                        colorClass="bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400"
                    />
                </div>
            )}

            {/* CONCEPT CLOUD ROW */}
            {stats && stats.top_concepts && (
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-2">
                            <Box className="w-5 h-5 text-indigo-500" />
                            <h2 className="text-lg font-bold text-slate-800 dark:text-white">Nuvem de Conceitos & Disciplinas (Qdrant)</h2>
                        </div>
                        <div className="flex gap-4 text-xs font-medium">
                            <span className="flex items-center gap-1.5 text-slate-500"><div className="w-2.5 h-2.5 rounded-full bg-slate-100 dark:bg-slate-700"></div> Conceitos</span>
                            <span className="flex items-center gap-1.5 text-purple-600"><div className="w-2.5 h-2.5 rounded-full bg-purple-100 dark:bg-purple-900/40 border border-purple-200 dark:border-purple-700"></div> Disciplinas</span>
                        </div>
                    </div>
                    
                    <div className="flex flex-wrap gap-2">
                        {stats.top_concepts.map((concept, i) => (
                            <div 
                                key={i}
                                className={clsx(
                                    "px-3 py-1.5 rounded-full text-sm font-medium flex items-center gap-2 transition-all hover:scale-105 border",
                                    concept.type === 'subject' 
                                        ? "bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-700" 
                                        : "bg-slate-50 text-slate-700 border-slate-100 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600"
                                )}
                                title={concept.type === 'subject' ? 'Disciplina (Subject)' : 'Conceito'}
                            >
                                {concept.name}
                                <span className={clsx(
                                    "text-[10px] px-1.5 rounded font-bold",
                                    concept.count > 10 
                                        ? "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400" 
                                        : "bg-black/5 dark:bg-white/10 text-slate-500 dark:text-slate-400"
                                )}>
                                    {concept.count}
                                    {concept.count > 10 && <Zap className="w-2.5 h-2.5 inline ml-0.5" />}
                                </span>
                            </div>
                        ))}
                    </div>
                    <p className="mt-4 text-xs text-slate-400 italic flex items-center gap-1.5">
                        <Lightbulb className="w-3.5 h-3.5" />
                        Termos em <span className="text-purple-600 dark:text-purple-400 font-bold">roxo</span> são Disciplinas (Subjects). Quando detectados, forçam filtros de alta precisão na busca.
                    </p>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* SETTINGS PANEL */}
                <div className="lg:col-span-1 space-y-6">
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
                                            onChange={(e) => setConfigState({...configState, vector_search_enabled: e.target.checked})}
                                        />
                                        <div className={clsx("block w-14 h-8 rounded-full transition-colors", configState.vector_search_enabled ? "bg-emerald-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                        <div className={clsx("dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform", configState.vector_search_enabled && "transform translate-x-6")}></div>
                                    </div>
                                    <div>
                                        <span className="font-medium text-slate-800 dark:text-white">Motor Semântico</span>
                                        <p className="text-xs text-slate-500">Habilita ou desabilita o pipeline Xavier na plataforma toda.</p>
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
                                        onChange={(e) => setConfigState({...configState, concept_detection_threshold: parseFloat(e.target.value)})}
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
                                            <span className="text-red-500 whitespace-nowrap ml-2">{new Date(job.failed_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                        </div>
                                        <p className="text-red-600 dark:text-red-400 font-mono text-[10px] break-all line-clamp-2" title={job.error_preview}>
                                            {job.error_preview}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {stats && (
                        <div className="bg-slate-900 rounded-xl border border-slate-800 p-6 shadow-sm overflow-hidden text-sm">
                           <h3 className="text-indigo-400 font-medium mb-3 flex items-center gap-2"><FileJson className="w-4 h-4"/> Rerank Weights Dump</h3>
                           <pre className="text-slate-300 font-mono text-xs overflow-x-auto whitespace-pre-wrap">
                               {JSON.stringify(stats.config.rerank_weights, null, 2)}
                           </pre>
                        </div>
                    )}
                </div>

                {/* SEARCH TESTER */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm flex flex-col h-full">
                        <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                            <Search className="w-5 h-5 text-indigo-500" />
                            Console de Busca Avançado (Debug)
                        </h2>
                        
                        <form onSubmit={handleTestSearch} className="mb-6 flex gap-3 items-stretch w-full">
                            <input 
                                type="text"
                                value={searchPrompt}
                                onChange={(e) => setSearchPrompt(e.target.value)}
                                placeholder="Digite uma busca para simular a visão da IA (ex: perguntas de matemática nivel medio)"
                                className="flex-1 min-w-0 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 dark:text-white"
                            />
                            <button 
                                type="submit" 
                                disabled={testLoading || !searchPrompt}
                                className="flex-none bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-400 text-white px-6 py-2.5 flex items-center justify-center rounded-lg shadow-sm transition-colors"
                            >
                                {testLoading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <PlayCircle className="w-5 h-5" />}
                            </button>
                        </form>

                        {searchResults ? (
                            <div className="flex-1 flex flex-col gap-4 overflow-hidden">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-100 dark:border-slate-700">
                                        <h4 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Motor Xavier (Vectorial)</h4>
                                        <p className="text-xs text-slate-500 mb-1">Latência Total: <span className="font-mono text-emerald-600">{searchResults.latency_ms}ms</span></p>
                                        <div className="flex justify-between items-center text-xs text-slate-500 mb-1">
                                            <span>Qdrant Retornou:</span>
                                            <span className="font-mono text-slate-700 dark:text-white">{searchResults.results?.length} candidatos</span>
                                        </div>
                                        <div className="flex justify-between items-center text-xs text-slate-500">
                                            <span>Tolerância Ativa:</span>
                                            <span className="font-mono font-bold text-indigo-500">{stats?.config?.concept_detection_threshold || 'v5'}</span>
                                        </div>
                                    </div>
                                    <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg border border-slate-100 dark:border-slate-700">
                                        <h4 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Motor Antigo (SQL Fallback)</h4>
                                        <p className="text-xs text-slate-500 mb-1">Latência Simulada: <span className="font-mono text-amber-600">{searchResults.sql_fallback.latency_ms}ms</span></p>
                                        <p className="text-xs text-slate-500 mb-2 whitespace-nowrap">
                                            Status: {searchResults.sql_fallback.cache_hit 
                                                ? <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">No Cache L2</span> 
                                                : <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">Precisaria LLM (Async)</span>}
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-2 bg-slate-900 text-slate-300 font-mono text-xs p-3 rounded-lg overflow-y-auto max-h-40 border border-slate-800">
                                    {searchResults.logs?.map((log: string, idx: number) => (
                                        <div key={idx} className="mb-1 text-slate-400">
                                            <span className="text-indigo-400">[{idx+1}]</span> {log}
                                        </div>
                                    ))}
                                </div>

                                {searchResults.results?.some((r: any) => r.source === 'sql_fallback') && (
                                    <div className="mt-4 bg-orange-50 dark:bg-orange-900/20 border-l-4 border-orange-500 p-4 rounded-r-lg">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <AlertCircle className="h-5 w-5 text-orange-500 dark:text-orange-400" />
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm text-orange-800 dark:text-orange-300">
                                                    <strong>Aviso Explicito: Plano B Ativado!</strong> Nenhum vetor foi encontrado no Qdrant com nota suficiente (ou a coleção está vazia). O sistema recorreu ao motor <strong>SQL Fallback</strong> buscando apenas palavras-chave textuais no MySQL. A nota Vetorial (VEC) destes resultados será sempre 0.000.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <h4 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mt-6 border-b border-slate-200 dark:border-slate-700 pb-2">
                                    Resultados Ranqueados ({searchResults.results?.length})
                                </h4>
                                <div className="overflow-y-auto pr-2 max-h-[500px] space-y-4">
                                    {searchResults.results?.map((res: any) => (
                                        <details key={res.question_id} className="group mb-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm hover:border-indigo-300 transition-colors overflow-hidden">
                                            <summary className="flex justify-between items-start p-3 cursor-pointer list-none">
                                                <div className="flex flex-col gap-1 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="flex items-center justify-center min-w-[24px] h-6 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300">
                                                            #{res.rank}
                                                        </span>
                                                        <span className="text-sm font-bold text-slate-800 dark:text-white">Questão #{res.question_id}</span>
                                                        <div className="flex gap-1 flex-wrap">
                                                            {res.subjects?.map((s: string) => (
                                                                <span key={s} className="text-[9px] bg-slate-100 dark:bg-slate-700 text-slate-500 px-1.5 py-0.5 rounded">{s}</span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                    <p className="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 group-open:hidden pr-4">
                                                        {res.statement.replace(/<[^>]*>?/gm, '')}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2 shrink-0">
                                                    <div className="flex flex-col items-end gap-1">
                                                        <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                                                            Vec: {res.qdrant_score.toFixed(3)}
                                                        </span>
                                                        <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800">
                                                            Final: {res.final_score.toFixed(3)}
                                                        </span>
                                                    </div>
                                                    <ChevronDown className="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform mt-1" />
                                                </div>
                                            </summary>
                                            
                                            <div className="p-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 space-y-4">
                                                
                                                {res.score_details && Object.keys(res.score_details).length > 0 && (
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg p-3">
                                                        <h5 className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 border-b border-slate-200 dark:border-slate-700 pb-1 flex justify-between">
                                                            <span>📊 Composição da Nota Final (Weights)</span>
                                                            <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">FINAL: {res.final_score.toFixed(4)}</span>
                                                        </h5>
                                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
                                                            <div className="flex flex-col bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-slate-100 dark:border-slate-700/50">
                                                                <span className="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Semântica VEC</span>
                                                                <span className="text-sm font-mono text-indigo-600 dark:text-indigo-400 font-bold">+{res.score_details.vector.weighted.toFixed(4)}</span>
                                                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.vector.raw.toFixed(4)}</span>
                                                            </div>
                                                            <div className="flex flex-col bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-slate-100 dark:border-slate-700/50">
                                                                <span className="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Popularidade</span>
                                                                <span className="text-sm font-mono text-sky-600 dark:text-sky-400 font-bold">+{res.score_details.popularity.weighted.toFixed(4)}</span>
                                                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.popularity.raw.toFixed(4)}</span>
                                                            </div>
                                                            <div className="flex flex-col bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-slate-100 dark:border-slate-700/50">
                                                                <span className="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Qualidade Ped.</span>
                                                                <span className="text-sm font-mono text-amber-600 dark:text-amber-400 font-bold">+{res.score_details.quality.weighted.toFixed(4)}</span>
                                                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.quality.raw.toFixed(4)}</span>
                                                            </div>
                                                            <div className="flex flex-col bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-slate-100 dark:border-slate-700/50">
                                                                <span className="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Recência Ano</span>
                                                                <span className="text-sm font-mono text-emerald-600 dark:text-emerald-400 font-bold">+{res.score_details.recency.weighted.toFixed(4)}</span>
                                                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.recency.raw.toFixed(4)}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="prose prose-sm dark:prose-invert max-w-none mt-2">
                                                    <h5 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Enunciado / Contexto</h5>
                                                    <div className="text-sm text-slate-800 dark:text-slate-200" dangerouslySetInnerHTML={{ __html: res.statement }} />
                                                </div>

                                                {res.alternatives?.length > 0 && (
                                                    <div className="space-y-2">
                                                        <h5 className="text-xs font-bold text-slate-400 uppercase tracking-widest">Alternativas</h5>
                                                        <div className="grid gap-2">
                                                            {res.alternatives.map((alt: any) => (
                                                                <div key={alt.label} className={clsx(
                                                                    "p-2.5 rounded border text-sm flex gap-3",
                                                                    alt.is_correct 
                                                                        ? "bg-emerald-50/50 border-emerald-200 dark:bg-emerald-900/10 dark:border-emerald-800/50" 
                                                                        : "bg-slate-50/50 border-slate-100 dark:bg-slate-900/20 dark:border-slate-800"
                                                                )}>
                                                                    <span className={clsx("font-bold", alt.is_correct ? "text-emerald-600" : "text-slate-400")}>{alt.label})</span>
                                                                    <div dangerouslySetInnerHTML={{ __html: alt.content }} />
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {res.explanation && (
                                                    <div className="bg-amber-50/30 dark:bg-amber-900/10 border border-amber-100/50 dark:border-amber-800/30 p-3 rounded-lg">
                                                        <h5 className="text-xs font-bold text-amber-600/70 dark:text-amber-500/70 uppercase tracking-widest mb-1">Explicação / Resolução</h5>
                                                        <div className="text-xs text-slate-700 dark:text-slate-300" dangerouslySetInnerHTML={{ __html: res.explanation }} />
                                                    </div>
                                                )}
                                            </div>
                                        </details>
                                    ))}
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
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                <Activity className="w-5 h-5 text-emerald-500" />
                                Buscas Recentes (Ao Vivo)
                            </h2>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm whitespace-nowrap border-collapse">
                                    <thead>
                                        <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                                            <th className="font-medium px-4 py-3 bg-slate-50 dark:bg-slate-800/50">Data</th>
                                            <th className="font-medium px-4 py-3 bg-slate-50 dark:bg-slate-800/50">Usuário</th>
                                            <th className="font-medium px-4 py-3 bg-slate-50 dark:bg-slate-800/50">Prompt Buscado</th>
                                            <th className="font-medium px-4 py-3 bg-slate-50 dark:bg-slate-800/50">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-700 dark:text-slate-200">
                                        {stats.recent_searches.map((search) => (
                                            <tr key={search.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors">
                                                <td className="px-4 py-3 text-xs text-slate-500">
                                                    <div className="flex items-center gap-1.5 flex-nowrap shrink-0">
                                                        <Clock className="w-3.5 h-3.5" />
                                                        {search.created_at}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-400 flex items-center justify-center text-xs font-bold shrink-0">
                                                            {search.user_name.charAt(0).toUpperCase()}
                                                        </div>
                                                        <span className="truncate max-w-[120px]">{search.user_name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 font-medium text-slate-800 dark:text-white whitespace-normal break-words max-w-sm">
                                                    “{search.prompt}”
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={clsx(
                                                        "inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase border",
                                                        search.status === 'completed' ? "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50" :
                                                        search.status === 'failed' ? "bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50" :
                                                        "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50"
                                                    )}>
                                                        {search.status}
                                                    </span>
                                                </td>
                                            </tr>
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
                </div>
            </div>
            {/* INDEX MODAL */}
            {isIndexModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div className="p-6">
                            <div className="flex justify-between items-start mb-4">
                                <h3 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <Server className="w-6 h-6 text-indigo-500" />
                                    Batch Indexer Xavier
                                </h3>
                                <button onClick={() => setIsIndexModalOpen(false)} className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                    <RefreshCw className="w-5 h-5" style={{ transform: 'rotate(45deg)' }} />
                                </button>
                            </div>
                            
                            <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                                Dispare jobs de vetorização controlada para economizar nos custos de API.
                                <span className="block mt-1 text-[11px] font-mono text-indigo-500 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-900/20 w-fit px-1.5 py-0.5 rounded">
                                    Pipeline Ativo: {stats?.config.pipeline_version || 'v6_intent_unification'}
                                </span>
                            </p>

                            <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl p-4 mb-6">
                                <div className="flex justify-between items-center mb-1">
                                    <span className="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wider">
                                        {indexForce ? 'Total de Questões para Re-indexar' : 'Pendentes de Indexação'}
                                    </span>
                                    <span className={clsx("text-lg font-bold", indexForce ? "text-indigo-600 dark:text-indigo-400" : "text-amber-800 dark:text-amber-200")}>
                                        {stats ? (
                                            indexForce 
                                                ? stats.overview.mysql_published_questions 
                                                : stats.overview.mysql_published_questions - stats.overview.mysql_indexed_questions
                                        ) : '...'}
                                    </span>
                                </div>
                                <p className="text-[10px] text-amber-600 dark:text-amber-500">
                                    {indexForce 
                                        ? "Modo FORÇAR ativado: Todas as questões publicadas no banco serão processadas novamente."
                                        : "Questões publicadas (exceto redações) que ainda não possuem vetores sincronizados."}
                                </p>
                            </div>

                            <div className="space-y-2 mb-8">
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Quantas questões deseja indexar nesta leva?
                                </label>
                                <input 
                                    type="number" 
                                    min="1" max="5000"
                                    value={indexBatchLimit}
                                    onChange={(e) => setIndexBatchLimit(parseInt(e.target.value) || 0)}
                                    className="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-indigo-500 outline-none text-slate-800 dark:text-white"
                                />
                                <p className="text-[10px] text-slate-400">
                                    Custo estimado: aprox. ${(indexBatchLimit * 0.0001).toFixed(4)} USD (estimativa baseada em texto médio).
                                </p>
                            </div>

                            <div className="mb-6">
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <div className="relative">
                                        <input 
                                            type="checkbox" 
                                            className="sr-only" 
                                            checked={indexForce}
                                            onChange={(e) => setIndexForce(e.target.checked)}
                                        />
                                        <div className={clsx("block w-10 h-6 rounded-full transition-colors", indexForce ? "bg-amber-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                        <div className={clsx("dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform", indexForce && "transform translate-x-4")}></div>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Forçar Re-indexação</span>
                                        <p className="text-[10px] text-slate-500">Ignora se a questão já possui vetores e atualiza com a nova lógica.</p>
                                    </div>
                                </label>
                            </div>

                            <div className="flex gap-3">
                                <button 
                                    onClick={() => setIsIndexModalOpen(false)}
                                    className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button 
                                    onClick={handleReindex}
                                    disabled={reindexing || indexBatchLimit <= 0}
                                    className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-indigo-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                                >
                                    {reindexing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <PlayCircle className="w-4 h-4" />}
                                    Iniciar Batch
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* CONCEPT INDEX MODAL */}
            {isConceptModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div className="p-6">
                            <div className="flex justify-between items-start mb-4">
                                <h3 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <Lightbulb className="w-6 h-6 text-purple-500" />
                                    Indexar Entidades Semânticas
                                </h3>
                                <button onClick={() => setIsConceptModalOpen(false)} className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                    <RefreshCw className="w-5 h-5" style={{ transform: 'rotate(45deg)' }} />
                                </button>
                            </div>
                            
                            <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                                Vetorize Disciplinas (Subjects), Tópicos e Conceitos no Qdrant para a Detecção de Intenção da busca semântica.
                                <span className="block mt-1 text-[11px] font-mono text-purple-500 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/20 w-fit px-1.5 py-0.5 rounded">
                                    Pipeline Ativo: {stats?.config.pipeline_version || 'v6_intent_unification'}
                                </span>
                            </p>

                            <div className="grid grid-cols-1 gap-3 mb-6">
                                {/* Subjects Stat */}
                                <div className="bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800 rounded-xl p-3">
                                    <div className="flex justify-between items-center">
                                        <span className="text-[10px] font-medium text-purple-700 dark:text-purple-400 uppercase tracking-wider">Disciplinas Pendentes</span>
                                        <span className="text-sm font-bold text-purple-800 dark:text-purple-200">
                                            {stats ? (conceptForce ? stats.overview.mysql_total_subjects : stats.overview.mysql_total_subjects - stats.overview.mysql_indexed_subjects) : '...'}
                                        </span>
                                    </div>
                                </div>

                                {/* Topics Stat */}
                                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-3">
                                    <div className="flex justify-between items-center">
                                        <span className="text-[10px] font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wider">Tópicos Pendentes</span>
                                        <span className="text-sm font-bold text-blue-800 dark:text-blue-200">
                                            {stats ? (conceptForce ? stats.overview.mysql_total_topics : stats.overview.mysql_total_topics - stats.overview.mysql_indexed_topics) : '...'}
                                        </span>
                                    </div>
                                </div>

                                {/* Concepts Stat */}
                                <div className="bg-slate-50 dark:bg-slate-900/20 border border-slate-100 dark:border-slate-800 rounded-xl p-3">
                                    <div className="flex justify-between items-center">
                                        <span className="text-[10px] font-medium text-slate-700 dark:text-slate-400 uppercase tracking-wider">Conceitos Pendentes</span>
                                        <span className="text-sm font-bold text-slate-800 dark:text-slate-200">
                                            {stats ? (conceptForce ? stats.overview.mysql_total_concepts : stats.overview.mysql_total_concepts - stats.overview.mysql_indexed_concepts) : '...'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2 mb-8">
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Quantos conceitos indexar nesta leva?
                                </label>
                                <input 
                                    type="number" 
                                    min="1" max="5000"
                                    value={conceptBatchLimit}
                                    onChange={(e) => setConceptBatchLimit(parseInt(e.target.value) || 0)}
                                    className="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-purple-500 outline-none text-slate-800 dark:text-white"
                                />
                            </div>

                            <div className="mb-6">
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <div className="relative">
                                        <input 
                                            type="checkbox" 
                                            className="sr-only" 
                                            checked={conceptForce}
                                            onChange={(e) => setConceptForce(e.target.checked)}
                                        />
                                        <div className={clsx("block w-10 h-6 rounded-full transition-colors", conceptForce ? "bg-purple-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                        <div className={clsx("dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform", conceptForce && "transform translate-x-4")}></div>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Forçar Re-indexação</span>
                                        <p className="text-[10px] text-slate-500">Re-indexa com novos IDs SHA-256 e formato de embedding atualizado.</p>
                                    </div>
                                </label>
                            </div>

                            <div className="flex gap-3">
                                <button 
                                    onClick={() => setIsConceptModalOpen(false)}
                                    className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button 
                                    onClick={handleReindexConcepts}
                                    disabled={reindexingConcepts || conceptBatchLimit <= 0}
                                    className="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                                >
                                    {reindexingConcepts ? <RefreshCw className="w-4 h-4 animate-spin" /> : <PlayCircle className="w-4 h-4" />}
                                    Iniciar Batch
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* RESET EMBEDDINGS MODAL */}
            {isResetModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-red-200 dark:border-red-900/50 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div className="bg-red-600 p-6 text-white">
                            <h3 className="text-xl font-bold flex items-center gap-2">
                                <Trash2 className="w-6 h-6" />
                                Reset Completo da Busca
                            </h3>
                            <p className="text-red-100 text-sm mt-2">
                                Esta é uma ação destrutiva irreversível.
                            </p>
                        </div>
                        
                        <div className="p-6">
                            <div className="space-y-4 mb-6">
                                <div className="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm border border-red-100 dark:border-red-800">
                                    <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                                    <ul className="list-disc pl-4 space-y-1">
                                        <li>Apagará TODAS as coleções do Qdrant.</li>
                                        <li>Limpará as tabelas question_vectors e ai_search_cache no MySQL.</li>
                                        <li>Zerar o status de indexação de todos os conceitos.</li>
                                        <li><strong>Você terá que rodar "Indexar Questões" e "Indexar Conceitos" novamente do zero.</strong></li>
                                    </ul>
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Para confirmar, digite <strong>RESET</strong> no campo abaixo:
                                    </label>
                                    <input 
                                        type="text" 
                                        placeholder="Digite RESET"
                                        value={resetConfirmText}
                                        onChange={(e) => setResetConfirmText(e.target.value)}
                                        className="w-full rounded-lg border-red-300 dark:border-red-700/50 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-red-500 outline-none text-red-600 dark:text-red-400 font-bold uppercase"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <button 
                                    onClick={() => {
                                        setIsResetModalOpen(false);
                                        setResetConfirmText('');
                                    }}
                                    className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button 
                                    onClick={handleResetEmbeddings}
                                    disabled={resetting || resetConfirmText !== 'RESET'}
                                    className="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-red-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                                >
                                    {resetting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
                                    Executar Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default SemanticDashboard;
