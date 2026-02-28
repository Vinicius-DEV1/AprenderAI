import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../../api/axios';

export default function Monetization() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-analytics-monetization'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/monetization');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando métricas de monetização...</div>;
    if (isError) return <div className="p-8 text-red-500">Erro ao carregar os dados.</div>;

    const pages = data?.pages || [];
    const insights = data?.insights || [];

    const formatTime = (seconds: number) => {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    };

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Analytics: Monetização</h1>
                    <p className="text-gray-500 text-sm mt-1">Foco na retenção de AdSense e desempenho de páginas ricas.</p>
                </div>
            </div>

            {/* Intern Menu */}
            <div className="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
                <Link to="/admin/analytics" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão Geral</Link>
                <Link to="/admin/analytics/behavior" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</Link>
                <Link to="/admin/analytics/acquisition" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</Link>
                <Link to="/admin/analytics/conversion" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</Link>
                <Link to="/admin/analytics/monetization" className="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-green-50 text-green-700 border border-green-200">Monetização</Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-50">
                        <h3 className="font-bold text-gray-800">Retenção Foco AdSense (Top 15 Páginas)</h3>
                        <p className="text-xs text-gray-500 mt-1">Páginas ordenadas por tempo médio em página.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">URL (Caminho)</th>
                                    <th className="px-6 py-4">Tempo Médio</th>
                                    <th className="px-6 py-4">Acessos</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {pages.map((p: any, idx: number) => (
                                    <tr key={idx} className="hover:bg-green-50/30 transition-colors">
                                        <td className="px-6 py-4">
                                            <span className="font-mono text-xs text-gray-600 bg-gray-50 px-2 py-1 rounded">{p.page_path}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="font-bold text-green-600 text-lg flex items-center gap-2">
                                                ⏱️ {formatTime(Number(p.avg_time))}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-bold text-gray-700">{p.total_views}</td>
                                    </tr>
                                ))}
                                {pages.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-10 text-center text-gray-400 italic">Sem inventário disponível.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg shadow-green-200">
                        <h3 className="font-black text-xl mb-2 flex items-center gap-2">💰 Dica AdSense</h3>
                        <p className="opacity-90 text-sm leading-relaxed font-medium">
                            Páginas com mais de <strong className="text-yellow-300">01:30</strong> de retenção são excelentes canditadas a blocos de anúncios "In-article" ou "Multi-plex". Considere injetar publicidades automaticamente caso o tempo médio se sustente.
                        </p>
                    </div>

                    {insights.length > 0 && (
                        <div className="bg-white border border-gray-100 rounded-xl p-6 shadow-sm">
                            <h3 className="font-bold text-gray-800 mb-4 flex items-center gap-2">
                                🧠 I.A Recommendations
                            </h3>
                            <ul className="space-y-3">
                                {insights.map((insight: string, idx: number) => (
                                    <li key={idx} className="flex gap-3 text-sm text-gray-600 items-start">
                                        <span className="text-indigo-400 mt-0.5">✧</span>
                                        <span className="leading-snug">{insight}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
