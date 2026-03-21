import React from 'react';
import { RealtimeData } from './Types';

interface ServiceStatusGridProps {
    realtimeData: RealtimeData;
}

const ServiceStatusGrid: React.FC<ServiceStatusGridProps> = ({ realtimeData }) => {
    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4 border-l-4 border-blue-500">
                <div className="p-3 bg-blue-50 text-blue-600 rounded-lg">
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                    </svg>
                </div>
                <div>
                    <p className="text-sm font-medium text-gray-500">Uptime Sistema</p>
                    <p className="text-lg font-bold text-gray-900">{realtimeData.uptime || '0s'}</p>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                <div className={`p-3 rounded-lg ${realtimeData.services?.webserver ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
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
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
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
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
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
    );
};

export default ServiceStatusGrid;
