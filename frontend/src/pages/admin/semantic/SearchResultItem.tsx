import React from 'react';
import { clsx } from 'clsx';
import { ChevronDown } from 'lucide-react';

interface SearchResultItemProps {
    res: any;
    targetPipelineVersion: string;
}

const SearchResultItem: React.FC<SearchResultItemProps> = ({ res, targetPipelineVersion }) => {
    return (
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
                        {res.source === 'sql_fallback' && (
                            <span className="text-[10px] uppercase font-black tracking-widest px-2 py-0.5 rounded bg-rose-500 text-white border border-rose-600 animate-pulse shadow-lg shadow-rose-500/20 mb-1">
                                SQL FALLBACK
                            </span>
                        )}
                        <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                            {res.source === 'sql_fallback' ? 'SQL' : 'VEC'}: {res.qdrant_score.toFixed(3)}
                        </span>
                        <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800">
                            Final: {res.final_score.toFixed(3)}
                        </span>
                        {res.payload?.pipeline_version && res.payload.pipeline_version !== targetPipelineVersion && (
                            <div className="group/vtip relative">
                                <span className="text-[9px] uppercase font-black tracking-tighter px-1.5 py-0.5 rounded bg-amber-500 text-white animate-pulse cursor-help">
                                    OUTDATED
                                </span>
                                <div className="absolute bottom-full right-0 mb-2 w-48 p-2 bg-slate-800 text-white text-[10px] rounded-lg shadow-xl opacity-0 group-hover/vtip:opacity-100 pointer-events-none transition-opacity z-50 border border-slate-700 font-normal">
                                    Este vetor pertence à versão anterior ({res.payload.pipeline_version}). Recomenda-se re-indexar para obter a precisão do {targetPipelineVersion}.
                                </div>
                            </div>
                        )}
                    </div>
                    <ChevronDown className="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform mt-1" />
                </div>
            </summary>

            <div className="p-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 space-y-4">
                {/* New Debug Metadata Section */}
                <div className="flex flex-wrap gap-4 p-2.5 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-700/50">
                    <div className="flex flex-col">
                        <span className="text-[8px] uppercase font-bold text-slate-400">Origem</span>
                        <span className={clsx(
                            "text-[10px] font-mono font-bold",
                            res.source === 'sql_fallback' ? "text-rose-500" : "text-indigo-500"
                        )}>
                            {res.source === 'sql_fallback' ? 'SQL_FALLBACK' : 'QDRANT_VECTOR'}
                        </span>
                    </div>
                    <div className="w-px h-6 bg-slate-200 dark:bg-slate-700 self-center"></div>
                    <div className="flex flex-col">
                        <span className="text-[8px] uppercase font-bold text-slate-400">Pipeline</span>
                        <span className="text-[10px] font-mono text-slate-600 dark:text-slate-300">
                            {res.payload?.pipeline_version || 'N/A'}
                        </span>
                    </div>
                    <div className="w-px h-6 bg-slate-200 dark:bg-slate-700 self-center"></div>
                    <div className="flex flex-col">
                        <span className="text-[8px] uppercase font-bold text-slate-400">Banca / Org</span>
                        <span className="text-[10px] text-slate-600 dark:text-slate-300 font-medium">
                            {res.payload?.organization || 'N/A'}
                        </span>
                    </div>
                    <div className="w-px h-6 bg-slate-200 dark:bg-slate-700 self-center"></div>
                    <div className="flex flex-col">
                        <span className="text-[8px] uppercase font-bold text-slate-400">Instituição</span>
                        <span className="text-[10px] text-slate-600 dark:text-slate-300 font-medium shrink-0">
                            {res.payload?.institution || 'N/A'}
                        </span>
                    </div>
                </div>
                {res.score_details && Object.keys(res.score_details).length > 0 && (
                    <div className="bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg p-3">
                        <h5 className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 border-b border-slate-200 dark:border-slate-700 pb-1 flex justify-between items-center">
                            <span className="flex items-center gap-1">
                                📊 Anatomia do Score (Re-Ranking)
                                <span className="text-[9px] text-slate-400 font-normal ml-2">Explicando por que esta questão venceu</span>
                            </span>
                            <span className="font-mono text-emerald-600 dark:text-emerald-400 font-black px-2 py-0.5 bg-emerald-50 dark:bg-emerald-900/30 rounded">Bscore: {res.final_score.toFixed(4)}</span>
                        </h5>
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mt-2">
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
                            <div className="flex flex-col bg-indigo-50 dark:bg-indigo-900/20 p-2 rounded shadow-sm border border-indigo-100 dark:border-indigo-800">
                                <span className="text-[9px] text-indigo-500 dark:text-indigo-400 uppercase tracking-widest font-bold">Intent Boost</span>
                                <span className="text-sm font-mono text-purple-600 dark:text-purple-400 font-bold">+{res.score_details.intent.weighted.toFixed(4)}</span>
                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.intent.raw.toFixed(4)}</span>
                            </div>
                            <div className="flex flex-col bg-emerald-50 dark:bg-emerald-900/20 p-2 rounded shadow-sm border border-emerald-100 dark:border-emerald-800">
                                <span className="text-[9px] text-emerald-600 dark:text-emerald-400 uppercase tracking-widest font-bold">Proficiency</span>
                                <span className="text-sm font-mono text-emerald-700 dark:text-emerald-300 font-bold">+{res.score_details.proficiency?.weighted.toFixed(4) || '0.0000'}</span>
                                <span className="text-[9px] text-slate-400 mt-0.5">Raw: {res.score_details.proficiency?.raw.toFixed(4) || '0.0000'}</span>
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
    );
};

export default SearchResultItem;
