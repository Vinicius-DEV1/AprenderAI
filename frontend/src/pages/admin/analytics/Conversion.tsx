import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../../api/axios';

export default function Conversion() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-analytics-conversion'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/conversion');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando métricas de conversão...</div>;
    if (isError) return <div className="p-8 text-red-500">Erro ao carregar os dados.</div>;

    const events = data?.events || [];

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Analytics: Conversão</h1>
                    <p className="text-gray-500 text-sm mt-1">Eventos customizados e funil de conversão (Ex: Checkout, Registros).</p>
                </div>
            </div>

            {/* Intern Menu */}
            <div className="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
                <Link to="/admin/analytics" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão Geral</Link>
                <Link to="/admin/analytics/behavior" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</Link>
                <Link to="/admin/analytics/acquisition" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</Link>
                <Link to="/admin/analytics/conversion" className="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Conversão</Link>
                <Link to="/admin/analytics/monetization" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</Link>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="p-6 border-b border-gray-50 flex justify-between items-center">
                    <h3 className="font-bold text-gray-800">Eventos Acompanhados (Últimos 7 dias)</h3>
                    <span className="text-xs bg-indigo-50 text-indigo-600 font-bold px-3 py-1 rounded-full">{events.length} Eventos Distintos</span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-50 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-4">Nome do Evento</th>
                                <th className="px-6 py-4">Disparos (Total)</th>
                                <th className="px-6 py-4">Usuários Únicos</th>
                                <th className="px-6 py-4 w-32">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {events.map((e: any, idx: number) => (
                                <tr key={idx} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded bg-blue-50 text-blue-600 flex items-center justify-center font-bold">🎯</div>
                                            <span className="font-bold text-gray-800 font-mono text-xs bg-gray-100 px-2 py-1 rounded">{e.event_name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-black text-indigo-600 text-lg">{e.total_events}</td>
                                    <td className="px-6 py-4 font-bold text-gray-600">{e.total_users}</td>
                                    <td className="px-6 py-4">
                                        <div className="w-full bg-gray-200 rounded-full h-1.5">
                                            <div className="bg-indigo-600 h-1.5 rounded-full" style={{ width: `${Math.min((e.total_events / (events[0]?.total_events || 1)) * 100, 100)}%` }}></div>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {events.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-6 py-10 text-center text-gray-400 italic">Sem dados de conversão e eventos.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
