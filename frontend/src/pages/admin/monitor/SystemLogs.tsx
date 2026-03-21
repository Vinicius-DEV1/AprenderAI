import React, { useState, useEffect, useRef } from 'react';
import { Search, Play, Pause } from 'lucide-react';

interface SystemLogsProps {
    logsData: string[];
    onClear: () => void;
}

const SystemLogs: React.FC<SystemLogsProps> = ({ logsData, onClear }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [autoScroll, setAutoScroll] = useState(true);
    const scrollRef = useRef<HTMLDivElement>(null);

    // Smart auto-scroll implementation
    useEffect(() => {
        if (autoScroll && scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [logsData, autoScroll]);

    const handleScroll = () => {
        if (!scrollRef.current) return;
        
        const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
        const isNearBottom = scrollHeight - scrollTop - clientHeight < 50;
        
        if (autoScroll && !isNearBottom) {
            setAutoScroll(false);
        } else if (!autoScroll && isNearBottom) {
            setAutoScroll(true);
        }
    };

    const filteredLogs = logsData?.filter(line => 
        line.toLowerCase().includes(searchTerm.toLowerCase())
    ) || [];

    return (
        <div className="mt-8 bg-gray-900 rounded-xl shadow-sm overflow-hidden border border-gray-800 flex flex-col h-[600px]">
            <div className="px-4 py-3 bg-gray-800 border-b border-gray-700 flex flex-wrap gap-4 items-center justify-between">
                <div className="flex items-center gap-4">
                    <h3 className="text-sm font-bold text-gray-200 flex items-center gap-2 whitespace-nowrap">
                        <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        storage/logs/laravel.log
                    </h3>
                    
                    <div className="relative">
                        <Search className="w-4 h-4 text-gray-400 absolute left-2.5 top-1.5" />
                        <input
                            type="text"
                            placeholder="Filtrar logs (ex: ERROR, Exception)..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="bg-gray-950 border border-gray-700 text-gray-200 text-xs rounded-md pl-8 pr-3 py-1.5 w-64 focus:outline-none focus:border-blue-500 transition-colors"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        onClick={() => setAutoScroll(!autoScroll)}
                        className={`text-[10px] font-bold uppercase tracking-wider transition-colors flex items-center gap-1.5 px-2.5 py-1.5 rounded border ${
                            autoScroll 
                                ? 'text-green-400 bg-green-950/30 border-green-900/50 hover:bg-green-900/40' 
                                : 'text-gray-400 bg-gray-800 border-gray-700 hover:bg-gray-700'
                        }`}
                        title="Auto-scroll"
                    >
                        {autoScroll ? <Pause className="w-3.5 h-3.5 fill-current" /> : <Play className="w-3.5 h-3.5 fill-current" />}
                        {autoScroll ? 'Pausar' : 'Acompanhar'}
                    </button>
                    <button 
                        onClick={() => {
                            if (window.confirm('Deseja limpar o arquivo de log do servidor?')) {
                                onClear();
                            }
                        }}
                        className="text-[10px] font-bold uppercase tracking-wider text-red-400 hover:text-red-300 transition-colors flex items-center gap-1.5 bg-red-950/30 px-2.5 py-1.5 rounded border border-red-900/50"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Limpar Log
                    </button>
                    <span className="text-[10px] text-gray-500 font-mono flex items-center gap-1.5">
                        <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        10s
                    </span>
                </div>
            </div>
            
            <div 
                ref={scrollRef}
                onScroll={handleScroll}
                className="p-4 bg-black flex-1 overflow-y-auto text-sm font-mono leading-relaxed" 
                id="logs-terminal"
            >
                {(!filteredLogs || filteredLogs.length === 0) ? (
                    <p className="text-gray-500 italic mt-2">
                        {logsData?.length > 0 
                            ? 'Nenhum log corresponde ao filtro.' 
                            : 'O arquivo de log está vazio ou não pôde ser lido atualmente.'}
                    </p>
                ) : (
                    filteredLogs.map((line: string, idx: number) => {
                        let color = 'text-gray-300';
                        const lineUpper = line.toUpperCase();
                        
                        if (lineUpper.includes('ERROR') || lineUpper.includes('EXCEPTION') || lineUpper.includes('FAILED') || lineUpper.includes('STACK TRACE')) {
                            color = 'text-red-400';
                        } else if (lineUpper.includes('WARNING')) {
                            color = 'text-yellow-400';
                        } else if (lineUpper.includes('INFO') || lineUpper.includes('SUCCESS')) {
                            color = 'text-blue-300';
                        } else if (lineUpper.startsWith('#')) {
                            color = 'text-gray-500'; // stacktrace items
                        }

                        // Optional: Highlight search term if it exists and is not empty
                        const renderLine = () => {
                            if (!searchTerm) return line;
                            
                            const parts = line.split(new RegExp(`(${searchTerm})`, 'gi'));
                            return parts.map((part, i) => 
                                part.toLowerCase() === searchTerm.toLowerCase() 
                                    ? <mark key={i} className="bg-yellow-500/30 text-yellow-200 rounded px-0.5">{part}</mark> 
                                    : part
                            );
                        };

                        return (
                            <div key={idx} className={`${color} break-words hover:bg-gray-800/80 px-1 py-0.5 rounded transition-colors`}>
                                {renderLine()}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
};

export default SystemLogs;
