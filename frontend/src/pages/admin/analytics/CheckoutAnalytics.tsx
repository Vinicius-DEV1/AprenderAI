import { useState, useEffect } from 'react';
import {
    getCheckoutOverview,
    getCheckoutFunnel,
    getCheckoutPlansRanking,
    getCheckoutAbandonments,
    getCheckoutAlerts,
    CheckoutOverview,
    FunnelStep,
    PlanRanking,
    CheckoutAlert,
    CheckoutAbandonmentItem
} from '../../../api/checkoutAnalytics';
import { toast } from 'sonner';
import { 
    Users, 
    CreditCard, 
    TrendingUp, 
    TrendingDown, 
    AlertTriangle, 
    Clock, 
    ChevronRight,
    Trophy,
    Target,
    Zap,
    History as HistoryIcon,
    ShoppingCart,
    MapPin,
    Monitor,
    Gift
} from 'lucide-react';

import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';
import { Pie } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
);

export default function CheckoutAnalytics() {
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    const [overview, setOverview] = useState<CheckoutOverview | null>(null);
    const [funnel, setFunnel] = useState<FunnelStep[]>([]);
    const [ranking, setRanking] = useState<PlanRanking[]>([]);
    const [abandonments, setAbandonments] = useState<CheckoutAbandonmentItem[]>([]);
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
                    abandonmentsRes,
                    alertsRes
                ] = await Promise.all([
                    getCheckoutOverview(days),
                    getCheckoutFunnel(days),
                    getCheckoutPlansRanking(days),
                    getCheckoutAbandonments(days),
                    getCheckoutAlerts()
                ]);

                if (!isMounted) return;

                setOverview(overviewRes.data);
                setFunnel(funnelRes.data.funnel);
                setRanking(rankingRes.data.ranking);
                setAbandonments(abandonmentsRes.data.abandonments || []);
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
            <div className="flex justify-center items-center h-[70vh]">
                <div className="flex flex-col items-center gap-4">
                    <div className="relative">
                        <div className="absolute inset-0 blur-2xl bg-indigo-500/30 rounded-full animate-pulse"></div>
                        <div className="animate-spin rounded-full h-16 w-16 border-4 border-slate-200 border-t-indigo-600 relative z-10"></div>
                    </div>
                    <p className="text-slate-500 font-medium animate-pulse">Carregando inteligência de checkout...</p>
                </div>
            </div>
        );
    }

    // Helper for funnel chart calculation
    const maxFunnelCount = funnel.length > 0 ? Math.max(...funnel.map(f => f.count)) : 1;

    // Chart Data: Device Conversion
    const deviceData = {
        labels: overview?.devices.map(d => d.device.toUpperCase()) || [],
        datasets: [{
            label: 'Conversões por Dispositivo',
            data: overview?.devices.map(d => Number(d.conversions)) || [],
            backgroundColor: [
                'rgba(99, 102, 241, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
            ],
            borderWidth: 0,
        }]
    };

    // Chart Data: Coupon Impact (Conversion with vs without)
    const totalConversions = overview?.payments_success || 1;
    const conversionsWithCoupon = overview?.conversions_coupon || 0;
    const couponData = {
        labels: ['Com Cupom', 'Sem Cupom'],
        datasets: [{
            data: [conversionsWithCoupon, totalConversions - conversionsWithCoupon],
            backgroundColor: ['rgba(139, 92, 246, 0.8)', 'rgba(100, 116, 139, 0.3)'],
            borderWidth: 0,
        }]
    };

    const currencyFormatter = Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    return (
        <div className="pb-12 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
            {/* Header & Controls */}
            <div className="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6">
                <div>
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-wider mb-3">
                        <Zap size={14} className="fill-current" />
                        Visão Premium • V2
                    </div>
                    <h1 className="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Checkout Intelligence
                    </h1>
                    <p className="text-lg text-slate-500 dark:text-slate-400 mt-2 max-w-2xl">
                        Análise profunda de conversão, saúde financeira e comportamento do usuário em tempo real.
                    </p>
                </div>
                <div className="flex bg-slate-100 dark:bg-slate-800/50 backdrop-blur-sm rounded-xl p-1.5 border border-slate-200 dark:border-slate-700 shadow-inner">
                    {[7, 14, 30, 90].map(d => (
                        <button
                            key={d}
                            onClick={() => setDays(d)}
                            className={`px-5 py-2 text-sm font-bold rounded-lg transition-all ${
                                days === d 
                                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-sm ring-1 ring-slate-200/50 dark:ring-white/10' 
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
                            }`}
                        >
                            {d}d
                        </button>
                    ))}
                </div>
            </div>

            {/* Smart Alerts */}
            {alerts.length > 0 && (
                <div className="grid gap-4">
                    {alerts.map((alert, idx) => (
                        <div 
                            key={idx} 
                            className={`group flex items-start gap-4 p-5 rounded-2xl border-2 transition-all hover:scale-[1.005] ${
                                alert.severity === 'critical' 
                                ? 'bg-red-50/50 dark:bg-red-950/20 border-red-100 dark:border-red-900/30 text-red-900 dark:text-red-300' 
                                : 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-100 dark:border-amber-900/30 text-amber-900 dark:text-amber-300'
                            }`}
                        >
                            <div className={`p-2 rounded-xl ${
                                alert.severity === 'critical' ? 'bg-red-100 dark:bg-red-900/50' : 'bg-amber-100 dark:bg-amber-900/50'
                            }`}>
                                {alert.severity === 'critical' ? <AlertTriangle size={20} /> : <AlertTriangle size={20} />}
                            </div>
                            <div className="flex-1">
                                <h4 className="font-black text-base tracking-tight">{alert.message}</h4>
                                <p className="text-sm mt-1 opacity-80">{alert.detail}</p>
                            </div>
                            <button className="opacity-0 group-hover:opacity-100 transition-opacity text-xs font-bold uppercase tracking-widest bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                                Diagnosticar
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Financial & Volume KPIs */}
            {overview && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <PremiumKpiCard 
                        label="Receita Realizada" 
                        value={currencyFormatter.format(overview.gained_revenue)} 
                        icon={<TrendingUp className="text-emerald-500" />}
                        subLabel={`Conversão global de ${overview.conversion_rate}%`}
                        trend="+12%" // Placeholder for trend
                        variant="emerald"
                    />
                    <PremiumKpiCard 
                        label="Receita em Abandono" 
                        value={currencyFormatter.format(overview.lost_revenue)} 
                        icon={<TrendingDown className="text-red-500" />}
                        subLabel={`Perda estimada últimos ${days} dias`}
                        trend="Gargalo"
                        variant="red"
                    />
                    <PremiumKpiCard 
                        label="Cliques Únicos" 
                        value={overview.unique_intentions} 
                        icon={<Users className="text-indigo-500" />}
                        subLabel={`${overview.purchase_intentions} cliques totais`}
                        variant="indigo"
                    />
                    <PremiumKpiCard 
                        label="Ticket Médio" 
                        value={overview.payments_success > 0 ? currencyFormatter.format(overview.gained_revenue / overview.payments_success) : 'R$ 0,00'} 
                        icon={<CreditCard className="text-blue-500" />}
                        subLabel="Baseado em assinaturas pagas"
                        variant="blue"
                    />
                </div>
            )}

            {/* Main Visuals: Funnel & Metrics */}
            <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
                
                {/* Visual conversion funnel */}
                <div className="xl:col-span-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <div className="flex justify-between items-center mb-10">
                        <div>
                            <h3 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                Funil Dinâmico de Conversão
                            </h3>
                            <p className="text-sm text-slate-500 mt-1">Onde os seus usuários estão desistindo?</p>
                        </div>
                        <div className="p-3 bg-slate-50 dark:bg-slate-800 rounded-2xl">
                            <Target className="text-indigo-600 dark:text-indigo-400" />
                        </div>
                    </div>
                    
                    <div className="space-y-8 relative">
                        {funnel.map((step, idx) => {
                            const widthPct = Math.max((step.count / maxFunnelCount) * 100, 2);
                            return (
                                <div key={step.step} className="group relative z-10">
                                    <div className="flex justify-between items-end mb-2">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-black text-slate-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                                {idx + 1}
                                            </div>
                                            <span className="text-sm font-black text-slate-900 dark:text-slate-100 uppercase tracking-widest">{step.label}</span>
                                        </div>
                                        <div className="text-right">
                                            <span className="text-2xl font-black text-slate-900 dark:text-white">{step.count.toLocaleString()}</span>
                                            {step.dropoff_pct !== null && step.dropoff_pct > 0 && (
                                                <div className="flex items-center justify-end gap-1 text-xs font-bold text-red-500 mt-0.5 animate-pulse">
                                                    <TrendingDown size={12} />
                                                    {step.dropoff_pct}% Dropoff
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="h-6 w-full bg-slate-100 dark:bg-slate-800 rounded-xl overflow-hidden shadow-inner">
                                        <div 
                                            className="h-full bg-gradient-to-r from-indigo-500 to-indigo-700 dark:from-indigo-600 dark:to-indigo-400 rounded-xl transition-all duration-1000 ease-out group-hover:brightness-110 shadow-lg"
                                            style={{ width: `${widthPct}%` }}
                                        ></div>
                                    </div>
                                </div>
                            );
                        })}
                        {/* Connecting background line */}
                        <div className="absolute top-4 left-4 bottom-4 w-0.5 bg-slate-50 dark:bg-slate-800 -z-10"></div>
                    </div>
                </div>

                {/* Vertical Panels: Device & Coupons */}
                <div className="xl:col-span-4 space-y-6">
                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                        <h4 className="text-sm font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                            <Monitor size={16} /> Dispositivos
                        </h4>
                        <div className="h-48 flex justify-center">
                            <Pie data={deviceData} options={{ maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10, weight: 'bold' } } } } }} />
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                        <h4 className="text-sm font-black text-slate-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                            <Gift size={16} /> Impacto de Cupons
                        </h4>
                        <div className="h-48 flex justify-center">
                            <Pie data={couponData} options={{ maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10, weight: 'bold' } } } } }} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Plan Performance & Top Origins */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <h3 className="text-base font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                        <Trophy className="text-amber-500" /> Top Planos por Performance
                    </h3>
                    <div className="space-y-4">
                        {ranking.map((plan, idx) => (
                            <div key={plan.plan_id} className="group flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800 hover:border-indigo-200 dark:hover:border-indigo-500/20 transition-all">
                                <div className="flex items-center gap-4">
                                    <div className="text-lg font-black text-slate-300 dark:text-slate-700">0{idx + 1}</div>
                                    <div>
                                        <div className="font-bold text-slate-900 dark:text-white capitalize leading-tight">{plan.plan_name}</div>
                                        <div className="text-xs text-slate-500 mt-1 uppercase font-bold tracking-tighter opacity-70">
                                            {plan.plan_interval === 'yearly' ? 'Anual' : 'Mensal'} • {currencyFormatter.format(plan.plan_price)}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-10 items-center">
                                    <div className="text-center">
                                        <div className="text-xs font-black text-slate-400 uppercase tracking-tighter mb-0.5">CLIQUES</div>
                                        <div className="text-sm font-black text-slate-900 dark:text-white">{plan.clicks}</div>
                                    </div>
                                    <div className="bg-indigo-600 text-white px-3 py-2 rounded-xl text-center min-w-[70px] shadow-lg shadow-indigo-500/20">
                                        <div className="text-[10px] font-black opacity-80 leading-none mb-1">CONV</div>
                                        <div className="text-sm font-black leading-none">{plan.conversion_rate}%</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <h3 className="text-base font-black text-slate-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                        <MapPin className="text-red-500" /> Top Telas de Origem
                    </h3>
                    <div className="space-y-6">
                        {overview?.top_origins.map((origin, idx) => {
                             const pct = Math.round((origin.total / (overview?.purchase_intentions || 1)) * 100);
                             return (
                                <div key={idx} className="space-y-2">
                                    <div className="flex justify-between items-center px-1">
                                        <span className="text-sm font-bold text-slate-700 dark:text-slate-300 font-mono tracking-tighter">{origin.source_page}</span>
                                        <span className="text-sm font-black text-indigo-600 dark:text-indigo-400">{pct}%</span>
                                    </div>
                                    <div className="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                        <div className="h-full bg-indigo-500 rounded-full" style={{ width: `${pct}%` }}></div>
                                    </div>
                                </div>
                             )
                        })}
                    </div>
                </div>
            </div>

            {/* Rich Abandonment & Error Tracing */}
            <div className="grid grid-cols-1 gap-6">
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
                    <div className="p-8 border-b border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h3 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                <HistoryIcon className="text-orange-500" /> Leads Perdidos (Abandono Inteligente)
                            </h3>
                            <p className="text-sm text-slate-500 mt-1">Identificamos o usuário mesmo antes da compra ser finalizada.</p>
                        </div>
                        <div className="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-100 dark:border-slate-800">
                           <span className="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                           <span className="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">Tempo Real</span>
                        </div>
                    </div>
                    
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50/50 dark:bg-slate-800/30 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                    <th className="px-8 py-5">Identidade Usuário</th>
                                    <th className="px-6 py-5 text-center">Última Etapa</th>
                                    <th className="px-6 py-5 text-center">Tempo/Método</th>
                                    <th className="px-6 py-5 text-center">Tentativas/Erros</th>
                                    <th className="px-8 py-5 text-right">Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {abandonments.map((ab) => (
                                    <tr key={ab.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/20 transition-colors group">
                                        <td className="px-8 py-5">
                                            <div className="flex items-center gap-4">
                                                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-500/20 overflow-hidden">
                                                    {ab.user_avatar ? <img src={ab.user_avatar} className="w-full h-full object-cover" /> : ab.user_name[0]}
                                                </div>
                                                <div>
                                                    <div className="font-bold text-slate-900 dark:text-white capitalize">{ab.user_name}</div>
                                                    <div className="text-xs text-slate-500 font-medium">{ab.user_email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 text-center">
                                            <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-black uppercase tracking-wider border border-slate-200 dark:border-slate-700">
                                                {ab.last_step}
                                            </div>
                                        </td>
                                        <td className="px-6 py-5">
                                            <div className="flex flex-col items-center gap-1">
                                                <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                                    <Clock size={12} className="text-slate-400" />
                                                    {ab.time_spent_mins || '?'} min
                                                </div>
                                                <div className="text-[10px] font-black uppercase text-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 px-2 rounded-md border border-indigo-100 dark:border-indigo-500/20">
                                                   {ab.payment_method || 'Indefinido'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5">
                                            <div className="flex flex-wrap justify-center gap-1">
                                                {ab.error_history.length > 0 ? ab.error_history.map((err, i) => (
                                                    <span key={i} className="px-2 py-0.5 rounded-md bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-[10px] font-bold border border-red-100 dark:border-red-500/20" title={`${err.count} ocorrências`}>
                                                        {err.error_type} {err.count > 1 && `(x${err.count})`}
                                                    </span>
                                                )) : (
                                                    <span className="text-[10px] font-bold text-emerald-500 opacity-60 italic">Sem erros técnicos</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-8 py-5 text-right">
                                            <div className="text-sm font-bold text-slate-900 dark:text-white">
                                                {new Date(ab.created_at).toLocaleDateString('pt-BR')}
                                            </div>
                                            <div className="text-xs text-slate-400 font-medium">
                                                {new Date(ab.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {abandonments.length === 0 && (
                            <div className="py-20 text-center flex flex-col items-center opacity-30">
                                <ShoppingCart size={48} className="mb-4" />
                                <p className="font-bold uppercase tracking-widest text-sm">Nenhum abandono registrado no período</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

// Sub-component for KPIs V2
function PremiumKpiCard({ label, value, icon, subLabel, trend, variant }: { 
    label: string, 
    value: string | number, 
    icon: React.ReactNode, 
    subLabel: string,
    trend?: string,
    variant: 'emerald' | 'red' | 'indigo' | 'blue'
}) {
    const variantStyles = {
        emerald: 'from-emerald-500/5 to-transparent border-emerald-100 dark:border-emerald-500/20',
        red: 'from-red-500/5 to-transparent border-red-100 dark:border-red-500/20',
        indigo: 'from-indigo-500/5 to-transparent border-indigo-100 dark:border-indigo-500/20',
        blue: 'from-blue-500/5 to-transparent border-blue-100 dark:border-blue-500/20',
    };

    return (
        <div className={`relative overflow-hidden bg-white dark:bg-slate-900 border-2 rounded-3xl p-6 shadow-xl shadow-slate-200/40 dark:shadow-none bg-gradient-to-br ${variantStyles[variant]} transition-all hover:translate-y-[-4px]`}>
            <div className="flex justify-between items-start mb-6">
                <div className="p-3 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                    {icon}
                </div>
                {trend && (
                    <div className="px-2 py-1 rounded-lg bg-slate-50 dark:bg-slate-800 text-[10px] font-black uppercase tracking-tighter text-slate-400 border border-slate-100 dark:border-slate-700">
                        {trend}
                    </div>
                )}
            </div>
            
            <h4 className="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 mb-2">{label}</h4>
            <div className="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none mb-4">
                {value}
            </div>
            <div className="text-xs font-bold text-slate-400 dark:text-slate-500 flex items-center gap-1.5 opacity-80">
                <ChevronRight size={14} className="opacity-40" />
                {subLabel}
            </div>
        </div>
    );
}
