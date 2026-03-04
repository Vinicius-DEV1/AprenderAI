import { useState } from 'react';
import { toast } from 'sonner';
import api from '../../../api/axios';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    questionId: number;
}

export default function DialogReportQuestion({ isOpen, onClose, questionId }: Props) {
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
            await api.post(`/api/v1/questions/${questionId}/report`, { reason });
            toast.success('Problema reportado com sucesso. Nossa equipe analisará em breve.');
            onClose();
            setReason('');
        } catch (error) {
            toast.error('Erro ao reportar questão.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={onClose}></div>
            <div className="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl animate-fade-in-up">
                <h3 className="mb-4 text-xl font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                    🚩 Reportar Erro na Questão
                </h3>
                <form onSubmit={handleSubmit}>
                    <textarea
                        className="w-full rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white p-3 text-sm focus:ring-2 focus:ring-indigo-500"
                        rows={4}
                        placeholder="Descreva o erro encontrado (ex: gabarito errado, questão desatualizada, erro de digitação)..."
                        value={reason}
                        onChange={e => setReason(e.target.value)}
                        disabled={loading}
                    />
                    <div className="mt-4 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" onClick={onClose} disabled={loading} className="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 font-semibold transition">
                            Cancelar
                        </button>
                        <button type="submit" disabled={loading} className="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-semibold transition">
                            {loading ? 'Enviando...' : 'Enviar Report'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
