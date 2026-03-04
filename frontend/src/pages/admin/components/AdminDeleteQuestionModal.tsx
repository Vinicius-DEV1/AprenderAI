import { motion, AnimatePresence } from 'framer-motion';
import api from '../../../api/axios';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useEffect } from 'react';

interface DeleteImpact {
    question_id: number;
    statement_preview: string;
    simulation_answers: number;
    user_answers: number;
    affected_simulations: number;
    favorites: number;
    notebooks: number;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number | null;
    onDeleted: () => void;
}

export default function AdminDeleteQuestionModal({ isOpen, onClose, questionId, onDeleted }: Props) {
    const queryClient = useQueryClient();
    const [impact, setImpact] = useState<DeleteImpact | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && questionId) {
            fetchImpact();
        } else {
            setImpact(null);
            setError(null);
        }
    }, [isOpen, questionId]);

    const fetchImpact = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.get(`/api/v1/admin/questions/${questionId}/delete-impact`);
            setImpact(res.data);
        } catch (err: any) {
            console.error('Erro ao buscar impacto:', err);
            setError('Não foi possível carregar o impacto da exclusão.');
        } finally {
            setLoading(false);
        }
    };

    const deleteMutation = useMutation({
        mutationFn: async () => {
            const res = await api.delete(`/api/v1/admin/questions/${questionId}`);
            return res.data;
        },
        onSuccess: () => {
            onDeleted();
            onClose();
        },
        onError: (err: any) => {
            setError(err.response?.data?.message || 'Erro ao excluir questão.');
        }
    });

    if (!isOpen) return null;

    return (
        <AnimatePresence>
            <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    onClick={onClose}
                    className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                />

                <motion.div
                    initial={{ opacity: 0, scale: 0.95, y: 20 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.95, y: 20 }}
                    className="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100"
                >
                    <div className="p-8">
                        <div className="flex items-center gap-4 mb-6">
                            <div className="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-2xl shadow-inner">⚠️</div>
                            <div>
                                <h2 className="text-2xl font-black text-slate-900">Excluir Questão</h2>
                                <p className="text-slate-500 font-medium text-sm">Esta ação é irreversível e afetará o histórico.</p>
                            </div>
                        </div>

                        {loading ? (
                            <div className="py-12 flex flex-col items-center justify-center gap-4">
                                <div className="w-10 h-10 border-4 border-slate-200 border-t-indigo-600 rounded-full animate-spin" />
                                <span className="text-xs font-black text-slate-400 uppercase tracking-widest">Calculando Impacto...</span>
                            </div>
                        ) : error ? (
                            <div className="p-4 bg-red-50 border border-red-100 rounded-2xl text-red-600 text-sm font-bold flex items-center gap-3">
                                <span>❌</span> {error}
                            </div>
                        ) : impact ? (
                            <div className="space-y-6">
                                <div className="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                    <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Questão #{impact.question_id}</span>
                                    <p className="text-sm font-bold text-slate-700 leading-relaxed italic">
                                        "{impact.statement_preview}"
                                    </p>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <ImpactStat label="Respostas Simulados" value={impact.simulation_answers} icon="📊" isCritical={impact.simulation_answers > 0} />
                                    <ImpactStat label="Respostas Alunos" value={impact.user_answers} icon="🎯" isCritical={impact.user_answers > 0} />
                                    <ImpactStat label="Simulados Afetados" value={impact.affected_simulations} icon="📝" isCritical={impact.affected_simulations > 0} />
                                    <ImpactStat label="Salva em Cadernos" value={impact.notebooks} icon="📚" isCritical={impact.notebooks > 0} />
                                </div>

                                <div className="p-4 bg-orange-50 border border-orange-100 rounded-2xl text-[11px] font-medium text-orange-800 leading-relaxed">
                                    <span className="font-black flex items-center gap-2 mb-1">💡 NOTA DE INTEGRIDADE:</span>
                                    A exclusão removerá permanentemente todos os registros vinculados (respostas, favoritos, anotações).
                                </div>
                            </div>
                        ) : null}

                        <div className="flex gap-3 mt-8">
                            <button
                                onClick={onClose}
                                className="flex-1 px-6 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={() => deleteMutation.mutate()}
                                disabled={deleteMutation.isPending || loading || !impact}
                                className="flex-[1.5] px-6 py-4 bg-red-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-200 disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                {deleteMutation.isPending ? (
                                    <>
                                        <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                        Excluindo...
                                    </>
                                ) : (
                                    <>Confirmar Exclusão 🧨</>
                                )}
                            </button>
                        </div>
                    </div>
                </motion.div>
            </div>
        </AnimatePresence>
    );
}

function ImpactStat({ label, value, icon, isCritical }: any) {
    return (
        <div className={`p-4 rounded-2xl border transition ${isCritical ? 'bg-white border-red-100 shadow-sm' : 'bg-slate-50 border-slate-100 opacity-60'}`}>
            <div className="flex justify-between items-start mb-2">
                <span className="text-lg">{icon}</span>
                <span className={`text-xl font-black ${isCritical ? 'text-red-600' : 'text-slate-400'}`}>{value}</span>
            </div>
            <p className={`text-[9px] font-black uppercase tracking-widest ${isCritical ? 'text-red-400' : 'text-slate-400'}`}>{label}</p>
        </div>
    );
}
