import { ApiKey, capabilityMeta } from './Types';
import { Reorder } from 'framer-motion';
import { UseMutationResult } from '@tanstack/react-query';

interface FailoverPrioritiesProps {
    availableCapabilities: Record<string, string>;
    capabilitiesGrid: Record<string, ApiKey[]>;
    handlePriorityReorder: (capability: string, newOrder: ApiKey[]) => void;
    retestMutation: UseMutationResult<any, any, number, any>;
    deleteApiKeyMutation: UseMutationResult<any, any, number, any>;
}

export default function FailoverPriorities({
    availableCapabilities,
    capabilitiesGrid,
    handlePriorityReorder,
    retestMutation,
    deleteApiKeyMutation
}: FailoverPrioritiesProps) {
    return (
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
    );
}
