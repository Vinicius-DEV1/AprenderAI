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

    const dayIcons: Record<string, string> = {
        'segunda': '📘',
        'terça': '📗',
        'terca': '📗',
        'quarta': '📙',
        'quinta': '📕',
        'sexta': '📓',
        'sábado': '📔',
        'sabado': '📔',
        'domingo': '🔄',
    };

    const getDayIcon = (day: string) => {
        const lower = day.toLowerCase();
        for (const [key, emoji] of Object.entries(dayIcons)) {
            if (lower.includes(key)) return emoji;
        }
        return '📅';
    };

    return (
        <div className="space-y-8 pb-24">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="font-extrabold text-3xl text-slate-900 dark:text-white tracking-tight">
                        Plano de Estudos
                    </h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Diagnóstico personalizado baseado no seu desempenho real
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    {can_update ? (
                        <button
                            onClick={() => updateMutation.mutate()}
                            disabled={updateMutation.isPending}
                            className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95 disabled:opacity-50"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            {updateMutation.isPending ? 'Atualizando...' : 'Atualizar Plano'}
                        </button>
                    ) : (
                        <div className="text-right">
                            <button disabled className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-sm font-bold rounded-xl cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                Plano Protegido
                            </button>
                            <p className="text-[10px] font-bold text-slate-400 mt-1.5 uppercase tracking-widest">
                                Disponível em {new Date(next_update_at).toLocaleDateString()} ({days_until_update} {days_until_update === 1 ? 'dia' : 'dias'})
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Low confidence warning */}
            {confidence?.warning && (
                <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-5 flex items-start gap-4 shadow-sm">
                    <div className="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                        <svg className="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div>
                        <p className="text-sm font-bold text-amber-900 dark:text-amber-200">{confidence.warning}</p>
                        <p className="text-xs text-amber-700/70 dark:text-amber-400/70 mt-0.5 font-medium">Questões respondidas: <span className="font-bold">{confidence.total}</span> / 100 mínimas</p>
                    </div>
                </div>
            )}

            {/* Introductory Message */}
            {plan?.plan_json?.overview && (
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-8 shadow-xl shadow-slate-200/50 dark:shadow-none">
                    <p className="text-slate-800 dark:text-slate-200 leading-relaxed text-lg font-medium italic">
                        "{plan.plan_json.overview.startsWith('Olá') ? plan.plan_json.overview : 'Olá! ' + plan.plan_json.overview}"
                    </p>
                    {motivation && (
                        <p className="mt-4 text-sm font-bold text-blue-600 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-900/20 p-4 rounded-2xl border-l-4 border-blue-500">
                            {motivation}
                        </p>
                    )}
                </div>
            )}

            {/* Diagnóstico */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl overflow-hidden shadow-sm">
                <div className="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shadow-sm">
                            <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-slate-900 dark:text-white">Diagnóstico Atual</h3>
                            <p className="text-[10px] text-slate-500 uppercase font-bold tracking-tight">Atualizado em tempo real</p>
                        </div>
                    </div>
                    <span className={`text-[10px] uppercase font-black px-3 py-1.5 rounded-full tracking-wider ${confidence?.level === 'high' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                        {confidence?.label}
                    </span>
                </div>
                <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                        {diagnostics.subjects.map((s: any) => {
                            const statusMap: Record<string, string> = {
                                'bom': 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                'critico': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                'atencao': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                'estavel': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'observacao': 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
                            };
                            return (
                                <div key={s.subject} className="p-5 rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/20 hover:border-slate-200 dark:hover:border-slate-700 transition-all group">
                                    <div className="flex justify-between items-start mb-3">
                                        <span className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase truncate pr-2">{s.label}</span>
                                        <span className={`text-[10px] font-black px-2 py-0.5 rounded-md leading-none uppercase ${statusMap[s.status] || statusMap.observacao}`}>
                                            {s.status}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline gap-2 mb-3">
                                        <span className="text-3xl font-black text-slate-900 dark:text-white">{s.accuracy}%</span>
                                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">meta {s.target}%</span>
                                    </div>
                                    <div className="w-full bg-slate-200 dark:bg-slate-700/50 rounded-full h-2 mb-3 overflow-hidden shadow-inner">
                                        <div className={`h-full rounded-full transition-all duration-700 shadow-sm ${s.accuracy >= s.target ? 'bg-green-500' : (s.accuracy < 50 ? 'bg-red-500' : 'bg-blue-500')}`} style={{ width: `${Math.min(100, s.accuracy)}%` }}></div>
                                    </div>

                                    {/* Diagnostic Insight Logic Styling */}
                                    <div className={`mt-4 p-3 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100/50 dark:border-slate-700/50 shadow-sm ${s.attempts < 20 ? 'border-slate-200' : (s.correct === 0 ? 'border-red-200 bg-red-50/10' : 'border-blue-200 bg-blue-50/10')}`}>
                                        <p className={`text-[10px] leading-relaxed font-bold italic ${s.attempts < 20 ? 'text-slate-500' : (s.correct === 0 ? 'text-red-500' : 'text-blue-600')}`}>
                                            {s.insight}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Meta Metrics Row */}
                    <div className="mt-8 grid grid-cols-2 lg:grid-cols-4 gap-4">
                        {[
                            { label: 'Nota Média Simulados', value: diagnostics.sim_avg, color: 'text-slate-900', icon: '🎯' },
                            { label: 'Média Redações', value: diagnostics.essay_avg, suffix: '/1000', color: 'text-blue-600', icon: '✍️' },
                            { label: 'Acerto (7 dias)', value: diagnostics.perf_7days, suffix: '%', color: 'text-emerald-600', icon: '📈' },
                            { label: 'Acerto (14 dias)', value: diagnostics.perf_14days, suffix: '%', color: 'text-indigo-600', icon: '📅' },
                        ].map((m, i) => m.value && (
                            <div key={i} className="bg-slate-50 dark:bg-slate-800/40 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 text-center">
                                <div className="text-xl mb-1">{m.icon}</div>
                                <p className={`text-2xl font-black ${m.color} dark:text-white`}>{m.value}{m.suffix}</p>
                                <p className="text-[10px] font-bold text-slate-500 uppercase mt-1 tracking-wider">{m.label}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>


            {/* Pontos Fracos e Fortes */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/30">
                    <div className="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/40 flex items-center justify-center">
                        <svg className="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 className="font-bold text-slate-900 dark:text-slate-100 text-base">Pontos Fracos e Fortes</h3>
                        <p className="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Mínimo 5 questões por tópico para classificação</p>
                    </div>
                </div>

                <div className="p-6 grid md:grid-cols-2 gap-8">
                    {/* Weak */}
                    <div>
                        <h4 className="text-xs font-black text-red-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                            Pontos Fracos <span className="text-slate-400 font-normal lowercase tracking-normal">(abaixo de 60%)</span>
                        </h4>
                        <div className="space-y-3">
                            {weak_strong.weak.length > 0 ? weak_strong.weak.map((item: any, i: number) => (
                                <div key={i} className="p-4 bg-red-50/50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-2xl">
                                    <div className="flex justify-between items-start mb-2">
                                        <div>
                                            <p className="text-sm font-bold text-slate-800 dark:text-slate-200">{item.topic}</p>
                                            <p className="text-[10px] text-slate-500 font-medium uppercase tracking-tighter">{item.subject}</p>
                                        </div>
                                        <span className="text-sm font-black text-red-600">{item.accuracy}%</span>
                                    </div>
                                    <div className="w-full bg-red-100 dark:bg-red-900/30 rounded-full h-1.5 overflow-hidden">
                                        <div className="bg-red-500 h-full rounded-full transition-all" style={{ width: `${item.accuracy}%` }}></div>
                                    </div>
                                    <p className="text-[10px] text-red-400/80 mt-1.5 font-bold uppercase">{item.attempts} questões respondidas</p>
                                </div>
                            )) : (
                                <p className="text-sm text-slate-400 italic text-center py-8 bg-slate-50/50 dark:bg-slate-800/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">Dados insuficientes para tópicos fracos.</p>
                            )}
                        </div>
                    </div>

                    {/* Strong */}
                    <div>
                        <h4 className="text-xs font-black text-green-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            Pontos Fortes <span className="text-slate-400 font-normal lowercase tracking-normal">(acima de 75%)</span>
                        </h4>
                        <div className="space-y-3">
                            {weak_strong.strong.length > 0 ? weak_strong.strong.map((item: any, i: number) => (
                                <div key={i} className="p-4 bg-green-50/50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/20 rounded-2xl">
                                    <div className="flex justify-between items-start mb-2">
                                        <div>
                                            <p className="text-sm font-bold text-slate-800 dark:text-slate-200">{item.topic}</p>
                                            <p className="text-[10px] text-slate-500 font-medium uppercase tracking-tighter">{item.subject}</p>
                                        </div>
                                        <span className="text-sm font-black text-green-600">{item.accuracy}%</span>
                                    </div>
                                    <div className="w-full bg-green-100 dark:bg-green-900/30 rounded-full h-1.5 overflow-hidden">
                                        <div className="bg-green-500 h-full rounded-full transition-all" style={{ width: `${item.accuracy}%` }}></div>
                                    </div>
                                    <p className="text-[10px] text-green-400/80 mt-1.5 font-bold uppercase">{item.attempts} questões respondidas</p>
                                </div>
                            )) : (
                                <p className="text-sm text-slate-400 italic text-center py-8 bg-slate-50/50 dark:bg-slate-800/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">Continue praticando para consolidar forças.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Projection & Strategy Row */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Projeção */}
                <div className="lg:col-span-2 bg-slate-900 text-white rounded-3xl overflow-hidden shadow-2xl p-8 relative group">
                    {/* Premium Glow Effect */}
                    <div className="absolute -top-24 -right-24 w-64 h-64 bg-blue-600/20 rounded-full blur-3xl group-hover:bg-blue-500/30 transition-all duration-700"></div>

                    <div className="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity">
                        <svg className="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    </div>

                    <div className="flex items-center gap-3 mb-8 relative z-10">
                        <div className="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-blue-400 shadow-inner">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-lg">Projeção de Desempenho</h3>
                            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Baseada em tendência real das últimas 2 semanas</p>
                        </div>
                    </div>

                    {!projection.unavailable ? (
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 relative z-10">
                            <div className="bg-white/5 rounded-2xl p-5 border border-white/5 text-center hover:bg-white/10 transition-colors">
                                <p className="text-[10px] text-slate-400 uppercase font-black mb-2 tracking-widest">Nota Estimada Atual</p>
                                <p className="text-4xl font-black">{projection.current}</p>
                                <p className="text-[10px] text-slate-500 mt-2 font-bold uppercase tracking-tighter">Média últimas simulações</p>
                            </div>
                            <div className="bg-blue-600 rounded-2xl p-5 shadow-lg shadow-blue-500/30 text-center hover:scale-[1.02] transition-transform">
                                <p className="text-[10px] text-blue-100 uppercase font-black mb-2 tracking-widest">Projeção 3 Meses</p>
                                <p className="text-4xl font-black">{projection.three_months}</p>
                                <p className="text-[10px] text-blue-200 mt-2 font-bold uppercase tracking-tighter">
                                    Tendência: {projection.trend_delta >= 0 ? '+' : ''}{projection.trend_delta} pts/período
                                </p>
                            </div>
                            <div className="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-5 shadow-lg shadow-emerald-500/30 text-center hover:scale-[1.02] transition-transform">
                                <p className="text-[10px] text-emerald-50 text-center uppercase font-black mb-2 tracking-widest px-1">Se estudar +1h/dia</p>
                                <p className="text-4xl font-black">{projection.plus_one_hour || '---'}</p>
                                <p className="text-[10px] text-emerald-100 mt-2 font-bold uppercase tracking-tighter">Estimativa bônus</p>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-4 bg-white/5 rounded-2xl p-6 border border-white/5 relative z-10">
                            <svg className="w-8 h-8 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p className="text-sm text-slate-400 font-medium italic">{projection.reason}</p>
                        </div>
                    )}
                </div>

                {/* Estratégia de Prova */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-6 shadow-sm flex flex-col">
                    <div className="flex items-center gap-3 mb-6">
                        <div className="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
                            <svg className="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-slate-900 dark:text-white">Ordem Prova</h3>
                            <p className="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Otimize seus acertos</p>
                        </div>
                    </div>

                    <div className="space-y-2 flex-grow">
                        {exam_strategy.suggested_order.map((s: any, i: number) => {
                            const timeLabel = exam_strategy.times?.[s.label] || '45 min';
                            return (
                                <div key={s.subject} className="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                    <span className="w-7 h-7 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-200 text-xs font-black flex items-center justify-center shadow-sm">{i + 1}</span>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{s.label}</p>
                                        <p className="text-[10px] text-slate-400 font-medium">{s.accuracy}% acerto</p>
                                    </div>
                                    <span className="text-[10px] font-black text-slate-400 uppercase">{timeLabel}</span>
                                </div>
                            );
                        })}
                    </div>

                    {exam_strategy.tip && (
                        <div className="mt-4 p-3 bg-violet-50 dark:bg-violet-900/10 rounded-xl border border-violet-100 dark:border-violet-900/20">
                            <p className="text-[11px] text-violet-800 dark:text-violet-300 leading-snug font-medium italic">
                                <span className="font-black">💡 DICA:</span> {exam_strategy.tip}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Schedule */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-slate-900 dark:text-white">Cronograma Semanal</h3>
                            <p className="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Seu roteiro de estudos sugerido</p>
                        </div>
                    </div>
                    {!can_update && (
                        <span className="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-full text-[10px] font-black tracking-widest uppercase">Plano Fixo</span>
                    )}
                </div>
                <div className="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    {Object.entries(plan.plan_json.weekly_schedule || {}).map(([day, tasks]: [string, any]) => (
                        <div key={day} className="bg-slate-50/50 dark:bg-slate-800/20 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <h4 className="text-sm font-black text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2 capitalize">
                                <span className="text-xl">{getDayIcon(day)}</span>
                                {day}
                            </h4>
                            <ul className="space-y-3">
                                {tasks.map((task: string, i: number) => (
                                    <li key={i} className="text-sm text-slate-600 dark:text-slate-400 flex gap-3 leading-snug">
                                        <span className="w-2 h-2 bg-blue-500 rounded-full mt-1.5 shrink-0 shadow-sm animate-pulse shadow-blue-500/50"></span>
                                        <span className="font-medium">{task}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>

            {/* Recommendations */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-8 shadow-sm">
                <div className="flex items-center gap-3 mb-8">
                    <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                        <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h3 className="font-bold text-lg text-slate-900 dark:text-white">Ações Prioritárias da Semana</h3>
                        <p className="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Não altera seu cronograma principal</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {recommendations.map((rec: any) => (
                        <div key={rec.priority} className="flex gap-5 p-5 border border-emerald-50 dark:border-emerald-900/10 bg-emerald-50/30 dark:bg-emerald-900/5 rounded-2xl group hover:scale-[1.01] transition-transform">
                            <span className="text-3xl font-black text-emerald-600/20 group-hover:text-emerald-600/30 transition-colors mt-0.5">#{rec.priority}</span>
                            <div>
                                <p className="text-base font-bold text-slate-800 dark:text-slate-100 mb-1">{rec.title}</p>
                                <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed font-medium">{rec.detail}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Last Update Detail Section */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-8 shadow-sm overflow-hidden relative">
                <div className="flex flex-col md:flex-row items-center justify-between gap-8 relative z-10">
                    <div className="max-w-xl">
                        <div className="flex items-center gap-3 mb-4">
                            <div className={`w-3 h-3 rounded-full ${can_update ? 'bg-green-500 animate-pulse shadow-green-500' : 'bg-slate-300'}`}></div>
                            <h3 className="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-sm">Janela de Atualização</h3>
                        </div>
                        {can_update ? (
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                                Seu diagnóstico apontou mudanças significativas no seu perfil. <span className="text-blue-600 font-black">Uma nova versão do cronograma está pronta para ser gerada.</span> Após clicar em atualizar, o plano será recalculado e fixado pelos próximos 14 dias para garantir consistência.
                            </p>
                        ) : (
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                                Seu plano semanal atual permanece fixo até <strong>{new Date(next_update_at).toLocaleDateString()}</strong> para maximizar a retenção. Faltam <strong>{days_until_update} {days_until_update === 1 ? 'dia' : 'dias'}</strong> para a próxima janela de otimização pela IA. Diagnóstico em tempo real e recomendações continuam ativos.
                            </p>
                        )}
                    </div>
                    {can_update ? (
                        <button
                            onClick={() => updateMutation.mutate()}
                            disabled={updateMutation.isPending}
                            className="w-full md:w-auto px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-2xl transition-all shadow-xl shadow-blue-500/25 active:scale-95 disabled:opacity-50 flex items-center justify-center gap-3"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            {updateMutation.isPending ? 'PROCESSANDO...' : 'ATUALIZAR PLANO AGORA'}
                        </button>
                    ) : (
                        <div className="flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-800/60 w-32 h-32 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-inner">
                            <span className="text-4xl font-black text-slate-800 dark:text-white">{days_until_update}</span>
                            <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">DIAS PARA IA</span>
                        </div>
                    )}
                </div>
            </div>

            {/* Methodology & Motivation Footer */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 bg-slate-900 text-white rounded-[40px] p-10 shadow-2xl relative overflow-hidden">
                <div className="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none">
                    <div className="absolute top-[-10%] right-[-10%] w-64 h-64 bg-blue-500 rounded-full blur-[100px]"></div>
                    <div className="absolute bottom-[-10%] left-[-10%] w-64 h-64 bg-indigo-500 rounded-full blur-[100px]"></div>
                </div>

                <div className="relative z-10">
                    <h4 className="text-[10px] uppercase font-black text-blue-400 mb-4 tracking-[0.2em]">Sua Evolução</h4>
                    <p className="text-xl font-bold leading-relaxed italic text-slate-100">
                        "{motivation}"
                    </p>
                </div>
                <div className="relative z-10 border-t md:border-t-0 md:border-l border-white/10 pt-8 md:pt-0 md:pl-10">
                    <h4 className="text-[10px] uppercase font-black text-blue-400 mb-4 tracking-[0.2em]">Estratégia Mestres</h4>
                    <p className="text-base text-slate-300 leading-relaxed font-medium">
                        {plan.plan_json.methodology || 'Foco absoluto em prática deliberada, análise de erros e revisão espaçada baseada em dados reais de simulação.'}
                    </p>
                    <div className="mt-8 flex items-center gap-4">
                        <div className="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center text-2xl">🤖</div>
                        <div>
                            <p className="text-xs font-black uppercase tracking-widest">Xavier AI System</p>
                            <p className="text-[10px] text-slate-500 font-bold">V 4.2.8 • PREDICTIVE MODEL</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
