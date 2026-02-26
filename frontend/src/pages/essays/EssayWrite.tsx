import { useState, FormEvent, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { createEssay } from '../../api/essays';

export default function EssayWrite() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [theme, setTheme] = useState('');
    const [content, setContent] = useState('');
    const [error, setError] = useState<string | null>(null);

    const charCount = content.length;
    const wordCount = content.trim() === '' ? 0 : content.trim().split(/\\s+/).filter(w => w.length > 0).length;

    // Simple Timer Implementation
    const timeLimitMinutes = 60;
    const [remainingSeconds, setRemainingSeconds] = useState(timeLimitMinutes * 60);

    useEffect(() => {
        if (remainingSeconds > 0) {
            const timer = setInterval(() => {
                setRemainingSeconds(prev => prev > 0 ? prev - 1 : 0);
            }, 1000);
            return () => clearInterval(timer);
        }
    }, [remainingSeconds]);

    const h = Math.floor(remainingSeconds / 3600).toString().padStart(2, '0');
    const m = Math.floor((remainingSeconds % 3600) / 60).toString().padStart(2, '0');
    const s = (remainingSeconds % 60).toString().padStart(2, '0');
    const timerDisplay = `${h}:${m}:${s}`;

    const mutation = useMutation({
        mutationFn: (data: { theme: string; content: string }) => createEssay(data),
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['essays'] });
            // Depending on the API, navigate logic:
            navigate(data?.data?.id ? `/essays/${data.data.id}` : '/essays');
        },
        onError: (err: any) => {
            setError(err.response?.data?.message || 'Falha ao enviar a redação. Verifique os dados e tente novamente.');
        }
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!theme.trim()) {
            setError('O tema da redação é obrigatório.');
            return;
        }
        if (content.trim().length < 100) {
            setError('A redação precisa ter no mínimo 100 caracteres.');
            return;
        }

        if (window.confirm('Tem certeza que deseja enviar sua redação para correção?')) {
            mutation.mutate({ theme, content });
        }
    };

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div className="mb-4">
                    <Link to="/essays" className="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                        &larr; Voltar para minhas redações
                    </Link>
                </div>

                <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div className="border-b border-gray-200 dark:border-gray-700 p-4">
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight text-center">
                            Escrever Nova Redação
                        </h2>
                    </div>

                    <div className="p-6 text-gray-900 dark:text-gray-100">
                        {error && (
                            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative mb-4">
                                <strong>Ops!</strong> <span className="block sm:inline">{error}</span>
                            </div>
                        )}

                        {/* Timer & Info */}
                        <div className={`flex justify-between items-center p-4 rounded-md mb-6 transition-colors duration-300 ${remainingSeconds <= 120 ? 'bg-red-100 dark:bg-red-900' : 'bg-blue-50 dark:bg-blue-900'}`}>
                            <div>
                                <span className="font-bold">Tempo limite sugerido:</span> {timeLimitMinutes} min
                            </div>
                            <div className="flex flex-col items-end">
                                <div className={`font-mono text-xl font-bold ${remainingSeconds <= 120 ? 'text-red-600 dark:text-red-400' : 'text-blue-700 dark:text-blue-300'}`}>
                                    {timerDisplay}
                                </div>
                                {remainingSeconds <= 120 && (
                                    <div className="text-xs font-bold text-red-600 dark:text-red-400 animate-pulse mt-1">
                                        ⚠ Faltam menos de 2 minutos!
                                    </div>
                                )}
                            </div>
                        </div>

                        <form onSubmit={handleSubmit}>
                            <div className="mb-6">
                                <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-1">
                                    Tema da Redação
                                </label>
                                <input
                                    type="text"
                                    value={theme}
                                    onChange={(e) => setTheme(e.target.value)}
                                    className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm p-3 font-semibold dark:text-white"
                                    placeholder="Ex: Os desafios da saúde mental no século XXI"
                                    disabled={mutation.isPending}
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-1">
                                    Conteúdo
                                </label>
                                <textarea
                                    value={content}
                                    onChange={(e) => setContent(e.target.value)}
                                    rows={20}
                                    className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none font-serif text-lg leading-relaxed p-6 dark:text-gray-100 disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                                    placeholder="Escreva sua redação aqui..."
                                    disabled={mutation.isPending}
                                />

                                <div className="flex justify-end space-x-4 mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span>Caracteres: <span className="font-semibold">{charCount}</span></span>
                                    <span>Palavras: <span className="font-semibold">{wordCount}</span></span>
                                </div>
                            </div>

                            <div className="flex justify-end mt-6">
                                <button
                                    type="submit"
                                    disabled={mutation.isPending}
                                    className="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                                >
                                    {mutation.isPending ? (
                                        <>
                                            <span className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></span>
                                            Enviando...
                                        </>
                                    ) : (
                                        "Enviar Redação"
                                    )}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    );
}
