import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { CreateForm, VaultEntry, DiscoveredModel } from './Types';

interface CreatePriceModalProps {
    isOpen: boolean;
    setOpen: (open: boolean) => void;
    createTab: 'manual' | 'api';
    setCreateTab: (tab: 'manual' | 'api') => void;
    createForm: CreateForm;
    setCreateForm: (form: CreateForm) => void;
    selectedVaultId: string;
    setSelectedVaultId: (id: string) => void;
    vaultsData?: VaultEntry[];
    discoverModels: () => void;
    isDiscovering: boolean;
    discoveredModels: DiscoveredModel[];
    onSelectDiscovered: (id: string) => void;
    onSubmit: (e: React.FormEvent) => void;
    isPending: boolean;
}

const CreatePriceModal: React.FC<CreatePriceModalProps> = ({
    isOpen,
    setOpen,
    createTab,
    setCreateTab,
    createForm,
    setCreateForm,
    selectedVaultId,
    setSelectedVaultId,
    vaultsData,
    discoverModels,
    isDiscovering,
    discoveredModels,
    onSelectDiscovered,
    onSubmit,
    isPending
}) => {
    return (
        <AnimatePresence>
            {isOpen && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
                        onClick={() => setOpen(false)}
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
                                onClick={() => setOpen(false)}
                                className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-200 text-slate-500 transition-colors"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={onSubmit} className="p-8 space-y-6">

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
                                            onClick={discoverModels}
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
                                                onChange={e => onSelectDiscovered(e.target.value)}
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
                                    onClick={() => setOpen(false)}
                                    className="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={isPending}
                                    className="flex-[2] py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 disabled:opacity-50"
                                >
                                    {isPending ? 'Salvando...' : 'Salvar Preço'}
                                </button>
                            </div>
                        </form>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
};

export default CreatePriceModal;
