import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';

export default function Analytics() {
    const { data, isLoading } = useQuery({
        queryKey: ['admin-analytics'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando métricas de analytics...</div>;

    const insights = data?.insights || [];
    const todayData = data?.todayData || { active_users: 0, sessions: 0, bounce_rate: 0, avg_session_duration: 0 };
    const yesterdayData = data?.yesterdayData || { active_users: 0, sessions: 0 };
    const dailyMetrics = data?.dailyMetrics || [];
    const hourlyData = data?.hourlyData || [];
    const analyticsEnabled = data?.analyticsEnabled ?? true;

    // Helper functions
    const formatNumber = (num: number) => new Intl.NumberFormat('pt-BR').format(num);
    const formatPercent = (num: number) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(num);
    const formatTime = (seconds: number) => {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = Math.floor(seconds % 60);
        if (h > 0) return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    };

    const usersPct = yesterdayData.active_users > 0 ? ((todayData.active_users - yesterdayData.active_users) / yesterdayData.active_users) * 100 : 0;
    const sessionsPct = yesterdayData.sessions > 0 ? ((todayData.sessions - yesterdayData.sessions) / yesterdayData.sessions) * 100 : 0;

    const maxSessions = Math.max(...dailyMetrics.map((d: any) => d.sessions || 0), 1);
    const maxHourly = Math.max(...hourlyData.map((d: any) => d.avg_sessions || 0), 1);

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Analytics: Visão Geral</h1>
                    <p className="text-gray-500 text-sm mt-1">Métricas diárias e consolidado dos acessos ao sistema.</p>
                </div>
                <div className="flex gap-2">
                    <Link to="/admin/analytics/realtime" className="px-4 py-2 bg-green-50 text-green-700 font-medium rounded-lg hover:bg-green-100 transition-colors flex items-center gap-2 text-sm border border-green-200">
                        <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        Tempo Real
                    </Link>
                </div>
            </div>

            {/* Warning */}
            {!analyticsEnabled && (
                <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg shadow-sm">
                    <div className="flex items-start">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm text-yellow-700">
                                O Analytics avançado não está configurado completamente.
                                <Link to="/admin/integrations" className="font-bold underline ml-1">Vá para configurações</Link> para inserir o JSON Service Account e ativar o sync.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Insights */}
            {insights.length > 0 && (
                <div className="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8 shadow-sm">
                    <h3 className="flex items-center gap-2 text-blue-800 font-bold mb-3">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Insights Automáticos
                    </h3>
                    <ul className="space-y-2">
                        {insights.map((insight: string, idx: number) => (
                            <li key={idx} className="flex items-start gap-2 text-sm text-blue-700">
                                <span className="mt-1 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                {insight}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Intern Menu */}
            <div className="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
                <Link to="/admin/analytics" className="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Visão Geral</Link>
                <Link to="/admin/analytics/behavior" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</Link>
                <Link to="/admin/analytics/acquisition" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</Link>
                <Link to="/admin/analytics/conversion" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</Link>
                <Link to="/admin/analytics/monetization" className="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</Link>
            </div>

            {/* Cards Principais */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {/* Usuários Ativos */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-indigo-300 transition-colors">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Usuários Ativos (Hoje)</p>
                            <h3 className="text-3xl font-bold text-gray-800 mt-2">{formatNumber(todayData.active_users)}</h3>
                        </div>
                        <div className="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                    </div>
                    {yesterdayData.active_users > 0 ? (
                        <div className={`mt-4 flex items-center gap-1.5 text-xs font-medium ${usersPct >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={usersPct >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3'} />
                            </svg>
                            <span>{formatPercent(Math.abs(usersPct))}% vs Ontem</span>
                        </div>
                    ) : (
                        <div className="mt-4 text-xs font-medium text-gray-400">Dados insuficientes</div>
                    )}
                </div>

                {/* Sessões */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-blue-300 transition-colors">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Sessões (Hoje)</p>
                            <h3 className="text-3xl font-bold text-gray-800 mt-2">{formatNumber(todayData.sessions)}</h3>
                        </div>
                        <div className="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </div>
                    </div>
                    {yesterdayData.sessions > 0 ? (
                        <div className={`mt-4 flex items-center gap-1.5 text-xs font-medium ${sessionsPct >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={sessionsPct >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3'} />
                            </svg>
                            <span>{formatPercent(Math.abs(sessionsPct))}% vs Ontem</span>
                        </div>
                    ) : (
                        <div className="mt-4 text-xs font-medium text-gray-400">Dados insuficientes</div>
                    )}
                </div>

                {/* Bounce Rate */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-orange-300 transition-colors">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Taxa de Rejeição</p>
                            <h3 className="text-3xl font-bold text-gray-800 mt-2">{formatPercent(todayData.bounce_rate * 100)}%</h3>
                        </div>
                        <div className="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center group-hover:bg-orange-600 group-hover:text-white transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                    </div>
                </div>

                {/* Tempo Sessão */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-emerald-300 transition-colors">
                    <div className="flex justify-between items-start">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo Médio</p>
                            <h3 className="text-3xl font-bold text-gray-800 mt-2">
                                {formatTime(todayData.avg_session_duration)}
                            </h3>
                        </div>
                        <div className="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                </div>
            </div>

            {/* Gráficos */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-bold text-gray-800 mb-6">Tendência de Sessões (Últimos Dias)</h3>
                    <div className="relative h-64 w-full">
                        <div className="flex items-end justify-between h-48 w-full gap-1 border-b border-l border-gray-200 pl-2 pb-2">
                            {dailyMetrics.map((day: any, i: number) => {
                                const height = maxSessions > 0 ? (day.sessions / maxSessions) * 100 : 0;
                                return (
                                    <div key={i} className="w-full bg-blue-500 hover:bg-blue-600 transition-all rounded-t-sm group relative flex flex-col justify-end" style={{ height: `${height}%` }}>
                                        <div className="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-10">
                                            {day.date ? new Date(day.date).toLocaleDateString() : ''}: {day.sessions}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="flex justify-between text-[10px] text-gray-400 mt-2 pl-2">
                            <span>{dailyMetrics.length > 0 ? new Date(dailyMetrics[0].date).toLocaleDateString() : ''}</span>
                            <span>Hoje</span>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-bold text-gray-800 mb-6">Horários de Pico (Média Global)</h3>
                    <div className="relative h-64 w-full">
                        <div className="flex items-end justify-between h-48 w-full gap-1 border-b border-l border-gray-200 pl-2 pb-2">
                            {Array.from({ length: 24 }).map((_, hour) => {
                                const item = hourlyData.find((d: any) => d.hour === hour);
                                const avg = item ? item.avg_sessions : 0;
                                const height = maxHourly > 0 ? (avg / maxHourly) * 100 : 0;
                                return (
                                    <div key={hour} className="w-full bg-indigo-500 hover:bg-indigo-600 transition-all rounded-t-sm group relative flex flex-col justify-end" style={{ height: `${height}%` }}>
                                        <div className="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-10">
                                            {hour.toString().padStart(2, '0')}:00 - {formatNumber(avg)}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="flex justify-between text-[10px] text-gray-400 mt-2 pl-2">
                            <span>00:00</span>
                            <span>12:00</span>
                            <span>23:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
