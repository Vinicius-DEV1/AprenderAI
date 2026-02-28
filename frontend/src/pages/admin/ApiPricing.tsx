import { toast } from 'sonner';
import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../../api/axios';

// Interfaces
interface ApiPricingEntry {
    id: number;
    api_name: string;
    model_key: string;
    input_price_per_1m: number;
    output_price_per_1m: number;
    updated_at: string;
    updated_by: { id: number; name: string } | null;
}

interface EditForm {
    input_price_per_1m: string;
    output_price_per_1m: string;
}

interface CreateForm {
    api_name: string;
    model_key: string;
    input_price_per_1m: string;
    output_price_per_1m: string;
}

interface VaultEntry {
    id: number;
    nickname: string;
    provider: string;
}

interface DiscoveredModel {
    id: string;
    name: string;
}

const providerColors: Record<string, string> = {
    'Google Gemini': 'bg-blue-100 text-blue-700',
    'OpenAI': 'bg-emerald-100 text-emerald-700',
    'xAI': 'bg-purple-100 text-purple-700',
    'Genérico': 'bg-slate-100 text-slate-600',
    'gemini': 'bg-blue-100 text-blue-700',
    'openai': 'bg-emerald-100 text-emerald-700',
    'grok': 'bg-purple-100 text-purple-700'
};

