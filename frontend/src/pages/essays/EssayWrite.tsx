import { useState, FormEvent, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { createEssayDraft, startTopicGeneration, getTopicStatus, submitEssay } from '../../api/essays';

export default function EssayWrite() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    // Wizard State
    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [essayId, setEssayId] = useState<number | null>(null);

    // Step 1 State
    const [type, setType] = useState('enem');
    const [timeLimit, setTimeLimit] = useState(60);

    // Step 2 State
    const [theme, setTheme] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [regenCount, setRegenCount] = useState(0);

    // Step 3 State
    const [inputType, setInputType] = useState<'text' | 'image'>('text');
    const [content, setContent] = useState('');
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);

    // Shared State
    const [error, setError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const charCount = content.length;
    const wordCount = content.trim() === '' ? 0 : content.trim().split(/\s+/).filter(w => w.length > 0).length;

    // Timer
    const [remainingSeconds, setRemainingSeconds] = useState(timeLimit * 60);

    // Mutations
    const draftMutation = useMutation({
        mutationFn: () => createEssayDraft({ type, time_limit: timeLimit }),
        onSuccess: (data) => {
            setEssayId(data.data.id);
            setStep(2);
            setError(null);
        },
        onError: () => setError('Erro ao criar rascunho. Tente novamente.')
    });

    const generateMutation = useMutation({
        mutationFn: () => startTopicGeneration(essayId!),
        onSuccess: () => {
            setIsGenerating(true);
            setError(null);
            pollTopicStatus();
        },
        onError: (err: any) => setError(err.response?.data?.message || 'Erro ao iniciar geração.')
    });

    const pollTopicStatus = () => {
        const interval = setInterval(async () => {
            try {
                const data = await getTopicStatus(essayId!);
                if (data.topic_description) {
                    clearInterval(interval);
                    setTheme(data.topic_description);
                    setRegenCount(data.topic_regen_count);
                    setIsGenerating(false);
                } else if (data.status === 'error') {
                    clearInterval(interval);
                    setIsGenerating(false);
                    setError('A IA falhou ao gerar o tema. Você pode digitar um tema manualmente.');
                }
            } catch (err) {
                // Keep polling unless it's a fatal error
            }
        }, 3000);
    };

    const submitMutation = useMutation({
        mutationFn: (data: FormData) => submitEssay(essayId!, data),
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['essays'] });
            navigate(data?.data?.id ? `/essays/${data.data.id}` : '/essays');
        },
        onError: (err: any) => setError(err.response?.data?.message || 'Falha ao enviar a redação.')
    });

    // Step Handlers
    const handleStep1Submit = (e: FormEvent) => {
        e.preventDefault();
        setRemainingSeconds(timeLimit * 60);
        draftMutation.mutate();
    };

    const handleStep2Submit = (e: FormEvent) => {
        e.preventDefault();
        if (!theme.trim()) {
            setError('Defina um tema antes de continuar.');
            return;
        }
        setError(null);
        setStep(3);
    };

    const handleStep3Submit = (e: FormEvent) => {
        e.preventDefault();
        setError(null);

        const formData = new FormData();
        formData.append('input_type', inputType);
        formData.append('custom_theme', theme); // Ensure backend updates it if manual

        if (inputType === 'text') {
            if (content.trim().length < 50) {
                setError('Escreva ao menos 50 caracteres para avaliação.');
                return;
            }
            formData.append('content', content);
        } else {
            if (!imageFile) {
                setError('Selecione a imagem.');
                return;
            }
            formData.append('image', imageFile);
        }

        if (window.confirm('Confirmar envio para correção?')) {
            submitMutation.mutate(formData);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 8 * 1024 * 1024) {
                setError('Imagem muito grande (máx 8MB).');
                return;
            }
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setImagePreview(reader.result as string);
            reader.readAsDataURL(file);
            setError(null);
        }
    };

    // Timer Effect
    useEffect(() => {
        if (step === 3 && remainingSeconds > 0) {
            const timer = setInterval(() => {
                setRemainingSeconds(prev => prev > 0 ? prev - 1 : 0);
            }, 1000);
            return () => clearInterval(timer);
        }
    }, [step, remainingSeconds]);

    const h = Math.floor(remainingSeconds / 3600).toString().padStart(2, '0');
    const m = Math.floor((remainingSeconds % 3600) / 60).toString().padStart(2, '0');
    const s = (remainingSeconds % 60).toString().padStart(2, '0');
    const timerDisplay = `${h}:${m}:${s}`;

    return (
        <div className="py-12">
            <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <div className="mb-6 flex justify-between items-center">
                    <Link to="/essays" className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Voltar
                    </Link>
                    {step === 3 && (
                        <div className={`px-4 py-2 rounded-full font-mono font-bold shadow-sm border ${remainingSeconds <= 300 ? 'bg-red-50 border-red-200 text-red-600 animate-pulse' : 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300'}`}>
                            {timerDisplay}
                        </div>
                    )}
                </div>

                <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    {/* Steps Indicator */}
                    <div className="border-b border-gray-200 dark:border-gray-700 p-4">
                        <div className="flex items-center justify-center space-x-8">
                            <div className={`font-bold ${step >= 1 ? 'text-blue-600' : 'text-gray-400'}`}>1. Tipo e Tempo</div>
                            <div className={`w-12 h-0.5 ${step >= 2 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}></div>
                            <div className={`font-bold ${step >= 2 ? 'text-blue-600' : 'text-gray-400'}`}>2. Tema</div>
                            <div className={`w-12 h-0.5 ${step >= 3 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}></div>
                            <div className={`font-bold ${step >= 3 ? 'text-blue-600' : 'text-gray-400'}`}>3. Escrita</div>
                        </div>
                    </div>

                    <div className="p-6 text-gray-900 dark:text-gray-100">
                        {error && (
                            <div className="bg-red-100 dark:bg-red-900 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-md mb-6">
                                <span className="text-sm font-medium">{error}</span>
                            </div>
                        )}

                        {/* STEP 1: Type & Time */}
                        {step === 1 && (
                            <form onSubmit={handleStep1Submit} className="space-y-6">
                                <h3 className="text-lg font-medium">Escolha o formato</h3>
                                <div>
                                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Tipo de Redação</label>
                                    <select
                                        value={type}
                                        onChange={(e) => setType(e.target.value)}
                                        className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        disabled={draftMutation.isPending}
                                    >
                                        <option value="enem">ENEM</option>
                                        <option value="concurso">Concurso Público</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Tempo Disponível</label>
                                    <select
                                        value={timeLimit}
                                        onChange={(e) => setTimeLimit(Number(e.target.value))}
                                        className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        disabled={draftMutation.isPending}
                                    >
                                        <option value={30}>30 minutos</option>
                                        <option value={45}>45 minutos</option>
                                        <option value={60}>60 minutos (1 hora)</option>
                                        <option value={90}>90 minutos (1h 30m)</option>
                                        <option value={120}>120 minutos (2 horas)</option>
                                    </select>
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" disabled={draftMutation.isPending} className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50 font-bold">
                                        {draftMutation.isPending ? 'Criando...' : 'Continuar'}
                                    </button>
                                </div>
                            </form>
                        )}

                        {/* STEP 2: Theme */}
                        {step === 2 && (
                            <form onSubmit={handleStep2Submit} className="space-y-6">
                                <h3 className="text-lg font-medium">Defina o Tema</h3>

                                {isGenerating ? (
                                    <div className="flex flex-col items-center justify-center space-y-4 py-8">
                                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                                        <p className="font-medium text-gray-600 dark:text-gray-300">A IA (Xavier) está gerando um tema para você...</p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <label className="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Tema da Redação</label>
                                            <textarea
                                                value={theme}
                                                onChange={(e) => setTheme(e.target.value)}
                                                rows={4}
                                                placeholder="Digite o tema no qual você deseja escrever..."
                                                className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none"
                                            />
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <button
                                                type="button"
                                                onClick={() => generateMutation.mutate()}
                                                disabled={generateMutation.isPending || regenCount >= 3}
                                                className="text-blue-600 hover:text-blue-800 font-semibold text-sm disabled:opacity-50"
                                            >
                                                {regenCount < 3 ? '✨ Gerar tema com IA' : 'Limite de gerações atingido'}
                                            </button>

                                            <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-bold">
                                                Continuar
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </form>
                        )}

                        {/* STEP 3: Write */}
                        {step === 3 && (
                            <form onSubmit={handleStep3Submit} className="space-y-6">
                                <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-6 text-sm">
                                    <span className="font-bold">Tema:</span> {theme}
                                </div>

                                <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md mb-4 text-sm">
                                    <label className="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Formato de Envio</label>
                                    <select
                                        className="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        value={inputType}
                                        onChange={(e: any) => setInputType(e.target.value)}
                                    >
                                        <option value="text">Digitar Texto</option>
                                        <option value="image">Enviar Foto (OCR)</option>
                                    </select>
                                </div>

                                {inputType === 'text' ? (
                                    <div>
                                        <textarea
                                            value={content}
                                            onChange={(e) => setContent(e.target.value)}
                                            rows={25}
                                            className="lined-paper w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm resize-none font-serif text-lg p-6 disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                                            placeholder="Escreva sua redação aqui..."
                                            disabled={submitMutation.isPending}
                                        />
                                        <div className="flex justify-end space-x-4 mt-2 text-sm text-gray-500">
                                            <span>Caracteres: <span>{charCount}</span></span>
                                            <span>Palavras: <span>{wordCount}</span></span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="p-4 border border-gray-300 dark:border-gray-700 border-dashed rounded-md bg-white dark:bg-gray-900 text-center">
                                        <input
                                            type="file"
                                            ref={fileInputRef}
                                            onChange={handleFileChange}
                                            accept="image/jpeg,image/png,image/webp"
                                            className="hidden"
                                            id="image-upload"
                                            disabled={submitMutation.isPending}
                                        />

                                        {imagePreview ? (
                                            <div className="mt-4">
                                                <img src={imagePreview} alt="Preview" className="max-h-96 mx-auto rounded shadow-sm" />
                                                <button type="button" onClick={() => { setImageFile(null); setImagePreview(null); }} className="mt-2 text-sm text-red-600 hover:text-red-800">
                                                    Remover imagem
                                                </button>
                                            </div>
                                        ) : (
                                            <label htmlFor="image-upload" className="cursor-pointer">
                                                <div className="text-gray-500 dark:text-gray-400 py-12">
                                                    <svg className="mx-auto h-12 w-12 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28H8z" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" /></svg>
                                                    <span className="text-sm font-medium text-indigo-600 dark:text-indigo-400">Clique para selecionar foto (máx 8MB)</span>
                                                </div>
                                            </label>
                                        )}
                                    </div>
                                )}

                                <div className="flex justify-end mt-6">
                                    <button
                                        type="submit"
                                        disabled={submitMutation.isPending}
                                        className="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 font-bold text-lg disabled:opacity-50 flex items-center gap-2"
                                    >
                                        {submitMutation.isPending && (
                                            <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                                        )}
                                        {submitMutation.isPending ? 'Enviando...' : 'Enviar para Correção'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

