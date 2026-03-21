import { VaultKey, capabilityMeta } from './Types';
import { UseMutationResult } from '@tanstack/react-query';

interface ApiRoutingConfigProps {
    routingForm: { vault_id: string; preferred_model: string; capabilities: string[] };
    setRoutingForm: (form: { vault_id: string; preferred_model: string; capabilities: string[] }) => void;
    vaultKeys: VaultKey[];
    availableCapabilities: Record<string, string>;
    discoveryLoading: boolean;
    handleDiscover: () => void;
    activateRoutingMutation: UseMutationResult<any, any, any, any>;
}

export default function ApiRoutingConfig({
    routingForm,
    setRoutingForm,
    vaultKeys,
    availableCapabilities,
    discoveryLoading,
    handleDiscover,
    activateRoutingMutation
}: ApiRoutingConfigProps) {
    return (
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
    );
}