export default function ApiPricing() {
    const queryClient = useQueryClient();

    // States for Editing
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editForm, setEditForm] = useState<EditForm>({ input_price_per_1m: '', output_price_per_1m: '' });
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingUpdate, setPendingUpdate] = useState<{ id: number; form: EditForm } | null>(null);
    const [logsOpen, setLogsOpen] = useState<number | null>(null);

    // States for Creating
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [createTab, setCreateTab] = useState<'manual' | 'api'>('manual');
    const [createForm, setCreateForm] = useState<CreateForm>({
        api_name: '',
        model_key: '',
        input_price_per_1m: '',
        output_price_per_1m: ''
    });

    // States for API Discovery
    const [selectedVaultId, setSelectedVaultId] = useState<string>('');
    const [isDiscovering, setIsDiscovering] = useState(false);
    const [discoveredModels, setDiscoveredModels] = useState<DiscoveredModel[]>([]);

    // ----------------------------------------------------
    // Queries
    // ----------------------------------------------------
    const { data, isLoading } = useQuery({
        queryKey: ['admin-api-pricing'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-pricing');
            return res.data.data as ApiPricingEntry[];
        }
    });

    const { data: logsData, isLoading: logsLoading } = useQuery({
        queryKey: ['admin-api-pricing-logs', logsOpen],
        queryFn: async () => {
            if (!logsOpen) return [];
            const res = await api.get(`/api/v1/admin/api-pricing/${logsOpen}/logs`);
            return res.data.data;
        },
        enabled: !!logsOpen,
    });

    const { data: vaultsData } = useQuery({
        queryKey: ['admin-api-pricing-vaults'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-pricing/vaults');
            return res.data.data as VaultEntry[];
        },
        enabled: createModalOpen && createTab === 'api'
    });

    // ----------------------------------------------------
    // Mutations
    // ----------------------------------------------------
    const updateMutation = useMutation({
        mutationFn: async ({ id, form }: { id: number; form: EditForm }) => {
            const res = await api.put(`/api/v1/admin/api-pricing/${id}`, {
                input_price_per_1m: parseFloat(form.input_price_per_1m),
                output_price_per_1m: parseFloat(form.output_price_per_1m),
            });
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing'] });
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing-logs'] });
            toast.success(data.message || 'Preço atualizado com sucesso!');
            setEditingId(null);
            setConfirmOpen(false);
            setPendingUpdate(null);
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.message || 'Erro ao atualizar preço.';
            toast.error(msg);
        }
    });

    const createMutation = useMutation({
        mutationFn: async (form: CreateForm) => {
            const res = await api.post('/api/v1/admin/api-pricing', {
                api_name: form.api_name,
                model_key: form.model_key,
                input_price_per_1m: parseFloat(form.input_price_per_1m),
                output_price_per_1m: parseFloat(form.output_price_per_1m),
            });
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-api-pricing'] });
            toast.success(data.message || 'Preço cadastrado com sucesso!');
            setCreateModalOpen(false);
            resetCreateForm();
        },
        onError: (err: any) => {
            const msg = err.response?.data?.message || err.message || 'Erro ao cadastrar preço.';
            toast.error(msg);
        }
    });

    // ----------------------------------------------------
    // Actions
    // ----------------------------------------------------
    const openEdit = (entry: ApiPricingEntry) => {
        setEditingId(entry.id);
        setEditForm({
            input_price_per_1m: String(entry.input_price_per_1m),
            output_price_per_1m: String(entry.output_price_per_1m),
        });
    };

    const requestSave = () => {
        if (!editingId) return;
        const inputVal = parseFloat(editForm.input_price_per_1m);
        const outputVal = parseFloat(editForm.output_price_per_1m);
        if (isNaN(inputVal) || inputVal < 0 || isNaN(outputVal) || outputVal < 0) {
            toast.error('Valores devem ser números não-negativos.');
            return;
        }
        setPendingUpdate({ id: editingId, form: editForm });
        setConfirmOpen(true);
    };

    const confirmSave = () => {
        if (pendingUpdate) {
            updateMutation.mutate(pendingUpdate);
        }
    };

    const resetCreateForm = () => {
        setCreateForm({
            api_name: '',
            model_key: '',
            input_price_per_1m: '',
            output_price_per_1m: ''
        });
        setSelectedVaultId('');
        setDiscoveredModels([]);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const inputVal = parseFloat(createForm.input_price_per_1m);
        const outputVal = parseFloat(createForm.output_price_per_1m);

        if (!createForm.api_name || !createForm.model_key) {
            toast.error('Provedor e Modelo são obrigatórios.');
            return;
        }
        if (isNaN(inputVal) || inputVal < 0 || isNaN(outputVal) || outputVal < 0) {
            toast.error('Os preços devem ser números positivos.');
            return;
        }

        createMutation.mutate(createForm);
    };

    const discoverModelsFromVault = async () => {
        if (!selectedVaultId) return;
        setIsDiscovering(true);
        setDiscoveredModels([]);
        try {
            const res = await api.post('/api/v1/admin/api-keys/discover', { vault_id: selectedVaultId });
            if (res.data.is_valid && res.data.models) {
                setDiscoveredModels(res.data.models);
                toast.success(`${res.data.models.length} modelos encontrados!`);
            } else {
                toast.error(res.data.error || 'Nenhum modelo retornado ou chave inválida.');
            }
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Erro ao buscar modelos na API.');
        } finally {
            setIsDiscovering(false);
        }
    };

    const handleSelectDiscoveredModel = (modelId: string) => {
        const vault = vaultsData?.find(v => v.id.toString() === selectedVaultId);
        if (vault) {
            // Map common provider names for nicer display
            const providerNames: Record<string, string> = {
                'openai': 'OpenAI',
                'gemini': 'Google Gemini',
                'grok': 'xAI'
            };
            setCreateForm({
                ...createForm,
                api_name: providerNames[vault.provider] || vault.provider,
                model_key: modelId
            });
        }
    };


    if (isLoading) {
        return (
            <div className="p-8 flex justify-center items-center">
                <div className="flex items-center gap-3 text-slate-500 font-medium">
                    <div className="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                    Carregando configurações de preço...
                </div>
            </div>
        );
    }

    const pricing = data || [];

    return (
        <div className="p-4 md:p-8 max-w-6xl mx-auto space-y-8">

            {/* Header */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">Custos de API 💰</h1>
                    <p className="text-slate-500 font-medium mt-1">Defina o custo (Input/Output) de cada modelo para faturamento e estimativas.</p>
                </div>
                <button
                    onClick={() => { resetCreateForm(); setCreateModalOpen(true); }}
                    className="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 flex items-center gap-2"
                >
                    <span>➕</span> Novo Preço de Modelo
                </button>
            </div>

            {/* Warning Banner */}
            <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-xl flex items-start gap-3 shadow-sm">
                <span className="text-amber-500 text-lg mt-0.5">⚠️</span>
                <div>
                    <p className="text-sm font-bold text-amber-800">Impacto nos Cálculos de Custo</p>
                    <p className="text-sm text-amber-700 mt-0.5">
                        As alterações nos preços aplicam-se imediatamente no cálculo das <strong>futuras requisições</strong>. Modelos que não possuem preço definido aqui cobrarão <strong className="text-red-600">$ 0.00</strong> por padrão. Os valores são expressos em <strong>USD por 1.000.000 tokens</strong>.
                    </p>
                </div>
            </div>

            {/* Pricing Table */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div className="p-6 border-b border-slate-50 bg-slate-50/50">
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-indigo-50 text-indigo-600 rounded-lg text-sm">📊</span>
                        Tabela de Preços Atual
                    </h3>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50/80 text-slate-400 uppercase text-xs font-bold tracking-wider">
                            <tr>
                                <th className="px-6 py-4">Provedor</th>
                                <th className="px-6 py-4">Modelo</th>
                                <th className="px-6 py-4">Input (USD / 1M tokens)</th>
                                <th className="px-6 py-4">Output (USD / 1M tokens)</th>
                                <th className="px-6 py-4">Última Atualização</th>
                                <th className="px-6 py-4">Alterado por</th>
                                <th className="px-6 py-4 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {pricing.map(entry => (
                                <tr key={entry.id} className="hover:bg-slate-50/60 transition-colors">
                                    <td className="px-6 py-4">
                                        <span className={`text-[10px] font-black uppercase px-2 py-1 rounded-md ${providerColors[entry.api_name] || 'bg-slate-100 text-slate-600'}`}>
                                            {entry.api_name}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-slate-700 font-bold">{entry.model_key}</td>

                                    {editingId === entry.id ? (
                                        <>
                                            <td className="px-6 py-3">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={editForm.input_price_per_1m}
                                                    onChange={e => setEditForm({ ...editForm, input_price_per_1m: e.target.value })}
                                                    className="w-32 px-3 py-1.5 rounded-lg border border-indigo-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono"
                                                    autoFocus
                                                />
                                            </td>
                                            <td className="px-6 py-3">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={editForm.output_price_per_1m}
                                                    onChange={e => setEditForm({ ...editForm, output_price_per_1m: e.target.value })}
                                                    className="w-32 px-3 py-1.5 rounded-lg border border-indigo-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono"
                                                />
                                            </td>
                                        </>
                                    ) : (
                                        <>
                                            <td className={`px-6 py-4 font-mono font-bold ${entry.input_price_per_1m === 0 ? 'text-red-500' : 'text-slate-800'}`}>
                                                $ {entry.input_price_per_1m.toFixed(4)}
                                            </td>
                                            <td className={`px-6 py-4 font-mono font-bold ${entry.output_price_per_1m === 0 ? 'text-red-500' : 'text-slate-800'}`}>
                                                $ {entry.output_price_per_1m.toFixed(4)}
                                            </td>
                                        </>
                                    )}

                                    <td className="px-6 py-4 text-xs text-slate-400">
                                        {new Date(entry.updated_at).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-6 py-4 text-xs text-slate-500 font-medium">
                                        {entry.updated_by ? (
                                            <span className="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-bold">
                                                {entry.updated_by.name}
                                            </span>
                                        ) : (
                                            <span className="italic text-slate-300">Sistema</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {editingId === entry.id ? (
                                                <>
                                                    <button
                                                        onClick={requestSave}
                                                        disabled={updateMutation.isPending}
                                                        className="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                                    >
                                                        {updateMutation.isPending ? '...' : 'Salvar'}
                                                    </button>
                                                    <button
                                                        onClick={() => setEditingId(null)}
                                                        className="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-200 transition-colors"
                                                    >
                                                        X
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button
                                                        onClick={() => openEdit(entry)}
                                                        className="px-4 py-1.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-lg hover:border-indigo-300 hover:text-indigo-600 transition-all font-medium"
                                                    >
                                                        Editar
                                                    </button>
                                                    <button
                                                        onClick={() => setLogsOpen(logsOpen === entry.id ? null : entry.id)}
                                                        className={`px-3 py-1.5 text-xs font-bold rounded-lg transition-colors ${logsOpen === entry.id ? 'bg-indigo-50 text-indigo-600' : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600'}`}
                                                        title="Ver histórico de alterações"
                                                    >
                                                        Histórico
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {pricing.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center text-slate-400 italic">
                                        Nenhum modelo configurado no momento. Defina os preços manualmente.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Inline Audit Log Drawer */}
                <AnimatePresence>
                    {logsOpen && (
                        <motion.div
                            initial={{ height: 0, opacity: 0 }}
                            animate={{ height: 'auto', opacity: 1 }}
                            exit={{ height: 0, opacity: 0 }}
                            className="overflow-hidden border-t border-slate-100"
                        >
                            <div className="p-6 bg-slate-50/80">
                                <h4 className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span className="w-2 h-2 rounded-full bg-indigo-400"></span>
                                    Log de Alterações — <span className="text-indigo-600 font-mono">{pricing.find(p => p.id === logsOpen)?.model_key}</span>
                                </h4>
                                {logsLoading ? (
                                    <div className="flex gap-2 items-center text-sm text-slate-400 italic">
                                        <div className="w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                                        Carregando logs...
                                    </div>
                                ) : (logsData?.length ?? 0) === 0 ? (
                                    <p className="text-sm text-slate-400 italic">Nenhuma alteração registrada para este modelo.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {logsData?.map((log: any) => (
                                            <div key={log.id} className="flex items-start gap-4 p-4 bg-white rounded-xl shadow-sm text-xs border border-white">
                                                <div className="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4">
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[9px] mb-1">Alterado por</p>
                                                        <p className="font-bold text-slate-700">{log.updated_by?.name || 'Sistema'}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[9px] mb-1">Input Anterior</p>
                                                        <p className="font-mono text-red-500">$ {parseFloat(log.old_input_price_per_1m).toFixed(4)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[9px] mb-1">Input Novo</p>
                                                        <p className="font-mono text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded w-fit">$ {parseFloat(log.new_input_price_per_1m).toFixed(4)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[9px] mb-1">Output: Antigo → Novo</p>
                                                        <p className="font-mono text-slate-600 flex items-center gap-2">
                                                            <span className="text-red-400 line-through decoration-red-200">${parseFloat(log.old_output_price_per_1m).toFixed(4)}</span>
                                                            <span className="text-slate-300">→</span>
                                                            <span className="text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded">${parseFloat(log.new_output_price_per_1m).toFixed(4)}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-slate-400 whitespace-nowrap text-[10px] font-medium mt-1 bg-slate-50 px-2 py-1 rounded-md">
                                                    {new Date(log.created_at).toLocaleString('pt-BR')}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </section>

            {/* Create Modal */}
            <AnimatePresence>
                {createModalOpen && (
                    <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
                            onClick={() => setCreateModalOpen(false)}
                        />
                        <motion.div
                            initial={{ scale: 0.95, opacity: 0, y: 10 }}
                            animate={{ scale: 1, opacity: 1, y: 0 }}
                            exit={{ scale: 0.95, opacity: 0, y: 10 }}
                            className="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden text-left"
                        >
                            <div className="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 className="text-xl font-black text-slate-800">
                                    Cadastrar Preço de Modelo
                                </h3>
                                <button
                                    onClick={() => setCreateModalOpen(false)}
                                    className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-200 text-slate-500 transition-colors"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleCreateSubmit} className="p-8 space-y-6">

                                {/* Tabs */}
                                <div className="flex bg-slate-100 p-1 rounded-xl">
                                    <button
                                        type="button"
                                        onClick={() => setCreateTab('manual')}
                                        className={`flex-1 text-sm font-bold py-2 rounded-lg transition-colors ${createTab === 'manual' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}
                                    >
                                        Preenchimento Manual
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setCreateTab('api')}
                                        className={`flex-1 text-sm font-bold py-2 rounded-lg transition-colors flex items-center justify-center gap-2 ${createTab === 'api' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}
                                    >
                                        📡 Buscar da API
                                    </button>
                                </div>

                                {/* API Search Section */}
                                {createTab === 'api' && (
                                    <div className="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 space-y-4">
                                        <p className="text-xs text-indigo-800 font-medium">Selecione uma chave de API para buscar o nome exato dos modelos e evitar erros de digitação.</p>
                                        <div className="flex gap-2">
                                            <select
                                                value={selectedVaultId}
                                                onChange={e => setSelectedVaultId(e.target.value)}
                                                className="flex-1 rounded-xl border border-indigo-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-full"
                                            >
                                                <option value="">-- Selecione uma chave do cofre --</option>
                                                {vaultsData?.map(v => (
                                                    <option key={v.id} value={v.id}>{v.nickname} ({v.provider})</option>
                                                ))}
                                            </select>
                                            <button
                                                type="button"
                                                onClick={discoverModelsFromVault}
                                                disabled={!selectedVaultId || isDiscovering}
                                                className="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap"
                                            >
                                                {isDiscovering ? 'Buscando...' : 'Buscar Modelos'}
                                            </button>
                                        </div>

                                        {discoveredModels.length > 0 && (
                                            <div className="pt-2 animate-fade-in">
                                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Modelos Encontrados ({discoveredModels.length})</label>
                                                <select
                                                    onChange={e => handleSelectDiscoveredModel(e.target.value)}
                                                    className="w-full rounded-xl border border-slate-200 px-4 py-3 bg-white focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-medium text-slate-700 cursor-pointer shadow-sm"
                                                    defaultValue=""
                                                >
                                                    <option value="" disabled>-- Selecione para preencher --</option>
                                                    {discoveredModels.map(m => (
                                                        <option key={m.id} value={m.id}>{m.name}</option>
                                                    ))}
                                                </select>
                                                <p className="text-[10px] text-slate-400 mt-2 text-right">Após selecionar, defina o preço abaixo.</p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Main Form Fields */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="col-span-2 md:col-span-1">
                                        <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Nome do Provedor</label>
                                        <input
                                            type="text"
                                            placeholder="Ex: OpenAI"
                                            value={createForm.api_name}
                                            onChange={e => setCreateForm({ ...createForm, api_name: e.target.value })}
                                            className="w-full rounded-xl border border-slate-200 px-4 py-3 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-medium transition-colors"
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2 md:col-span-1">
                                        <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Chave do Modelo</label>
                                        <input
                                            type="text"
                                            placeholder="Ex: gpt-4o"
                                            value={createForm.model_key}
                                            onChange={e => setCreateForm({ ...createForm, model_key: e.target.value })}
                                            className="w-full rounded-xl border border-slate-200 px-4 py-3 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none font-mono text-sm transition-colors text-indigo-700"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 mb-1.5">Input Price (USD/1M)</label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono">$</span>
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.000"
                                                value={createForm.input_price_per_1m}
                                                onChange={e => setCreateForm({ ...createForm, input_price_per_1m: e.target.value })}
                                                className="w-full rounded-xl border border-slate-200 pl-8 pr-4 py-3 bg-white focus:ring-2 focus:ring-emerald-500 outline-none font-mono text-sm"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 mb-1.5">Output Price (USD/1M)</label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono">$</span>
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.000"
                                                value={createForm.output_price_per_1m}
                                                onChange={e => setCreateForm({ ...createForm, output_price_per_1m: e.target.value })}
                                                className="w-full rounded-xl border border-slate-200 pl-8 pr-4 py-3 bg-white focus:ring-2 focus:ring-emerald-500 outline-none font-mono text-sm"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex gap-3 pt-4">
                                    <button
                                        type="button"
                                        onClick={() => setCreateModalOpen(false)}
                                        className="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={createMutation.isPending}
                                        className="flex-[2] py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 disabled:opacity-50"
                                    >
                                        {createMutation.isPending ? 'Salvando...' : 'Salvar Preço'}
                                    </button>
                                </div>
                            </form>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>

            {/* Confirmation Dialog for Edit */}
            <AnimatePresence>
                {confirmOpen && pendingUpdate && (
                    <div className="fixed inset-0 z-[300] flex items-center justify-center p-4">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"
                            onClick={() => setConfirmOpen(false)}
                        />
                        <motion.div
                            initial={{ scale: 0.9, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            exit={{ scale: 0.9, opacity: 0 }}
                            className="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8"
                        >
                            <div className="text-center mb-6">
                                <div className="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">⚠️</div>
                                <h3 className="text-xl font-black text-slate-800">Confirmar Alteração</h3>
                                <p className="text-slate-500 text-sm mt-2">
                                    Esta ação atualiza os preços e afeta o cálculo de custo de <strong>todas as requisições futuras</strong> com este modelo.
                                </p>
                            </div>
                            <div className="bg-slate-50 rounded-2xl p-5 mb-6 space-y-3 border border-slate-100">
                                <div className="flex justify-between items-center border-b border-slate-200 pb-2">
                                    <span className="text-slate-500 text-xs font-bold uppercase tracking-wider">Modelo</span>
                                    <span className="font-mono font-bold text-slate-800 bg-white px-2 py-1 rounded shadow-sm">
                                        {pricing.find(p => p.id === pendingUpdate.id)?.model_key}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500 text-xs font-bold uppercase tracking-wider">Novo Input</span>
                                    <span className="font-mono font-black text-emerald-600">$ {parseFloat(pendingUpdate.form.input_price_per_1m).toFixed(6)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-slate-500 text-xs font-bold uppercase tracking-wider">Novo Output</span>
                                    <span className="font-mono font-black text-emerald-600">$ {parseFloat(pendingUpdate.form.output_price_per_1m).toFixed(6)}</span>
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <button
                                    onClick={() => setConfirmOpen(false)}
                                    className="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={confirmSave}
                                    disabled={updateMutation.isPending}
                                    className="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 disabled:opacity-50"
                                >
                                    {updateMutation.isPending ? 'Salvando...' : 'Confirmar'}
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
}
