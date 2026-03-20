import { toast } from 'sonner';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence, Reorder } from 'framer-motion';
import Chart from 'react-apexcharts';
import api from '../../api/axios';
import { useConfigStore } from '../../stores/configStore';

// Interfaces for data structures
interface VaultKey {
    id: number;
    nickname: string;
    provider: string;
    is_valid: boolean;
    created_at: string;
}

interface ApiKey {
    id: number;
    provider: string;
    effective_provider: string;
    preferred_model: string | null;
    status: 'online' | 'offline' | 'quota_exceeded';
    last_error_message: string | null;
    vault?: VaultKey;
    pivot: {
        id: number;
        capability: string;
        priority: number;
    };
}

interface AiLog {
    id: number;
    user: { name: string } | null;
    provider: string;
    model: string;
    prompt_text: string;
    response_text: string;
    tokens_used_input: number;
    tokens_used_output: number;
    tokens_used_total: number;
    execution_time: number;
    estimated_cost: number;
    module: string;
    created_at: string;
    apiKey?: {
        vault?: {
            nickname: string;
        }
    };
}

interface AiRanking {
    user_id: number;
    user: { name: string; email: string } | null;
    total_tokens: number;
    total_cost: number;
    request_count: number;
}

interface AnalyticsDaily {
    date: string;
    provider: string;
    model?: string;
    nickname?: string;
    requests: number;
    cost: number;
    last_request_at?: string;
}

interface AnalyticsModule {
    module: string;
    provider: string;
    requests: number;
    cost: number;
}

