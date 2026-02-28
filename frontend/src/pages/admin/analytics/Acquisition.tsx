import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../../api/axios';

export default function Acquisition() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-analytics-acquisition'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/acquisition');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando métricas de aquisição...</div>;
    if (isError) return <div className="p-8 text-red-500">Erro ao carregar os dados.</div>;

    const devices = data?.devices || [];
    const sources = data?.sources || [];
    const countries = data?.countries || [];

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Analytics: Aquisição</h1>
                    <p className="text-gray-500 text-sm mt-1">Origem do tráfego e perfis de dispositivos.</p>
                </div>
            </div>

            {/* Intern Menu */}
            <div className="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
                <Link to="/admin/analytics" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Visão Geral</Link>
                <Link to="/admin/analytics/behavior" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</Link>
                <Link to="/admin/analytics/acquisition" className="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Aquisição</Link>
                <Link to="/admin/analytics/conversion" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</Link>
                <Link to="/admin/analytics/monetization" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Dispositivos */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div className="p-6 border-b border-gray-50">
                        <h3 className="font-bold text-gray-800">Dispositivos</h3>
                    </div>
                    <div className="p-6">
                        <div className="space-y-4">
                            {devices.map((d: any, idx: number) => (
                                <div key={idx} className="flex justify-between items-center bg-gray-50 p-4 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <span className="text-xl">{d.device_category === 'mobile' ? '📱' : d.device_category === 'desktop' ? '💻' : '🖥️'}</span>
                                        <span className="font-bold text-gray-700 capitalize">{d.device_category}</span>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-indigo-600">{d.total_sessions} sessões</p>
                                        <p className="text-xs text-gray-500">{d.total_users} usuários</p>
                                    </div>
                                </div>
                            ))}
                            {devices.length === 0 && <p className="text-gray-400 italic text-sm">Sem dados de dispositivos</p>}
                        </div>
                    </div>
                </div>

                {/* Canais */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div className="p-6 border-b border-gray-50">
                        <h3 className="font-bold text-gray-800">Canais de Aquisição</h3>
                    </div>
                    <div className="p-6">
                        <div className="space-y-4">
                            {sources.map((s: any, idx: number) => (
                                <div key={idx} className="flex justify-between items-center group">
                                    <div className="flex items-center gap-3">
                                        <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <span className="font-medium text-sm text-gray-700 font-mono bg-gray-50 px-2 py-1 rounded">{s.source_medium}</span>
                                    </div>
                                    <span className="font-bold text-gray-800">{s.total_sessions} <span className="text-xs font-normal text-gray-400">sessões</span></span>
                                </div>
                            ))}
                            {sources.length === 0 && <p className="text-gray-400 italic text-sm">Sem dados de aquisição</p>}
                        </div>
                    </div>
                </div>

                {/* Localização */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                    <div className="p-6 border-b border-gray-50">
                        <h3 className="font-bold text-gray-800">Localização (Países)</h3>
                    </div>
                    <div className="p-6 flex flex-wrap gap-4">
                        {countries.map((c: any, idx: number) => (
                            <div key={idx} className="flex items-center border border-gray-200 rounded-full px-4 py-2 hover:border-indigo-300 transition-colors">
                                <span className="font-bold text-gray-800 mr-2">{c.country}</span>
                                <span className="bg-indigo-50 text-indigo-600 text-xs font-bold px-2 py-0.5 rounded-full">{c.total_sessions} sessões</span>
                            </div>
                        ))}
                        {countries.length === 0 && <p className="text-gray-400 italic text-sm">Sem dados geográficos</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}
