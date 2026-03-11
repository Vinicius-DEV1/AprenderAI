import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import { useDashboard } from '../../hooks/useDashboard';
import StudyPlanEmpty from './StudyPlanEmpty';
import StudyPlanWizard from './StudyPlanWizard';

const getStudyPlanData = async () => {
    try {
        const { data } = await api.get('/api/v1/study-plan', {
            validateStatus: (status) => status < 500
        });
        return data || { view_state: 'empty', code: 'EMPTY_RESPONSE' };
    } catch (e) {
        return { view_state: 'empty', code: 'FETCH_ERROR' };
    }
};

// ── Helpers ────────────────────────────────────────────────────────────────
const dayIcons: Record<string, string> = {
    'segunda': '📘', 'terça': '📗', 'terca': '📗', 'quarta': '📙',
    'quinta': '📕', 'sexta': '📓', 'sábado': '📔', 'sabado': '📔', 'domingo': '🔄',
};
const getDayIcon = (day: string) => {
    const lower = day.toLowerCase();
    for (const [key, emoji] of Object.entries(dayIcons)) {
        if (lower.includes(key)) return emoji;
    }
    return '📅';
};

const PT_WEEKDAY_NAMES = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
// alias without accents for matching
const PT_WEEKDAY_ALIASES: Record<string, string[]> = {
    'domingo': ['domingo', 'sunday', 'dom'],
    'segunda': ['segunda', 'monday', 'seg', 'segunda-feira'],
    'terca': ['terca', 'terça', 'tuesday', 'ter', 'terca-feira', 'terça-feira'],
    'quarta': ['quarta', 'wednesday', 'qua', 'quarta-feira'],
    'quinta': ['quinta', 'thursday', 'qui', 'quinta-feira'],
    'sexta': ['sexta', 'friday', 'sex', 'sexta-feira'],
    'sabado': ['sabado', 'sábado', 'saturday', 'sab'],
};

function normalizeDay(s: string): string {
    return s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
}

// Derive "Missão do Dia" from today's schedule entry
function deriveTodaysMission(plan: any) {
    const schedule = plan?.plan_json?.weekly_schedule?.days || plan?.plan_json?.weekly_schedule || {};
    const today = new Date();
    const todayBaseKey = PT_WEEKDAY_NAMES[today.getDay()]; // e.g. 'terca'
    const todayAliases = PT_WEEKDAY_ALIASES[todayBaseKey] || [todayBaseKey];

    const keys = Object.keys(schedule);
    // Try to find a key that matches any of today's aliases
    let todayKey = keys.find(k => {
        const norm = normalizeDay(k);
        return todayAliases.some(alias => norm.startsWith(alias) || alias.startsWith(norm));
    });
    // Fallback to first day so there's always content
    if (!todayKey) todayKey = keys[0];

    const dayLabel = todayKey || 'Hoje';
    const tasks = todayKey ? schedule[todayKey] : [];
    return { dayLabel, tasks: Array.isArray(tasks) ? tasks.slice(0, 3) : [] };
}

// Derive "Prioridade da Semana" from weaknesses
function deriveWeekPriority(weak_strong: any, recommendations: any[]) {
    const topWeak = weak_strong?.weak?.[0];
    if (topWeak) {
        return {
            subject: topWeak.topic,
            subjectLabel: topWeak.subject,
            reason: `Apenas ${topWeak.accuracy}% de acerto — abaixo do mínimo. Foco aqui impacta diretamente sua nota.`,
            impact: 'Alto',
        };
    }
    const topRec = recommendations?.[0];
    if (topRec) {
        return {
            subject: topRec.title,
            subjectLabel: 'Ação Estratégica',
            reason: topRec.detail,
            impact: 'Alto',
        };
    }
    return null;
}

// ── Sub-components ─────────────────────────────────────────────────────────

