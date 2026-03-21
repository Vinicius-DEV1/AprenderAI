import React from 'react';
import { RealtimeData } from './Types';

interface TopProcessesProps {
    realtimeData: RealtimeData;
    formatBytes: (bytes: number) => string;
}

const TopProcesses: React.FC<TopProcessesProps> = ({ realtimeData, formatBytes }) => {
    return (
        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 overflow-hidden">
            <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
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
                                <td colSpan={4} className="px-4 py-8 text-center text-gray-500">
                                    Nenhum processo detectado. Requer permissão read-only no /proc (Linux via Docker -v /proc:/host_proc).
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default TopProcesses;
