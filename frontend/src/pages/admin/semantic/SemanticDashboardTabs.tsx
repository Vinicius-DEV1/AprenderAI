import React from 'react';
import { 
    Activity, 
    Settings, 
    LayoutDashboard,
    Search
} from 'lucide-react';
import { clsx } from 'clsx';

interface Tab {
    id: string;
    label: string;
    icon: React.ElementType;
    description: string;
}

interface SemanticDashboardTabsProps {
    activeTab: string;
    setActiveTab: (id: string) => void;
}

const tabs: Tab[] = [
    { 
        id: 'monitor', 
        label: 'Monitoramento', 
        icon: LayoutDashboard,
        description: 'Saúde infra, filas e chaves'
    },
    { 
        id: 'activity', 
        label: 'Atividade e Logs', 
        icon: Activity,
        description: 'Eventos, performance e histórico'
    },
    { 
        id: 'engine', 
        label: 'Motor Semântico', 
        icon: Settings,
        description: 'Pesos, nuvem e configurações'
    },
    { 
        id: 'maestro', 
        label: 'Maestro (Tester)', 
        icon: Search,
        description: 'Simulador Xavier 2.0'
    },
];

const SemanticDashboardTabs: React.FC<SemanticDashboardTabsProps> = ({ activeTab, setActiveTab }) => {
    return (
        <div className="flex flex-wrap gap-2 mb-8 bg-slate-100/50 dark:bg-slate-900/30 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800">
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;
                
                return (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className={clsx(
                            "flex-1 min-w-[140px] flex items-center gap-3 p-3 rounded-xl transition-all duration-200 outline-none",
                            isActive 
                                ? "bg-white dark:bg-slate-800 text-indigo-600 shadow-sm border border-slate-200 dark:border-slate-700" 
                                : "text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50"
                        )}
                    >
                        <div className={clsx(
                            "p-2 rounded-lg shrink-0",
                            isActive ? "bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600" : "bg-slate-200/50 dark:bg-slate-800 text-slate-400"
                        )}>
                            <Icon className="w-5 h-5" />
                        </div>
                        <div className="text-left">
                            <span className="block text-xs font-black uppercase tracking-widest leading-none mb-1">
                                {tab.label}
                            </span>
                            <span className="text-[10px] text-slate-400 dark:text-slate-500 font-medium leading-none whitespace-nowrap">
                                {tab.description}
                            </span>
                        </div>
                    </button>
                );
            })}
        </div>
    );
};

export default SemanticDashboardTabs;
