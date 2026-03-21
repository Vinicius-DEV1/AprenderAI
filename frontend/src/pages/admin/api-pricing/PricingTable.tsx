import React from 'react';
import { AnimatePresence } from 'framer-motion';
import { ApiPricingEntry, EditForm, providerColors, ApiPricingLog } from './Types';
import AuditLogDrawer from './AuditLogDrawer';

interface PricingTableProps {
    pricing: ApiPricingEntry[];
    editingId: number | null;
    editForm: EditForm;
    setEditForm: (form: EditForm) => void;
    setEditingId: (id: number | null) => void;
    openEdit: (entry: ApiPricingEntry) => void;
    requestSave: () => void;
    updateMutationPending: boolean;
    logsOpen: number | null;
    setLogsOpen: (id: number | null) => void;
    logsLoading: boolean;
    logsData?: ApiPricingLog[];
}

const PricingTable: React.FC<PricingTableProps> = ({
    pricing,
    editingId,
    editForm,
    setEditForm,
    setEditingId,
    openEdit,
    requestSave,
    updateMutationPending,
    logsOpen,
    setLogsOpen,
    logsLoading,
    logsData
}) => {
    return (
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
                            <React.Fragment key={entry.id}>
                                <tr className="hover:bg-slate-50/60 transition-colors">
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
                                                        disabled={updateMutationPending}
                                                        className="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                                    >
                                                        {updateMutationPending ? '...' : 'Salvar'}
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
                                
                                {logsOpen === entry.id && (
                                    <tr>
                                        <td colSpan={7} className="p-0 border-none">
                                            <AnimatePresence>
                                                <AuditLogDrawer 
                                                    logsLoading={logsLoading}
                                                    logsData={logsData}
                                                    modelKey={entry.model_key}
                                                />
                                            </AnimatePresence>
                                        </td>
                                    </tr>
                                )}
                            </React.Fragment>
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
        </section>
    );
};

export default PricingTable;
