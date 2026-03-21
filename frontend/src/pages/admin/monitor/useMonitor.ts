import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { RealtimeData, QueuesData } from './Types';

export const useMonitor = () => {
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

    const formatBytes = (bytes: number, decimals = 1) => {
        if (!Number(bytes)) return '0 B/s';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    const fmtGb = (val: number) => val > 0 ? `${val.toFixed(2)} GB` : '—';

    const { data: historyData, isLoading: isHistoryLoading } = useQuery({
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
        refetchInterval: 10000
    });

    const { data: queuesData } = useQuery<QueuesData>({
        queryKey: ['admin-monitor-queues'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/monitor/queues');
            return res.data || { jobs: [], failed: [], completed: [] };
        },
        refetchInterval: 5000
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
        interval = setInterval(fetchRealtime, 1000);
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

    return {
        currentRange,
        setCurrentRange,
        activeTab,
        setActiveTab,
        realtimeData,
        historyData,
        isHistoryLoading,
        logsData,
        queuesData,
        formatBytes,
        fmtGb,
        resourceChartData,
        networkChartData
    };
};
