import React from 'react';

interface MonitorHeaderProps {
    currentRange: string;
    setCurrentRange: (range: string) => void;
}

const MonitorHeader: React.FC<MonitorHeaderProps> = ({ currentRange, setCurrentRange }) => {
    return (
        <div className="mb-8 flex justify-between items-center">
            <div>
                <h1 className="text-3xl font-bold text-gray-800 mb-2">Monitoramento VPS</h1>
                <p className="text-gray-600 font-medium">
                    Acompanhe a saúde do servidor em tempo real (atualização: 
                    <span className="text-blue-600 font-black animate-pulse ml-1">1s</span>)
                </p>
            </div>
            <div className="bg-white rounded-lg shadow-sm p-1 inline-flex">
                {['1h', '24h', '7d'].map(r => (
                    <button 
                        key={r} 
                        onClick={() => setCurrentRange(r)}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            currentRange === r ? 'bg-blue-50 text-blue-600 shadow-sm' : 'text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        {r === '1h' ? '1 Hora' : r === '24h' ? '24 Horas' : '7 Dias'}
                    </button>
                ))}
            </div>
        </div>
    );
};

export default MonitorHeader;
