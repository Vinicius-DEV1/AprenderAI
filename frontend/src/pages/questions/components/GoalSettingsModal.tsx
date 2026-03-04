import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface GoalSettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    currentGoal: number;
    onSave: (newGoal: number) => void;
}

export default function GoalSettingsModal({ isOpen, onClose, currentGoal, onSave }: GoalSettingsModalProps) {
    const [goal, setGoal] = useState(currentGoal);
    const [saving, setSaving] = useState(false);

    const handleSave = async () => {
        setSaving(true);
        try {
            await onSave(goal);
            onClose();
        } catch (error) {
            console.error('Error saving goal:', error);
        } finally {
            setSaving(false);
        }
    };

    return (
        <AnimatePresence>
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                    <motion.div
                        initial={{ opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.95 }}
                        className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden"
                    >
                        <div className="p-6">
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                                🎯 Definir Meta Diária
                            </h3>
                            <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                                Quantas questões você deseja resolver por dia para manter seu ritmo de estudos?
                            </p>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Meta de Questões</label>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            min="1"
                                            max="500"
                                            value={goal}
                                            onChange={(e) => setGoal(parseInt(e.target.value) || 0)}
                                            className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-lg font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                                        />
                                        <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-medium">questões</span>
                                    </div>
                                    <p className="mt-2 text-[10px] text-slate-400 italic">Recomendado: 20 a 50 questões para constância.</p>
                                </div>
                            </div>

                            <div className="flex gap-3 mt-8">
                                <button
                                    onClick={onClose}
                                    className="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={handleSave}
                                    disabled={saving || goal < 1}
                                    className="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 dark:shadow-none transition-all disabled:opacity-50"
                                >
                                    {saving ? 'Salvando...' : 'Salvar Meta'}
                                </button>
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
