import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

interface TopProcess {
    pid: string;
    name: string;
    cpu: number;
    mem_bytes: number;
}

interface ServiceHealth {
    database: boolean;
    redis: boolean;
    app: boolean;
    webserver: boolean;
}

interface QueueStats {
    pending: number;
    failed: number;
    workers: string;
}

interface PerformanceMetric {
    jobs_per_minute: number;
    avg_duration_seconds: number;
}

interface RealtimeData {
    cpu_usage: number;
    ram_usage: number;
    ram_used_gb: number;
    ram_total_gb: number;
    disk_usage: number;
    disk_used_gb: number;
    disk_total_gb: number;
    net_rx_speed: number;
    net_tx_speed: number;
    uptime?: string;
    services?: ServiceHealth;
    queues?: QueueStats;
    queue_performance?: Record<string, PerformanceMetric>;
    top_processes?: TopProcess[];
}

interface QueueJob {
    id: number;
    queue: string;
    name: string;
    attempts: number;
    is_processing: boolean;
    created_at: string;
}

interface FailedJob {
    id: number;
    queue: string;
    name: string;
    exception: string;
    failed_at: string;
}

interface CompletedBatch {
    id: string;
    name: string;
    total_jobs: number;
    failed_jobs: number;
    finished_at: string;
}

