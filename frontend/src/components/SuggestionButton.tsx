import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { submitSuggestion } from '../api/suggestions';

/**
 * SuggestionButton — floating "Sugerir melhoria" button + modal form.
 *
 * Placed in the AppLayout so it's available on all authenticated pages.
 * Uses a clean modal overlay with title + description fields.
 */
export default function SuggestionButton() {
    const [isOpen, setIsOpen] = useState(false);
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const queryClient = useQueryClient();

    const { mutate, isPending } = useMutation({
        mutationFn: () => submitSuggestion(title.trim(), body.trim() || undefined),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-suggestions'] });
            setSubmitted(true);
            setTimeout(() => {
                setIsOpen(false);
                setSubmitted(false);
                setTitle('');
                setBody('');
            }, 2000);
        },
    });

    const canSubmit = title.trim().length >= 3 && !isPending;

    return (
        <>
            {/* Trigger Button — discreet, placed near the support button */}
            <button
                onClick={() => setIsOpen(true)}
                className="fixed bottom-24 right-6 z-40 hidden sm:flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full shadow-lg text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all duration-200 hover:shadow-xl"
                title="Sugerir melhoria"
            >
                <span>💡</span>
                <span>Sugerir melhoria</span>
            </button>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
                    {/* Backdrop */}
                    <div
                        className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                        onClick={() => { if (!isPending) { setIsOpen(false); } }}
                    />

                    {/* Panel */}
                    <div className="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {/* Header */}
                        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                            <div className="flex items-center gap-2">
                                <span className="text-xl">💡</span>
                                <h2 className="text-base font-bold text-slate-900 dark:text-slate-100">Sugerir melhoria</h2>
                            </div>
                            <button
                                onClick={() => setIsOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Content */}
                        {submitted ? (
                            <div className="px-6 py-10 text-center">
                                <div className="text-5xl mb-3">🎉</div>
                                <p className="text-base font-semibold text-slate-800 dark:text-slate-200">Sugestão enviada!</p>
                                <p className="text-sm text-slate-500 mt-1">Obrigado pela sua contribuição.</p>
                            </div>
                        ) : (
                            <div className="px-6 py-5 space-y-4">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Tem uma ideia para melhorar a plataforma? Compartilhe — lemos tudo! ✨
                                </p>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                        Título da sugestão <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={title}
                                        onChange={e => setTitle(e.target.value)}
                                        placeholder="Ex: Exportar resultados de simulados em PDF"
                                        maxLength={200}
                                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none transition-shadow"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                        Descrição (opcional)
                                    </label>
                                    <textarea
                                        value={body}
                                        onChange={e => setBody(e.target.value)}
                                        placeholder="Explique melhor sua ideia..."
                                        rows={4}
                                        maxLength={2000}
                                        className="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none resize-none transition-shadow"
                                    />
                                    <p className="text-right text-xs text-slate-400 mt-1">{body.length}/2000</p>
                                </div>

                                <div className="flex gap-3 pt-1">
                                    <button
                                        onClick={() => setIsOpen(false)}
                                        className="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        onClick={() => mutate()}
                                        disabled={!canSubmit}
                                        className="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-semibold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-opacity flex items-center justify-center gap-2"
                                    >
                                        {isPending ? (
                                            <>
                                                <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                                Enviando...
                                            </>
                                        ) : 'Enviar sugestão'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
