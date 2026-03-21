import React from 'react';
import { RealtimeData } from './Types';

interface WorkerPerformanceProps {
    realtimeData: RealtimeData;
}

const WorkerPerformance: React.FC<WorkerPerformanceProps> = ({ realtimeData }) => {
    return (
        <div className="mt-8">
            <h2 className="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg className="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Performance dos Workers (Vazão e Latência)
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {Object.entries(realtimeData.queue_performance || {}).map(([queue, perf]) => (
                    <div key={queue} className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 transition-all hover:shadow-md">
                        <div className="flex justify-between items-start mb-3">
                            <span className={`px-2 py-0.5 text-[10px] font-black uppercase rounded ${
                                queue === 'ai-batches' ? 'bg-purple-100 text-purple-700' :
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
    );
};

export default WorkerPerformance;
