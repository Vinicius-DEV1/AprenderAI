/**
 * Monitor
 * Main entry point for the System Monitor dashboard.
 * Displays real-time resource usage, service health, and background queue status.
 */
import { useMonitor } from './monitor/useMonitor';
import MonitorHeader from './monitor/MonitorHeader';
import ServiceStatusGrid from './monitor/ServiceStatusGrid';
import ResourceGauges from './monitor/ResourceGauges';
import ResourceCharts from './monitor/ResourceCharts';
import TopProcesses from './monitor/TopProcesses';
import QueueStatsPanel from './monitor/QueueStatsPanel';
import WorkerPerformance from './monitor/WorkerPerformance';
import QueueTabs from './monitor/QueueTabs';
import SystemLogs from './monitor/SystemLogs';

export default function Monitor() {
    const {
        currentRange,
        setCurrentRange,
        activeTab,
        setActiveTab,
        realtimeData,
        isHistoryLoading,
        logsData,
        queuesData,
        formatBytes,
        fmtGb,
        resourceChartData,
        networkChartData
    } = useMonitor();

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <MonitorHeader 
                currentRange={currentRange} 
                setCurrentRange={setCurrentRange} 
            />

            <ServiceStatusGrid realtimeData={realtimeData} />

            <ResourceGauges 
                realtimeData={realtimeData} 
                fmtGb={fmtGb} 
                formatBytes={formatBytes} 
            />

            <ResourceCharts 
                isHistoryLoading={isHistoryLoading}
                resourceChartData={resourceChartData}
                networkChartData={networkChartData}
                formatBytes={formatBytes}
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                <TopProcesses 
                    realtimeData={realtimeData} 
                    formatBytes={formatBytes} 
                />
                <QueueStatsPanel realtimeData={realtimeData} />
            </div>

            <WorkerPerformance realtimeData={realtimeData} />

            <QueueTabs 
                activeTab={activeTab} 
                setActiveTab={setActiveTab} 
                queuesData={queuesData} 
            />

            <SystemLogs logsData={logsData} />
        </div>
    );
}
