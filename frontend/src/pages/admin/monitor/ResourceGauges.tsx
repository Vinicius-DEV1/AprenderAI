import React from 'react';
import { RealtimeData } from './Types';

interface ResourceGaugesProps {
    realtimeData: RealtimeData;
    fmtGb: (val: number) => string;
    formatBytes: (bytes: number) => string;
}

const ResourceGauges: React.FC<ResourceGaugesProps> = ({ realtimeData, fmtGb, formatBytes }) => {
    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {/* CPU */}
            <div className="bg-white rounded-xl shadow-sm p-4 relative overflow-hidden">
                <div className="flex justify-between items-start mb-2">
                    <div>
                        <h3 className="text-xs font-medium text-gray-500 uppercase">CPU</h3>
                        <div className="text-xl font-bold text-gray-900 mt-1">{(realtimeData.cpu_usage || 0).toFixed(1)}%</div>
                    </div>
                    <div className="p-1.5 bg-blue-50 rounded-lg text-blue-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
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
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
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
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
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
                        <span className="text-xs text-green-600 font-bold flex items-center gap-1">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M19 14l-7 7m0 0l-7-7" />
                            </svg>RX
                        </span>
                        <span className="font-bold text-gray-700">{formatBytes(realtimeData.net_rx_speed)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-xs text-orange-600 font-bold flex items-center gap-1">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 10l7-7m0 0l7 7" />
                            </svg>TX
                        </span>
                        <span className="font-bold text-gray-700">{formatBytes(realtimeData.net_tx_speed)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ResourceGauges;