export default function Monitor() {
    const [currentRange, setCurrentRange] = useState('24h');
    const [activeTab, setActiveTab] = useState<'pending' | 'failed' | 'completed'>('pending');
    const [realtimeData, setRealtimeData] = useState<RealtimeData>({
        cpu_usage: 0, ram_usage: 0, ram_used_gb: 0, ram_total_gb: 0,
        disk_usage: 0, disk_used_gb: 0, disk_total_gb: 0,
        net_rx_speed: 0, net_tx_speed: 0,
        uptime: '0s',
        services: { database: false, redis: false, app: false, webserver: false },
        queues: { pending: 0, failed: 0, workers: 'Offline' },
        top_processes: []
    });

    // Formata bytes em string legível (B/s, KB/s, MB/s...)
    const formatBytes = (bytes: number, decimals = 1) => {
        if (!Number(bytes)) return '0 B/s';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    // Formata GB com 2 casas decimais
    const fmtGb = (val: number) => val > 0 ? `${val.toFixed(2)} GB` : '—';

    const { data: historyData, isLoading } = useQuery({
        queryKey: ['admin-monitor-history', currentRange],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/monitor/history?range=${currentRange}`);
            return res.data || [];
        },
        refetchInterval: 60000
    });

    const { data: logsData } = useQuery({
        queryKey: ['admin-monitor-logs'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/monitor/logs');
            return res.data?.logs || [];
        },
        refetchInterval: 10000 // A cada 10s
    });

    const { data: queuesData } = useQuery({
        queryKey: ['admin-monitor-queues'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/monitor/queues');
            return res.data || { jobs: [], failed: [], completed: [] };
        },
        refetchInterval: 5000 // A cada 5s
    });

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        const fetchRealtime = async () => {
            try {
                const res = await api.get('/api/v1/admin/monitor/realtime');
                if (res.data) setRealtimeData(res.data);
            } catch (e) { /* silent */ }
        };
        fetchRealtime();
        interval = setInterval(fetchRealtime, 1000); // TAXA DE ATUALIZAÇÃO 1s (Desejo do Usuário)
        return () => clearInterval(interval);
    }, []);

    const labels = Array.isArray(historyData) ? historyData.map((d: any) => d.created_at ? new Date(d.created_at).toLocaleTimeString() : '') : [];

    const resourceChartData = {
        labels,
        datasets: [
            {
                label: 'CPU %',
                data: Array.isArray(historyData) ? historyData.map((d: any) => d.cpu_usage || 0) : [],
                borderColor: '#2563EB', backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 2, tension: 0.4, fill: true,
            },
            {
                label: 'RAM %',
                data: Array.isArray(historyData) ? historyData.map((d: any) => d.ram_usage || 0) : [],
                borderColor: '#9333EA', backgroundColor: 'rgba(147, 51, 234, 0.1)',
                borderWidth: 2, tension: 0.4, fill: true,
            }
        ]
    };

    const networkChartData = {
        labels,
        datasets: [
            {
                label: 'Download (RX)',
                data: Array.isArray(historyData) ? historyData.map((d: any) => d.net_rx_speed || 0) : [],
                borderColor: '#10B981', backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2, tension: 0.4, fill: true,
            },
            {
                label: 'Upload (TX)',
                data: Array.isArray(historyData) ? historyData.map((d: any) => d.net_tx_speed || 0) : [],
                borderColor: '#EA580C', backgroundColor: 'rgba(234, 88, 12, 0.1)',
                borderWidth: 2, tension: 0.4, fill: true,
            }
        ]
    };

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="mb-8 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Monitoramento VPS</h1>
                    <p className="text-gray-600 font-medium">Acompanhe a saúde do servidor em tempo real (atualização: <span className="text-blue-600 font-black animate-pulse">1s</span>)</p>
                </div>
                <div className="bg-white rounded-lg shadow-sm p-1 inline-flex">
                    {['1h', '24h', '7d'].map(r => (
                        <button key={r} onClick={() => setCurrentRange(r)}
                            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${currentRange === r ? 'bg-blue-50 text-blue-600 shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}>
                            {r === '1h' ? '1 Hora' : r === '24h' ? '24 Horas' : '7 Dias'}
                        </button>
                    ))}
                </div>
            </div>

            {/* Services & Uptime */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4 border-l-4 border-blue-500">
                    <div className="p-3 bg-blue-50 text-blue-600 rounded-lg">
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-500">Uptime Systema</p>
                        <p className="text-lg font-bold text-gray-900">{realtimeData.uptime || '0s'}</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                    <div className={`p-3 rounded-lg ${realtimeData.services?.webserver ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-500">Webserver (Nginx)</p>
                        <p className="text-sm font-bold text-gray-900 flex items-center gap-1">
                            <span className={`w-2 h-2 rounded-full ${realtimeData.services?.webserver ? 'bg-green-500' : 'bg-red-500'}`}></span>
                            {realtimeData.services?.webserver ? 'Online' : 'Offline'}
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                    <div className={`p-3 rounded-lg ${realtimeData.services?.database ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-500">Database (MySQL)</p>
                        <p className="text-sm font-bold text-gray-900 flex items-center gap-1">
                            <span className={`w-2 h-2 rounded-full ${realtimeData.services?.database ? 'bg-green-500' : 'bg-red-500'}`}></span>
                            {realtimeData.services?.database ? 'Online' : 'Offline'}
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                    <div className={`p-3 rounded-lg ${realtimeData.services?.redis ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-500">Cache (Redis)</p>
                        <p className="text-sm font-bold text-gray-900 flex items-center gap-1">
                            <span className={`w-2 h-2 rounded-full ${realtimeData.services?.redis ? 'bg-green-500' : 'bg-red-500'}`}></span>
                            {realtimeData.services?.redis ? 'Online' : 'Offline'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Gauges - 2 rows: CPU/RAM/Disk + Net */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                {/* CPU */}
                <div className="bg-white rounded-xl shadow-sm p-4 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-2">
                        <div>
                            <h3 className="text-xs font-medium text-gray-500 uppercase">CPU</h3>
                            <div className="text-xl font-bold text-gray-900 mt-1">{(realtimeData.cpu_usage || 0).toFixed(1)}%</div>
                        </div>
                        <div className="p-1.5 bg-blue-50 rounded-lg text-blue-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                        <div className="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style={{ width: `${Math.min(realtimeData.cpu_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* RAM */}
                <div className="bg-white rounded-xl shadow-sm p-4 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-2">
                        <div>
                            <h3 className="text-xs font-medium text-gray-500 uppercase">RAM</h3>
                            <div className="flex items-baseline gap-2 mt-1">
                                <div className="text-xl font-bold text-gray-900">{(realtimeData.ram_usage || 0).toFixed(1)}%</div>
                                <span className="text-xs text-gray-400">{fmtGb(realtimeData.ram_used_gb)}/{fmtGb(realtimeData.ram_total_gb)}</span>
                            </div>
                        </div>
                        <div className="p-1.5 bg-purple-50 rounded-lg text-purple-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                        <div className="bg-purple-600 h-1.5 rounded-full transition-all duration-500" style={{ width: `${Math.min(realtimeData.ram_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* Disco */}
                <div className="bg-white rounded-xl shadow-sm p-4 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-2">
                        <div>
                            <h3 className="text-xs font-medium text-gray-500 uppercase">Disco</h3>
                            <div className="flex items-baseline gap-2 mt-1">
                                <span className="text-xl font-bold text-gray-900">{(realtimeData.disk_usage || 0).toFixed(1)}%</span>
                                <span className="text-xs text-gray-400">{fmtGb(realtimeData.disk_used_gb)}/{fmtGb(realtimeData.disk_total_gb)}</span>
                            </div>
                        </div>
                        <div className="p-1.5 bg-amber-50 rounded-lg text-amber-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                        <div className={`h-1.5 rounded-full transition-all duration-500 ${(realtimeData.disk_usage || 0) > 85 ? 'bg-red-500' : 'bg-amber-500'}`}
                            style={{ width: `${Math.min(realtimeData.disk_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* Rede */}
                <div className="bg-white rounded-xl shadow-sm p-4">
                    <h3 className="text-xs font-medium text-gray-500 uppercase mb-2">Rede (I/O)</h3>
                    <div className="space-y-1 mt-1">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-xs text-green-600 font-bold flex items-center gap-1"><svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M19 14l-7 7m0 0l-7-7" /></svg>RX</span>
                            <span className="font-bold text-gray-700">{formatBytes(realtimeData.net_rx_speed)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-xs text-orange-600 font-bold flex items-center gap-1"><svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 10l7-7m0 0l7 7" /></svg>TX</span>
                            <span className="font-bold text-gray-700">{formatBytes(realtimeData.net_tx_speed)}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Charts */}
            {isLoading ? (
                <div className="text-center py-12 text-gray-500">Carregando gráficos de histórico...</div>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4">Uso de Recursos (%)</h3>
                        <div className="relative h-72">
                            <Line data={resourceChartData} options={{
                                responsive: true, maintainAspectRatio: false,
                                scales: { y: { beginAtZero: true, max: 100 }, x: { display: false } },
                                interaction: { mode: 'index', intersect: false }
                            }} />
                        </div>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4">Tráfego de Rede</h3>
                        <div className="relative h-72">
                            <Line data={networkChartData} options={{
                                responsive: true, maintainAspectRatio: false,
                                scales: {
                                    y: { beginAtZero: true, ticks: { callback: function (value: any) { return typeof value === 'number' ? formatBytes(value) : value; } } },
                                    x: { display: false }
                                },
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function (context: any) {
                                                let label = context.dataset.label || '';
                                                if (label) label += ': ';
                                                if (context.parsed.y !== null) label += formatBytes(context.parsed.y);
                                                return label;
                                            }
                                        }
                                    }
                                }
                            }} />
                        </div>
                    </div>
                </div>
            )}

            {/* Split View: Top Processes & Queues */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                {/* Top Processes (Col-span 2) */}
                <div className="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 overflow-hidden">
                    <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        Top Processes (Host Base)
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3">PID</th>
                                    <th className="px-4 py-3">Process Name</th>
                                    <th className="px-4 py-3 text-right">CPU %</th>
                                    <th className="px-4 py-3 text-right">RAM</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(realtimeData.top_processes || []).map(proc => (
                                    <tr key={proc.pid} className="border-b hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium text-gray-900">{proc.pid}</td>
                                        <td className="px-4 py-3 text-gray-700 font-mono">{proc.name}</td>
                                        <td className="px-4 py-3 text-right font-medium text-blue-600">{proc.cpu.toFixed(1)}%</td>
                                        <td className="px-4 py-3 text-right text-purple-600">{formatBytes(proc.mem_bytes)}</td>
                                    </tr>
                                ))}
                                {(!realtimeData.top_processes || realtimeData.top_processes.length === 0) && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-gray-500">Nenhum processo detectado. Requer permissão read-only no /proc (Linux via Docker -v /proc:/host_proc).</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Queue Summary / Stats (Col-span 1) */}
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        Filas & Trabalhadores
                    </h3>

                    <div className="space-y-4">
                        <div className="p-4 rounded-lg bg-gray-50 border border-gray-100 flex justify-between items-center">
                            <div>
                                <p className="text-sm text-gray-500 font-medium">Jobs Pendentes</p>
                                <p className="text-2xl font-bold text-gray-900 mt-1">{realtimeData.queues?.pending || 0}</p>
                            </div>
                            <div className="p-3 bg-blue-100 text-blue-600 rounded-full">
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                        </div>

                        <div className="p-4 rounded-lg bg-gray-50 border border-gray-100 flex justify-between items-center">
                            <div>
                                <p className="text-sm text-gray-500 font-medium">Jobs Falhados</p>
                                <p className="text-2xl font-bold text-red-600 mt-1">{realtimeData.queues?.failed || 0}</p>
                            </div>
                            <div className="p-3 bg-red-100 text-red-600 rounded-full">
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        </div>

                        <div className="p-4 rounded-lg bg-gray-50 border border-gray-100 flex justify-between items-center">
                            <div>
                                <p className="text-sm text-gray-500 font-medium">Status do Worker</p>
                                <p className="text-sm font-bold text-gray-900 mt-1 flex items-center gap-1">
                                    <span className={`w-2 h-2 rounded-full ${realtimeData.queues?.workers === 'Ativo' ? 'bg-green-500' : 'bg-red-500'}`}></span>
                                    {realtimeData.queues?.workers || 'Desconhecido'}
                                </p>
                            </div>
                            <div className="p-3 bg-green-100 text-green-600 rounded-full">
                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Performance Metrics Section (NEW) */}
            <div className="mt-8">
                <h2 className="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <svg className="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Performance dos Workers (Vazão e Latência)
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {Object.entries(realtimeData.queue_performance || {}).map(([queue, perf]) => (
                        <div key={queue} className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 transition-all hover:shadow-md">
                            <div className="flex justify-between items-start mb-3">
                                <span className={`px-2 py-0.5 text-[10px] font-black uppercase rounded ${queue === 'ai-batches' ? 'bg-purple-100 text-purple-700' :
                                        queue === 'essays' ? 'bg-blue-100 text-blue-700' :
                                            'bg-gray-100 text-gray-600'
                                    }`}>
                                    Fila: {queue}
                                </span>
                                <div className="animate-ping w-2 h-2 rounded-full bg-green-400"></div>
                            </div>

                            <div className="space-y-3">
                                <div className="flex justify-between items-end">
                                    <span className="text-xs text-gray-500 font-medium italic">Vazão (Realtime)</span>
                                    <div className="text-right">
                                        <div className="text-xl font-black text-gray-900 leading-none">{perf.jobs_per_minute}</div>
                                        <div className="text-[10px] text-gray-400 font-bold">jobs / min</div>
                                    </div>
                                </div>

                                <div className="flex justify-between items-end">
                                    <span className="text-xs text-gray-500 font-medium italic">Duração Média</span>
                                    <div className="text-right">
                                        <div className="text-xl font-black text-blue-600 leading-none">{perf.avg_duration_seconds.toFixed(2)}s</div>
                                        <div className="text-[10px] text-gray-400 font-bold">por job</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                    {(!realtimeData.queue_performance || Object.keys(realtimeData.queue_performance).length === 0) && (
                        <div className="col-span-full p-6 text-center text-gray-400 italic text-sm border-2 border-dashed border-gray-100 rounded-xl bg-gray-50">
                            Aguardando atividade nos workers para coletar métricas de vazão e latência...
                        </div>
                    )}
                </div>
            </div>

            {/* Tabbed Detailed Queues */}
            <div className="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="flex border-b border-gray-100 bg-gray-50/50">
                    <button
                        onClick={() => setActiveTab('pending')}
                        className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${activeTab === 'pending' ? 'border-blue-500 text-blue-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`}
                    >
                        Jobs Pendentes/Ativos
                        <span className="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">{queuesData?.jobs?.length || 0}</span>
                    </button>
                    <button
                        onClick={() => setActiveTab('completed')}
                        className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${activeTab === 'completed' ? 'border-green-500 text-green-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`}
                    >
                        Lotes Concluídos
                        <span className="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">{queuesData?.completed?.length || 0}</span>
                    </button>
                    <button
                        onClick={() => setActiveTab('failed')}
                        className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${activeTab === 'failed' ? 'border-red-500 text-red-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'}`}
                    >
                        Falhas Recentes
                        <span className="bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs font-bold">{queuesData?.failed?.length || 0}</span>
                    </button>
                </div>

                <div className="p-0">
                    {/* Active/Pending Jobs Tab */}
                    {activeTab === 'pending' && (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left text-gray-500">
                                <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3">ID</th>
                                        <th className="px-4 py-3">Classe (Job)</th>
                                        <th className="px-4 py-3">Fila</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Criado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(queuesData?.jobs || []).length > 0 ? (
                                        queuesData?.jobs.map((job: QueueJob) => (
                                            <tr key={job.id} className="border-b hover:bg-gray-50">
                                                <td className="px-4 py-3 font-medium text-gray-900">#{job.id}</td>
                                                <td className="px-4 py-3 font-mono text-blue-600">{job.name}</td>
                                                <td className="px-4 py-3">
                                                    <span className="px-2 py-1 text-[10px] font-bold uppercase bg-gray-100 text-gray-600 rounded">
                                                        {job.queue}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {job.is_processing ? (
                                                        <span className="px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full flex items-center w-max gap-1">
                                                            <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                            Processando
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-1 text-xs font-semibold bg-amber-100 text-amber-700 rounded-full">
                                                            Pendente (Tentativas: {job.attempts})
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-400">
                                                    {new Date(job.created_at).toLocaleTimeString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-8 text-center text-gray-500 italic">Nenhum job pendente no momento. Tudo limpo! 🎉</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Completed Batches Tab */}
                    {activeTab === 'completed' && (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left text-gray-500">
                                <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3">Lote ID / Nome</th>
                                        <th className="px-4 py-3 text-center">Jobs Totais</th>
                                        <th className="px-4 py-3 text-center">Falhas no Lote</th>
                                        <th className="px-4 py-3 text-right">Data de Conclusão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(queuesData?.completed || []).length > 0 ? (
                                        queuesData?.completed.map((batch: CompletedBatch) => (
                                            <tr key={batch.id} className="border-b hover:bg-gray-50">
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-gray-900">{batch.name}</div>
                                                    <div className="font-mono text-[10px] text-gray-400">{batch.id}</div>
                                                </td>
                                                <td className="px-4 py-3 text-center font-medium text-gray-700">{batch.total_jobs}</td>
                                                <td className="px-4 py-3 text-center">
                                                    {batch.failed_jobs > 0 ? (
                                                        <span className="text-red-500 font-bold">{batch.failed_jobs}</span>
                                                    ) : (
                                                        <span className="text-green-500">—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-500">
                                                    {new Date(batch.finished_at).toLocaleString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-8 text-center text-gray-500 italic">Nenhum lote foi concluído recentemente.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Failed Jobs Tab */}
                    {activeTab === 'failed' && (
                        <div className="overflow-x-auto bg-red-50/20">
                            <table className="w-full text-sm text-left text-gray-500">
                                <thead className="text-xs text-red-800 uppercase bg-red-50">
                                    <tr>
                                        <th className="px-4 py-3">ID</th>
                                        <th className="px-4 py-3">Classe</th>
                                        <th className="px-4 py-3">Erro (Exception)</th>
                                        <th className="px-4 py-3 text-right">Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(queuesData?.failed || []).length > 0 ? (
                                        queuesData?.failed.map((job: FailedJob) => (
                                            <tr key={job.id} className="border-b border-gray-100 hover:bg-red-50/50">
                                                <td className="px-4 py-3 font-medium text-gray-900">#{job.id}</td>
                                                <td className="px-4 py-3 font-mono text-gray-600">{job.name}</td>
                                                <td className="px-4 py-3 text-red-600 max-w-xl truncate" title={job.exception}>
                                                    {job.exception}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-500">
                                                    {new Date(job.failed_at).toLocaleString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-8 text-center text-gray-500 italic">Nenhuma falha registrada! Ótimo trabalho. 👍</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* System Logs */}
            <div className="mt-8 bg-gray-900 rounded-xl shadow-sm overflow-hidden border border-gray-800">
                <div className="px-4 py-3 bg-gray-800 border-b border-gray-700 flex justify-between items-center">
                    <h3 className="text-sm font-bold text-gray-200 flex items-center gap-2">
                        <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Terminal &gt; storage/logs/laravel.log
                    </h3>
                    <span className="text-xs text-gray-500 font-mono text-green-400">Ao vivo (10s)</span>
                </div>
                <div className="p-4 bg-black h-80 overflow-y-auto text-sm font-mono leading-relaxed" id="logs-terminal">
                    {(!logsData || logsData.length === 0) ? (
                        <p className="text-gray-500 italic">O arquivo de log está vazio ou não pôde ser lido atualmente.</p>
                    ) : (
                        logsData.map((line: string, idx: number) => {
                            let color = 'text-gray-300';
                            if (line.includes('ERROR') || line.includes('Exception') || line.includes('Failed') || line.includes('Stack trace')) color = 'text-red-400';
                            else if (line.includes('WARNING')) color = 'text-yellow-400';
                            else if (line.includes('INFO') || line.includes('Success')) color = 'text-blue-300';
                            else if (line.startsWith('#')) color = 'text-gray-500'; // stacktrace items

                            return (
                                <div key={idx} className={`${color} break-words hover:bg-gray-800 px-1 rounded`}>
                                    {line}
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </div>
    );
}
