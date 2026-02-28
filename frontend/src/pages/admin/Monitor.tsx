import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

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
}

export default function Monitor() {
    const [currentRange, setCurrentRange] = useState('24h');
    const [realtimeData, setRealtimeData] = useState<RealtimeData>({
        cpu_usage: 0, ram_usage: 0, ram_used_gb: 0, ram_total_gb: 0,
        disk_usage: 0, disk_used_gb: 0, disk_total_gb: 0,
        net_rx_speed: 0, net_tx_speed: 0
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

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        const fetchRealtime = async () => {
            try {
                const res = await api.get('/api/v1/admin/monitor/realtime');
                if (res.data) setRealtimeData(res.data);
            } catch (e) { /* silent */ }
        };
        fetchRealtime();
        interval = setInterval(fetchRealtime, 5000);
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
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div className="mb-8 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Monitoramento VPS</h1>
                    <p className="text-gray-600">Acompanhe a saúde do servidor em tempo real (atualização: 5s)</p>
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

            {/* Gauges - 2 rows: CPU/RAM/Disk + Net */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {/* CPU */}
                <div className="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <h3 className="text-sm font-medium text-gray-500">CPU Usage</h3>
                            <div className="text-3xl font-bold text-gray-900 mt-1">{(realtimeData.cpu_usage || 0).toFixed(1)}%</div>
                        </div>
                        <div className="p-2 bg-blue-50 rounded-lg">
                            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                        <div className="bg-blue-600 h-2 rounded-full transition-all duration-500" style={{ width: `${Math.min(realtimeData.cpu_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* RAM — com valor absoluto */}
                <div className="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <h3 className="text-sm font-medium text-gray-500">RAM Usage</h3>
                            <div className="text-3xl font-bold text-gray-900 mt-1">{(realtimeData.ram_usage || 0).toFixed(1)}%</div>
                            <p className="text-xs text-gray-400 mt-0.5">{fmtGb(realtimeData.ram_used_gb)} / {fmtGb(realtimeData.ram_total_gb)}</p>
                        </div>
                        <div className="p-2 bg-purple-50 rounded-lg">
                            <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                        <div className="bg-purple-600 h-2 rounded-full transition-all duration-500" style={{ width: `${Math.min(realtimeData.ram_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* Disco */}
                <div className="bg-white rounded-xl shadow-sm p-6 relative overflow-hidden">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <h3 className="text-sm font-medium text-gray-500">Disco</h3>
                            <div className="text-3xl font-bold text-gray-900 mt-1">{(realtimeData.disk_usage || 0).toFixed(1)}%</div>
                            <p className="text-xs text-gray-400 mt-0.5">{fmtGb(realtimeData.disk_used_gb)} / {fmtGb(realtimeData.disk_total_gb)}</p>
                        </div>
                        <div className="p-2 bg-amber-50 rounded-lg">
                            <svg className="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                        </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                        <div className={`h-2 rounded-full transition-all duration-500 ${(realtimeData.disk_usage || 0) > 85 ? 'bg-red-500' : 'bg-amber-500'}`}
                            style={{ width: `${Math.min(realtimeData.disk_usage || 0, 100)}%` }}></div>
                    </div>
                </div>

                {/* Rede */}
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <h3 className="text-sm font-medium text-gray-500 mb-3">Tráfego de Rede</h3>
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-green-600 font-bold flex items-center gap-1"><svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M19 14l-7 7m0 0l-7-7" /></svg>RX</span>
                            <span className="font-bold text-green-600 text-sm">{formatBytes(realtimeData.net_rx_speed)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-orange-600 font-bold flex items-center gap-1"><svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 10l7-7m0 0l7 7" /></svg>TX</span>
                            <span className="font-bold text-orange-600 text-sm">{formatBytes(realtimeData.net_tx_speed)}</span>
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
                                    y: { beginAtZero: true, ticks: { callback: function (value: any) { return formatBytes(value); } } },
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
        </div>
    );
}
