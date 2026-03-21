import React from 'react';
import { AlertCircle } from 'lucide-react';
import { DashboardStats } from './Types';

interface RecentFailuresProps {
    stats: DashboardStats;
}

const RecentFailures: React.FC<RecentFailuresProps> = ({ stats }) => {
    if (!stats.jobs.recent_failures || stats.jobs.recent_failures.length === 0) return null;

    return (
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
    );
};

export default RecentFailures;
