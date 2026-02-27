import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence, Reorder } from 'framer-motion';
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
    created_at: string;
}

interface AiRanking {
    user_id: number;
    user: { name: string; email: string } | null;
    total_tokens: number;
    total_cost: number;
    request_count: number;
}

interface ApiEvent {
    id: number;
    type: 'success' | 'error' | 'warning' | 'fallback';
    provider: string;
    message: string;
    status_code: number | null;
    created_at: string;
}

export default function AdminApiKeys() {
    const { aiName } = useConfigStore();
    const queryClient = useQueryClient();

    // UI Local State
    const [vaultCollapsed, setVaultCollapsed] = useState(false);
    const [routingCollapsed, setRoutingCollapsed] = useState(false);
    const [priorityCollapsed, setPriorityCollapsed] = useState(false);
    const [historyCollapsed, setHistoryCollapsed] = useState(false);

    // Modals
    const [showModelModal, setShowModelModal] = useState(false);
    const [showLogModal, setShowLogModal] = useState(false);
    const [activeLog, setActiveLog] = useState<AiLog | null>(null);
    const [discoveredModels, setDiscoveredModels] = useState<any[]>([]);

    // Form States
    const [vaultForm, setVaultForm] = useState({ nickname: '', provider: 'gemini', key: '' });
    const [routingForm, setRoutingForm] = useState({ vault_id: '', preferred_model: '', capabilities: [] as string[] });
    const [discoveryLoading, setDiscoveryLoading] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-api-keys'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-keys');
            return res.data;
        }
    });

    // Mutations
    const addVaultMutation = useMutation({
        mutationFn: async (payload: any) => api.post('/api/v1/admin/api-keys/vault', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            setVaultForm({ nickname: '', provider: 'gemini', key: '' });
        }
    });

    const discoverModelsMutation = useMutation({
        mutationFn: async (vaultId: string) => api.post('/api/v1/admin/api-keys/discover', { vault_id: vaultId }),
        onSuccess: (res) => {
            if (res.data.is_valid && res.data.models) {
                setDiscoveredModels(res.data.models);
                setShowModelModal(true);
            } else {
                alert(res.data.error || 'Falha na descoberta de modelos.');
            }
        },
        onSettled: () => setDiscoveryLoading(false)
    });

    const activateRoutingMutation = useMutation({
        mutationFn: async (payload: any) => api.post('/api/v1/admin/api-keys', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-keys'] });
            setRoutingForm({ vault_id: '', preferred_model: '', capabilities: [] });
            alert('Roteamento ativado com sucesso!');
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
        const orderedIds = newOrder.map(item => item.pivot.id);
        updatePriorityMutation.mutate({ capability, ordered_ids: orderedIds });
    };

    if (isLoading) return <div className="p-8 flex justify-center items-center font-bold text-gray-500 animate-pulse">Invocando infraestrutura SRE...</div>;
    if (!data) return null;

    const vaultKeys: VaultKey[] = data.vault_keys || [];
    const availableCapabilities: Record<string, string> = data.available_capabilities || {};
    const capabilitiesGrid: Record<string, ApiKey[]> = data.capabilities_grid || {};
    const aiLogs: AiLog[] = data.ai_logs || [];
    const aiRanking: AiRanking[] = data.ai_ranking || [];
    const events: ApiEvent[] = data.logs || [];

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto space-y-8 bg-slate-50/30 min-h-screen">
            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">SRE Dashboard: APIs 🤖</h1>
                    <p className="text-slate-500 font-medium">Monitoramento e controle de provedores de IA para o {aiName}.</p>
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

            {/* 1. KEY VAULT */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div
                    onClick={() => setVaultCollapsed(!vaultCollapsed)}
                    className="p-6 border-b border-slate-50 flex justify-between items-center cursor-pointer hover:bg-slate-50 transition-colors">
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-indigo-50 text-indigo-600 rounded-lg text-sm">🔐</span>
                        Cofre de Chaves (Key Vault)
                    </h3>
                    <motion.svg animate={{ rotate: vaultCollapsed ? 0 : 180 }} className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></motion.svg>
                </div>

                <AnimatePresence>
                    {!vaultCollapsed && (
                        <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
                            <div className="p-8 grid grid-cols-1 lg:grid-cols-3 gap-10">
                                {/* Form */}
                                <div className="space-y-4">
                                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest">Novo Registro</h4>
                                    <div className="space-y-3">
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
                                            <label className="text-xs font-bold text-slate-600 mb-1 block">Chave Secreta</label>
                                            <input
                                                type="password"
                                                value={vaultForm.key}
                                                onChange={e => setVaultForm({ ...vaultForm, key: e.target.value })}
                                                className="w-full px-4 py-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                                placeholder="sk-..." />
                                        </div>
                                        <button
                                            onClick={() => addVaultMutation.mutate(vaultForm)}
                                            disabled={addVaultMutation.isPending}
                                            className="w-full py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-100 disabled:opacity-50 transition-all">
                                            {addVaultMutation.isPending ? 'Guardando...' : 'Guardar no Cofre'}
                                        </button>
                                    </div>
                                </div>

                                {/* List */}
                                <div className="lg:col-span-2 space-y-4">
                                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest">Chaves Armazenadas</h4>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        {vaultKeys.map(vk => (
                                            <div key={vk.id} className="p-4 rounded-xl border border-slate-100 bg-white shadow-sm hover:border-indigo-200 transition-all flex justify-between items-center group">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-slate-800">{vk.nickname}</span>
                                                        <span className={`text-[10px] font-black uppercase px-2 py-0.5 rounded-full ${vk.provider === 'openai' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'}`}>
                                                            {vk.provider}
                                                        </span>
                                                    </div>
                                                    <p className="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">
                                                        Status: {vk.is_valid ? <span className="text-emerald-500">✅ Validada</span> : <span className="text-amber-500">❓ Não Testada</span>}
                                                    </p>
                                                </div>
                                                <span className="text-[10px] text-slate-300 italic group-hover:text-indigo-400 transition-colors">No Cofre</span>
                                            </div>
                                        ))}
                                        {vaultKeys.length === 0 && <p className="col-span-2 text-center py-10 text-slate-400 italic">O cofre está vazio.</p>}
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </section>

            {/* 2. ROUTING CONFIG */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div
                    onClick={() => setRoutingCollapsed(!routingCollapsed)}
                    className="p-6 border-b border-slate-50 flex justify-between items-center cursor-pointer hover:bg-slate-50 transition-colors">
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-purple-50 text-purple-600 rounded-lg text-sm">🎯</span>
                        Configuração por Funcionalidade (Roteamento)
                    </h3>
                    <motion.svg animate={{ rotate: routingCollapsed ? 0 : 180 }} className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></motion.svg>
                </div>

                <AnimatePresence>
                    {!routingCollapsed && (
                        <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
                            <div className="p-8 space-y-8">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <label className="text-xs font-bold text-slate-600 mb-2 block">1. Selecione a Chave do Cofre</label>
                                        <select
                                            value={routingForm.vault_id}
                                            onChange={e => {
                                                setRoutingForm({ ...routingForm, vault_id: e.target.value });
                                                // Handle discovery logic or just rely on the manual button
                                            }}
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
                                        <span className="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Roteamento N:N</span>
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        {Object.entries(availableCapabilities).map(([code, label]) => (
                                            <label key={code} className="relative flex items-center bg-white p-4 rounded-xl border border-slate-100 shadow-sm hover:border-indigo-300 transition-all cursor-pointer group">
                                                <input
                                                    type="checkbox"
                                                    checked={routingForm.capabilities.includes(code)}
                                                    onChange={e => {
                                                        const caps = e.target.checked
                                                            ? [...routingForm.capabilities, code]
                                                            : routingForm.capabilities.filter(c => c !== code);
                                                        setRoutingForm({ ...routingForm, capabilities: caps });
                                                    }}
                                                    className="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                                <div className="ml-3">
                                                    <span className="text-sm font-bold text-slate-700 block">{label}</span>
                                                    <span className="text-[10px] text-slate-400 font-medium">Configurar motor</span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <button
                                        onClick={() => activateRoutingMutation.mutate(routingForm)}
                                        disabled={!routingForm.preferred_model || routingForm.capabilities.length === 0}
                                        className="px-10 py-4 bg-indigo-600 text-white font-bold rounded-2xl shadow-xl shadow-indigo-100 hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 disabled:scale-100">
                                        Ativar Roteamento
                                    </button>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </section>

            {/* 3. PRIORITY GRID */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div
                    onClick={() => setPriorityCollapsed(!priorityCollapsed)}
                    className="p-6 border-b border-slate-50 flex justify-between items-center cursor-pointer hover:bg-slate-50 transition-colors">
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-emerald-50 text-emerald-600 rounded-lg text-sm">⚡</span>
                        Prioridades de Roteamento (Failover M:N)
                    </h3>
                    <motion.svg animate={{ rotate: priorityCollapsed ? 0 : 180 }} className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></motion.svg>
                </div>

                <AnimatePresence>
                    {!priorityCollapsed && (
                        <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
                            <div className="p-8 bg-slate-50/50 space-y-8">
                                <p className="text-sm text-slate-500 font-medium italic">
                                    (ℹ) Arraste e solte os provedores para definir a ordem de tentativa. O sistema usará o primeiro online.
                                </p>
                                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                    {Object.entries(availableCapabilities).map(([cap, label]) => {
                                        const keys = capabilitiesGrid[cap] || [];
                                        return (
                                            <div key={cap} className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col">
                                                <div className="bg-indigo-50/30 px-4 py-3 border-b border-indigo-100 flex justify-between items-center">
                                                    <h4 className="font-bold text-indigo-900 text-sm flex items-center gap-2">
                                                        <span className="text-indigo-400">⚡</span> {label}
                                                    </h4>
                                                    <span className="text-[10px] font-black bg-white border border-indigo-100 px-2 py-0.5 rounded text-indigo-400 uppercase">{cap}</span>
                                                </div>
                                                <div className="p-4 flex-1">
                                                    {keys.length === 0 ? (
                                                        <div className="py-10 text-center border-2 border-dashed border-slate-100 rounded-xl text-slate-400 text-xs italic">
                                                            Nenhum provedor configurado.
                                                        </div>
                                                    ) : (
                                                        <Reorder.Group axis="y" values={keys} onReorder={(newOrder) => handlePriorityReorder(cap, newOrder)} className="space-y-3">
                                                            {keys.map((key, index) => (
                                                                <Reorder.Item
                                                                    key={key.pivot.id}
                                                                    value={key}
                                                                    className={`p-3 bg-white border border-slate-100 rounded-xl shadow-sm flex items-center gap-3 cursor-grab active:cursor-grabbing hover:border-indigo-300 transition-all ${key.status === 'offline' ? 'bg-red-50/30 border-red-100' : 'bg-white'
                                                                        }`}>
                                                                    <div className="text-slate-300">
                                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                                                    </div>
                                                                    <div className={`w-6 h-6 rounded-full flex items-center justify-center font-black text-[10px] ${index === 0 ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-500'}`}>
                                                                        {index + 1}
                                                                    </div>
                                                                    <div className="flex-1 min-w-0">
                                                                        <div className="flex items-center gap-2 mb-0.5">
                                                                            <span className="font-bold text-slate-800 text-sm truncate">{key.effective_provider}</span>
                                                                            <span className="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-slate-400 whitespace-nowrap">
                                                                                {key.vault?.nickname || 'Direto'}
                                                                            </span>
                                                                        </div>
                                                                        <p className="text-[10px] font-mono text-slate-400 truncate">{key.preferred_model || 'Auto'}</p>
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        <span className={`w-2 h-2 rounded-full ${key.status === 'online' ? 'bg-emerald-500' : key.status === 'quota_exceeded' ? 'bg-amber-500' : 'bg-red-500'}`}></span>
                                                                        <div className="flex divide-x divide-slate-100 border border-slate-100 rounded-lg overflow-hidden bg-slate-50/50">
                                                                            <button onClick={() => retestMutation.mutate(key.id)} className="p-1.5 hover:bg-slate-100 text-amber-600">⚡</button>
                                                                            <button onClick={() => deleteApiKeyMutation.mutate(key.pivot.id)} className="p-1.5 hover:bg-red-50 text-red-500">✕</button>
                                                                        </div>
                                                                    </div>
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
                        </motion.div>
                    )}
                </AnimatePresence>
            </section>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* 4. ACTIVITY LOGS */}
                <div className="lg:col-span-2 space-y-8">
                    <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div className="p-6 border-b border-slate-50 flex justify-between items-center">
                            <h3 className="font-bold text-slate-800">Histórico de Uso (IA Logs)</h3>
                            <button onClick={() => setHistoryCollapsed(!historyCollapsed)} className="text-slate-400">
                                <motion.svg animate={{ rotate: historyCollapsed ? 0 : 180 }} className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></motion.svg>
                            </button>
                        </div>
                        <AnimatePresence>
                            {!historyCollapsed && (
                                <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-slate-400 uppercase font-bold">
                                            <tr>
                                                <th className="px-6 py-4">Usuário</th>
                                                <th className="px-6 py-4">Provedor/Modelo</th>
                                                <th className="px-6 py-4">Tokens (I/O)</th>
                                                <th className="px-6 py-4">Tempo</th>
                                                <th className="px-6 py-4">Custo (R$)</th>
                                                <th className="px-6 py-4 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50">
                                            {aiLogs.map(log => (
                                                <tr key={log.id} className="hover:bg-slate-50 transition-colors">
                                                    <td className="px-6 py-4 font-bold text-slate-700">{log.user?.name || 'Sistema/Job'}</td>
                                                    <td className="px-6 py-4">
                                                        <p className="font-bold text-slate-800">{log.provider}</p>
                                                        <p className="text-[10px] text-slate-400 font-mono">{log.model}</p>
                                                    </td>
                                                    <td className="px-6 py-4 font-mono">{log.tokens_used_input} / {log.tokens_used_output}</td>
                                                    <td className="px-6 py-4">{log.execution_time.toFixed(2)}s</td>
                                                    <td className="px-6 py-4 font-bold text-slate-700">{log.estimated_cost.toFixed(4)}</td>
                                                    <td className="px-6 py-4 text-right">
                                                        <button
                                                            onClick={() => { setActiveLog(log); setShowLogModal(true); }}
                                                            className="text-indigo-600 font-bold hover:underline">Detalhes</button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {aiLogs.length === 0 && <tr><td colSpan={6} className="text-center py-10 text-slate-400 italic">Nenhum log registrado.</td></tr>}
                                        </tbody>
                                    </table>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </section>

                    {/* API Events List */}
                    <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                        <h3 className="font-bold text-slate-800 mb-6">Logs de Eventos Recentes</h3>
                        <div className="space-y-4">
                            {events.map((e, idx) => (
                                <div key={idx} className="flex items-start gap-4 p-3 bg-slate-50/50 rounded-xl border border-slate-100">
                                    <span className={`mt-0.5 text-lg ${e.type === 'success' ? 'text-emerald-500' : e.type === 'error' ? 'text-red-500' : 'text-amber-500'}`}>
                                        {e.type === 'success' ? '✅' : e.type === 'error' ? '❌' : '⚠️'}
                                    </span>
                                    <div className="flex-1">
                                        <div className="flex justify-between">
                                            <p className="text-xs font-black uppercase text-slate-400 tracking-widest">{e.provider} • {new Date(e.created_at).toLocaleTimeString()}</p>
                                            {e.status_code && <span className="text-[10px] px-1.5 rounded font-mono bg-white border border-slate-200">CODE: {e.status_code}</span>}
                                        </div>
                                        <p className="text-sm font-medium text-slate-600 mt-1">{e.message}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* 5. RANKING */}
                <div className="space-y-8">
                    <section className="bg-slate-900 rounded-3xl p-8 text-white shadow-2xl relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                        <h3 className="text-xl font-black mb-8 flex items-center gap-3">
                            <span className="text-2xl">🏆</span> Maiores Consumidores
                        </h3>
                        <div className="space-y-8">
                            {aiRanking.map((rank, idx) => (
                                <div key={idx} className="flex items-center gap-5 group">
                                    <span className={`text-2xl font-black ${idx === 0 ? 'text-yellow-400' : idx === 1 ? 'text-slate-300' : idx === 2 ? 'text-amber-600' : 'text-slate-700'}`}>
                                        {(idx + 1).toString().padStart(2, '0')}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-bold truncate text-indigo-50 group-hover:text-white transition-colors">{rank.user?.name || 'Anon'}</p>
                                        <p className="text-[10px] font-black uppercase tracking-widest text-indigo-400/60 mt-0.5">{rank.total_tokens.toLocaleString()} tokens</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-black text-emerald-400 tracking-tighter text-lg">R$ {rank.total_cost.toFixed(2)}</p>
                                    </div>
                                </div>
                            ))}
                            {aiRanking.length === 0 && <p className="text-center text-slate-500 py-10 font-medium italic">Ranking indisponível.</p>}
                        </div>
                    </section>
                </div>
            </div>

            {/* MODALS */}

            {/* Model Selection */}
            {showModelModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" onClick={() => setShowModelModal(false)} />
                    <motion.div initial={{ scale: 0.9, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} className="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-100">
                        <div className="bg-indigo-600 p-6 flex justify-between items-center text-white">
                            <h3 className="font-black flex items-center gap-2">🤖 Modelos Disponíveis</h3>
                            <button onClick={() => setShowModelModal(false)}>✕</button>
                        </div>
                        <div className="p-4 max-h-[60vh] overflow-y-auto space-y-2">
                            {discoveredModels.map(m => (
                                <div
                                    key={m.id}
                                    onClick={() => { setRoutingForm({ ...routingForm, preferred_model: m.id }); setShowModelModal(false); }}
                                    className="p-4 border rounded-2xl hover:bg-slate-50 cursor-pointer flex justify-between items-center group transition-all">
                                    <div>
                                        <p className="font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">{m.name}</p>
                                        <p className="text-[10px] font-mono text-slate-400">{m.id}</p>
                                    </div>
                                    <span className="text-indigo-600 font-bold opacity-0 group-hover:opacity-100 transition-all">Selecionar →</span>
                                </div>
                            ))}
                        </div>
                    </motion.div>
                </div>
            )}

            {/* Log Detail */}
            {showLogModal && activeLog && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-10">
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" onClick={() => setShowLogModal(false)} />
                    <motion.div initial={{ y: 50, opacity: 0 }} animate={{ y: 0, opacity: 1 }} className="relative bg-white rounded-3xl shadow-2xl w-full max-w-5xl h-full max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
                        <div className="p-6 border-b border-slate-100 flex justify-between items-center">
                            <h3 className="text-xl font-black text-slate-800">Detalhes da Transação IA</h3>
                            <button onClick={() => setShowLogModal(false)} className="p-2 hover:bg-slate-100 rounded-full">✕</button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-8 space-y-8 font-sans">
                            <div className="grid grid-cols-2 md:grid-cols-5 gap-6">
                                <div><p className="text-[10px] font-black uppercase text-slate-400 mb-1">Usuário</p><p className="font-bold text-slate-800">{activeLog.user?.name || 'Sistema'}</p></div>
                                <div><p className="text-[10px] font-black uppercase text-slate-400 mb-1">Provedor / Modelo</p><p className="font-bold text-slate-800">{activeLog.provider} / {activeLog.model}</p></div>
                                <div><p className="text-[10px] font-black uppercase text-slate-400 mb-1">Tokens (I/O)</p><p className="font-bold text-slate-800">{activeLog.tokens_used_input} / {activeLog.tokens_used_output}</p></div>
                                <div><p className="text-[10px] font-black uppercase text-slate-400 mb-1">Tempo Execução</p><p className="font-bold text-slate-800">{activeLog.execution_time.toFixed(3)}s</p></div>
                                <div><p className="text-[10px] font-black uppercase text-slate-400 mb-1">Custo Est.</p><p className="font-bold text-indigo-600">R$ {activeLog.estimated_cost.toFixed(4)}</p></div>
                            </div>
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <h4 className="text-xs font-black uppercase text-slate-400 tracking-widest pl-2 border-l-4 border-indigo-500">Prompt Enviado</h4>
                                    <div className="bg-slate-900 p-6 rounded-2xl text-emerald-400 font-mono text-xs whitespace-pre-wrap overflow-x-auto shadow-inner border border-slate-800">
                                        {activeLog.prompt_text}
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <h4 className="text-xs font-black uppercase text-slate-400 tracking-widest pl-2 border-l-4 border-purple-500">Resposta da IA</h4>
                                    <div className="bg-indigo-50/50 p-6 rounded-2xl text-slate-700 font-medium text-xs whitespace-pre-wrap shadow-inner border border-indigo-100 italic">
                                        {activeLog.response_text}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </div>
    );
}
