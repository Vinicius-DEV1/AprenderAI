import React from 'react';
import { TrendingUp, Zap, BarChart3 } from 'lucide-react';
import { DashboardStats } from './Types';

interface PerformanceAnalyticsProps {
    stats: DashboardStats;
}

const PerformanceAnalytics: React.FC<PerformanceAnalyticsProps> = ({ stats }) => {
    return (
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
    );
};

export default PerformanceAnalytics;
