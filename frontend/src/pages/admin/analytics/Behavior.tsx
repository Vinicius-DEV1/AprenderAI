import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../../api/axios';

export default function Behavior() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-analytics-behavior'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/behavior');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando métricas de comportamento...</div>;
    if (isError) return <div className="p-8 text-red-500">Erro ao carregar os dados.</div>;

    const pages = data?.pages || [];

    const formatTime = (seconds: number) => {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    };

    const formatPercent = (num: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(num * 100);

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Analytics: Comportamento</h1>
                    <p className="text-gray-500 text-sm mt-1">Páginas mais acessadas e engajamento do usuário.</p>
                </div>
            </div>

            {/* Intern Menu */}
            <div className="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
                <Link to="/admin/analytics" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão Geral</Link>
                <Link to="/admin/analytics/behavior" className="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Comportamento</Link>
                <Link to="/admin/analytics/acquisition" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</Link>
                <Link to="/admin/analytics/conversion" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</Link>
                <Link to="/admin/analytics/monetization" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</Link>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="p-6 border-b border-gray-50">
                    <h3 className="font-bold text-gray-800">Páginas Mais Acessadas (Últimos 7 dias)</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-50 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-4">Página</th>
                                <th className="px-6 py-4">Visualizações</th>
                                <th className="px-6 py-4">Tempo Médio</th>
                                <th className="px-6 py-4">Taxa de Saída</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {pages.map((p: any, idx: number) => (
                                <tr key={idx} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <p className="font-bold text-gray-800 text-sm">{p.page_title}</p>
                                        <p className="text-xs text-gray-500 font-mono mt-0.5">{p.page_path}</p>
                                    </td>
                                    <td className="px-6 py-4 font-bold text-gray-700">{p.total_views}</td>
                                    <td className="px-6 py-4 text-gray-600">{formatTime(Number(p.avg_time))}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2 py-1 rounded text-xs font-bold ${Number(p.exit_rate) > 0.5 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'}`}>
                                            {formatPercent(Number(p.exit_rate))}%
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {pages.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-6 py-10 text-center text-gray-400 italic">Sem dados de comportamento no período.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
