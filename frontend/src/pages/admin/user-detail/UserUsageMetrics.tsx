import React from 'react';

interface UserUsageMetricsProps {
    stats: any;
    monthly_simulation_used: number;
    monthly_essay_used: number;
}

export const UserUsageMetrics: React.FC<UserUsageMetricsProps> = ({ 
    stats, 
    monthly_simulation_used, 
    monthly_essay_used 
}) => {
    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
            <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Métricas de Uso</h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                    <div className="flex justify-between items-start mb-2">
                        <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">Simulados</div>
                        <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    </div>
                    <div className="text-2xl font-black text-slate-800">{stats?.simulations || 0}</div>
                    <div className="text-xs text-gray-500 mt-1">Neste ciclo: <span className="font-semibold">{monthly_simulation_used || 0}</span></div>
                </div>

                <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                    <div className="flex justify-between items-start mb-2">
                        <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">Redações</div>
                        <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </div>
                    <div className="text-2xl font-black text-slate-800">{stats?.essays || 0}</div>
                    <div className="text-xs text-gray-500 mt-1">Neste ciclo: <span className="font-semibold">{monthly_essay_used || 0}</span></div>
                </div>

                <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                    <div className="flex justify-between items-start mb-2">
                        <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">LTV Aluno</div>
                        <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div className="text-2xl font-black text-slate-800">
                        R$ {(stats?.investment || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </div>
                    <div className="text-[10px] text-gray-500 mt-1 uppercase">Gasto total acumulado</div>
                </div>

                <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl text-indigo-900 border-l-4 border-l-indigo-400">
                    <div className="flex justify-between items-start mb-2">
                        <div className="text-indigo-800/70 text-xs font-bold uppercase tracking-wider">Custo IA</div>
                        <svg className="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div className="text-2xl font-black text-indigo-900">
                        R$ {Number(stats?.ai?.total_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}
                    </div>
                    <div className="text-[10px] uppercase text-indigo-600/70 mt-1 font-bold">{stats?.ai?.request_count || 0} req(s). feitas</div>
                </div>
            </div>
        </div>
    );
};
