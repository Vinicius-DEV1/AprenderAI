import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import StudyPlanEmpty from './StudyPlanEmpty';
import StudyPlanWizard from './StudyPlanWizard';

const getStudyPlanData = async () => {
    const { data } = await api.get('/api/v1/study-plan');
    return data;
};

export default function StudyPlanDashboard() {
    const queryClient = useQueryClient();
    const { data, isLoading } = useQuery({
        queryKey: ['studyPlanDashboard'],
        queryFn: getStudyPlanData
    });

    const updateMutation = useMutation({
        mutationFn: () => api.post('/api/v1/study-plan/update'),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['studyPlanDashboard'] });
        }
    });

    if (isLoading) return <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>;

    if (data?.view_state === 'paywall') {
        return (
            <div className="py-20 text-center">
                <div className="bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-500 p-8 rounded-xl max-w-2xl mx-auto shadow-sm">
                    <h2 className="text-2xl font-bold text-purple-800 dark:text-purple-300 mb-4">Plano Plus Necessário 🚀</h2>
                    <p className="text-purple-600 dark:text-purple-400 mb-6">A geração de Planos de Estudo personalizados via IA é uma funcionalidade exclusiva para membros Plus.</p>
                    <button onClick={() => window.location.href = '/plans'} className="px-6 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 transition">Ver Planos</button>
                </div>
            </div>
        );
    }

    if (data?.view_state === 'empty') return <StudyPlanEmpty />;
    if (data?.view_state === 'wizard') return <StudyPlanWizard />;

    // Main Dashboard
    const { confidence, diagnostics, projection, weak_strong, recommendations, exam_strategy, plan, can_update, next_update_at, days_until_update, motivation } = data;

    return (
        <div className="space-y-6 pb-20">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
                <div>
                    <h2 className="font-bold text-2xl text-slate-800 dark:text-slate-200">Plano de Estudos</h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400">Diagnóstico personalizado baseado no seu desempenho real</p>
                </div>

                {can_update ? (
                    <button
                        onClick={() => updateMutation.mutate()}
                        disabled={updateMutation.isPending}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm disabled:opacity-50"
                    >
                        {updateMutation.isPending ? 'Atualizando...' : (
                            <>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                Atualizar Plano
                            </>
                        )}
                    </button>
                ) : (
                    <div className="text-right">
                        <button disabled className="inline-flex items-center gap-2 px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 text-sm font-semibold rounded-lg cursor-not-allowed">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            Plano Protegido
                        </button>
                        <p className="text-[10px] text-slate-400 mt-1">Disponível em {new Date(next_update_at).toLocaleDateString()} ({days_until_update} dias)</p>
                    </div>
                )}
            </div>

            {/* Warning Message */}
            {confidence?.warning && (
                <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 flex items-start gap-3">
                    <svg className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <div>
                        <p className="text-sm font-semibold text-amber-800 dark:text-amber-300">{confidence.warning}</p>
                        <p className="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Questões respondidas: {confidence.total} / 100 mínimas</p>
                    </div>
                </div>
            )}

            {/* Overview */}
            {plan?.plan_json?.overview && (
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 shadow-sm">
                    <p className="text-slate-700 dark:text-slate-300 leading-relaxed italic">"{plan.plan_json.overview}"</p>
                </div>
            )}

            {/* Diagnóstico */}
            <div className="bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        <h3 className="font-bold text-slate-800 dark:text-slate-100">Diagnóstico Atual</h3>
                    </div>
                    <span className={`text-[10px] uppercase font-bold px-2 py-1 rounded-full ${confidence?.level === 'high' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>{confidence?.label}</span>
                </div>
                <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {diagnostics.subjects.map((s: any) => (
                            <div key={s.subject} className="p-4 rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40">
                                <div className="flex justify-between items-start mb-2">
                                    <span className="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight">{s.label}</span>
                                    <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded leading-none ${s.status === 'bom' ? 'bg-green-100 text-green-600' : s.status === 'critico' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'}`}>
                                        {s.status.toUpperCase()}
                                    </span>
                                </div>
                                <div className="flex items-baseline gap-1 mb-2">
                                    <span className="text-2xl font-black text-slate-800 dark:text-slate-100">{s.accuracy}%</span>
                                    <span className="text-[10px] text-slate-400">meta {s.target}%</span>
                                </div>
                                <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mb-2 overflow-hidden">
                                    <div className={`h-full rounded-full transition-all duration-500 ${s.accuracy >= s.target ? 'bg-green-500' : 'bg-blue-500'}`} style={{ width: `${s.accuracy}%` }}></div>
                                </div>
                                <p className="text-[10px] text-slate-500">{s.attempts} questões respondidas</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Pontos Fracos e Fortes */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center">
                        <svg className="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 className="font-bold text-slate-900 dark:text-slate-100 text-base">Pontos Fracos e Fortes</h3>
                        <p className="text-[10px] text-slate-500">Mínimo 5 questões por tópico para classificação</p>
                    </div>
                </div>

                <div className="p-6 grid md:grid-cols-2 gap-6">
                    {/* Weak */}
                    <div>
                        <h4 className="text-[10px] font-bold text-red-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-red-500"></span>
                            Pontos Fracos <span className="text-slate-400 font-normal lowercase">(abaixo de 60%)</span>
                        </h4>
                        {weak_strong.weak.length > 0 ? weak_strong.weak.map((item: any, i: number) => (
                            <div key={i} className="mb-3 p-3 bg-red-50/50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-xl">
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <p className="text-xs font-bold text-slate-800 dark:text-slate-200">{item.topic}</p>
                                        <p className="text-[10px] text-slate-500">{item.subject}</p>
                                    </div>
                                    <span className="text-xs font-black text-red-600">{item.accuracy}%</span>
                                </div>
                                <div className="w-full bg-red-100 dark:bg-red-900/30 rounded-full h-1">
                                    <div className="bg-red-500 h-1 rounded-full" style={{ width: `${item.accuracy}%` }}></div>
                                </div>
                            </div>
                        )) : (
                            <p className="text-xs text-slate-400 italic text-center py-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">Dados insuficientes para tópicos fracos.</p>
                        )}
                    </div>

                    {/* Strong */}
                    <div>
                        <h4 className="text-[10px] font-bold text-green-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-green-500"></span>
                            Pontos Fortes <span className="text-slate-400 font-normal lowercase">(acima de 75%)</span>
                        </h4>
                        {weak_strong.strong.length > 0 ? weak_strong.strong.map((item: any, i: number) => (
                            <div key={i} className="mb-3 p-3 bg-green-50/50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/20 rounded-xl">
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <p className="text-xs font-bold text-slate-800 dark:text-slate-200">{item.topic}</p>
                                        <p className="text-[10px] text-slate-500">{item.subject}</p>
                                    </div>
                                    <span className="text-xs font-black text-green-600">{item.accuracy}%</span>
                                </div>
                                <div className="w-full bg-green-100 dark:bg-green-900/30 rounded-full h-1">
                                    <div className="bg-green-500 h-1 rounded-full" style={{ width: `${item.accuracy}%` }}></div>
                                </div>
                            </div>
                        )) : (
                            <p className="text-xs text-slate-400 italic text-center py-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">Nenhum ponto forte consolidado.</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Projection & Strategy Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Projeção */}
                <div className="bg-slate-900 text-white rounded-2xl overflow-hidden shadow-xl p-6 relative">
                    <div className="flex items-center gap-3 mb-6">
                        <div className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-blue-400">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <h3 className="font-bold">Projeção de Desempenho</h3>
                    </div>

                    {!projection.unavailable ? (
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <p className="text-[10px] text-slate-400 uppercase font-bold mb-1">Atual</p>
                                <p className="text-2xl font-black">{projection.current}</p>
                            </div>
                            <div>
                                <p className="text-[10px] text-blue-400 uppercase font-bold mb-1">3 Meses</p>
                                <p className="text-2xl font-black text-blue-400">{projection.three_months}</p>
                            </div>
                            <div className="col-span-2 sm:col-span-1 border-t sm:border-t-0 sm:border-l border-white/10 pt-4 sm:pt-0 sm:pl-4">
                                <p className="text-[10px] text-emerald-400 uppercase font-bold mb-1">+1h/dia</p>
                                <p className="text-2xl font-black text-emerald-400">{projection.plus_one_hour || '---'}</p>
                            </div>
                        </div>
                    ) : (
                        <p className="text-xs text-slate-400 italic">{projection.reason}</p>
                    )}
                </div>

                {/* Estratégia de Prova */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 shadow-sm">
                    <h3 className="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 mb-4">
                        <svg className="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        Sugestão de Ordem de Prova
                    </h3>
                    <div className="space-y-2">
                        {exam_strategy.suggested_order.map((s: any, i: number) => (
                            <div key={s.subject} className="flex items-center gap-3 p-2 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <span className="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center">{i + 1}</span>
                                <span className="text-xs font-semibold flex-1 truncate">{s.label}</span>
                                <span className="text-[10px] font-bold text-blue-600">{s.accuracy}%</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Schedule */}
            <div className="bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                    <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <h3 className="font-bold">Cronograma Semanal</h3>
                </div>
                <div className="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {Object.entries(plan.plan_json.weekly_schedule || {}).map(([day, tasks]: [string, any]) => (
                        <div key={day} className="bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 p-4 rounded-xl">
                            <h4 className="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider">{day}</h4>
                            <ul className="space-y-2">
                                {tasks.map((task: string, i: number) => (
                                    <li key={i} className="text-xs text-slate-600 dark:text-slate-400 flex gap-2">
                                        <span className="w-1 h-1 bg-blue-500 rounded-full mt-1.5 flex-shrink-0"></span>
                                        {task}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>

            {/* Recommendations */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 shadow-sm">
                <h3 className="font-bold flex items-center gap-2 mb-6">
                    <svg className="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Ações Prioritárias da Semana
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {recommendations.map((rec: any) => (
                        <div key={rec.priority} className="flex gap-4 p-4 border border-slate-100 dark:border-slate-800 bg-teal-50/20 dark:bg-teal-900/10 rounded-xl">
                            <span className="text-lg font-black text-teal-600/30">#{rec.priority}</span>
                            <div>
                                <p className="text-sm font-bold text-slate-800 dark:text-slate-100">{rec.title}</p>
                                <p className="text-xs text-slate-600 dark:text-slate-400">{rec.detail}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Methodology & Motivation */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-white/5 border border-slate-200 dark:border-slate-700 rounded-2xl p-8">
                <div>
                    <h4 className="text-[10px] uppercase font-bold text-slate-400 mb-2">Sua Evolução</h4>
                    <p className="text-sm font-medium italic text-slate-700 dark:text-slate-300">"{motivation}"</p>
                </div>
                <div className="border-l border-slate-100 dark:border-slate-800 pl-6">
                    <h4 className="text-[10px] uppercase font-bold text-slate-400 mb-2">Metodologia</h4>
                    <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{plan.plan_json.methodology || 'Foco em prática deliberada e revisão ativa.'}</p>
                </div>
            </div>
        </div>
    );
}
