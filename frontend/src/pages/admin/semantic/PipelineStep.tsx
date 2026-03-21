import React from 'react';
import { HelpCircle } from 'lucide-react';
import { clsx } from 'clsx';

interface PipelineStepProps {
    icon: any;
    title: string;
    status?: string | number;
    description: string;
    children?: React.ReactNode;
    isActive: boolean;
    helpText?: string;
}

const PipelineStep = ({ icon: Icon, title, status, description, children, isActive, helpText }: PipelineStepProps) => (
    <div className={clsx(
        "relative pl-8 pb-8 border-l-2 last:pb-0 transition-all duration-500",
        isActive ? "border-indigo-500" : "border-slate-200 dark:border-slate-800"
    )}>
        <div className={clsx(
            "absolute -left-[11px] top-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all",
            isActive ? "bg-indigo-600 border-indigo-600 shadow-lg shadow-indigo-500/50" : "bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700"
        )}>
            <div className={clsx("w-1.5 h-1.5 rounded-full", isActive ? "bg-white" : "bg-slate-300 dark:bg-slate-700")}></div>
        </div>
        <div className={clsx(
            "p-4 rounded-xl border transition-all",
            isActive
                ? "bg-white dark:bg-slate-800/80 shadow-lg border-indigo-100 dark:border-indigo-900/30"
                : "bg-slate-50/50 dark:bg-slate-900/30 border-transparent opacity-60"
        )}>
            <div className="flex items-center gap-2 mb-1">
                <Icon className={clsx("w-4 h-4", isActive ? "text-indigo-500" : "text-slate-400")} />
                <h4 className={clsx("text-sm font-bold flex items-center gap-1", isActive ? "text-slate-800 dark:text-white" : "text-slate-500")}>
                    {title}
                    {helpText && (
                        <div className="cursor-help text-slate-400 font-normal group/ptip relative">
                            <HelpCircle className="w-3 h-3" />
                            <div className="absolute bottom-full left-0 mb-2 w-48 p-2 bg-slate-800 text-white text-[10px] rounded-lg shadow-xl opacity-0 group-hover/ptip:opacity-100 pointer-events-none transition-opacity z-50 border border-slate-700 font-normal">
                                {helpText}
                            </div>
                        </div>
                    )}
                </h4>
                {status !== undefined && <span className="ml-auto text-[10px] font-mono bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 px-1.5 py-0.5 rounded">{status}</span>}
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">{description}</p>
            {children}
        </div>
    </div>
);

export default PipelineStep;
