import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';

// Max polling duration: 5 minutes (150 polls x 2s)
const MAX_POLL_ATTEMPTS = 150;

export default function StudyPlanWizard() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hours, setHours] = useState(4);
    const [examType, setExamType] = useState('enem');
    const [examName, setExamName] = useState('');
    const [examDate, setExamDate] = useState('');
    const [elapsedSeconds, setElapsedSeconds] = useState(0);

    const pollerRef = useRef<NodeJS.Timeout | null>(null);
    const timerRef = useRef<NodeJS.Timeout | null>(null);
    const pollAttemptsRef = useRef(0);
    const isMounted = useRef(true);

    const STORAGE_KEY = 'studyPlanGenerationInProgress';
    const PLAN_ID_KEY = 'studyPlanGeneratingId';

    useEffect(() => {
        const checkInitialState = async () => {
            if (localStorage.getItem(STORAGE_KEY) === 'true') {
                const savedId = localStorage.getItem(PLAN_ID_KEY);
                if (savedId) {
                    setLoading(true);
                    startElapsedTimer();
                    pollStatus(parseInt(savedId, 10));
                    return;
                }
            }

            try {
                const { data } = await api.get('/api/v1/study-plan');
                if (data.view_state === 'wizard' && data.processing_plan_id) {
                    setLoading(true);
                    localStorage.setItem(STORAGE_KEY, 'true');
                    localStorage.setItem(PLAN_ID_KEY, data.processing_plan_id.toString());
                    startElapsedTimer();
                    pollStatus(data.processing_plan_id);
                }
            } catch (err) {
                // Silently fail
            }
        };

        checkInitialState();

        return () => {
            isMounted.current = false;
            if (pollerRef.current) clearInterval(pollerRef.current);
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, []);

    const startElapsedTimer = () => {
        if (timerRef.current) return;
        timerRef.current = setInterval(() => {
            setElapsedSeconds(s => s + 1);
        }, 1000);
    };

    const stopPollingAndClean = () => {
        if (pollerRef.current) clearInterval(pollerRef.current);
        if (timerRef.current) clearInterval(timerRef.current);
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(PLAN_ID_KEY);
        pollAttemptsRef.current = 0;
    };

    // KEY FIX: Invalidate the React Query cache before navigating so the
    // dashboard component always fetches fresh data instead of showing stale cache.
    const finishAndRedirect = async () => {
        stopPollingAndClean();
        await queryClient.invalidateQueries({ queryKey: ['studyPlanDashboard'] });
        queryClient.removeQueries({ queryKey: ['studyPlanDashboard'] });
        navigate('/plano-de-estudo', { replace: true });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);
        setElapsedSeconds(0);
        pollAttemptsRef.current = 0;

        try {
            const response = await api.post('/api/v1/study-plan', {
                hours_per_day: hours,
                exam_type: examType,
                exam_name: examName,
                exam_date: examDate || null
            }, {
                validateStatus: (status) => status < 500
            });

            if (!isMounted.current) return;

            if (response.status === 202) {
                const planId = response.data.study_plan_id;
                localStorage.setItem(STORAGE_KEY, 'true');
                if (planId) localStorage.setItem(PLAN_ID_KEY, planId.toString());
                startElapsedTimer();
                pollStatus(planId);
            } else if (response.status === 403) {
                setLoading(false);
                setError(response.data.message || 'Dados insuficientes para gerar o plano.');
            } else {
                await finishAndRedirect();
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
            pollAttemptsRef.current++;

            // Guard: max 5 minutes of polling
            if (pollAttemptsRef.current >= MAX_POLL_ATTEMPTS) {
                stopPollingAndClean();
                if (isMounted.current) {
                    setLoading(false);
                    setError('A geração está demorando mais que o esperado. Tente novamente ou volte em alguns instantes.');
                }
                return;
            }

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
                    await finishAndRedirect();
                } else if (res.data.status === 'ready') {
                    await finishAndRedirect();
                } else if (res.data.status === 'failed') {
                    stopPollingAndClean();
                    setLoading(false);
                    setError(res.data.message || 'Falha na geração do plano. Tente novamente.');
                }
            } catch (err) {
                // Keep polling
            }
        }, 2000);
    };

    const formatElapsed = (s: number) => {
        if (s < 60) return `${s}s`;
        return `${Math.floor(s / 60)}m ${s % 60}s`;
    };

    return (
        <div className="py-12">
            <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div className="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none sm:rounded-lg border border-slate-200 dark:border-slate-700">
                    <div className="p-6">
                        <h2 className="text-xl font-bold text-slate-800 dark:text-slate-100 mb-6">Gerar Seu Plano de Estudos</h2>

                        {loading ? (
                            <div className="py-12 px-4 text-center">
                                <div className="w-24 h-24 mx-auto mb-6 bg-blue-50 dark:bg-blue-900/20 rounded-full flex items-center justify-center relative">
                                    <div className="absolute inset-0 rounded-full border-4 border-blue-100 dark:border-blue-900/40"></div>
                                    <div className="absolute inset-0 rounded-full border-4 border-blue-600 border-t-transparent animate-spin"></div>
                                    <span className="text-4xl">🤖</span>
                                </div>
                                <h3 className="text-2xl font-black text-slate-900 dark:text-white mb-3">
                                    Xavier está analisando seus dados...
                                </h3>
                                <p className="text-slate-500 dark:text-slate-400 mb-2 text-sm font-medium">
                                    Tempo decorrido: {formatElapsed(elapsedSeconds)}
                                </p>
                                <p className="text-slate-600 dark:text-slate-400 mb-8 max-w-md mx-auto leading-relaxed">
                                    A Inteligência Artificial está cruzando seu histórico de erros e acertos para montar o cronograma perfeito. Isso leva cerca de 10 a 30 segundos.
                                </p>
                                <div className="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 inline-flex items-center gap-3">
                                    <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span className="text-sm font-bold text-emerald-800 dark:text-emerald-300">
                                        Você pode sair desta página! Seu plano continuará sendo gerado.
                                    </span>
                                </div>
                            </div>
                        ) : (
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
                                            Gerar Plano ✨
                                        </button>
                                    </div>
                                    <p className="mt-4 text-xs text-slate-500 text-center">
                                        O sistema analisará seus simulados anteriores para criar a melhor estratégia. Isso pode levar alguns segundos.
                                    </p>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
