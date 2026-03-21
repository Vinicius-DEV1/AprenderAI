import React from 'react';
import { Line } from 'react-chartjs-2';

interface ResourceChartsProps {
    isHistoryLoading: boolean;
    resourceChartData: any;
    networkChartData: any;
    formatBytes: (bytes: number) => string;
}

const ResourceCharts: React.FC<ResourceChartsProps> = ({ 
    isHistoryLoading, 
    resourceChartData, 
    networkChartData,
    formatBytes 
}) => {
    if (isHistoryLoading) {
        return <div className="text-center py-12 text-gray-500">Carregando gráficos de histórico...</div>;
    }

    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="bg-white rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-bold text-gray-800 mb-4">Uso de Recursos (%)</h3>
                <div className="relative h-72">
                    <Line 
                        data={resourceChartData} 
                        options={{
                            responsive: true, 
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, max: 100 }, x: { display: false } },
                            interaction: { mode: 'index', intersect: false }
                        }} 
                    />
                </div>
            </div>
            <div className="bg-white rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-bold text-gray-800 mb-4">Tráfego de Rede</h3>
                <div className="relative h-72">
                    <Line 
                        data={networkChartData} 
                        options={{
                            responsive: true, 
                            maintainAspectRatio: false,
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    ticks: { 
                                        callback: function (value: any) { 
                                            return typeof value === 'number' ? formatBytes(value) : value; 
                                        } 
                                    } 
                                },
                                x: { display: false }
                            },
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function (context: any) {
                                            let label = context.dataset.label || '';
                                            if (label) label += ': ';
                                            if (context.parsed.y !== null) label += formatBytes(context.parsed.y);
                                            return label;
                                        }
                                    }
                                }
                            }
                        }} 
                    />
                </div>
            </div>
        </div>
    );
};

export default ResourceCharts;
