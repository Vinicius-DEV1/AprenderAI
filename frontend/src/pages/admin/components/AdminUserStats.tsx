import { useQuery } from '@tanstack/react-query';
import api from '../../../api/axios';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';
import { Bar, Line, Doughnut } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

export default function AdminUserStats({ userId }: { userId: string | number }) {
    const { data: stats, isLoading } = useQuery({
        queryKey: ['admin-user-stats', userId],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/users/${userId}/stats`);
            return res.data;
        }
    });

    if (isLoading) return <div className="p-12 text-center text-gray-500 animate-pulse font-medium">Extraindo estatísticas...</div>;
    if (!stats) return <div className="p-12 text-center text-gray-500">Nenhum dado encontrado para este usuário.</div>;

    const overview = stats.overview;
    const bySubject = stats.bySubject || [];
    const temporal = stats.temporal || [];
    const byDifficulty = stats.byDifficulty || [];

    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
            <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Resultados e Desempenho do Aluno
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl text-center">
                    <div className="text-3xl font-black text-slate-800">{overview.total}</div>
                    <div className="text-[11px] text-gray-500 font-bold uppercase tracking-wider mt-1">Qst. Respondidas</div>
                </div>
                <div className="bg-emerald-50 border border-emerald-100 p-5 rounded-xl text-center">
                    <div className="text-3xl font-black text-emerald-600">{overview.accuracy}%</div>
                    <div className="text-[11px] text-emerald-800/70 font-bold uppercase tracking-wider mt-1">Taxa de Acerto</div>
                </div>
                <div className="bg-emerald-50 border border-emerald-100 p-5 rounded-xl text-center">
                    <div className="text-3xl font-black text-emerald-600">{overview.correct}</div>
                    <div className="text-[11px] text-emerald-800/70 font-bold uppercase tracking-wider mt-1">Total de Acertos</div>
                </div>
                <div className="bg-red-50 border border-red-100 p-5 rounded-xl text-center">
                    <div className="text-3xl font-black text-red-500">{overview.incorrect}</div>
                    <div className="text-[11px] text-red-800/70 font-bold uppercase tracking-wider mt-1">Total de Erros</div>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h4 className="text-sm font-bold text-gray-700 mb-4 border-b border-gray-50 pb-2">Evolução de Resoluções (30 dias)</h4>
                    <div className="h-64">
                        <Line
                            data={{
                                labels: temporal.map((d: any) => d.date),
                                datasets: [
                                    { label: 'Resolvidas', data: temporal.map((d: any) => d.total), borderColor: '#6366f1', fill: true, tension: 0.3, pointRadius: 3, backgroundColor: 'rgba(99, 102, 241, 0.1)' },
                                    { label: 'Acertos', data: temporal.map((d: any) => d.correct), borderColor: '#10b981', fill: true, tension: 0.3, pointRadius: 3, backgroundColor: 'rgba(16, 185, 129, 0.1)' }
                                ]
                            }}
                            options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }}
                        />
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h4 className="text-sm font-bold text-gray-700 mb-4 border-b border-gray-50 pb-2">Performance por Matéria</h4>
                    <div className="h-64">
                        <Bar
                            data={{
                                labels: bySubject.map((d: any) => d.subject),
                                datasets: [
                                    { label: 'Acertos', data: bySubject.map((d: any) => d.correct), backgroundColor: '#10b981', borderRadius: 4 },
                                    { label: 'Erros', data: bySubject.map((d: any) => d.total - d.correct), backgroundColor: '#ef4444', borderRadius: 4 }
                                ]
                            }}
                            options={{ responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } }, plugins: { legend: { position: 'bottom' } } }}
                        />
                    </div>
                </div>
            </div>

            <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <h4 className="text-sm font-bold text-gray-700 mb-4 text-center border-b border-gray-50 pb-2">Distribuição de Dificuldade (Taxa de Acerto)</h4>
                <div className="h-56 flex justify-center">
                    <Doughnut
                        data={{
                            labels: byDifficulty.map((d: any) => d.difficulty === 'easy' ? 'Fácil' : d.difficulty === 'medium' ? 'Média' : 'Difícil'),
                            datasets: [{
                                data: byDifficulty.map((d: any) => d.accuracy),
                                backgroundColor: byDifficulty.map((d: any) => d.difficulty === 'easy' ? '#10b981' : d.difficulty === 'medium' ? '#f59e0b' : '#ef4444'),
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        }}
                        options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }}
                    />
                </div>
            </div>
        </div>
    );
}
