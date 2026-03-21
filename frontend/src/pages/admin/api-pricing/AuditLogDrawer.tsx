import React from 'react';
import { motion } from 'framer-motion';
import { ApiPricingLog } from './Types';

interface AuditLogDrawerProps {
    logsLoading: boolean;
    logsData?: ApiPricingLog[];
    modelKey?: string;
}

const AuditLogDrawer: React.FC<AuditLogDrawerProps> = ({ logsLoading, logsData, modelKey }) => {
    return (
        <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-slate-100"
        >
            <div className="p-6 bg-slate-50/80">
                <h4 className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-indigo-400"></span>
                    Log de Alterações — <span className="text-indigo-600 font-mono">{modelKey}</span>
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
                        {logsData?.map((log) => (
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
    );
};

export default AuditLogDrawer;
