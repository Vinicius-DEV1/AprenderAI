import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/axios';

export default function StudyPlanWizard() {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hours, setHours] = useState(4);
    const [examType, setExamType] = useState('enem');
    const [examName, setExamName] = useState('');
    const [examDate, setExamDate] = useState('');

    const pollerRef = useRef<NodeJS.Timeout | null>(null);
    const isMounted = useRef(true);

    const STORAGE_KEY = 'studyPlanGenerationInProgress';
    const PLAN_ID_KEY = 'studyPlanGeneratingId';

    useEffect(() => {
        // Resume polling if we left the page while generating
        if (localStorage.getItem(STORAGE_KEY) === 'true') {
            setLoading(true);
            const savedId = localStorage.getItem(PLAN_ID_KEY);
            if (savedId) {
                pollStatus(parseInt(savedId, 10));
            } else {
                // If API supports polling without ID to get the latest plan
                pollStatus();
            }
        }

        return () => {
            isMounted.current = false;
            if (pollerRef.current) clearInterval(pollerRef.current);
        };
    }, []);

    const stopPollingAndClean = () => {
        if (pollerRef.current) clearInterval(pollerRef.current);
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(PLAN_ID_KEY);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const response = await api.post('/api/v1/study-plan', {
                hours_per_day: hours,
                exam_type: examType,
                exam_name: examName,
                exam_date: examDate || null
            }, {
                validateStatus: (status) => status < 500 // Impede o toast lateral vermelho interceptando o 403 silenciosamente
            });

            if (!isMounted.current) return;

            if (response.status === 202) {
                const planId = response.data.study_plan_id;
                localStorage.setItem(STORAGE_KEY, 'true');
                if (planId) localStorage.setItem(PLAN_ID_KEY, planId.toString());
                pollStatus(planId);
            } else if (response.status === 403) {
                setLoading(false);
                setError(response.data.message || 'Dados insuficientes para gerar o plano.');
            } else {
                setLoading(false);
                navigate('/study-plan');
            }
        } catch (err: any) {
            if (!isMounted.current) return;
            console.error('Study plan generation error:', err);
            setError(err.response?.data?.message || err.response?.data?.error || 'Erro ao iniciar geração. Tente novamente.');
            setLoading(false);
        }
    };

    const pollStatus = (id?: number) => {
        pollerRef.current = setInterval(async () => {
            try {
                const params = id ? { id } : {};
                const res = await api.get('/api/v1/study-plan/status', {
                    params,
                    validateStatus: (status) => status < 500
                });

                if (!isMounted.current) {
                    if (pollerRef.current) clearInterval(pollerRef.current);
                    return;
                }

                if (res.status === 404) {
                    stopPollingAndClean();
                    setLoading(false);
                } else if (res.data.status === 'ready') {
                    stopPollingAndClean();
                    navigate('/study-plan');
                } else if (res.data.status === 'failed') {
                    stopPollingAndClean();
                    setLoading(false);
                    setError(res.data.message || 'Falha na geração do plano.');
                }
            } catch (err) {
                // Keep polling
            }
        }, 2000);
    };

    return (
        <div className="py-12">
            <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none sm:rounded-lg border border-slate-200 dark:border-slate-700">
                    <div className="p-6">
                        <h2 className="text-xl font-bold text-slate-800 dark:text-slate-100 mb-6">Gerar Seu Plano de Estudos</h2>

                        <form onSubmit={handleSubmit}>
                            {error && (
                                <div className="mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 text-red-700 dark:text-red-400">
                                    <p>{error}</p>
                                </div>
                            )}

                            {/* Step 1: Availability */}
                            <div className="mb-8">
                                <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100 mb-4">Disponibilidade</h3>
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Quantas horas por dia você pode estudar?
                                </label>
                                <select
                                    value={hours}
                                    onChange={e => setHours(parseInt(e.target.value))}
                                    className="mt-1 block w-full pl-3 pr-10 py-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                >
                                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(h => (
                                        <option key={h} value={h}>{h} hora{h > 1 ? 's' : ''}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Step 2: Goal */}
                            <div className="mb-8">
                                <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100 mb-4">Objetivo Principal</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">Tipo de Prova</label>
                                        <select
                                            value={examType}
                                            onChange={e => setExamType(e.target.value)}
                                            className="mt-1 block w-full pl-3 pr-10 py-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                        >
                                            <option value="enem">ENEM</option>
                                            <option value="concurso">Concurso Público</option>
                                        </select>
                                    </div>
                                    {examType === 'concurso' && (
                                        <div className="animate-xavier-pop">
                                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">Nome do Concurso (opcional)</label>
                                            <input
                                                type="text"
                                                value={examName}
                                                onChange={e => setExamName(e.target.value)}
                                                className="mt-1 block w-full border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                placeholder="Ex: Receita Federal"
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Step 3: Date */}
                            <div className="mb-8">
                                <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100 mb-4">Data da Prova (Opcional)</h3>
                                <div className="max-w-xs">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">Quando será a prova?</label>
                                    <input
                                        type="date"
                                        value={examDate}
                                        onChange={e => setExamDate(e.target.value)}
                                        className="mt-1 block w-full border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-200 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>

                            <div className="pt-5 border-t border-slate-200 dark:border-slate-700">
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className={`inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 transition-all ${loading ? 'opacity-75 cursor-not-allowed' : 'hover:bg-blue-700 hover:scale-105'}`}
                                    >
                                        {!loading ? 'Gerar Plano ✨' : (
                                            <span className="flex items-center">
                                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" strokeWidth="4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Gerando...
                                            </span>
                                        )}
                                    </button>
                                </div>
                                <p className="mt-4 text-xs text-slate-500 text-center">
                                    O sistema analisará seus simulados anteriores para criar a melhor estratégia. Isso pode levar alguns segundos.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
