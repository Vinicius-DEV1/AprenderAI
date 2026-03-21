import React from 'react';
import { Clock, Activity, Zap, RefreshCw } from 'lucide-react';
import { DashboardStats } from './Types';

interface CongestionMonitorProps {
    stats: DashboardStats;
    wakingUp: boolean;
    handleClearCongestion: () => Promise<void>;
}

const CongestionMonitor: React.FC<CongestionMonitorProps> = ({ stats, wakingUp, handleClearCongestion }) => {
    if (!stats.jobs.waiting_list || stats.jobs.waiting_list.length === 0) return null;

    return (
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

            {(stats.jobs.waiting_total || 0) > (stats.jobs.waiting_list.length) && (
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
                    onClick={handleClearCongestion}
                    disabled={wakingUp}
                    className="flex items-center gap-2 text-[10px] font-bold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white px-3 py-1.5 rounded-lg border border-amber-600 transition-all shadow-sm"
                >
                    {wakingUp ? <RefreshCw className="w-3 h-3 animate-spin" /> : <Zap className="w-3 h-3" />}
                    Acordar Jobs Agora
                </button>
            </div>
        </div>
    );
};

export default CongestionMonitor;
