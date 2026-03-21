import { VaultKey, ApiKey, AiLog, AiRanking, AnalyticsDaily, AnalyticsModule } from './Types';
import Chart from 'react-apexcharts';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function ApiAnalyticsDashboard({
    daily,
    modules,
    vaultKeys,
    availableCapabilities,
    aiLogs,
    aiRanking,
    filters,
    onFilterChange,
    isFetching
}: {
    daily: AnalyticsDaily[],
    modules: AnalyticsModule[],
    vaultKeys: VaultKey[],
    availableCapabilities: Record<string, string>,
    aiLogs: AiLog[],
    aiRanking: AiRanking[],
    filters: any,
    onFilterChange: (newFilters: any) => void,
    isFetching: boolean
}) {
    const [collapsed, setCollapsed] = useState(false);

    // Grouping Daily Data
    const datesMap = new Map<string, number>();
    daily.forEach(d => {
        datesMap.set(d.date, (datesMap.get(d.date) || 0) + Number(d.requests));
    });
    const sortedDates = Array.from(datesMap.keys()).sort();
    const dailyRequests = sortedDates.map(date => datesMap.get(date) || 0);

    const dailyChartOptions: ApexCharts.ApexOptions = {
        chart: { type: 'area', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, sparkline: { enabled: false }, zoom: { enabled: false } },
        colors: ['#6366f1'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: { categories: sortedDates.map(d => new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })), tooltip: { enabled: false }, labels: { style: { colors: '#94a3b8', fontWeight: 600 } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: '#94a3b8', fontWeight: 600 } } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { top: 0, right: 0, bottom: 0, left: 10 } },
        tooltip: { theme: 'light', y: { formatter: (val) => `${val} requisições` } }
    };

    // Grouping Module Data
    const moduleMap = new Map<string, number>();
    modules.forEach(m => {
        const modName = m.module || 'Geral';
        moduleMap.set(modName, (moduleMap.get(modName) || 0) + Number(m.requests));
    });
    const sortedModulesEntries = Array.from(moduleMap.entries()).sort((a, b) => b[1] - a[1]);
    const moduleNames = sortedModulesEntries.map(e => e[0].toUpperCase());
    const moduleRequests = sortedModulesEntries.map(e => e[1]);

    const moduleChartOptions: ApexCharts.ApexOptions = {
        chart: { type: 'bar', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%', distributed: true } },
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'],
        dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, formatter: (val) => val.toString(), offsetX: 0 },
        xaxis: { categories: moduleNames, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: '#475569', fontWeight: 700 } } },
        grid: { show: false },
        tooltip: { theme: 'light', y: { formatter: (val) => `${val} requisições` } },
        legend: { show: false }
    };

    const totalRequests = dailyRequests.reduce((a, b) => a + b, 0);
    const totalCost = daily.reduce((acc, curr) => acc + Number(curr.cost), 0);

    return (
        <section className="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div
                onClick={() => setCollapsed(!collapsed)}
                className="p-6 border-b border-slate-50 flex justify-between items-center cursor-pointer hover:bg-slate-50 transition-colors">
                <div className="flex items-center gap-4">
                    <span className="p-3 bg-gradient-to-br from-indigo-50 to-blue-50 text-indigo-600 rounded-xl shadow-sm">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                    </span>
                    <div>
                        <h3 className="text-xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                            Consumo de Inteligência Artificial
                            {isFetching && <motion.span animate={{ rotate: 360 }} transition={{ repeat: Infinity, duration: 1 }} className="text-indigo-400 text-sm">⌛</motion.span>}
                        </h3>
                        <p className="text-xs font-bold text-slate-400 mt-0.5 uppercase tracking-wider">Monitoramento Dinâmico de Custos e Performance</p>
                    </div>
                </div>
                <motion.svg animate={{ rotate: collapsed ? 0 : 180 }} className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></motion.svg>
            </div>

            <AnimatePresence>
                {!collapsed && (
                    <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
                        <div className="p-6 bg-slate-50/50">
                            {/* Filter Bar */}
                            <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                                <div className="space-y-1">
                                    <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Período (Início)</label>
                                    <input
                                        type="date"
                                        value={filters.start_date}
                                        onChange={e => onFilterChange({ ...filters, start_date: e.target.value })}
                                        className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Período (Fim)</label>
                                    <input
                                        type="date"
                                        value={filters.end_date}
                                        onChange={e => onFilterChange({ ...filters, end_date: e.target.value })}
                                        className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Chave / Provedor</label>
                                    <select
                                        value={filters.vault_id}
                                        onChange={e => onFilterChange({ ...filters, vault_id: e.target.value })}
                                        className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none"
                                    >
                                        <option value="">Todas as Chaves</option>
                                        {vaultKeys.map(vk => (
                                            <option key={vk.id} value={vk.id}>{vk.nickname} ({vk.provider.toUpperCase()})</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Módulo / Recurso</label>
                                    <select
                                        value={filters.module}
                                        onChange={e => onFilterChange({ ...filters, module: e.target.value })}
                                        className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none"
                                    >
                                        <option value="">Todos os Módulos</option>
                                        {Object.entries(availableCapabilities).map(([code, label]) => (
                                            <option key={code} value={code}>{label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Modelo Específico</label>
                                    <input
                                        type="text"
                                        placeholder="Ex: gpt-4o"
                                        value={filters.model}
                                        onChange={e => onFilterChange({ ...filters, model: e.target.value })}
                                        className="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none"
                                    />
                                </div>
                            </div>

                            {/* Summary Stats */}
                            <div className="flex gap-6 mb-8 pl-2">
                                <div>
                                    <p className="text-xs font-black text-slate-400 uppercase tracking-wider">Requisições Filtradas</p>
                                    <p className="text-3xl font-black text-slate-800">{totalRequests.toLocaleString()}</p>
                                </div>
                                <div className="w-px h-12 bg-slate-200 my-auto"></div>
                                <div>
                                    <p className="text-xs font-black text-slate-400 uppercase tracking-wider">Custo Calculado</p>
                                    <p className="text-3xl font-black text-indigo-600">${totalCost.toFixed(5)}</p>
                                </div>
                            </div>

                            {/* Charts */}
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                                <div className="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                                    <h4 className="text-sm font-bold text-slate-800 mb-4 bg-slate-50 inline-block px-3 py-1 rounded-lg">Tendência de Uso Diário</h4>
                                    <div className="h-[280px]">
                                        <Chart options={dailyChartOptions} series={[{ name: 'Requisições', data: dailyRequests }]} type="area" height="100%" />
                                    </div>
                                </div>
                                <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                                    <h4 className="text-sm font-bold text-slate-800 mb-4 bg-slate-50 inline-block px-3 py-1 rounded-lg">Distribuição por Módulo</h4>
                                    {moduleRequests.length > 0 ? (
                                        <div className="h-[280px]">
                                            <Chart options={moduleChartOptions} series={[{ name: 'Requisições', data: moduleRequests }]} type="bar" height="100%" />
                                        </div>
                                    ) : (
                                        <div className="h-[280px] flex items-center justify-center">
                                            <p className="text-slate-400 font-medium text-sm text-center">Nenhum dado para este filtro.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Detailed Usage Table */}
                            <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <div className="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                    <h4 className="text-sm font-bold text-slate-800 tracking-tight">Logs Detalhados de Operações</h4>
                                    <span className="text-[10px] font-black text-indigo-600 bg-white border border-indigo-100 px-3 py-1 rounded-full uppercase tracking-widest">
                                        Monitoramento em Tempo Real
                                    </span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-[11px]">
                                        <thead className="bg-slate-50/50 text-slate-400 uppercase font-black border-b border-slate-100">
                                            <tr>
                                                <th className="px-6 py-4">Horário Exacto</th>
                                                <th className="px-6 py-4">Usuário / Requisitante</th>
                                                <th className="px-6 py-4">Módulo</th>
                                                <th className="px-6 py-4">Chave Utilizada</th>
                                                <th className="px-6 py-4">Modelo</th>
                                                <th className="px-6 py-4 text-right">Custo Estimado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50">
                                            {aiLogs.map((log, i) => (
                                                <tr key={i} className="hover:bg-indigo-50/30 transition-colors">
                                                    <td className="px-6 py-3 font-bold text-slate-500">
                                                        <span className="text-slate-800">{new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</span>
                                                        <p className="text-[9px] text-slate-400">{new Date(log.created_at).toLocaleDateString('pt-BR')}</p>
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500">
                                                                {log.user?.name?.charAt(0) || 'S'}
                                                            </div>
                                                            <span className="font-bold text-slate-700">{log.user?.name || 'Sistema / Interno'}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        <span className="bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md font-black uppercase text-[9px] tracking-tighter">
                                                            {log.module || 'Geral'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        <span className="font-black text-slate-800">{(log.apiKey as any)?.vault?.nickname || 'API Direta'}</span>
                                                        <p className="text-[9px] text-slate-400 uppercase font-bold tracking-tighter">{log.provider}</p>
                                                    </td>
                                                    <td className="px-6 py-3 font-mono text-indigo-600 font-bold">{log.model}</td>
                                                    <td className="px-6 py-3 text-right">
                                                        <span className="font-black text-slate-700">${Number(log.estimated_cost).toFixed(6)}</span>
                                                        <p className="text-[9px] text-slate-400 font-bold">{log.tokens_used_total} tokens</p>
                                                    </td>
                                                </tr>
                                            ))}
                                            {aiLogs.length === 0 && (
                                                <tr>
                                                    <td colSpan={6} className="px-6 py-12 text-center text-slate-400 italic">Nenhum registro encontrado para os filtros selecionados.</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        {/* 6. CONSUMPTION RANKING */}
                        <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-12">
                            <div className="p-6 border-b border-slate-50">
                                <h3 className="text-xl font-black text-slate-800 tracking-tight">Ranking de Consumo (Usuários)</h3>
                                <p className="text-xs font-bold text-slate-400 mt-0.5 uppercase tracking-wider">Top 20 Usuários por Consumo de IA</p>
                            </div>
                            <div className="p-6">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="text-xs text-slate-400 uppercase font-black border-b border-slate-100">
                                            <tr>
                                                <th className="px-6 py-3">Usuário</th>
                                                <th className="px-6 py-3 text-center">Requisições</th>
                                                <th className="px-6 py-3 text-center">Tokens Totais</th>
                                                <th className="px-6 py-3 text-right">Custo Estimado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50">
                                            {aiRanking.map((rank, index) => (
                                                <tr key={index} className="hover:bg-slate-50/50 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-xs">
                                                                {rank.user?.name?.charAt(0) || 'U'}
                                                            </div>
                                                            <div>
                                                                <p className="font-bold text-slate-800">{rank.user?.name || 'Sistema / Interno'}</p>
                                                                <p className="text-[10px] text-slate-400 font-bold">{rank.user?.email || 'N/A'}</p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-3 text-center font-bold text-slate-600">{rank.request_count}</td>
                                                    <td className="px-6 py-3 text-center">
                                                        <span className="bg-slate-100 px-2 py-1 rounded-md text-slate-500 font-black text-[10px]">{Number(rank.total_tokens).toLocaleString()} TK</span>
                                                    </td>
                                                    <td className="px-6 py-3 text-right">
                                                        <span className="font-black text-indigo-600">${Number(rank.total_cost).toFixed(6)}</span>
                                                    </td>
                                                </tr>
                                            ))}
                                            {aiRanking.length === 0 && (
                                                <tr>
                                                    <td colSpan={4} className="px-6 py-12 text-center text-slate-400 italic">Nenhum ranking disponível para os filtros selecionados.</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </motion.div>
                )}
            </AnimatePresence>
        </section>
    );
}
