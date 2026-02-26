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
import { Line, Doughnut } from 'react-chartjs-2';

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

export default function AdminDashboard() {
    const { data, isLoading } = useQuery({
        queryKey: ['admin-dashboard'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/dashboard');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando painel analítico...</div>;
    if (!data) return <div className="p-8 text-red-500">Erro ao carregar dados.</div>;

    const { kpis, charts, activity_feed } = data;

    const lineData = {
        labels: charts.labels,
        datasets: [{
            label: 'Novas Assinaturas',
            data: charts.subscriptions,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };

    const doughnutData = {
        labels: ['Pagantes', 'Gratuitos'],
        datasets: [{
            data: charts.user_distribution,
            backgroundColor: ['#10B981', '#E5E7EB'],
            borderWidth: 0
        }]
    };

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto">
            <header className="mb-10 flex justify-between items-end">
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
                <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-lg">
                    <p className="text-blue-100 text-xs font-bold uppercase">Assinaturas Ativas</p>
                    <h3 className="text-4xl font-bold mt-2">{kpis.active_subscriptions}</h3>
                </div>
                <div className="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg">
                    <p className="text-green-100 text-xs font-bold uppercase">Receita Mensal (Est.)</p>
                    <h3 className="text-4xl font-bold mt-2">R$ {kpis.revenue.toLocaleString('pt-BR')}</h3>
                </div>
                <div className="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg">
                    <p className="text-purple-100 text-xs font-bold uppercase">Novos Usuários (Semana)</p>
                    <h3 className="text-4xl font-bold mt-2">{kpis.new_users_this_week}</h3>
                </div>
                <div className="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg">
                    <p className="text-amber-100 text-xs font-bold uppercase">Status do Sistema</p>
                    <h3 className="text-2xl font-bold mt-2">Operacional</h3>
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
                </div>

                <div>
                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">
                        <h3 className="font-bold mb-6">Últimas Atividades</h3>
                        <div className="space-y-6">
                            {activity_feed.map((act: any, idx: number) => (
                                <div key={idx} className="flex gap-4">
                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs shrink-0 ${act.type === 'subscription' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'}`}>
                                        {act.type === 'subscription' ? '💰' : '👤'}
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium">{act.message}</p>
                                        <p className="text-xs text-gray-400 mt-1">{new Date(act.created_at).toLocaleString()}</p>
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