function ApiAnalyticsDash({
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

export function AdminApiKeys() {
    const { aiName } = useConfigStore();
    const queryClient = useQueryClient();

    // UI Local State (New Tab System)
    const [activeTab, setActiveTab] = useState<'dashboard' | 'management' | 'failover' | 'audit'>('dashboard');
    const [activeSubTab, setActiveSubTab] = useState<string>('list');

    // Modals
    const [showModelModal, setShowModelModal] = useState(false);
    const [showLogModal, setShowLogModal] = useState(false);
    const [activeLog, setActiveLog] = useState<AiLog | null>(null);
    const [showRawResponse, setShowRawResponse] = useState(false);
    const [discoveredModels, setDiscoveredModels] = useState<any[]>([]);

    // Form States
    const [vaultForm, setVaultForm] = useState({ nickname: '', provider: 'gemini', key: '' });
    const [editingVaultId, setEditingVaultId] = useState<number | null>(null);
    const [routingForm, setRoutingForm] = useState({ vault_id: '', preferred_model: '', capabilities: [] as string[] });
    const [discoveryLoading, setDiscoveryLoading] = useState(false);

    // Analytics Filters State
    const [filters, setFilters] = useState({
        start_date: new Date(Date.now() - 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        vault_id: '',
        model: '',
        module: ''
    });

    const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
        queryKey: ['admin-api-keys', filters],
        queryFn: async () => {
            const params = new URLSearchParams();
            if (filters.start_date) params.append('start_date', filters.start_date);
            if (filters.end_date) params.append('end_date', filters.end_date);
            if (filters.vault_id) params.append('vault_id', filters.vault_id);
            if (filters.model) params.append('model', filters.model);
            if (filters.module) params.append('module', filters.module);

            const res = await api.get(`/api/v1/admin/api-keys?${params.toString()}`);
            return res.data;
        },
        retry: 1,
        refetchInterval: 3600000 // 1 hour auto-refresh
    });

    const handleManualRefresh = async () => {
        try {
            toast.loading('Sincronizando dados com provedores...', { id: 'api-sync' });
            await api.get('/api/v1/admin/api-keys?refresh=1');
            await refetch();
            toast.success('Dados atualizados com sucesso!', { id: 'api-sync' });
        } catch (err) {
            toast.error('Erro ao sincronizar dados.', { id: 'api-sync' });
        }
    };

    // Mutations
    const addVaultMutation = useMutation({
        mutationFn: async (payload: any) => {
            if (editingVaultId) {
                return api.put(`/api/v1/admin/api-keys/vault/${editingVaultId}`, payload);
            }
            return api.post('/api/v1/admin/api-keys/vault', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            setVaultForm({ nickname: '', provider: 'gemini', key: '' });
            setEditingVaultId(null);
            toast.success(editingVaultId ? 'Chave atualizada com sucesso!' : 'Chave guardada no cofre!');
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Erro ao salvar chave no cofre.');
        }
    });

    const deleteVaultMutation = useMutation({
        mutationFn: async (id: number) => api.delete(`/api/v1/admin/api-keys/vault/${id}`),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            toast.success(res.data.message || 'Chave removida do cofre.');
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Erro ao remover chave.');
        }
    });

    const discoverModelsMutation = useMutation({
        mutationFn: async (vaultId: string) => api.post('/api/v1/admin/api-keys/discover', { vault_id: vaultId }),
        onSuccess: (res) => {
            if (res.data.is_valid && res.data.models) {
                setDiscoveredModels(res.data.models);
                setShowModelModal(true);
            } else {
                toast.info(res.data.error || 'Falha na descoberta de modelos.');
            }
        },
        onError: (err: any) => {
            const msg = err.response?.data?.error || err.message || 'Erro desconhecido na API.';
            toast.error('Erro Crítico: ' + msg);
        },
        onSettled: () => setDiscoveryLoading(false)
    });

    const activateRoutingMutation = useMutation({
        mutationFn: async (payload: any) => api.post('/api/v1/admin/api-keys', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            queryClient.invalidateQueries({ queryKey: ['admin-triage-active-keys'] });
            setRoutingForm({ vault_id: '', preferred_model: '', capabilities: [] });
            toast.success('Roteamento ativado com sucesso!');
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.response?.data?.error || err.message || 'Erro desconhecido ao ativar roteamento.';
            toast.error('Falha ao ativar roteamento: ' + msg);
        }
    });

    const updatePriorityMutation = useMutation({
        mutationFn: async (payload: { capability: string, ordered_ids: number[] }) =>
            api.post('/api/v1/admin/api-keys/priority', payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const retestMutation = useMutation({
        mutationFn: async (id: number) => api.post(`/api/v1/admin/api-keys/${id}/retest`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const deleteApiKeyMutation = useMutation({
        mutationFn: async (id: number) => api.delete(`/api/v1/admin/api-keys/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })
    });

    const handleDiscover = () => {
        if (!routingForm.vault_id) return;
        setDiscoveryLoading(true);
        discoverModelsMutation.mutate(routingForm.vault_id);
    };

    const handlePriorityReorder = (capability: string, newOrder: ApiKey[]) => {
        const orderedIds = newOrder
            .map(item => item?.pivot?.id)
            .filter(id => id !== undefined && id !== null) as number[];

        if (orderedIds.length > 0) {
            updatePriorityMutation.mutate({ capability, ordered_ids: orderedIds });
        }
    };

    if (isLoading) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-slate-500 gap-4">
            <div className="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
            <p className="font-bold tracking-tight animate-pulse">Invocando infraestrutura SRE...</p>
        </div>
    );

    if (isError) return (
        <div className="p-12 flex flex-col items-center justify-center min-h-[400px] text-red-500 gap-4 bg-red-50 rounded-3xl m-8">
            <span className="text-4xl">🚫</span>
            <div className="text-center">
                <p className="font-black text-xl mb-2">Falha Crítica na Comunicação</p>
                <p className="text-sm font-medium opacity-70">{(error as any)?.response?.data?.message || (error as any)?.message || 'Ocorreu um erro ao carregar as chaves de API.'}</p>
            </div>
            <button
                onClick={() => queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] })}
                className="mt-4 px-6 py-2 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-all shadow-lg shadow-red-100"
            >
                Tentar Novamente
            </button>
        </div>
    );

    if (!data) return null;

    const vaultKeys: VaultKey[] = data?.vault_keys || [];
    const availableCapabilities: Record<string, string> = data?.available_capabilities || {};
    const capabilitiesGrid: Record<string, ApiKey[]> = data?.capabilities_grid || {};
    const aiLogs: AiLog[] = data?.ai_logs || [];
    const aiRanking: AiRanking[] = data?.ai_ranking || [];

    const capabilityMeta: Record<string, { icon: string, description: string }> = {
        chat_tutor: { icon: '💬', description: 'Responde dúvidas dos alunos sobre questões resolvidas.' },
        questions: { icon: '✍️', description: 'Gera questões inéditas, explicações e gabaritos comentados.' },
        triage: { icon: '⚙️', description: 'Classifica e modera questões durante o processamento em lote.' },
        search: { icon: '🔍', description: 'Interpreta buscas em linguagem natural na barra de pesquisa.' }
    };

    return (
        <div className="p-4 md:p-6 w-full space-y-6 bg-slate-50/30 min-h-screen">
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">SRE Dashboard: APIs 🤖</h1>
                    <div className="flex items-center gap-4 mt-1">
                        <p className="text-slate-500 font-medium">Monitoramento e controle de provedores de IA para o {aiName}.</p>
                        <button
                            onClick={handleManualRefresh}
                            disabled={isFetching}
                            className={`flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-full text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-slate-50 transition-all shadow-sm ${isFetching ? 'opacity-50 cursor-not-allowed' : ''}`}>
                            <svg className={`w-3 h-3 ${isFetching ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            {isFetching ? 'Sincronizando...' : 'Sincronizar Dados'}
                        </button>
                    </div>
                </div>
                {data.has_recent_errors && (
                    <motion.div
                        initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }}
                        className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm animate-pulse">
                        <div className="flex items-center gap-3">
                            <span className="text-red-500 text-xl font-bold">⚠️</span>
                            <p className="text-sm font-bold text-red-700">ALERTA CRÍTICO: Erros detectados nas últimas 6h.</p>
                        </div>
                    </motion.div>
                )}
            </div>

            {/* Premium Tab Navigation */}
            <div className="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-100 flex gap-1 sticky top-4 z-40 backdrop-blur-xl bg-white/80">
                {[
                    { id: 'dashboard', label: 'Dashboard', icon: '📊' },
                    { id: 'management', label: 'Gerenciamento', icon: '⚙️' },
                    { id: 'failover', label: 'Failover & Prioridade', icon: '🛡️' },
                    { id: 'audit', label: 'Auditoria & Logs', icon: '📋' },
                ].map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id as any)}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs transition-all ${
                            activeTab === tab.id
                                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100 scale-[1.02]'
                                : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600'
                        }`}
                    >
                        <span>{tab.icon}</span>
                        <span className="hidden md:inline">{tab.label}</span>
                    </button>
                ))}
            </div>

            <AnimatePresence mode="wait">
                <motion.div
                    key={activeTab}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -10 }}
                    transition={{ duration: 0.2 }}
                >
                    {activeTab === 'dashboard' && (
                        /* 0. ANALYTICS DASHBOARD */
                        <ApiAnalyticsDash
                            daily={data.analytics_daily || []}
                            modules={data.analytics_modules || []}
                            vaultKeys={vaultKeys}
                            availableCapabilities={availableCapabilities}
                            aiLogs={aiLogs} 
                            aiRanking={aiRanking} 
                            filters={filters}
                            onFilterChange={setFilters}
                            isFetching={isFetching}
                        />
                    )}

                    {activeTab === 'management' && (
                        <div className="space-y-6">
                            {/* Sub-tabs for Management */}
                            <div className="flex gap-4 mb-2">
                                {[
                                    { id: 'list', label: 'Lista de Chaves' },
                                    { id: 'new', label: 'Cadastrar Nova' },
                                    { id: 'routing', label: 'Configurar Roteamento' }
                                ].map(sub => (
                                    <button
                                        key={sub.id}
                                        onClick={() => setActiveSubTab(sub.id)}
                                        className={`px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all ${
                                            activeSubTab === sub.id
                                                ? 'bg-slate-800 text-white'
                                                : 'bg-white text-slate-400 border border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        {sub.label}
                                    </button>
                                ))}
                            </div>

                            {activeSubTab === 'list' && (
                                <section className="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
                                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Chaves Armazenadas no Cofre</h4>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        {vaultKeys.map(vk => (
                                            <div key={vk.id} className="p-4 rounded-xl border border-slate-100 bg-slate-50 shadow-sm hover:border-indigo-200 transition-all flex justify-between items-center group">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-slate-800 truncate">{vk.nickname}</span>
                                                        <span className={`text-[9px] font-black uppercase px-2 py-0.5 rounded-full flex-shrink-0 ${vk.provider === 'openai' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'}`}>
                                                            {vk.provider}
                                                        </span>
                                                    </div>
                                                    <p className="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">
                                                        {vk.is_valid ? <span className="text-emerald-500">✅ Validada</span> : <span className="text-amber-500">❓ Não Testada</span>}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button
                                                        onClick={() => {
                                                            setEditingVaultId(vk.id);
                                                            setVaultForm({ nickname: vk.nickname, provider: vk.provider, key: '' });
                                                            setActiveSubTab('new');
                                                        }}
                                                        className="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-white rounded-lg transition-all"
                                                        title="Editar Chave">✏️</button>
                                                    <button
                                                        onClick={() => {
                                                            if (window.confirm(`Tem certeza?`)) {
                                                                deleteVaultMutation.mutate(vk.id);
                                                            }
                                                        }}
                                                        className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-white rounded-lg transition-all"
                                                        title="Excluir">🗑️</button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {activeSubTab === 'new' && (
                                <section className="max-w-xl bg-white rounded-2xl shadow-sm border border-slate-100 p-8 mx-auto">
                                    <div className="flex justify-between items-center mb-6">
                                        <h4 className="text-sm font-black text-slate-800 uppercase tracking-widest">{editingVaultId ? 'Editar Chave' : 'Nova Chave de API'}</h4>
                                        {editingVaultId && (
                                            <button
                                                onClick={() => { setEditingVaultId(null); setVaultForm({ nickname: '', provider: 'gemini', key: '' }); setActiveSubTab('list'); }}
                                                className="text-[10px] font-black text-indigo-600 uppercase hover:underline">Voltar</button>
                                        )}
                                    </div>
                                    <div className="space-y-4">
                                        <div>
                                            <label className="text-xs font-bold text-slate-600 mb-1 block">Apelido (Ex: Google Prod)</label>
                                            <input
                                                value={vaultForm.nickname}
                                                onChange={e => setVaultForm({ ...vaultForm, nickname: e.target.value })}
                                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                                placeholder="Nome amigável" />
                                        </div>
                                        <div>
                                            <label className="text-xs font-bold text-slate-600 mb-1 block">Provedor</label>
                                            <select
                                                value={vaultForm.provider}
                                                onChange={e => setVaultForm({ ...vaultForm, provider: e.target.value })}
                                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                                                <option value="gemini">Google Gemini</option>
                                                <option value="openai">OpenAI</option>
                                                <option value="grok">Grok (xAI)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="text-xs font-bold text-slate-600 mb-1 block">
                                                {editingVaultId ? 'Nova Chave (deixe em branco para manter)' : 'Chave Secreta'}
                                            </label>
                                            <input
                                                type="password"
                                                value={vaultForm.key}
                                                onChange={e => setVaultForm({ ...vaultForm, key: e.target.value })}
                                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                                placeholder={editingVaultId ? '••••••••••••' : 'sk-...'} />
                                        </div>
                                        <button
                                            onClick={() => addVaultMutation.mutate(vaultForm)}
                                            disabled={addVaultMutation.isPending}
                                            className={`w-full py-3 text-white font-bold rounded-xl shadow-lg disabled:opacity-50 transition-all ${editingVaultId ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-100' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-100'}`}>
                                            {addVaultMutation.isPending ? 'Guardando...' : editingVaultId ? 'Atualizar no Cofre' : 'Guardar no Cofre'}
                                        </button>
                                    </div>
                                </section>
                            )}

                            {activeSubTab === 'routing' && (
                                <section className="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-4xl mx-auto">
                                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3 mb-6">
                                        <span className="p-2 bg-purple-50 text-purple-600 rounded-lg text-sm">🎯</span>
                                        Roteamento Inteligente
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                        <div>
                                            <label className="text-xs font-bold text-slate-600 mb-2 block">1. Selecione a Chave do Cofre</label>
                                            <select
                                                value={routingForm.vault_id}
                                                onChange={e => setRoutingForm({ ...routingForm, vault_id: e.target.value })}
                                                className="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                                                <option value="">-- Escolha um Apelido --</option>
                                                {vaultKeys.map(vk => (
                                                    <option key={vk.id} value={vk.id}>{vk.nickname} ({vk.provider.toUpperCase()})</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="text-xs font-bold text-slate-600 mb-2 block">2. Modelo Selecionado</label>
                                            <div className="flex gap-2">
                                                <div className="flex-1 px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-600 flex items-center">
                                                    {routingForm.preferred_model ? (
                                                        <span className="flex items-center gap-2"><span className="text-emerald-500">✨</span> {routingForm.preferred_model}</span>
                                                    ) : <span className="text-slate-400 italic">Descoberta Necessária...</span>}
                                                </div>
                                                <button
                                                    onClick={handleDiscover}
                                                    disabled={!routingForm.vault_id || discoveryLoading}
                                                    className="px-6 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm disabled:opacity-50">
                                                    {discoveryLoading ? <span className="animate-spin inline-block">⌛</span> : '🔍'}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-6 bg-slate-50/50 rounded-2xl border border-slate-100">
                                        <div className="flex justify-between items-center mb-6">
                                            <h4 className="text-sm font-bold text-slate-700">3. Atribuir Funcionalidades</h4>
                                            <div className="flex gap-2">
                                                <button onClick={() => setRoutingForm({...routingForm, capabilities: Object.keys(availableCapabilities)})} className="text-[10px] font-black text-indigo-600 uppercase">Todas</button>
                                                <button onClick={() => setRoutingForm({...routingForm, capabilities: []})} className="text-[10px] font-black text-slate-400 uppercase">Limpar</button>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            {Object.entries(availableCapabilities).map(([cap, label]) => {
                                                const isSelected = routingForm.capabilities.includes(cap);
                                                return (
                                                    <button key={cap} onClick={() => {
                                                        const next = isSelected ? routingForm.capabilities.filter(c => c !== cap) : [...routingForm.capabilities, cap];
                                                        setRoutingForm({...routingForm, capabilities: next});
                                                    }} className={`p-4 rounded-xl border-2 text-left transition-all ${isSelected ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg' : 'bg-white border-slate-100 text-slate-600'}`}>
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <span>{capabilityMeta[cap]?.icon || '✨'}</span>
                                                            <span className="text-[10px] font-black uppercase">{label}</span>
                                                        </div>
                                                        <p className={`text-[10px] leading-tight ${isSelected ? 'text-indigo-100' : 'text-slate-400'}`}>{capabilityMeta[cap]?.description}</p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                    <button onClick={() => activateRoutingMutation.mutate(routingForm)} disabled={activateRoutingMutation.isPending || !routingForm.vault_id || !routingForm.preferred_model || routingForm.capabilities.length === 0} className="w-full mt-8 py-4 bg-slate-900 text-white font-black uppercase tracking-widest rounded-2xl shadow-xl">
                                        {activateRoutingMutation.isPending ? 'Ativando...' : '⚡ Ativar Roteamento AI'}
                                    </button>
                                </section>
                            )}
                        </div>
                    )}

                    {activeTab === 'failover' && (
                        <div className="space-y-6">
                            <div className="bg-indigo-900 rounded-3xl p-8 text-white relative overflow-hidden shadow-2xl">
                                <h3 className="text-2xl font-black mb-2">Failover M:N Dinâmico 🛡️</h3>
                                <p className="text-indigo-200 text-sm font-medium">Arraste os provedores para definir a ordem de prioridade. O sistema usará o primeiro online disponível.</p>
                            </div>
                            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                {availableCapabilities && Object.entries(availableCapabilities).map(([cap, label]) => {
                                    const keys = (capabilitiesGrid && capabilitiesGrid[cap]) || [];
                                    return (
                                        <div key={cap} className="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                                            <div className="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                                                <h4 className="font-bold text-slate-800 text-sm flex items-center gap-2">
                                                    <span>{capabilityMeta[cap]?.icon}</span> {label}
                                                </h4>
                                                <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{cap}</span>
                                            </div>
                                            <div className="p-4 flex-1">
                                                {keys.length === 0 ? <p className="text-center py-10 text-slate-400 text-xs italic">Nada configurado.</p> : (
                                                    <Reorder.Group axis="y" values={keys} onReorder={(newOrder) => handlePriorityReorder(cap, newOrder)} className="space-y-2">
                                                        {keys.map((key, idx) => (
                                                            <Reorder.Item key={key.pivot?.id || key.id} value={key} className={`p-3 bg-white border border-slate-100 rounded-xl shadow-sm flex items-center gap-3 cursor-grab active:cursor-grabbing hover:border-indigo-300 transition-all ${key.status !== 'online' ? 'bg-red-50' : ''}`}>
                                                                <div className={`w-6 h-6 rounded-full flex items-center justify-center font-black text-[10px] ${idx === 0 ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-100 text-slate-400'}`}>{idx + 1}</div>
                                                                <div className="flex-1 min-w-0">
                                                                    <div className="flex items-center gap-2">
                                                                        {key.status === 'online' ? (
                                                                            <span className="flex h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]" title="Online"></span>
                                                                        ) : (
                                                                            <span className="flex h-2 w-2 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]" title="Offline"></span>
                                                                        )}
                                                                        <p className="font-bold text-slate-800 text-sm truncate">{key.effective_provider} ({key.vault?.nickname || 'Direto'})</p>
                                                                    </div>
                                                                    <p className="text-[10px] font-mono text-slate-400 truncate mt-0.5">{key.preferred_model || 'Auto'}</p>
                                                                </div>
                                                                <button onClick={() => retestMutation.mutate(key.id)} className="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg">⚡</button>
                                                                <button onClick={() => key.pivot?.id && deleteApiKeyMutation.mutate(key.pivot.id)} className="p-1.5 text-red-400 hover:bg-red-50 rounded-lg">✕</button>
                                                            </Reorder.Item>
                                                        ))}
                                                    </Reorder.Group>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {activeTab === 'audit' && (
                        <div className="space-y-6">
                            <div className="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                                <div className="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                                    <h3 className="font-bold text-slate-800 flex items-center gap-3">
                                        <span className="p-2 bg-indigo-50 text-indigo-600 rounded-lg text-xs">📋</span>
                                        Logs Auditados
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-white text-slate-400 font-bold uppercase tracking-widest whitespace-nowrap">
                                            <tr>
                                                <th className="px-6 py-4">Usuário</th>
                                                <th className="px-6 py-4">Módulo</th>
                                                <th className="px-6 py-4">Horário</th>
                                                <th className="px-6 py-4">Modelo</th>
                                                <th className="px-6 py-4">Tokens</th>
                                                <th className="px-6 py-4 text-right">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50">
                                            {aiLogs.map(log => (
                                                <tr key={log.id} className="hover:bg-slate-50 transition-colors group">
                                                    <td className="px-6 py-4 font-bold text-slate-700">{log.user?.name || 'Sistema'}</td>
                                                    <td className="px-6 py-4 uppercase text-[9px] font-black text-slate-400">{log.module}</td>
                                                    <td className="px-6 py-4">{new Date(log.created_at).toLocaleTimeString()}</td>
                                                    <td className="px-6 py-4">
                                                        <p className="font-bold text-slate-800">{log.provider}</p>
                                                        <p className="text-[10px] text-slate-400 font-mono">{log.model}</p>
                                                    </td>
                                                    <td className="px-6 py-4 font-mono">{log.tokens_used_input + log.tokens_used_output}</td>
                                                    <td className="px-6 py-4 text-right">
                                                        <button onClick={() => { setActiveLog(log); setShowLogModal(true); }} className="text-indigo-600 font-bold hover:underline">Ver</button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}
                </motion.div>
            </AnimatePresence>

            {/* MODALS */}
            {showModelModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" onClick={() => setShowModelModal(false)} />
                    <motion.div initial={{ scale: 0.9, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} className="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-100">
                        <div className="bg-indigo-600 p-6 flex justify-between items-center text-white">
                            <h3 className="font-black">🤖 Modelos Disponíveis</h3>
                            <button onClick={() => setShowModelModal(false)}>✕</button>
                        </div>
                        <div className="p-4 max-h-[60vh] overflow-y-auto space-y-2">
                            {(discoveredModels || []).map((m: any) => (
                                <div key={m.id} onClick={() => { setRoutingForm({...routingForm, preferred_model: m.id}); setShowModelModal(false); }} className="p-3 border rounded-xl hover:bg-slate-50 cursor-pointer flex justify-between items-center group transition-all">
                                    <div className="min-w-0">
                                        <p className="font-bold text-sm text-slate-800 truncate">{m.name}</p>
                                        <p className="text-[10px] font-mono text-slate-400 truncate">{m.id}</p>
                                    </div>
                                    <span className="text-indigo-600 font-bold opacity-0 group-hover:opacity-100 transition-all shrink-0">Selecionar</span>
                                </div>
                            ))}
                            {(!discoveredModels || discoveredModels.length === 0) && (
                                <p className="text-center py-8 text-slate-400 italic">Nenhum modelo descoberto.</p>
                            )}
                        </div>
                    </motion.div>
                </div>
            )}

            {showLogModal && activeLog && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" onClick={() => setShowLogModal(false)} />
                    <motion.div initial={{ y: 50, opacity: 0 }} animate={{ y: 0, opacity: 1 }} className="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
                        <div className="p-6 border-b flex justify-between items-center bg-slate-50">
                            <h3 className="text-lg font-black text-slate-800">Detalhes Trasancionais IA</h3>
                            <button onClick={() => setShowLogModal(false)} className="p-2 hover:bg-slate-200 rounded-full transition-colors">✕</button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6 space-y-6">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                                <div><p className="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Usuário</p><p className="font-bold">{activeLog.user?.name || 'Sistema'}</p></div>
                                <div><p className="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Provedor</p><p className="font-bold">{activeLog.provider}</p></div>
                                <div><p className="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Módulo</p><p className="font-bold">{activeLog.module}</p></div>
                                <div><p className="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Custo Est.</p><p className="font-bold text-indigo-600 font-mono">R$ {Number(activeLog.estimated_cost).toFixed(4)}</p></div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-[9px] font-black uppercase text-slate-400 tracking-widest">Prompt</p>
                                <div className="bg-slate-900 p-4 rounded-xl text-emerald-400 font-mono text-[10px] whitespace-pre-wrap shadow-inner overflow-x-auto">{activeLog.prompt_text}</div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between items-center">
                                    <p className="text-[9px] font-black uppercase text-slate-400 tracking-widest">Resposta</p>
                                    <button onClick={() => setShowRawResponse(!showRawResponse)} className="text-[9px] font-black uppercase text-indigo-600 hover:underline">Toggle Visualização</button>
                                </div>
                                <div className={`p-4 rounded-xl font-mono text-[10px] whitespace-pre-wrap transition-colors shadow-inner ${showRawResponse ? 'bg-slate-900 text-emerald-400' : 'bg-slate-50 text-slate-700'}`}>
                                    {showRawResponse ? activeLog.response_text : (() => {
                                        try {
                                            const clean = activeLog.response_text.replace(/```json\n?|\n?```/g, '').trim();
                                            return JSON.stringify(JSON.parse(clean), null, 2);
                                        } catch { return activeLog.response_text; }
                                    })()}
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </div>
    );
}

export default AdminApiKeys;
