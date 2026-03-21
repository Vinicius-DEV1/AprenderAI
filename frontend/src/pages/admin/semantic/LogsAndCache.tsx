import React from 'react';
import { Activity, Database, Info, Trash2 } from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats } from './Types';
import SearchRow from './SearchRow';
import CacheRow from './CacheRow';

interface LogsAndCacheProps {
    stats: DashboardStats;
    handleReplay: (prompt: string) => void;
    handleClearCache: () => Promise<void>;
    clearingCache: boolean;
}

const LogsAndCache: React.FC<LogsAndCacheProps> = ({
    stats,
    handleReplay,
    handleClearCache,
    clearingCache
}) => {
    return (
        <div className="space-y-6">
            {/* RECENT SEARCHES PANEL */}
            {stats.recent_searches && (
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
            {stats.search_cache && (
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm overflow-hidden">
                    <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Database className="w-5 h-5 text-indigo-500" />
                            Cache Semântico (L2)
                        </div>
                        <div className="flex items-center gap-2">
                             <button 
                                onClick={handleClearCache}
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
    );
};

export default LogsAndCache;
