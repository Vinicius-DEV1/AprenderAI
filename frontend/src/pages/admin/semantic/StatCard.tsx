import React from 'react';
import { HelpCircle } from 'lucide-react';
import { clsx } from 'clsx';

interface StatCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: any;
    colorClass: string;
    helpText?: string;
}

const StatCard = ({ title, value, subtitle, icon: Icon, colorClass, helpText }: StatCardProps) => (
    <div className="bg-white dark:bg-slate-800/50 backdrop-blur-md rounded-2xl border border-slate-200 dark:border-slate-700/50 p-5 flex flex-col items-start shadow-xl hover:shadow-indigo-500/10 transition-all hover:-translate-y-1 group relative">
        {helpText && (
            <div className="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help transition-all group/tip">
                <HelpCircle className="w-4 h-4" />
                <div className="absolute bottom-full right-0 mb-2 w-48 p-2 bg-slate-800 text-white text-[10px] rounded-lg shadow-xl opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-50 border border-slate-700">
                    {helpText}
                </div>
            </div>
        )}
        <div className={clsx("p-3 rounded-xl mb-4 shadow-inner", colorClass)}>
            <Icon className="w-6 h-6" />
        </div>
        <div className="flex flex-col">
            <h3 className="text-slate-500 dark:text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">{title}</h3>
            <span className="text-2xl font-black text-slate-800 dark:text-white">{value}</span>
            {subtitle && <span className="text-[10px] text-slate-400 mt-1 font-medium">{subtitle}</span>}
        </div>
    </div>
);

export default StatCard;
