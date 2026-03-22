import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend
} from 'chart.js';
import { Bar } from 'react-chartjs-2';
import { AdminPageSkeleton } from './components/AdminSkeletons';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend
);

export default function AdminAcquisition() {
    const { data, isLoading } = useQuery({
        queryKey: ['admin-utm-cohorts'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/utm-cohorts');
            return res.data;
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (!data) return <div className="p-8 text-center text-red-500 font-bold uppercase tracking-widest">Erro ao carregar dados de aquisição.</div>;

    const { cohorts, summary } = data;

    // Sort cohorts by MRR for the chart (top 10)
    const topCohorts = [...cohorts].sort((a, b) => b.mrr - a.mrr).slice(0, 10);

    const chartData = {
        labels: topCohorts.map((c: any) => `${c.utm_source} / ${c.utm_campaign}`.substring(0, 25)),
        datasets: [
            {
                label: 'Receita Est. (MRR)',
                data: topCohorts.map((c: any) => c.mrr),
                backgroundColor: 'rgba(16, 185, 129, 0.8)', // Emerald 500
                borderRadius: 4,
            }
        ],
    };

    const chartOptions = {
        responsive: true,
        plugins: {
            legend: { position: 'top' as const },
            title: { display: false },
        },
    };

    return (
        <div className="p-4 md:p-6 w-full animate-fade-in">
            <header className="mb-6">
                <h1 className="text-3xl font-bold text-gray-800 dark:text-white mb-2 flex items-center gap-3">
                    <svg className="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Aquisição (UTM) ao MRR
                </h1>
                <p className="text-gray-600 dark:text-slate-400">
                    Acompanhe quais integrações e campanhas geram mais usuários pagantes e receita.
                </p>
            </header>

            {/* KPI Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6 mb-8">
                <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
                    <p className="text-[11px] font-black tracking-widest text-gray-400 uppercase mb-2">Total Rastreado</p>
                    <h3 className="text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {summary?.total_signups.toLocaleString('pt-BR')} <span className="text-lg font-medium text-gray-400">usuários</span>
                    </h3>
                </div>

                <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
                    <p className="text-[11px] font-black tracking-widest text-gray-400 uppercase mb-2">Conversões Pagas</p>
                    <h3 className="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                        {summary?.total_paid.toLocaleString('pt-BR')}
                    </h3>
                </div>

                <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
                    <p className="text-[11px] font-black tracking-widest text-gray-400 uppercase mb-2">Conv. Média (Lead &rarr; Pago)</p>
                    <h3 className="text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {summary?.overall_conversion}%
                    </h3>
                </div>

                <div className="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 shadow-lg text-white">
                    <p className="text-[11px] font-black tracking-widest text-green-100 uppercase mb-2">Receita Total Rastreada</p>
                    <h3 className="text-3xl font-bold">
                        R$ {(summary?.total_mrr ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </h3>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                {/* Visual Chart */}
                <div className="lg:col-span-3 bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
                    <h2 className="text-lg font-bold text-gray-800 dark:text-white mb-4">Top 10 Campanhas por Receita</h2>
                    <div className="h-64">
                        <Bar options={chartOptions} data={chartData} />
                    </div>
                </div>

                {/* Data Table */}
                <div className="lg:col-span-3 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                    <div className="p-6 border-b border-gray-100 dark:border-slate-700">
                        <h2 className="text-lg font-bold text-gray-800 dark:text-white">Detalhamento de Coortes</h2>
                    </div>
                    
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 dark:bg-slate-900/50 text-gray-500 dark:text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">Source</th>
                                    <th className="px-6 py-4">Medium</th>
                                    <th className="px-6 py-4">Campaign</th>
                                    <th className="px-6 py-4 text-center">Signups</th>
                                    <th className="px-6 py-4 text-center">Pagantes</th>
                                    <th className="px-6 py-4 text-center">Conv. (%)</th>
                                    <th className="px-6 py-4 text-right">MRR Adicional</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-slate-700/50">
                                {cohorts?.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-8 text-center text-gray-500">
                                            Nenhum dado UTM rastreado ainda.
                                        </td>
                                    </tr>
                                ) : (
                                    cohorts?.map((row: any, idx: number) => (
                                        <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                            <td className="px-6 py-4 font-medium text-gray-900 dark:text-gray-200">
                                                <span className="bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded-md">{row.utm_source}</span>
                                            </td>
                                            <td className="px-6 py-4 text-gray-600 dark:text-gray-300">{row.utm_medium}</td>
                                            <td className="px-6 py-4 text-gray-600 dark:text-gray-300 max-w-[200px] truncate" title={row.utm_campaign}>
                                                {row.utm_campaign}
                                            </td>
                                            <td className="px-6 py-4 text-center font-bold text-gray-700 dark:text-gray-300">
                                                {row.signups}
                                            </td>
                                            <td className="px-6 py-4 text-center font-bold text-indigo-600 dark:text-indigo-400">
                                                {row.paid_conversions}
                                            </td>
                                            <td className="px-6 py-4 text-center font-medium">
                                                <span className={row.conversion_rate > 5 ? 'text-green-600' : 'text-gray-600 dark:text-gray-400'}>
                                                    {row.conversion_rate}%
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right font-black text-emerald-600 dark:text-emerald-400">
                                                R$ {row.mrr.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
