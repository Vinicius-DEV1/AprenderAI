import { useState } from 'react';
import { toast } from 'sonner';
import api from '../../../api/axios';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number;
    onSuccess: () => void;
}

export default function AdminRevertToTriageModal({ isOpen, onClose, questionId, onSuccess }: Props) {
    const [reason, setReason] = useState('');
    const [loading, setLoading] = useState(false);

    if (!isOpen) return null;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!reason.trim()) {
            toast.error('O motivo é obrigatório.');
            return;
        }

        setLoading(true);
        try {
            await api.post(`/api/v1/admin/questions/${questionId}/revert-triage`, { reason });
            toast.success('Questão retornada para o banco de triagem.');
            onSuccess();
            onClose();
            setReason('');
        } catch (error) {
            toast.error('Erro ao retornar questão para triagem.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={onClose}></div>
            <div className="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl animate-fade-in-up">
                <h3 className="mb-4 text-xl font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                    🔄 Retornar para Triagem
                </h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    Ao confirmar, a questão será desativada e enviada de volta para a fila de revisão administrativa.
                    Explique o motivo para orientar o revisor.
                </p>
                <form onSubmit={handleSubmit}>
                    <textarea
                        className="w-full rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                        rows={4}
                        placeholder="Ex: Gabarito incorreto, erro na formatação do enunciado, explicação da IA está confusa..."
                        value={reason}
                        onChange={e => setReason(e.target.value)}
                        disabled={loading}
                    />
                    <div className="mt-4 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" onClick={onClose} disabled={loading} className="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 font-semibold transition">
                            Cancelar
                        </button>
                        <button type="submit" disabled={loading} className="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-semibold transition">
                            {loading ? 'Processando...' : 'Confirmar Reversão'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