function MissaoDoDia({ plan }: { plan: any }) {
    const { dayLabel, tasks } = deriveTodaysMission(plan);
    if (!tasks.length) return null;

    return (
        <div 
            className="text-white rounded-3xl p-7 shadow-2xl shadow-blue-500/30 relative overflow-hidden"
            style={{ backgroundImage: 'radial-gradient(circle at top right, rgba(255,255,255,0.08), transparent), linear-gradient(135deg, #142D50 0%, #2A4780 100%)' }}
        >
            <div className="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -mr-12 -mt-12 pointer-events-none" />
            <div className="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full -ml-8 -mb-8 pointer-events-none" />
            <div className="relative z-10">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <p className="text-[10px] uppercase font-black tracking-[0.2em] text-blue-200 mb-1">Missão de Hoje</p>
                        <h3 className="text-xl font-black capitalize">{dayLabel}</h3>
                    </div>
                    <div className="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-2xl backdrop-blur-sm">🎯</div>
                </div>
                <div className="space-y-3">
                    {tasks.map((task: any, i: number) => (
                        <div key={i} className="flex items-start gap-3 bg-white/10 hover:bg-white/15 transition-colors rounded-2xl p-4 backdrop-blur-sm">
                            <div className="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-xs font-black shrink-0 mt-0.5">{i + 1}</div>
                            <div className="min-w-0">
                                <p className="font-bold text-sm leading-tight">
                                    {typeof task === 'object' ? (task.activity || task.topic || task.subject) : task}
                                </p>
                                {typeof task === 'object' && (
                                    <div className="flex flex-wrap gap-2 mt-1.5">
                                        {task.time && (
                                            <span className="text-[10px] font-bold bg-white/15 px-2 py-0.5 rounded-full">{task.time}</span>
                                        )}
                                        {task.goal && (
                                            <span className="text-[10px] font-bold bg-emerald-400/20 text-emerald-200 px-2 py-0.5 rounded-full">Meta: {task.goal}</span>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function PrioridadeDaSemana({ weak_strong, recommendations }: { weak_strong: any; recommendations: any[] }) {
    const priority = deriveWeekPriority(weak_strong, recommendations);
    if (!priority) return null;

    return (
        <div className="bg-white dark:bg-slate-900 border border-amber-100 dark:border-amber-900/30 rounded-3xl p-6 shadow-sm relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-amber-50 dark:bg-amber-900/10 rounded-full -mr-8 -mt-8 pointer-events-none" />
            <div className="relative z-10">
                <div className="flex items-center gap-3 mb-4">
                    <div className="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-xl">⚡</div>
                    <div>
                        <p className="text-[10px] uppercase font-black tracking-wider text-amber-600 dark:text-amber-400">Prioridade da Semana</p>
                        <h3 className="font-bold text-slate-900 dark:text-white text-sm">Foco estratégico atual</h3>
                    </div>
                    <span className="ml-auto text-[10px] font-black px-2.5 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 uppercase tracking-wide">
                        Impacto {priority.impact}
                    </span>
                </div>
                <div className="bg-amber-50 dark:bg-amber-900/10 rounded-2xl p-4 border border-amber-100 dark:border-amber-900/20">
                    <p className="text-xs font-black text-amber-700 dark:text-amber-400 uppercase tracking-wide mb-1">{priority.subjectLabel}</p>
                    <p className="text-base font-extrabold text-slate-900 dark:text-white mb-2">{priority.subject}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{priority.reason}</p>
                </div>
            </div>
        </div>
    );
}

export default function StudyPlanDashboard() {
    const queryClient = useQueryClient();
    const { data: dashboardData, isLoading: isDashLoading } = useDashboard();

    const { data, isLoading } = useQuery({
        queryKey: ['studyPlanDashboard'],
        queryFn: getStudyPlanData,
        refetchOnWindowFocus: false,
        retry: false,
    });

    const updateMutation = useMutation({
        mutationFn: () => api.post('/api/v1/study-plan/update'),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['studyPlanDashboard'] });
        }
    });

    const isAnyLoading = isLoading || isDashLoading;

    if (isAnyLoading) {
        return <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>;
    }

    const answeredCount = dashboardData?.stats?.total_questions_answered ?? 0;
    const hasPrereq = answeredCount >= 50 || (dashboardData?.stats?.total_simulations ?? 0) > 0;
    const isInsufficient = data?.code === 'INSUFFICIENT_DATA' || data?.view_state === 'empty';

    if (data?.code === 'PAYWALL' || data?.view_state === 'paywall') {
        return (
            <div className="py-20 text-center px-4">
                <div className="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/10 dark:to-indigo-900/10 border border-purple-100 dark:border-purple-800 p-10 rounded-[2.5rem] max-w-2xl mx-auto shadow-2xl relative overflow-hidden">
                    <div className="absolute top-0 right-0 p-4 opacity-10">
                        <svg className="w-24 h-24 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    </div>
                    <div className="relative z-10 text-center">
                        <div className="w-20 h-20 bg-blue-600 text-white rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-blue-500/40 transform -rotate-3 hover:rotate-0 transition-transform">
                            <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                        </div>
                        <h2 className="text-3xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">Evolua para o Plano Plus 🚀</h2>
                        <p className="text-slate-600 dark:text-slate-400 mb-8 text-lg leading-relaxed">O <strong>Plano de Estudos Premium</strong> do Xavier analisa suas fraquezas reais e cria um cronograma dinâmico de alta performance.</p>
                        <button onClick={() => window.location.href = '/planos'} className="px-10 py-4 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-2xl transition-all shadow-xl shadow-blue-500/30 active:scale-95">QUERO SER PLUS</button>
                    </div>
                </div>
            </div>
        );
    }

    if (data?.view_state === 'dashboard') {
        // Continue to render main dashboard below
    } else if (isInsufficient) {
        return <StudyPlanEmpty />;
    } else if (data?.view_state === 'wizard' || hasPrereq) {
        return <StudyPlanWizard />;
    } else {
        return <StudyPlanEmpty />;
    }

    let { confidence, diagnostics, projection, weak_strong, recommendations, exam_strategy, plan, can_update, next_update_at, days_until_update, motivation } = data;

    if (motivation && (motivation.includes('precisão estatística') || motivation.includes('dados insuficientes'))) {
        motivation = "O Xavier está calibrando suas métricas. Continue resolvendo questões para uma análise precisa de sua melhor área.";
    }

    return (
        <div className="space-y-6 pb-24 mt-5 pt-5">

            {/* ── 1. Header ────────────────────────────────────────────────── */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="font-extrabold text-3xl text-slate-900 dark:text-white tracking-tight">Plano de Estudos</h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Diagnóstico personalizado baseado no seu desempenho real</p>
                </div>
            </div>

            {/* ── 2. Mission of the Day + Weekly Priority (side by side on lg) ─ */}
            <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">
                <div className="lg:col-span-3">
                    <MissaoDoDia plan={plan} />
                </div>
                <div className="lg:col-span-2">
                    <PrioridadeDaSemana weak_strong={weak_strong} recommendations={recommendations} />
                </div>
            </div>

            {/* ── 3. Xavier Analysis ───────────────────────────────────────── */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-7 shadow-sm">
                <h4 className="text-[10px] uppercase font-black text-blue-600 mb-3 tracking-[0.2em] flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                    Análise do Xavier
                </h4>
                <p className="text-slate-700 dark:text-slate-200 leading-relaxed font-medium text-base">
                    {plan?.plan_json?.diagnostic_summary || plan?.plan_json?.overview || 'Bem-vindo ao seu plano premium! Estou analisando seu desempenho para otimizar sua jornada.'}
                </p>
                {motivation && (
                    <div className="mt-4 p-4 bg-blue-50/70 dark:bg-blue-900/20 rounded-2xl border-l-4 border-blue-500 flex items-start gap-3">
                        <span className="text-lg shrink-0">💡</span>
                        <p className="text-sm font-semibold text-blue-700 dark:text-blue-300">{motivation}</p>
                    </div>
                )}
            </div>

            {/* ── 4. Weekly Schedule ───────────────────────────────────────── */}
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

                <div className="p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {Object.entries(plan?.plan_json?.weekly_schedule?.days || plan?.plan_json?.weekly_schedule || {}).map(([day, tasks]: [string, any]) => (
                        <div key={day} className="bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
                            {/* Day Header */}
                            <div className="px-4 py-3 bg-white dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700/50 flex items-center gap-2">
                                <span className="text-lg">{getDayIcon(day)}</span>
                                <h4 className="text-sm font-black text-slate-700 dark:text-slate-200 capitalize">{day}</h4>
                            </div>

                            {/* Tasks */}
                            <div className="p-3 space-y-2">
                                {Array.isArray(tasks) ? tasks.map((task: any, i: number) => (
                                    <div key={i} className="bg-white dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700/50 hover:border-blue-200 dark:hover:border-blue-700/40 transition-colors">
                                        {typeof task === 'object' ? (
                                            <>
                                                <div className="flex items-center gap-2 mb-1.5">
                                                    {task.time && (
                                                        <span className="text-[9px] font-black text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded shrink-0">{task.time}</span>
                                                    )}
                                                    <span className="text-xs font-extrabold text-slate-800 dark:text-slate-100 leading-tight truncate">
                                                        {task.activity || task.topic}
                                                    </span>
                                                </div>
                                                {task.subject && (
                                                    <p className="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-1">{task.subject}</p>
                                                )}
                                                {task.reason && (
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{task.reason}</p>
                                                )}
                                                <div className="flex flex-wrap gap-1.5 mt-2">
                                                    {task.method && (
                                                        <span className="text-[9px] font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-full uppercase">{task.method}</span>
                                                    )}
                                                    {task.goal && (
                                                        <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded-full uppercase">✓ {task.goal}</span>
                                                    )}
                                                </div>
                                            </>
                                        ) : (
                                            <p className="text-sm text-slate-600 dark:text-slate-400">{typeof task === 'string' ? task : '-'}</p>
                                        )}
                                    </div>
                                )) : (
                                    <div className="p-4 text-center bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-900/20">
                                        <p className="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">🛋️ Descanso</p>
                                        <p className="text-[10px] text-blue-400 mt-1">{typeof tasks === 'string' ? tasks : 'Recarregue as energias.'}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── 5. Priority Actions (single, not duplicated) ──────────────── */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-7 shadow-sm">
                <div className="flex items-center gap-3 mb-6">
                    <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                        <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h3 className="font-bold text-lg text-slate-900 dark:text-white">Ações Prioritárias da Semana</h3>
                        <p className="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Complementa seu cronograma</p>
                    </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {recommendations.map((rec: any) => (
                        <div key={rec.priority} className="flex gap-4 p-5 border border-emerald-50 dark:border-emerald-900/10 bg-emerald-50/30 dark:bg-emerald-900/5 rounded-2xl group hover:scale-[1.01] transition-transform">
                            <span className="text-3xl font-black text-emerald-600/20 group-hover:text-emerald-600/30 transition-colors mt-0.5">#{rec.priority}</span>
                            <div>
                                <p className="text-base font-bold text-slate-800 dark:text-slate-100 mb-1">{rec.title}</p>
                                <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed font-medium">{rec.detail}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── 6. Weak & Strong Points ──────────────────────────────────── */}
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
                                <p className="text-sm text-slate-400 italic text-center py-8 bg-slate-50/50 dark:bg-slate-800/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">Continue sua jornada para identificar pontos de melhoria.</p>
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
                                <p className="text-sm text-slate-400 italic text-center py-8 bg-slate-50/50 dark:bg-slate-800/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">Mantenha o ritmo para transformar dedicação em domínio.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* ── 7. Performance Projection + Exam Strategy ─────────────────── */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Projection */}
                <div className="lg:col-span-2 bg-slate-900 text-white rounded-3xl overflow-hidden shadow-2xl p-8 relative group">
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
                                <p className="text-[10px] text-slate-400 uppercase font-black mb-2 tracking-widest">Nota Atual</p>
                                <p className="text-4xl font-black">{projection.current}</p>
                                <p className="text-[10px] text-slate-500 mt-2 font-bold uppercase tracking-tighter">Média últimas simulações</p>
                            </div>
                            <div className="bg-blue-600 rounded-2xl p-5 shadow-lg shadow-blue-500/30 text-center flex flex-col justify-center hover:scale-[1.02] transition-transform">
                                <p className="text-[10px] text-blue-100 uppercase font-black mb-1 tracking-widest">Projeção em 3 Meses</p>
                                <p className="text-4xl font-black">{projection.three_months} <span className="text-base font-bold opacity-80">pontos</span></p>
                                <p className="text-[10px] text-blue-200 mt-2 font-bold uppercase tracking-tighter">
                                    Tendência: {projection.trend_delta >= 0 ? '+' : ''}{projection.trend_delta} pts/período
                                </p>
                                <p className="text-[10px] text-blue-100/80 mt-2 leading-tight">
                                    Com base no seu ritmo atual de estudo e desempenho recente em simulados.
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
                            <p className="text-sm text-slate-400 font-medium italic">O Xavier está calibrando suas métricas. Continue resolvendo questões para uma projeção precisa.</p>
                        </div>
                    )}
                </div>

                {/* Exam Strategy */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-6 shadow-sm flex flex-col">
                    <div className="flex items-center gap-3 mb-6">
                        <div className="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
                            <svg className="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-slate-900 dark:text-white">Ordem na Prova</h3>
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

            {/* ── 8. Diagnostic ───────────────────────────────────────────── */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl overflow-hidden shadow-sm">
                <div className="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shadow-sm">
                            <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        <div>
                            <h3 className="font-bold text-slate-900 dark:text-white">Diagnóstico por Matéria</h3>
                            <p className="text-[10px] text-slate-500 uppercase font-bold tracking-tight">Atualizado em tempo real</p>
                        </div>
                    </div>
                    <span className={`text-[10px] uppercase font-black px-3 py-1.5 rounded-full tracking-wider ${confidence?.level === 'high' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                        {confidence?.label}
                    </span>
                </div>
                {confidence?.level !== 'high' && (
                    <div className="px-6 py-4 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100/50 dark:border-amber-900/20">
                        <p className="text-sm font-medium text-amber-800 dark:text-amber-300">
                            Ainda não há dados suficientes para gerar análises precisas. Continue resolvendo questões para melhorar a personalização do plano.
                        </p>
                    </div>
                )}
                <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
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
                                    <div className={`mt-3 p-3 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-100/50 dark:border-slate-700/50 shadow-sm`}>
                                        <p className={`text-[10px] leading-relaxed font-semibold italic ${s.attempts < 20 ? 'text-slate-500' : (s.correct === 0 ? 'text-red-500' : 'text-blue-600')}`}>
                                            {s.insight}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Meta Metrics */}
                    <div className="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
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

            {/* ── 9. Update Window ────────────────────────────────────────── */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-8 shadow-sm overflow-hidden relative">
                <div className="flex flex-col md:flex-row items-center justify-between gap-8 relative z-10">
                    <div className="max-w-xl">
                        <div className="flex items-center gap-3 mb-4">
                            <div className={`w-3 h-3 rounded-full ${can_update ? 'bg-green-500 animate-pulse shadow-green-500' : 'bg-slate-300'}`}></div>
                            <h3 className="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-sm">Janela de Atualização</h3>
                        </div>
                        {can_update ? (
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                                Seu diagnóstico apontou mudanças significativas no seu perfil. <span className="text-blue-600 font-black">Uma nova versão do cronograma está pronta para ser gerada.</span>
                            </p>
                        ) : (
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400 leading-relaxed">
                                Seu plano semanal atual permanece fixo até <strong>{new Date(next_update_at).toLocaleDateString('pt-BR')}</strong> para maximizar a retenção. Faltam <strong>{days_until_update} {days_until_update === 1 ? 'dia' : 'dias'}</strong> para a próxima janela de otimização.
                            </p>
                        )}
                    </div>
                    <button
                        onClick={() => updateMutation.mutate()}
                        disabled={!can_update || updateMutation.isPending}
                        className={`w-full md:w-auto px-8 py-4 font-black rounded-2xl transition-all shadow-xl flex items-center justify-center gap-3 active:scale-95 disabled:cursor-not-allowed ${
                            can_update
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-500/25'
                                : 'bg-slate-300 dark:bg-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed grayscale shadow-none'
                        }`}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        {updateMutation.isPending ? 'PROCESSANDO...' : (can_update ? 'ATUALIZAR PLANO AGORA' : `BLOQUEADO (${days_until_update} DIAS)`)}
                    </button>
                </div>
            </div>

            {/* ── 10. Footer ──────────────────────────────────────────────── */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 bg-slate-900 text-white rounded-[40px] p-10 shadow-2xl relative overflow-hidden">
                <div className="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none">
                    <div className="absolute top-[-10%] right-[-10%] w-64 h-64 bg-blue-500 rounded-full blur-[100px]"></div>
                    <div className="absolute bottom-[-10%] left-[-10%] w-64 h-64 bg-indigo-500 rounded-full blur-[100px]"></div>
                </div>
                <div className="relative z-10">
                    <h4 className="text-[10px] uppercase font-black text-blue-400 mb-4 tracking-[0.2em]">Sua Evolução</h4>
                    <p className="text-xl font-bold leading-relaxed italic text-slate-100">"{motivation}"</p>
                </div>
                <div className="relative z-10 border-t md:border-t-0 md:border-l border-white/10 pt-8 md:pt-0 md:pl-10">
                    <h4 className="text-[10px] uppercase font-black text-blue-400 mb-4 tracking-[0.2em]">Estratégia Mestres</h4>
                    <p className="text-base text-slate-300 leading-relaxed font-medium">
                        {plan.plan_json?.methodology || 'Foco absoluto em prática deliberada, análise de erros e revisão espaçada baseada em dados reais de simulação.'}
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
