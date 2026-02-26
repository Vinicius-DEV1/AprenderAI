import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import { useUIStore } from '../../stores/uiStore';

// Local API calls just for this view's specific needs (polling/answering)
const checkSimulationStatus = async (id: string) => {
    const { data } = await api.get(`/api/v1/simulations/${id}/status`);
    return data;
};

const getSimulationDetails = async (id: string) => {
    const { data } = await api.get(`/api/v1/simulations/${id}`, { params: { include_answers: 1 } });
    return data.data;
};

const submitSingleAnswer = async (id: string, payload: any) => {
    const { data } = await api.post(`/api/v1/simulations/${id}/answer`, payload);
    return data;
};

const finishSimulationApi = async (id: string) => {
    const { data } = await api.post(`/api/v1/simulations/${id}/finish`);
    return data;
};

export default function SimulationView() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { toggleSidebar } = useUIStore();

    const [currentQuestion, setCurrentQuestion] = useState(0);
    const [timeRemaining, setTimeRemaining] = useState<number | null>(null);
    const [currentMsgIdx, setCurrentMsgIdx] = useState(0);

    const messages = [
        'Analisando seu desempenho histórico...',
        'Selecionando questões inéditas...',
        'Equilibrando níveis de dificuldade...',
        'Construindo seu DNA pedagógico...',
        'Finalizando a estrutura da prova...',
        'Quase lá! Preparando seu ambiente...'
    ];

    // Queries
    const { data: statusData, isError: isStatusError } = useQuery({
        queryKey: ['simulationStatus', id],
        queryFn: () => checkSimulationStatus(id!),
        refetchInterval: (query: any) => {
            const status = query.state.data?.simulation_status;
            if (status === 'error' || status === 'finished' || status === 'corrected' || (status === 'pending' && query.state.data?.answers_count > 0) || status === 'in_progress') {
                return false; // stop polling
            }
            return 2000; // poll every 2s
        },
        enabled: !!id
    });

    const isGenerating = statusData?.simulation_status === 'generating' || (statusData?.simulation_status === 'pending' && statusData?.answers_count === 0);
    const hasError = statusData?.simulation_status === 'error' || isStatusError;

    const { data: simulation, isLoading: isLoadingDetails } = useQuery({
        queryKey: ['simulationDetails', id],
        queryFn: () => getSimulationDetails(id!),
        enabled: !!id && !isGenerating && !hasError
    });

    // Timer Setup
    useEffect(() => {
        if (simulation && timeRemaining === null) {
            const limit = simulation.configuration?.time_limit || 10800; // default 3 hours

            // Calculate elapsed time from the server's started_at
            if (simulation.started_at) {
                const startTime = new Date(simulation.started_at).getTime();
                const now = Date.now();
                // We add elapsed seconds here
                const elapsedSeconds = Math.floor((now - startTime) / 1000);
                const remaining = Math.max(0, limit - elapsedSeconds);
                setTimeRemaining(remaining);
            } else {
                setTimeRemaining(limit);
            }
        }
    }, [simulation, timeRemaining]);

    useEffect(() => {
        if (!isGenerating && timeRemaining !== null && timeRemaining > 0) {
            const timer = setInterval(() => {
                setTimeRemaining(prev => (prev !== null && prev > 0 ? prev - 1 : 0));
            }, 1000);
            return () => clearInterval(timer);
        } else if (timeRemaining === 0) {
            handleFinishSimulation();
        }
    }, [isGenerating, timeRemaining]);

    // AI Messages Cycle
    useEffect(() => {
        if (isGenerating) {
            const interval = setInterval(() => {
                setCurrentMsgIdx(prev => (prev + 1) % messages.length);
            }, 3000);
            return () => clearInterval(interval);
        }
    }, [isGenerating, messages.length]);

    // Mutations
    const answerMutation = useMutation({
        mutationFn: (payload: any) => submitSingleAnswer(id!, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['simulationDetails', id] });
        }
    });

    const finishMutation = useMutation({
        mutationFn: () => finishSimulationApi(id!),
        onSuccess: () => {
            navigate(`/simulations/${id}/result`);
        }
    });

    // Handlers
    const handleAnswer = (questionId: number, answerStr: string) => {
        if (!simulation) return;
        const timeSpent = (simulation.configuration?.time_limit || 10800) - (timeRemaining || 0);
        answerMutation.mutate({
            question_id: questionId,
            answer: answerStr,
            time_spent: timeSpent > 0 ? timeSpent : 0
        });
    };

    const handleToggleMark = (questionId: number, currentMarked: boolean) => {
        answerMutation.mutate({
            question_id: questionId,
            marked_for_review: !currentMarked
        });
    };

    const handleFinishSimulation = () => {
        if (window.confirm('Tem certeza que deseja finalizar a prova? Esta ação não pode ser desfeita.')) {
            finishMutation.mutate();
        }
    };

    // UI helpers
    const formatTime = (seconds: number) => {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    if (hasError) {
        return (
            <div className="flex flex-col items-center justify-center min-h-screen p-6">
                <div className="bg-white p-8 rounded-xl shadow-lg max-w-md text-center border-t-4 border-red-500">
                    <h2 className="text-xl font-bold text-gray-800 mb-4">Ocorreu um Erro</h2>
                    <p className="text-gray-600 mb-6">Não foi possível processar este simulado. Por favor, tente criar um novo.</p>
                    <button onClick={() => navigate('/simulations/create')} className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Novo Simulado</button>
                </div>
            </div>
        );
    }

    // GENERATING STATE
    if (isGenerating || !statusData) {
        return (
            <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-950 p-6">
                <style>{`
          @keyframes pulse-glow {
              0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(79, 70, 229, 0.4); }
              50% { transform: scale(1.05); box-shadow: 0 0 40px rgba(79, 70, 229, 0.6); }
          }
          @keyframes rotate-ring {
              from { transform: rotate(0deg); }
              to { transform: rotate(360deg); }
          }
          @keyframes shimmer {
              0% { transform: translateX(-100%); }
              100% { transform: translateX(100%); }
          }
          .ai-orb-container { position: relative; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; }
          .ai-orb { width: 80px; height: 80px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; letter-spacing: 1px; z-index: 10; animation: pulse-glow 3s ease-in-out infinite; position: relative; }
          .ai-ring { position: absolute; width: 110px; height: 110px; border: 2px solid transparent; border-top-color: #4f46e5; border-right-color: rgba(79, 70, 229, 0.2); border-bottom-color: #7c3aed; border-left-color: rgba(124, 58, 237, 0.2); border-radius: 50%; animation: rotate-ring 4s linear infinite; }
          .ai-ring-outer { position: absolute; width: 120px; height: 120px; border: 1px dashed rgba(79, 70, 229, 0.3); border-radius: 50%; animation: rotate-ring 12s linear infinite reverse; }
          .progress-bar-container { width: 100%; max-width: 350px; height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; position: relative; }
          :root.dark .progress-bar-container { background: #334155; }
          .progress-bar-fill { height: 100%; width: 100%; background: linear-gradient(90deg, #4f46e5, #7c3aed, #4f46e5); background-size: 200% 100%; animation: bg-move 3s linear infinite; border-radius: 10px; position: relative; }
          @keyframes bg-move { 0% { background-position: 0% 0%; } 100% { background-position: -200% 0%; } }
          .shimmer-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent); animation: shimmer 1.5s infinite; }
        `}</style>

                <div className="text-center p-10 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl dark:shadow-none dark:border dark:border-slate-700 max-w-md w-full border border-slate-100">
                    <div className="ai-orb-container mx-auto">
                        <div className="ai-ring-outer"></div>
                        <div className="ai-ring"></div>
                        <div className="ai-orb"><span className="text-xl">AI</span></div>
                    </div>
                    <h2 className="text-2xl font-extrabold text-slate-800 dark:text-slate-100 mb-2">Construindo seu Simulado</h2>
                    <div className="h-12 flex items-center justify-center mb-6 relative">
                        <p className="text-indigo-600 dark:text-indigo-400 font-medium text-lg transition-all duration-500">
                            {messages[currentMsgIdx]}
                        </p>
                    </div>
                    <div className="progress-bar-container mx-auto mb-4">
                        <div className="progress-bar-fill"><div className="shimmer-overlay"></div></div>
                    </div>
                    <p className="text-slate-400 text-sm">Isso geralmente leva menos de 10 segundos.</p>
                </div>
            </div>
        );
    }

    // LOADING DETAILS STATE
    if (isLoadingDetails || !simulation || simulation.answers?.length === 0) {
        return <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>;
    }

    const answersList = simulation.answers || [];
    const currentAnswerData = answersList[currentQuestion];
    const question = currentAnswerData?.question;
    const totalQuestions = answersList.length;

    return (
        <div className="simulation-page p-4 lg:p-8 max-w-[1400px] mx-auto">
            <style>{`
        .simulation-container { display: grid; grid-template-columns: 250px 1fr; gap: 24px; height: calc(100vh - 120px); }
        @media (max-width: 768px) { .simulation-container { grid-template-columns: 1fr; height: auto; } .question-nav { display: none; } }
        .question-nav { background: white; border-radius: 12px; padding: 20px; overflow-y: auto; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; }
        .timer { background: #1e293b; color: white; padding: 16px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .timer-label { font-size: 12px; opacity: 0.7; margin-bottom: 4px; }
        .timer-value { font-size: 28px; font-weight: 700; font-family: 'Courier New', monospace; }
        .nav-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 20px; flex: 1; overflow-y: auto; }
        .nav-btn { width: 100%; aspect-ratio: 1; border: 2px solid #e2e8f0; background: white; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .nav-btn:hover { border-color: #cbd5e1; }
        .nav-btn.answered { background: #d1fae5; border-color: #10b981; color: #065f46; }
        .nav-btn.marked { background: #fef3c7; border-color: #f59e0b; color: #78350f; }
        .nav-btn.active { background: #2563EB; border-color: #2563EB; color: white; }
        .legend { font-size: 12px; margin-top: 16px; }
        .legend-item { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; border: 2px solid; }
        .question-area { background: white; border-radius: 12px; padding: 32px; overflow-y: auto; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); height: 100%; }
        .question-header { display: flex; justify-content: space-between; items-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f1f5f9; gap: 10px; flex-wrap: wrap; }
        .question-number { font-size: 14px; font-weight: 600; color: #64748b; }
        .question-statement { font-size: 16px; line-height: 1.7; color: #1e293b; margin-bottom: 32px; max-width: 900px; white-space: pre-wrap; }
        .alternatives { list-style: none; max-width: 900px; }
        .alternative { margin-bottom: 16px; }
        .alternative label { display: flex; align-items: flex-start; gap: 12px; padding: 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .alternative label:hover { border-color: #cbd5e1; background: #f8fafc; }
        .alternative input[type="radio"]:checked + label { border-color: #2563EB; background: #eff6ff; }
        .alternative input[type="radio"] { margin-top: 2px; }
        .alternative-letter { font-weight: 700; color: #2563EB; min-width: 20px; }
        .question-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; padding-top: 24px; border-top: 2px solid #f1f5f9; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-secondary { background: #f1f5f9; color: #334155; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-primary { background: #2563EB; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .checkbox-mark { display: flex; align-items: center; gap: 8px; }
        
        :root.dark .question-nav, :root.dark .question-area { background: #1e293b; color: #f1f5f9; }
        :root.dark h2, :root.dark .question-statement { color: #e2e8f0; }
        :root.dark .question-number { color: #94a3b8; }
        :root.dark .question-header { border-bottom-color: rgba(255,255,255,0.1); }
        :root.dark .question-actions { border-top-color: rgba(255,255,255,0.1); }
        :root.dark .alternative label { border-color: rgba(255,255,255,0.1); color: #cbd5e1; }
        :root.dark .alternative label:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.2); }
        :root.dark .alternative input[type="radio"]:checked + label { background: rgba(37,99,235,0.2); border-color: #3b82f6; color: #e2e8f0; }
        :root.dark .nav-btn { background: #0f172a; border-color: rgba(255,255,255,0.1); color: #cbd5e1; }
        :root.dark .nav-btn:hover { border-color: rgba(255,255,255,0.3); }
        :root.dark .nav-btn.active { background: #2563EB; color: white; border-color: #2563EB; }
        :root.dark .nav-btn.answered { background: rgba(6, 95, 70, 0.4); border-color: #059669; color: #a7f3d0; }
        :root.dark .nav-btn.marked { background: rgba(120, 53, 15, 0.4); border-color: #d97706; color: #fde68a; }
        :root.dark .btn-secondary { background: #334155; color: #e2e8f0; }
        :root.dark .btn-secondary:hover { background: #475569; }
      `}</style>

            <div style={{ marginBottom: '20px', display: 'flex', alignItems: 'center' }}>
                <button
                    onClick={() => toggleSidebar()}
                    className="mr-4 p-2 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center gap-2 transition-colors"
                    title="Menu Painel"
                >
                    <svg className="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Menu Painel</span>
                </button>
                <h2 className="text-xl font-bold text-gray-800 dark:text-slate-200">Simulado em Progresso</h2>
            </div>

            <div className="simulation-container">
                {/* Sidebar Navigation */}
                <aside className="question-nav">
                    <div className="timer">
                        <div className="timer-label">Tempo restante</div>
                        <div className="timer-value">{formatTime(timeRemaining || 0)}</div>
                    </div>

                    <div className="nav-grid">
                        {answersList.map((ans: any, index: number) => {
                            let classes = "nav-btn ";
                            if (index === currentQuestion) classes += "active ";
                            else if (ans.user_answer) classes += "answered ";
                            else if (ans.marked_for_review) classes += "marked ";

                            return (
                                <button
                                    key={index}
                                    type="button"
                                    className={classes.trim()}
                                    onClick={() => setCurrentQuestion(index)}
                                >
                                    {index + 1}
                                </button>
                            );
                        })}
                    </div>

                    <div className="legend">
                        <div className="legend-item">
                            <div className="legend-color" style={{ background: '#d1fae5', borderColor: '#10b981' }}></div>
                            <span>Respondida</span>
                        </div>
                        <div className="legend-item">
                            <div className="legend-color" style={{ background: '#fef3c7', borderColor: '#f59e0b' }}></div>
                            <span>Marcada</span>
                        </div>
                        <div className="legend-item">
                            <div className="legend-color dark:bg-slate-800 dark:border-slate-600" style={{ background: 'white', borderColor: '#e2e8f0' }}></div>
                            <span>Não respondida</span>
                        </div>
                    </div>

                    <button type="button" className="btn btn-danger w-full mt-5" onClick={handleFinishSimulation}>
                        Finalizar Prova
                    </button>
                </aside>

                {/* Main Question Area */}
                <main className="question-area">
                    {question && (
                        <div className="question-content animate-fade-in">
                            <div className="question-header">
                                <span className="question-number">Questão {currentQuestion + 1} de {totalQuestions}</span>
                                <span style={{ fontSize: '11px', fontWeight: 600, color: '#64748b', background: '#f1f5f9', padding: '4px 12px', borderRadius: '12px', textTransform: 'uppercase', letterSpacing: '0.05em' }} className="dark:bg-slate-800 dark:text-slate-300">
                                    {question.subjects?.map((s: any) => s.name).join(', ') || 'Geral'}
                                </span>
                            </div>

                            <div className="question-statement" dangerouslySetInnerHTML={{ __html: question.html_statement || question.statement }} />

                            <ul className="alternatives">
                                {(question.alternatives || []).map((alt: any) => (
                                    <li key={alt.id || alt.label} className="alternative">
                                        <input
                                            type="radio"
                                            id={`q${question.id}_${alt.label}`}
                                            name={`question_${question.id}`}
                                            value={alt.label}
                                            checked={currentAnswerData.user_answer === alt.label}
                                            onChange={() => handleAnswer(question.id, alt.label)}
                                            className="sr-only" // using + label selector
                                        />
                                        <label htmlFor={`q${question.id}_${alt.label}`}>
                                            <span className="alternative-letter">{alt.label})</span>
                                            <div className="flex flex-col gap-2 flex-grow overflow-hidden">
                                                {alt.content && <span className="word-break-all">{alt.content}</span>}
                                                {alt.image_path && <img src={`/storage/${alt.image_path}`} alt={`Alternativa ${alt.label}`} className="max-w-full h-auto rounded object-contain mt-2" />}
                                            </div>
                                        </label>
                                    </li>
                                ))}
                            </ul>

                            <div className="question-actions">
                                <div className="checkbox-mark">
                                    <input
                                        type="checkbox"
                                        id={`mark_${currentQuestion}`}
                                        checked={currentAnswerData.marked_for_review ? true : false}
                                        onChange={() => handleToggleMark(question.id, currentAnswerData.marked_for_review)}
                                    />
                                    <label htmlFor={`mark_${currentQuestion}`} className="cursor-pointer select-none text-sm text-gray-600 dark:text-gray-300">
                                        Marcar para revisão
                                    </label>
                                </div>

                                <div className="flex flex-wrap gap-3">
                                    {currentQuestion > 0 && (
                                        <button type="button" className="btn btn-secondary" onClick={() => setCurrentQuestion(curr => curr - 1)}>
                                            ← Anterior
                                        </button>
                                    )}
                                    {currentQuestion < totalQuestions - 1 && (
                                        <button type="button" className="btn btn-primary" onClick={() => setCurrentQuestion(curr => curr + 1)}>
                                            Próxima →
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </div>
    );
}
