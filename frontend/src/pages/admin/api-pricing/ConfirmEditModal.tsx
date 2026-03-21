import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { EditForm } from './Types';

interface ConfirmEditModalProps {
    isOpen: boolean;
    setOpen: (open: boolean) => void;
    pendingUpdate: { id: number; form: EditForm } | null;
    modelKey?: string;
    onConfirm: () => void;
    isPending: boolean;
}

const ConfirmEditModal: React.FC<ConfirmEditModalProps> = ({
    isOpen,
    setOpen,
    pendingUpdate,
    modelKey,
    onConfirm,
    isPending
}) => {
    return (
        <AnimatePresence>
            {isOpen && pendingUpdate && (
                <div className="fixed inset-0 z-[300] flex items-center justify-center p-4">
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"
                        onClick={() => setOpen(false)}
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
                                    {modelKey}
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
                                onClick={() => setOpen(false)}
                                className="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={onConfirm}
                                disabled={isPending}
                                className="flex-1 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 disabled:opacity-50"
                            >
                                {isPending ? 'Salvando...' : 'Confirmar'}
                            </button>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
};

export default ConfirmEditModal;
