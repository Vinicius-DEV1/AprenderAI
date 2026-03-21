import React, { useState } from 'react';
import { 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    FileText,
    History,
    ExternalLink
} from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats } from './Types';

interface RecentActivityProps {
    stats: DashboardStats;
}

const RecentActivity: React.FC<RecentActivityProps> = ({ stats }) => {
    const [activeTab, setActiveTab] = useState<'success' | 'failure'>('success');
    
    const successes = stats.jobs.recent_successes || [];
    const failures = stats.jobs.recent_failures || [];

    return (
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col h-[400px]">
            {/* Header with Tabs */}
            <div className="px-4 pt-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between shrink-0">
                <h2 className="text-xs font-black text-slate-500 uppercase tracking-widest flex items-center gap-2 mb-3">
                    <History className="w-4 h-4 text-indigo-500" /> Atividade do Indexador
                </h2>
                
                <div className="flex gap-1 bg-slate-100 dark:bg-slate-900/50 p-1 rounded-xl mb-3">
                    <button
                        onClick={() => setActiveTab('success')}
                        className={clsx(
                            "px-3 py-1 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1.5",
                            activeTab === 'success' 
                                ? "bg-white dark:bg-slate-800 text-emerald-600 shadow-sm" 
                                : "text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                        )}
                    >
                        <CheckCircle2 className="w-3 h-3" /> Sucessos ({successes.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('failure')}
                        className={clsx(
                            "px-3 py-1 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1.5",
                            activeTab === 'failure' 
                                ? "bg-white dark:bg-slate-800 text-rose-600 shadow-sm" 
                                : "text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                        )}
                    >
                        <AlertCircle className="w-3 h-3" /> Falhas ({failures.length})
                    </button>
                </div>
            </div>

            {/* List Container */}
            <div className="flex-1 overflow-y-auto p-4 custom-scrollbar">
                {activeTab === 'success' ? (
                    <div className="space-y-3">
                        {successes.length > 0 ? (
                            successes.map((item) => (
                                <div key={item.id} className="group relative p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/50 hover:border-indigo-300 dark:hover:border-indigo-500/50 transition-all duration-200 shadow-sm hover:shadow-md">
                                    <div className="flex justify-between items-start gap-4 mb-2">
                                        <div className="flex flex-wrap gap-1.5">
                                            <span className="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[9px] font-black text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 uppercase tracking-wider">
                                                #{item.id}
                                            </span>
                                            {item.organization && (
                                                <span className="px-1.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-[9px] font-bold text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/50 uppercase">
                                                    {item.organization}
                                                </span>
                                            )}
                                            {item.year && (
                                                <span className="px-1.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-900/30 text-[9px] font-bold text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-800/50">
                                                    {item.year}
                                                </span>
                                            )}
                                            {item.subject && (
                                                <span className="px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-900/30 text-[9px] font-bold text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/50">
                                                    {item.subject}
                                                </span>
                                            )}
                                        </div>
                                        
                                        <div className="flex items-center gap-1.5 shrink-0">
                                            <span className="text-[9px] font-mono font-bold text-slate-400 bg-slate-50 dark:bg-slate-800 px-1 py-0.5 rounded border border-slate-100 dark:border-slate-700">
                                                {item.pipeline_version}
                                            </span>
                                            <a 
                                                href={`/admin/questions/${item.id}/edit`} 
                                                target="_blank" 
                                                rel="noopener noreferrer"
                                                className="p-1 rounded-lg text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                                                title="Editar Questão"
                                            >
                                                <ExternalLink className="w-3 h-3" />
                                            </a>
                                        </div>
                                    </div>

                                    <p className="text-[11px] text-slate-600 dark:text-slate-300 line-clamp-2 font-medium leading-relaxed mb-2">
                                        {item.statement}
                                    </p>

                                    <div className="flex items-center justify-between text-[9px] text-slate-400 font-medium">
                                        <div className="flex items-center gap-1">
                                            <Clock className="w-2.5 h-2.5 text-slate-300" />
                                            {new Date(item.indexed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                        </div>
                                        <span className="text-slate-300 dark:text-slate-600">
                                            {new Date(item.indexed_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-slate-400 py-10">
                                <FileText className="w-8 h-8 mb-2 opacity-20" />
                                <p className="text-xs italic">Nenhuma indexação recente.</p>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {failures.length > 0 ? (
                            failures.map((job) => (
                                <div key={job.id} className="p-3 bg-rose-50/50 dark:bg-rose-900/10 border border-rose-100/50 dark:border-rose-900/30 rounded-xl transition-all hover:border-rose-300">
                                    <div className="flex justify-between items-start mb-1 text-[10px]">
                                        <span className="font-bold text-rose-800 dark:text-rose-300">Job #{job.id}</span>
                                        <span className="text-rose-500 font-medium">{new Date(job.failed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                    </div>
                                    <p className="text-rose-600 dark:text-rose-400 font-mono text-[9px] break-all line-clamp-2 bg-white/50 dark:bg-slate-900/50 p-1.5 rounded-lg border border-rose-100 dark:border-rose-800/50" title={job.error_preview}>
                                        {job.error_preview}
                                    </p>
                                    <div className="mt-2 text-[8px] text-slate-400 font-bold uppercase tracking-widest">
                                        Payload: {job.payload}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-slate-400 py-10">
                                <CheckCircle2 className="w-8 h-8 mb-2 opacity-20 text-emerald-500" />
                                <p className="text-xs italic">Nenhuma falha registrada.</p>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Footer hint */}
            <div className="px-4 py-2 bg-slate-50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-800 shrink-0">
                <p className="text-[9px] text-slate-400 italic">Exibindo os últimos 10 eventos de processamento.</p>
            </div>
        </div>
    );
};

export default RecentActivity;
