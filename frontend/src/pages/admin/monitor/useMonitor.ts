import { useState, useEffect, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../../api/axios';
import { toast } from 'sonner';
import { RealtimeData, QueuesData } from './Types';

export const useMonitor = () => {
    // --- State Management ---
    const [currentRange, setCurrentRange] = useState('24h');
    const [activeTab, setActiveTab] = useState<'pending' | 'failed' | 'completed'>('pending');
    
    // Realtime metrics for the gaugues (1s polling)
    const [realtimeData, setRealtimeData] = useState<RealtimeData>({
        cpu_usage: 0, ram_usage: 0, ram_used_gb: 0, ram_total_gb: 0,
        disk_usage: 0, disk_used_gb: 0, disk_total_gb: 0,
        net_rx_speed: 0, net_tx_speed: 0,
        uptime: '0s',
        services: { database: false, redis: false, app: false, webserver: false },
        queues: { pending: 0, failed: 0, workers: 'Offline' },
        top_processes: []
    });

    /**
     * formatBytes
     * Formats network speeds or file sizes into human-readable strings (e.g. 1.2 MB/s).
     */
    const formatBytes = (bytes: number, decimals = 1) => {
        if (!Number(bytes)) return '0 B/s';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    /**
     * fmtGb
     * Formats numbers into Gigabytes string.
     */
    const fmtGb = (val: number) => val > 0 ? `${val.toFixed(2)} GB` : '—';

    // --- Queries ---

    // Historical resource usage for charts
    const { data: historyData, isLoading: isHistoryLoading } = useQuery({
        queryKey: ['admin-monitor-history', currentRange],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/monitor/history?range=${currentRange}`);
            return res.data || [];
        },
        refetchInterval: 60000 // Refresh history every 1 minute
    });

    // Recent system/laravel logs
    const { data: logsData, refetch: refetchLogs } = useQuery({
        queryKey: ['admin-monitor-logs'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/monitor/logs');
            return res.data?.logs || [];
        },
        refetchInterval: 10000 // Refresh logs every 10 seconds
    });

    const clearLogs = async () => {
        try {
            await api.delete('/api/v1/admin/monitor/logs');
            refetchLogs();
        } catch (error) {
            console.error('Failed to clear logs:', error);
        }
    };

    // Detailed queue information (jobs, failures, completed batches)
    const { data: queuesData, refetch: refetchQueues } = useQuery<QueuesData>({
        queryKey: ['admin-monitor-queues'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/monitor/queues');
            return res.data || { jobs: [], failed: [], completed: [] };
        },
        refetchInterval: 5000 // Refresh queue tables every 5 seconds
    });

    // --- Realtime Polling ---
    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        const fetchRealtime = async () => {
            try {
                const res = await api.get('/api/v1/admin/monitor/realtime');
                if (res.data) setRealtimeData(res.data);
            } catch (e) { /* silent fail on network error */ }
        };
        
        fetchRealtime();
        interval = setInterval(fetchRealtime, 1000); // Strict 1s polling for resource gauges
        
        return () => clearInterval(interval);
    }, []);

    // --- Chart Data Preparation ---
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
        networkChartData,
        clearLogs,
        refetchLogs,
        clearPendingTriage: async () => {
            try {
                await api.delete('/api/v1/admin/monitor/pending-triage');
                toast.success('Fila de triagem limpa com sucesso');
                refetchQueues();
            } catch (error) {
                toast.error('Falha ao limpar fila de triagem');
            }
        }
    };
};
