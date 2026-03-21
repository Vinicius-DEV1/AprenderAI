import React from 'react';

interface SystemLogsProps {
    logsData: string[];
}

const SystemLogs: React.FC<SystemLogsProps> = ({ logsData }) => {
    return (
        <div className="mt-8 bg-gray-900 rounded-xl shadow-sm overflow-hidden border border-gray-800">
            <div className="px-4 py-3 bg-gray-800 border-b border-gray-700 flex justify-between items-center">
                <h3 className="text-sm font-bold text-gray-200 flex items-center gap-2">
                    <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
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
    );
};

export default SystemLogs;
