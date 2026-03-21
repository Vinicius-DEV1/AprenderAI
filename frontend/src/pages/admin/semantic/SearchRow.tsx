import React, { useState } from 'react';
import { 
    Clock, 
    Target, 
    Filter, 
    AlertTriangle, 
    Box, 
    Code, 
    PlayCircle, 
    ChevronUp, 
    ChevronDown 
} from 'lucide-react';
import { clsx } from 'clsx';

interface SearchRowProps {
    search: any;
    onReplay: (prompt: string) => void;
}

const SearchRow = ({ search, onReplay }: SearchRowProps) => {
    const [isExpanded, setIsExpanded] = useState(false);

    const filters = search.filters ? (typeof search.filters === 'string' ? JSON.parse(search.filters) : search.filters) : null;

    return (
        <>
            <tr className={clsx(
                "hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors cursor-pointer group",
                isExpanded && "bg-slate-50/80 dark:bg-slate-700/40"
            )} onClick={() => setIsExpanded(!isExpanded)}>
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
                    <div className="flex items-center justify-between gap-4">
                        <span className={clsx(
                            "inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase border",
                            search.status === 'completed' ? "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50" :
                                search.status === 'failed' ? "bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50" :
                                    "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50"
                        )}>
                            {search.status}
                        </span>
                        
                        <div className="flex items-center gap-2">
                             <button 
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onReplay(search.prompt);
                                }}
                                className="p-1.5 text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-all"
                                title="Replay Search"
                            >
                                <PlayCircle className="w-4 h-4" />
                            </button>
                            {isExpanded ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
                        </div>
                    </div>
                </td>
            </tr>
            {isExpanded && (
                <tr className="bg-slate-50/50 dark:bg-slate-700/20">
                    <td colSpan={4} className="px-4 py-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-1 duration-200">
                            {/* PATH & STRATEGY */}
                            <div className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h5 className="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                                    <Target className="w-3 h-3 text-indigo-500" />
                                    Caminho e Estratégia
                                </h5>
                                <div className="space-y-2">
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-500">Path:</span>
                                        <span className="font-mono bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 px-1.5 py-0.5 rounded">
                                            {filters?.search_path || 'standard_sql'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-500">Modo:</span>
                                        <span className="font-medium text-slate-700 dark:text-slate-200">
                                            {filters?.search_mode === 'vector' ? 'Vetor (Híbrido)' : 'Lexical (SQL)'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-500">Threshold:</span>
                                        <span className="font-medium text-slate-700 dark:text-slate-200">
                                            {search.similarity_threshold || 'N/A'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* APPLIED FILTERS */}
                            <div className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h5 className="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                                    <Filter className="w-3 h-3 text-emerald-500" />
                                    Filtros Extraídos
                                </h5>
                                <div className="space-y-1.5">
                                    {filters?.organization && (
                                        <div className="flex flex-wrap gap-1 items-center">
                                            <span className="text-[10px] text-slate-400">Banca:</span>
                                            {(Array.isArray(filters.organization) ? filters.organization : [filters.organization]).map((org: string) => (
                                                <span key={org} className="text-[10px] bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 px-1.5 py-0.5 rounded border border-emerald-100 dark:border-emerald-800/50">{org}</span>
                                            ))}
                                        </div>
                                    )}
                                    {filters?.institution && (
                                        <div className="flex flex-wrap gap-1 items-center">
                                            <span className="text-[10px] text-slate-400">Inst:</span>
                                            {(Array.isArray(filters.institution) ? filters.institution : [filters.institution]).map((inst: string) => (
                                                <span key={inst} className="text-[10px] bg-blue-50 dark:bg-blue-900/30 text-blue-600 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800/50">{inst}</span>
                                            ))}
                                        </div>
                                    )}
                                    {(filters?.year || filters?.difficulty) && (
                                        <div className="flex gap-2 items-center">
                                            {filters.year && (
                                                <div className="flex items-center gap-1">
                                                    <span className="text-[10px] text-slate-400">Ano:</span>
                                                    <span className="text-[10px] font-medium text-slate-700 dark:text-slate-200">{filters.year_operator || '='} {filters.year}</span>
                                                </div>
                                            )}
                                            {filters.difficulty && (
                                                <div className="flex items-center gap-1">
                                                    <span className="text-[10px] text-slate-400">Dif:</span>
                                                    <span className="text-[10px] font-medium text-slate-700 dark:text-slate-200">{filters.difficulty}</span>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    {!filters?.organization && !filters?.institution && !filters?.year && !filters?.difficulty && (
                                        <span className="text-xs text-slate-400 italic">Nenhum filtro estrito aplicado.</span>
                                    )}
                                </div>
                            </div>

                            {/* ERROR OR CONCEPTS */}
                            <div className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                                {search.status === 'failed' ? (
                                    <>
                                        <div className="absolute top-0 right-0 w-1 h-full bg-red-500"></div>
                                        <h5 className="text-[10px] font-bold uppercase tracking-wider text-red-500 mb-2 flex items-center gap-1.5">
                                            <AlertTriangle className="w-3 h-3" />
                                            Erro de Execução
                                        </h5>
                                        <p className="text-xs text-red-600 dark:text-red-400 font-mono leading-relaxed truncate-2-lines">
                                            {search.error || 'Erro desconhecido durante o pipeline.'}
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <h5 className="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                                            <Box className="w-3 h-3 text-amber-500" />
                                            Conceitos & Objetos
                                        </h5>
                                        <div className="flex flex-wrap gap-1">
                                            {filters?.concepts?.length > 0 ? filters.concepts.map((c: any) => (
                                                <span key={c.id || c.name} className="text-[10px] bg-amber-50 dark:bg-amber-900/30 text-amber-600 px-1.5 py-0.5 rounded border border-amber-100 dark:border-amber-800/50">
                                                    {c.name || c}
                                                </span>
                                            )) : (
                                                <span className="text-xs text-slate-400 italic">Busca puramente semântica.</span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                        
                        {/* MORE JSON DATA */}
                        <details className="mt-3 group/details">
                            <summary className="text-[10px] font-bold text-slate-400 hover:text-indigo-500 cursor-pointer list-none flex items-center gap-1 transition-colors">
                                <Code className="w-3.5 h-3.5" />
                                RAW DEBUG DATA
                            </summary>
                            <div className="mt-2 p-3 bg-slate-900 rounded-lg text-[10px] font-mono text-emerald-400 overflow-x-auto border border-slate-800">
                                <pre>{JSON.stringify(filters, null, 2)}</pre>
                            </div>
                        </details>
                    </td>
                </tr>
            )}
        </>
    );
};

export default SearchRow;
