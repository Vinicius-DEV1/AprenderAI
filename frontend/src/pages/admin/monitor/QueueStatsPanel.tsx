import React from 'react';
import { RealtimeData } from './Types';

interface QueueStatsPanelProps {
    realtimeData: RealtimeData;
}

const QueueStatsPanel: React.FC<QueueStatsPanelProps> = ({ realtimeData }) => {
    return (
        <div className="bg-white rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Filas & Trabalhadores
            </h3>

            <div className="space-y-4">
                <div className="p-4 rounded-lg bg-gray-50 border border-gray-100 flex justify-between items-center">
                    <div>
                        <p className="text-sm text-gray-500 font-medium">Jobs Pendentes</p>
                        <p className="text-2xl font-bold text-gray-900 mt-1">{realtimeData.queues?.pending || 0}</p>
                    </div>
                    <div className="p-3 bg-blue-100 text-blue-600 rounded-full">
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <div className="p-4 rounded-lg bg-gray-50 border border-gray-100 flex justify-between items-center">
                    <div>
                        <p className="text-sm text-gray-500 font-medium">Jobs Falhados</p>
                        <p className="text-2xl font-bold text-red-600 mt-1">{realtimeData.queues?.failed || 0}</p>
                    </div>
                    <div className="p-3 bg-red-100 text-red-600 rounded-full">
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
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
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default QueueStatsPanel;
