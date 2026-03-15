import { useState, useEffect } from 'react';
import {
    getCheckoutOverview,
    getCheckoutFunnel,
    getCheckoutPlansRanking,
    getCheckoutErrors,
    getCheckoutAbandonments,
    getCheckoutAlerts,
    CheckoutOverview,
    FunnelStep,
    PlanRanking,
    CheckoutAlert
} from '../../../api/checkoutAnalytics';
import { toast } from 'sonner';

export default function CheckoutAnalytics() {
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    const [overview, setOverview] = useState<CheckoutOverview | null>(null);
    const [funnel, setFunnel] = useState<FunnelStep[]>([]);
    const [ranking, setRanking] = useState<PlanRanking[]>([]);
    const [errors, setErrors] = useState<any[]>([]);
    const [abandonments, setAbandonments] = useState<any[]>([]);
    const [alerts, setAlerts] = useState<CheckoutAlert[]>([]);

    useEffect(() => {
        let isMounted = true;
        
        const loadDashboard = async () => {
            setLoading(true);
            try {
                // Fetch all data in parallel
                const [
                    overviewRes,
                    funnelRes,
                    rankingRes,
                    errorsRes,
                    abandonmentsRes,
                    alertsRes
                ] = await Promise.all([
                    getCheckoutOverview(days),
                    getCheckoutFunnel(days),
                    getCheckoutPlansRanking(days),
                    getCheckoutErrors(days),
                    getCheckoutAbandonments(days),
                    getCheckoutAlerts()
                ]);

                if (!isMounted) return;

                setOverview(overviewRes.data);
                setFunnel(funnelRes.data.funnel);
                setRanking(rankingRes.data.ranking);
                setErrors(errorsRes.data.data || []);
                setAbandonments(abandonmentsRes.data.data || []);
                setAlerts(alertsRes.data.alerts || []);
                
            } catch (err) {
                console.error('Failed to load checkout analytics', err);
                toast.error('Erro ao carregar dados de observabilidade do checkout.');
            } finally {
                if (isMounted) setLoading(false);
            }
        };

        loadDashboard();

        return () => {
            isMounted = false;
        };
    }, [days]);

    if (loading && !overview) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    // Helper for funnel chart calculation
    const maxFunnelCount = funnel.length > 0 ? Math.max(...funnel.map(f => f.count)) : 1;

    return (
        <div className="space-y-6 animate-in fade-in duration-300">
            {/* Header & Controls */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        Observabilidade do Checkout
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Monitore a saúde financeira, funil de conversão e gargalos técnicos.
                    </p>
                </div>
                <div className="flex bg-white dark:bg-slate-800 rounded-lg p-1 border border-slate-200 dark:border-slate-700">
                    {[7, 14, 30, 90].map(d => (
                        <button
                            key={d}
                            onClick={() => setDays(d)}
                            className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                days === d 
                                ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' 
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50'
                            }`}
                        >
                            {d}d
                        </button>
                    ))}
                </div>
            </div>

            {/* Smart Alerts */}
            {alerts.length > 0 && (
                <div className="grid gap-3">
                    {alerts.map((alert, idx) => (
                        <div 
                            key={idx} 
                            className={`flex items-start gap-4 p-4 rounded-xl border ${
                                alert.severity === 'critical' 
                                ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20 text-red-800 dark:text-red-400' 
                                : 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20 text-amber-800 dark:text-amber-400'
                            }`}
                        >
                            <div className="mt-0.5">
                                {alert.severity === 'critical' ? (
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                ) : (
                                    <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                )}
                            </div>
                            <div>
                                <h4 className="font-bold text-sm tracking-tight">{alert.message}</h4>
                                <p className="text-xs mt-1 opacity-90">{alert.detail}</p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Top KPIs */}
            {overview && (
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <KpiCard label="Visualizações" value={overview.prices_viewed} />
                    <KpiCard label="Intenções (Cliques)" value={overview.purchase_intentions} />
                    <KpiCard label="Checkouts Abertos" value={overview.checkouts_opened} />
                    <KpiCard label="Pagamentos Pagos" value={overview.payments_success} valueColor="text-emerald-600 dark:text-emerald-400" />
                    <KpiCard label="Falhas" value={overview.payments_failed} valueColor="text-red-600 dark:text-red-400" />
                    <KpiCard label="Conversão" value={overview.conversion_rate !== null ? `${overview.conversion_rate}%` : 'N/A'} valueColor="text-blue-600 dark:text-blue-400" />
                </div>
            )}

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                {/* Funnel Chart */}
                <div className="xl:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                    <h3 className="text-sm font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase mb-6">
                        Funil de Conversão
                    </h3>
                    
                    <div className="space-y-4">
                        {funnel.map((step, idx) => {
                            const widthPct = Math.max((step.count / maxFunnelCount) * 100, 2);
                            return (
                                <div key={step.step} className="relative">
                                    <div className="flex justify-between items-end mb-1">
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium text-slate-900 dark:text-white">{step.label}</span>
                                        </div>
                                        <div className="text-right">
                                            <span className="text-lg font-bold text-slate-900 dark:text-white">{step.count}</span>
                                            {step.dropoff_pct !== null && step.dropoff_pct > 0 && (
                                                <span className="ml-2 text-xs font-medium text-red-500 bg-red-50 dark:bg-red-500/10 px-1.5 py-0.5 rounded">
                                                    -{step.dropoff_pct}% Dropoff
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="h-4 w-full bg-slate-100 dark:bg-slate-800 rounded-r-md overflow-hidden flex">
                                        <div 
                                            className="h-full bg-indigo-500 dark:bg-indigo-600 rounded-r-md transition-all duration-1000 ease-out"
                                            style={{ width: `${widthPct}%` }}
                                        ></div>
                                    </div>
                                    
                                    {/* Arrow connecting steps */}
                                    {idx < funnel.length - 1 && (
                                        <div className="absolute -bottom-4 left-4 h-4 border-l-2 border-slate-200 dark:border-slate-700/50"></div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Plans Ranking Target */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm flex flex-col">
                    <h3 className="text-sm font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase mb-4">
                        Planos mais Clicados
                    </h3>
                    <div className="flex-1 overflow-auto">
                        <div className="space-y-3">
                            {ranking.length === 0 ? (
                                <p className="text-slate-500 dark:text-slate-400 text-sm text-center py-4">Nenhum dado de planos.</p>
                            ) : ranking.map(plan => (
                                <div key={plan.plan_id} className="flex justify-between items-center p-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                                    <div>
                                        <h4 className="font-bold text-sm text-slate-900 dark:text-white capitalize">{plan.plan_name} {plan.plan_interval === 'yearly' ? 'Anual' : 'Mensal'}</h4>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Conv: {plan.conversion_rate}% • R$ {plan.plan_price}</p>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-xs font-medium text-slate-500 bg-white dark:bg-slate-800 px-2 py-1 rounded shadow-sm border border-slate-200 dark:border-slate-700">
                                            {plan.clicks} cliques
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {/* Errors List */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-sm font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase">
                            Últimas Falhas Técnicas
                        </h3>
                    </div>
                    {errors.length === 0 ? (
                        <p className="text-slate-500 dark:text-slate-400 font-medium py-8 text-center bg-slate-50 dark:bg-slate-800/20 rounded-lg">Zero falhas detectadas 🎉</p>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800 max-h-[400px] overflow-y-auto pr-2">
                            {errors.map((err, i) => (
                                <div key={i} className="py-3">
                                    <div className="flex justify-between items-start">
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400">
                                            {err.error_type}
                                        </span>
                                        <span className="text-xs text-slate-400">{new Date(err.created_at).toLocaleString('pt-BR')}</span>
                                    </div>
                                    <p className="text-sm text-slate-700 dark:text-slate-300 font-medium mt-1 truncate" title={err.error_message}>
                                        {err.error_message}
                                    </p>
                                    <div className="text-xs text-slate-500 mt-1 flex gap-2">
                                        {err.user?.name && <span>User: {err.user.name.split(' ')[0]}</span>}
                                        {err.payment_method && <span>• {err.payment_method.toUpperCase()}</span>}
                                        {err.checkout_step && <span>• Passo: {err.checkout_step}</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Abandonment List */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-sm font-semibold tracking-wide text-slate-500 dark:text-slate-400 uppercase">
                            Abandonos de Checkout Recentes
                        </h3>
                    </div>
                    {abandonments.length === 0 ? (
                        <p className="text-slate-500 dark:text-slate-400 font-medium py-8 text-center bg-slate-50 dark:bg-slate-800/20 rounded-lg">Sem abandonos recentes</p>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800 max-h-[400px] overflow-y-auto pr-2">
                            {abandonments.map((ab, i) => (
                                <div key={i} className="py-3 flex justify-between items-center">
                                    <div>
                                        <p className="text-sm font-bold text-slate-900 dark:text-white">
                                            {ab.user?.name || ab.user?.email || 'Usuário Desconhecido'}
                                        </p>
                                        <div className="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                                            <span className="bg-slate-100 dark:bg-slate-800 px-1.5 rounded">{ab.plan?.name || `Plano #${ab.plan_id}`}</span>
                                            <span>• Drop: {ab.last_step_reached}</span>
                                            {ab.time_spent_seconds && <span>• {ab.time_spent_seconds}s tela</span>}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-[10px] text-slate-400 block">{new Date(ab.created_at).toLocaleString('pt-BR')}</span>
                                        {ab.payment_method_selected && (
                                            <span className="text-[10px] uppercase font-bold text-slate-500 mt-0.5 inline-block border border-slate-200 dark:border-slate-700 px-1 rounded bg-slate-50 dark:bg-slate-800">
                                                {ab.payment_method_selected}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

            </div>
        </div>
    );
}

// Sub-component for KPIs
function KpiCard({ label, value, valueColor = "text-slate-900 dark:text-white" }: { label: string, value: string | number, valueColor?: string }) {
    return (
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{label}</h4>
            <div className={`text-2xl font-black ${valueColor}`}>
                {value}
            </div>
        </div>
    );
}
