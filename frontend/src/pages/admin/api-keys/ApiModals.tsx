import { motion } from 'framer-motion';
import { AiLog } from './Types';

interface ApiModalsProps {
    showModelModal: boolean;
    setShowModelModal: (show: boolean) => void;
    discoveredModels: any[];
    routingForm: { vault_id: string; preferred_model: string; capabilities: string[] };
    setRoutingForm: (form: { vault_id: string; preferred_model: string; capabilities: string[] }) => void;
    showLogModal: boolean;
    setShowLogModal: (show: boolean) => void;
    activeLog: AiLog | null;
    showRawResponse: boolean;
    setShowRawResponse: (show: boolean) => void;
}

export default function ApiModals({
    showModelModal,
    setShowModelModal,
    discoveredModels,
    routingForm,
    setRoutingForm,
    showLogModal,
    setShowLogModal,
    activeLog,
    showRawResponse,
    setShowRawResponse
}: ApiModalsProps) {
    return (
        <>
            {/* MODAL discovery models */}
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

            {/* MODAL log details */}
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
        </>
    );
}
