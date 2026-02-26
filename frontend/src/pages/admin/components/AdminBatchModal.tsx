import { useState, useEffect } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '../../../api/axios';

interface BatchModalProps {
    isOpen: boolean;
    onClose: () => void;
    pendingCount: number;
    onBatchStarted: (batchId: string) => void;
}

export default function AdminBatchModal({ isOpen, onClose, pendingCount, onBatchStarted }: BatchModalProps) {
    const [step, setStep] = useState<'config' | 'preview'>('config');
    const [quantity, setQuantity] = useState(10);
    const [type, setType] = useState('complete');
    const [model, setModel] = useState('gpt-4o');
    const [previewQuestions, setPreviewQuestions] = useState<any[]>([]);

    const [batchId, setBatchId] = useState<string | null>(null);
    const [progress, setProgress] = useState<any>(null);

    const previewMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/api/v1/admin/triage/preview', {
                quantity,
                type: type === 'complete' ? 'both' : type,
            });
            return res.data;
        },
        onSuccess: (data) => {
            setPreviewQuestions(data.questions);
            setStep('preview');
        }
    });

    const startMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/api/v1/admin/triage/start', {
                quantity: previewQuestions.length,
                type: type === 'complete' ? 'both' : type,
                model,
                question_ids: previewQuestions.map(q => q.id)
            });
            return res.data;
        },
        onSuccess: (data) => {
            setBatchId(data.batch_id);
            onBatchStarted(data.batch_id);
        }
    });

    // Polling for progress
    useEffect(() => {
        let interval: any;
        if (batchId) {
            interval = setInterval(async () => {
                try {
                    const res = await api.get(`/api/v1/admin/triage/${batchId}/status`);
                    setProgress(res.data);
                    if (res.data.status === 'completed' || res.data.status === 'failed' || res.data.status === 'cancelled') {
                        clearInterval(interval);
                        setTimeout(() => {
                            onClose();
                            setBatchId(null);
                            setProgress(null);
                            setStep('config');
                        }, 3000);
                    }
                } catch (e) {
                    clearInterval(interval);
                }
            }, 2000);
        }
        return () => clearInterval(interval);
    }, [batchId, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4">
                <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onClick={batchId ? undefined : onClose}></div>

                <div className="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]">

                    {/* Header */}
                    <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <span className="text-xl">{batchId ? '⏳' : '🤖'}</span>
                            <h3 className="text-xl font-black text-gray-900">
                                {batchId ? 'Processando Lote...' : step === 'config' ? 'Configurar Lote de IA' : 'Pré-visualização do Lote'}
                            </h3>
                        </div>
                        {!batchId && <button onClick={onClose} className="text-gray-400 hover:text-gray-600 font-bold">✕</button>}
                    </div>

                    {/* Body */}
                    <div className="p-6 overflow-y-auto flex-grow">
                        {batchId ? (
                            <div className="flex flex-col items-center justify-center py-10 space-y-6">
                                <div className="text-6xl animate-bounce">🚀</div>
                                <div className="w-full max-w-md bg-gray-100 h-4 rounded-full overflow-hidden">
                                    <div
                                        className="bg-indigo-600 h-full transition-all duration-500"
                                        style={{ width: `${progress ? (progress.processed / progress.total) * 100 : 0}%` }}
                                    ></div>
                                </div>
                                <div className="text-center">
                                    <p className="font-black text-gray-900 text-lg">
                                        {progress ? `${progress.processed} / ${progress.total}` : 'Iniciando...'}
                                    </p>
                                    <p className="text-sm text-gray-500 font-bold uppercase tracking-wider mt-1">
                                        {progress?.message || 'Aguardando servidor...'}
                                    </p>
                                </div>
                            </div>
                        ) : step === 'config' ? (
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">Quantidade Máxima (Disponível: {pendingCount})</label>
                                        <input
                                            type="number"
                                            value={quantity}
                                            onChange={(e) => setQuantity(parseInt(e.target.value))}
                                            max={pendingCount}
                                            className="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-black uppercase text-gray-400 mb-2">Modelo de IA</label>
                                        <select
                                            value={model}
                                            onChange={(e) => setModel(e.target.value)}
                                            className="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500 font-bold"
                                        >
                                            <option value="gpt-4o">OpenAI - GPT-4o</option>
                                            <option value="gpt-4">OpenAI - GPT-4</option>
                                            <option value="gpt-3.5-turbo">OpenAI - GPT-3.5</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-black uppercase text-gray-400 mb-4">Ações da IA</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {[
                                            { id: 'complete', label: '🚀 Completo', desc: 'Dificuldade, explicação e taxonomia' },
                                            { id: 'difficulty', label: '⚡ Apenas Dificuldade', desc: 'Gera nível e raciocínio' },
                                            { id: 'explanation', label: '📝 Apenas Explicação', desc: 'Gera texto explicativo' },
                                            { id: 'classification', label: '🏷️ Apenas Classificar', desc: 'Matéria e assunto' }
                                        ].map(opt => (
                                            <label
                                                key={opt.id}
                                                className={`relative flex cursor-pointer rounded-xl border p-4 shadow-sm transition-all ${type === opt.id ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-100 bg-white hover:border-gray-200'
                                                    }`}
                                            >
                                                <input type="radio" value={opt.id} checked={type === opt.id} onChange={() => setType(opt.id)} className="sr-only" />
                                                <div className="flex flex-col">
                                                    <span className="block text-sm font-black text-gray-900">{opt.label}</span>
                                                    <span className="block text-[10px] text-gray-500 mt-1 uppercase font-bold">{opt.desc}</span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col h-full">
                                <p className="text-sm text-gray-500 mb-4 font-medium italic">
                                    Revise as questões que serão processadas. Remova as que não deseja no lote.
                                </p>
                                <div className="border border-gray-100 rounded-xl overflow-hidden bg-white shadow-inner">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-gray-50 border-b border-gray-100 sticky top-0">
                                            <tr>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase w-16">ID</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase">Enunciado</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase w-32">Matéria</th>
                                                <th className="px-4 py-3 font-black text-gray-400 text-[10px] uppercase text-right w-24">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50 font-medium">
                                            {previewQuestions.map((q, idx) => (
                                                <tr key={q.id} className="hover:bg-red-50/30 transition-colors">
                                                    <td className="px-4 py-3 text-gray-400 font-mono">#{q.id}</td>
                                                    <td className="px-4 py-3 text-gray-700 max-w-xs truncate">{q.statement}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{q.subject}</span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <button
                                                            onClick={() => setPreviewQuestions(prev => prev.filter((_, i) => i !== idx))}
                                                            className="text-red-400 hover:text-red-600 text-[10px] font-black uppercase"
                                                        >
                                                            Remover
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    {!batchId && (
                        <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                            <button
                                onClick={onClose}
                                className="px-6 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-gray-50 transition"
                            >
                                Cancelar
                            </button>

                            {step === 'config' ? (
                                <button
                                    onClick={() => previewMutation.mutate()}
                                    disabled={previewMutation.isPending || pendingCount === 0}
                                    className="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 disabled:opacity-50"
                                >
                                    {previewMutation.isPending ? 'Carregando...' : 'Ver Prévia →'}
                                </button>
                            ) : (
                                <div className="flex gap-3">
                                    <button
                                        onClick={() => setStep('config')}
                                        className="px-6 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl font-bold text-sm hover:bg-indigo-100 transition"
                                    >
                                        ← Voltar
                                    </button>
                                    <button
                                        onClick={() => startMutation.mutate()}
                                        disabled={startMutation.isPending || previewQuestions.length === 0}
                                        className="px-8 py-2.5 bg-green-600 text-white rounded-xl font-bold text-sm hover:bg-green-700 transition shadow-lg shadow-green-100 disabled:opacity-50"
                                    >
                                        {startMutation.isPending ? 'Iniciando...' : '🚀 Confirmar Lote'}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
