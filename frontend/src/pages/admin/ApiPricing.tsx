import { toast } from 'sonner';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../../api/axios';

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

const providerColors: Record<string, string> = {
    'Google Gemini': 'bg-blue-100 text-blue-700',
    'OpenAI': 'bg-emerald-100 text-emerald-700',
    'xAI': 'bg-purple-100 text-purple-700',
    'Genérico': 'bg-slate-100 text-slate-600',
};

export default function ApiPricing() {
    const queryClient = useQueryClient();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editForm, setEditForm] = useState<EditForm>({ input_price_per_1m: '', output_price_per_1m: '' });
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingUpdate, setPendingUpdate] = useState<{ id: number; form: EditForm } | null>(null);
    const [logsOpen, setLogsOpen] = useState<number | null>(null);

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
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                    <h1 className="text-3xl font-black text-slate-800 tracking-tight">Custos de API 💰</h1>
                    <p className="text-slate-500 font-medium mt-1">Gerencie o preço por token de cada modelo de IA. Alterações afetam apenas consumos futuros.</p>
                </div>
            </div>

            {/* Warning Banner */}
            <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-xl flex items-start gap-3">
                <span className="text-amber-500 text-lg mt-0.5">⚠️</span>
                <div>
                    <p className="text-sm font-bold text-amber-800">Atenção: Impacto nos Cálculos de Custo</p>
                    <p className="text-sm text-amber-700 mt-0.5">
                        Alterações nos preços são aplicadas imediatamente a novas requisições. Históricos existentes em <code className="bg-amber-100 px-1 rounded font-mono">ai_request_logs</code> <strong>não são recalculados</strong>. Os valores são em <strong>USD por 1.000.000 tokens</strong>.
                    </p>
                </div>
            </div>

            {/* Pricing Table */}
            <section className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div className="p-6 border-b border-slate-50">
                    <h3 className="text-lg font-bold text-slate-800 flex items-center gap-3">
                        <span className="p-2 bg-indigo-50 text-indigo-600 rounded-lg text-sm">📊</span>
                        Tabela de Preços Atual
                    </h3>
                    <p className="text-xs text-slate-400 mt-1 font-medium">Clique em "Editar" para alterar os valores de um modelo. As mudanças ficam registradas no log de auditoria.</p>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-slate-400 uppercase text-xs font-bold tracking-wider">
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
                                        <span className={`text-xs font-black uppercase px-2.5 py-1 rounded-full ${providerColors[entry.api_name] || 'bg-slate-100 text-slate-600'}`}>
                                            {entry.api_name}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-slate-600 font-bold">{entry.model_key}</td>

                                    {editingId === entry.id ? (
                                        <>
                                            <td className="px-6 py-3">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={editForm.input_price_per_1m}
                                                    onChange={e => setEditForm({ ...editForm, input_price_per_1m: e.target.value })}
                                                    className="w-36 px-3 py-1.5 rounded-lg border border-indigo-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono"
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
                                                    className="w-36 px-3 py-1.5 rounded-lg border border-indigo-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono"
                                                />
                                            </td>
                                        </>
                                    ) : (
                                        <>
                                            <td className="px-6 py-4 font-mono font-bold text-slate-800">
                                                $ {entry.input_price_per_1m.toFixed(4)}
                                            </td>
                                            <td className="px-6 py-4 font-mono font-bold text-slate-800">
                                                $ {entry.output_price_per_1m.toFixed(4)}
                                            </td>
                                        </>
                                    )}

                                    <td className="px-6 py-4 text-xs text-slate-400">
                                        {new Date(entry.updated_at).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-6 py-4 text-xs text-slate-500 font-medium">
                                        {entry.updated_by?.name || <span className="italic text-slate-300">Sistema</span>}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {editingId === entry.id ? (
                                                <>
                                                    <button
                                                        onClick={requestSave}
                                                        disabled={updateMutation.isPending}
                                                        className="px-4 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                                    >
                                                        {updateMutation.isPending ? 'Salvando...' : 'Confirmar'}
                                                    </button>
                                                    <button
                                                        onClick={() => setEditingId(null)}
                                                        className="px-4 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-200 transition-colors"
                                                    >
                                                        Cancelar
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button
                                                        onClick={() => openEdit(entry)}
                                                        className="px-4 py-1.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-lg hover:border-indigo-300 hover:text-indigo-600 transition-all"
                                                    >
                                                        ✏️ Editar
                                                    </button>
                                                    <button
                                                        onClick={() => setLogsOpen(logsOpen === entry.id ? null : entry.id)}
                                                        className="px-3 py-1.5 text-slate-400 text-xs font-bold rounded-lg hover:text-slate-600 transition-colors"
                                                        title="Ver histórico de alterações"
                                                    >
                                                        📋
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
                                        Nenhum modelo configurado. Execute o seeder: <code className="bg-slate-100 px-2 py-0.5 rounded font-mono text-xs">php artisan db:seed --class=ApiPricingSeeder</code>
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
                            <div className="p-6 bg-slate-50/40">
                                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
                                    📋 Histórico de Alterações — {pricing.find(p => p.id === logsOpen)?.model_key}
                                </h4>
                                {logsLoading ? (
                                    <p className="text-sm text-slate-400 italic">Carregando histórico...</p>
                                ) : (logsData?.length ?? 0) === 0 ? (
                                    <p className="text-sm text-slate-400 italic">Nenhuma alteração registrada para este modelo.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {logsData?.map((log: any) => (
                                            <div key={log.id} className="flex items-start gap-4 p-3 bg-white rounded-xl border border-slate-100 shadow-sm text-xs">
                                                <div className="flex-1 grid grid-cols-2 md:grid-cols-4 gap-3">
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[10px] mb-0.5">Alterado por</p>
                                                        <p className="font-bold text-slate-700">{log.updated_by?.name || 'Sistema'}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[10px] mb-0.5">Input Anterior</p>
                                                        <p className="font-mono text-red-500">$ {parseFloat(log.old_input_price_per_1m).toFixed(4)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[10px] mb-0.5">Input Novo</p>
                                                        <p className="font-mono text-emerald-600">$ {parseFloat(log.new_input_price_per_1m).toFixed(4)}</p>
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-400 uppercase tracking-widest text-[10px] mb-0.5">Output: Anterior → Novo</p>
                                                        <p className="font-mono text-slate-600">
                                                            <span className="text-red-500">$ {parseFloat(log.old_output_price_per_1m).toFixed(4)}</span>
                                                            {' → '}
                                                            <span className="text-emerald-600">$ {parseFloat(log.new_output_price_per_1m).toFixed(4)}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-slate-400 whitespace-nowrap text-[10px] font-medium mt-0.5">
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

            {/* Info Card */}
            <section className="bg-slate-900 rounded-2xl p-6 text-white">
                <h3 className="font-bold text-lg mb-4 flex items-center gap-2">🧮 Fórmula de Cálculo</h3>
                <div className="font-mono text-sm text-indigo-200 space-y-2 bg-slate-800 p-4 rounded-xl">
                    <p><span className="text-slate-400">// Cálculo por requisição:</span></p>
                    <p><span className="text-emerald-400">custo_input</span>  = (tokens_input  / 1.000.000) × <span className="text-amber-400">input_price_per_1m</span></p>
                    <p><span className="text-emerald-400">custo_output</span> = (tokens_output / 1.000.000) × <span className="text-amber-400">output_price_per_1m</span></p>
                    <p><span className="text-emerald-400">custo_total_usd</span> = custo_input + custo_output</p>
                    <p className="text-slate-400 mt-2">// Resultado salvo em:</p>
                    <p><span className="text-purple-400">ai_request_logs</span>.<span className="text-emerald-400">estimated_cost</span> <span className="text-slate-400">(USD, 6 casas decimais)</span></p>
                </div>
                <p className="text-xs text-slate-400 mt-3">O cache de preços é invalidado automaticamente ao salvar e regenerado na próxima requisição (TTL: 60 min).</p>
            </section>

            {/* Confirmation Dialog */}
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
                            className="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 border border-slate-100"
                        >
                            <div className="text-center mb-6">
                                <div className="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">⚠️</div>
                                <h3 className="text-xl font-black text-slate-800">Confirmar Alteração</h3>
                                <p className="text-slate-500 text-sm mt-2">
                                    Esta ação atualiza os preços e afeta o cálculo de custo de <strong>todas as requisições futuras</strong> com este modelo.
                                </p>
                            </div>
                            <div className="bg-slate-50 rounded-xl p-4 mb-6 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Modelo:</span>
                                    <span className="font-mono font-bold text-slate-800">
                                        {pricing.find(p => p.id === pendingUpdate.id)?.model_key}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Novo Input:</span>
                                    <span className="font-mono font-bold text-indigo-600">$ {parseFloat(pendingUpdate.form.input_price_per_1m).toFixed(6)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Novo Output:</span>
                                    <span className="font-mono font-bold text-indigo-600">$ {parseFloat(pendingUpdate.form.output_price_per_1m).toFixed(6)}</span>
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
                                    {updateMutation.isPending ? 'Salvando...' : '✅ Confirmar'}
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
}
