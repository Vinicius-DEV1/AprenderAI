import React from 'react';
import { 
    Database, 
    AlertCircle, 
    RefreshCw, 
    Server, 
    Lightbulb, 
    Settings, 
    ChevronDown, 
    RefreshCw as RefreshIcon,
    Trash2
} from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats } from './Types';

interface SemanticHeaderProps {
    stats: DashboardStats | null;
    loading: boolean;
    loadStats: () => Promise<void>;
    setIsIndexModalOpen: (open: boolean) => void;
    reindexing: boolean;
    setIsIntentModalOpen: (open: boolean) => void;
    reindexingIntents: boolean;
    actionsMenuOpen: boolean;
    setActionsMenuOpen: (open: boolean | ((v: boolean) => boolean)) => void;
    actionsMenuRef: React.RefObject<HTMLDivElement>;
    handleClearCache: () => Promise<void>;
    clearingCache: boolean;
    handleClearQueue: () => Promise<void>;
    clearingQueue: boolean;
    setIsResetModalOpen: (open: boolean) => void;
}

const SemanticHeader: React.FC<SemanticHeaderProps> = ({
    stats,
    loading,
    loadStats,
    setIsIndexModalOpen,
    reindexing,
    setIsIntentModalOpen,
    reindexingIntents,
    actionsMenuOpen,
    setActionsMenuOpen,
    actionsMenuRef,
    handleClearCache,
    clearingCache,
    handleClearQueue,
    clearingQueue,
    setIsResetModalOpen
}) => {
    return (
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
    );
};

export default SemanticHeader;
