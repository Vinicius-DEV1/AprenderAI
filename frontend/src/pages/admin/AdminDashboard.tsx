import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';
import { Line, Doughnut, Bar } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function AdminDashboard() {
    const { data, isLoading } = useQuery({
        queryKey: ['admin-dashboard'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/dashboard');
            return res.data;
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (!data) return <div className="p-8 text-center text-red-500 font-bold uppercase tracking-widest">Erro ao carregar dados analíticos.</div>;

    // ── SAFE DEFAULTS: protect against partial or malformed API responses ──
    const kpis = {
        active_subscriptions: 0,
        revenue: 0,
        new_users_this_week: 0,
        user_growth_direction: 'up',
        ...(data?.kpis || {}),
    };
    const charts = {
        labels: [],
        subscriptions: [],
        user_distribution: [0, 0],
        ...(data?.charts || {}),
    };
    const activity_feed = data?.activity_feed || [];

    const lineData = {
        labels: charts.labels || [],
        datasets: [{
            label: 'Novas Assinaturas',
            data: charts.subscriptions || [],
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };

    const doughnutData = {
        labels: ['Pagantes', 'Gratuitos'],
        datasets: [{
            data: charts.user_distribution || [0, 0],
            backgroundColor: ['#10B981', '#E5E7EB'],
            borderWidth: 0
        }]
    };

    const deviceDoughnutData = {
        labels: ['Desktop', 'Mobile', 'Tablet'],
        datasets: [{
            data: charts.device_distribution || [0, 0, 0],
            backgroundColor: ['#3B82F6', '#F59E0B', '#10B981'],
            borderWidth: 0
        }]
    };

    const osBarData = {
        labels: charts.os_distribution?.labels || [],
        datasets: [{
            label: 'Acessos por SO',
            data: charts.os_distribution?.data || [],
            backgroundColor: 'rgba(99, 102, 241, 0.8)',
            borderRadius: 8,
            barThickness: 20
        }]
    };

    const osOptions = {
        indexAxis: 'y' as const,
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: { display: false },
                ticks: { display: false }
            },
            y: {
                grid: { display: false }
            }
        }
    };

    return (
        <div className="p-4 md:p-6 w-full">
            <header className="mb-6 flex justify-between items-end">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-white mb-2">Dashboard Analítico 🚀</h1>
                    <p className="text-gray-600 dark:text-slate-400">Visão geral da performance do sistema</p>
                </div>
                <div className="text-sm text-gray-500 hidden md:block">
                    Atualizado agora
                </div>
            </header>

            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                {/* Active Subscriptions */}
                <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-lg transform hover:scale-105 transition-transform duration-300">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-blue-100 text-[10px] font-black uppercase tracking-wider">Assinaturas Ativas</p>
                            <h3 className="text-4xl font-black mt-2">{kpis.active_subscriptions || 0}</h3>
                        </div>
                        <div className="bg-white/20 p-3 rounded-xl">
                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center text-blue-100 text-[10px] font-bold">
                        <span className="bg-white/20 px-2 py-0.5 rounded text-white text-[9px] font-black mr-2">LIVE</span>
                        <span>Monitoramento em tempo real</span>
                    </div>
                </div>

                {/* Revenue */}
                <div className="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg transform hover:scale-105 transition-transform duration-300">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-green-100 text-[10px] font-black uppercase tracking-wider">Receita Mensal (Est.)</p>
                            <h3 className="text-4xl font-black mt-2">R$ {(kpis.revenue || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</h3>
                        </div>
                        <div className="bg-white/20 p-3 rounded-xl">
                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center text-green-100 text-[10px] font-bold">
                        <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Baseado em planos ativos</span>
                    </div>
                </div>

                {/* New Users */}
                <div className="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg transform hover:scale-105 transition-transform duration-300">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-purple-100 text-[10px] font-black uppercase tracking-wider">Novos Usuários (Semana)</p>
                            <h3 className="text-4xl font-black mt-2">{kpis.new_users_this_week || 0}</h3>
                        </div>
                        <div className="bg-white/20 p-3 rounded-xl">
                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center text-purple-100 text-[10px] font-bold">
                        {kpis.user_growth_direction === 'up' ? (
                            <span className="bg-green-400/30 px-2 py-0.5 rounded text-white text-[9px] font-black mr-2 flex items-center">
                                <svg className="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                </svg>
                                CRESCIMENTO
                            </span>
                        ) : (
                            <span className="bg-red-400/30 px-2 py-0.5 rounded text-white text-[9px] font-black mr-2 flex items-center">
                                <svg className="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                                QUEDA
                            </span>
                        )}
                        <span>vs semana anterior</span>
                    </div>
                </div>

                {/* System Status */}
                <div className="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg transform hover:scale-105 transition-transform duration-300">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-amber-100 text-[10px] font-black uppercase tracking-wider">Status do Sistema</p>
                            <h3 className="text-2xl font-black mt-2 tracking-tight">Operacional</h3>
                        </div>
                        <div className="bg-white/20 p-3 rounded-xl">
                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center text-amber-100 text-[10px] font-bold">
                        <span className="w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
                        <span>Todos os serviços ativos</span>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 space-y-8">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">
                        <h3 className="font-bold mb-4">Evolução de Assinaturas</h3>
                        <div className="h-64">
                            <Line data={lineData} options={{ responsive: true, maintainAspectRatio: false }} />
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">
                        <h3 className="font-bold mb-4">Distribuição de Usuários</h3>
                        <div className="h-64 flex items-center justify-around">
                            <div className="w-48 h-48">
                                <Doughnut data={doughnutData} options={{ cutout: '70%', plugins: { legend: { display: false } } }} />
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <span className="w-3 h-3 rounded-full bg-green-500"></span>
                                    <span className="text-sm">Pagantes: <b>{charts.user_distribution[0]}</b></span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-3 h-3 rounded-full bg-gray-300"></span>
                                    <span className="text-sm">Gratuitos: <b>{charts.user_distribution[1]}</b></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* New Analytics Section: Devices & OS */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">
                            <h3 className="font-bold mb-4">Acessos por Dispositivo</h3>
                            <div className="h-48 flex items-center justify-around">
                                <div className="w-36 h-36">
                                    <Doughnut data={deviceDoughnutData} options={{ cutout: '70%', plugins: { legend: { display: false } } }} />
                                </div>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                        <span className="text-[11px] uppercase font-bold text-gray-500">Desktop: <b>{charts.device_distribution?.[0] || 0}</b></span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                                        <span className="text-[11px] uppercase font-bold text-gray-500">Mobile: <b>{charts.device_distribution?.[1] || 0}</b></span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                                        <span className="text-[11px] uppercase font-bold text-gray-500">Tablet: <b>{charts.device_distribution?.[2] || 0}</b></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">
                            <h3 className="font-bold mb-4">Top Sistemas Operacionais</h3>
                            <div className="h-48">
                                <Bar data={osBarData} options={osOptions} />
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-gray-100 dark:border-slate-700">
                        <h3 className="text-lg font-black text-gray-800 dark:text-white mb-8 flex items-center">
                            <svg className="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Últimas Atividades
                        </h3>

                        <div className="relative border-l-2 border-gray-100 dark:border-slate-700 ml-3 space-y-8">
                            {activity_feed.map((act: any, idx: number) => (
                                <div key={idx} className="relative ml-6">
                                    {/* Timeline Bullet */}
                                    <span className={`absolute -left-[33px] flex items-center justify-center w-8 h-8 rounded-full ring-4 ring-white dark:ring-slate-800 shadow-sm
                                        ${act.type === 'subscription' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'}`}>
                                        {act.type === 'subscription' ? (
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        ) : (
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                            </svg>
                                        )}
                                    </span>

                                    <div className="bg-gray-50 dark:bg-slate-900/50 rounded-2xl p-4 hover:bg-gray-100 dark:hover:bg-slate-900 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-slate-700">
                                        <div className="flex items-center mb-2">
                                            <img src={act.user?.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(act.user?.name || '?')}`}
                                                alt={act.user?.name} className="w-5 h-5 rounded-full mr-2" />
                                            <span className="text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-tighter">{act.user?.name}</span>
                                            <span className="text-[9px] text-gray-400 ml-auto font-bold">{new Date(act.created_at).toLocaleDateString('pt-BR')}</span>
                                        </div>
                                        <p className="text-xs font-bold text-gray-600 dark:text-slate-400 leading-relaxed flex items-center gap-2 flex-wrap">
                                            {act.message}
                                            {act.is_sandbox && (
                                                <span className="inline-flex items-center gap-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-full border border-amber-300 dark:border-amber-700">
                                                    🧪 SANDBOX
                                                </span>
                                            )}
                                            {act.is_manual_grant && (
                                                <span className="inline-flex items-center gap-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400 text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-full border border-purple-300 dark:border-purple-700">
                                                    🎁 MANUAL
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
