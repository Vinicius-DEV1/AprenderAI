import React from 'react';
import Chart from 'react-apexcharts';
import { ApexOptions } from 'apexcharts';

interface AdminBreakdown {
    admin_name: string;
    count: number;
}

interface StatsData {
    daily_stats: { date: string; count: number }[];
    admin_breakdown: Record<string, AdminBreakdown[]>;
}

interface Props {
    data: StatsData;
}

const ImportReviewStats: React.FC<Props> = ({ data }) => {
    const series = [
        {
            name: 'Questões Analisadas',
            data: data.daily_stats.map(d => d.count)
        }
    ];

    const options: ApexOptions = {
        chart: {
            type: 'line',
            height: 120,
            sparkline: {
                enabled: false
            },
            toolbar: {
                show: false
            },
            animations: {
                enabled: true,
                speed: 800,
            },
            events: {
                dataPointSelection: (_event, _chartContext, config) => {
                    const date = data.daily_stats[config.dataPointIndex].date;
                    const breakdown = data.admin_breakdown[date];
                    if (breakdown) {
                        const message = breakdown.map(b => `${b.admin_name}: ${b.count}`).join('\n');
                        alert(`Detalhamento de ${date}:\n${message}`);
                    }
                }
            }
        },
        stroke: {
            curve: 'smooth',
            width: 3,
            colors: ['#4F46E5']
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                gradientToColors: ['#818CF8'],
                inverseColors: true,
                opacityFrom: 0.5,
                opacityTo: 0.1,
            }
        },
        markers: {
            size: 4,
            colors: ['#4F46E5'],
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: {
                size: 6
            }
        },
        xaxis: {
            categories: data.daily_stats.map(d => {
                const date = new Date(d.date);
                return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            }),
            labels: {
                show: true,
                style: {
                    fontSize: '10px',
                    fontWeight: 600,
                    colors: '#94a3b8'
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            show: false
        },
        grid: {
            show: false,
            padding: {
                left: 10,
                right: 10
            }
        },
        tooltip: {
            enabled: true,
            theme: 'light',
            x: {
                show: true
            },
            y: {
                formatter: (val) => `${val} questões`
            },
            custom: ({ series, seriesIndex, dataPointIndex, w: _w }) => {
                const date = data.daily_stats[dataPointIndex].date;
                const breakdown = data.admin_breakdown[date] || [];
                const total = series[seriesIndex][dataPointIndex];
                
                return `
                    <div className="p-2 bg-white border border-gray-100 shadow-lg rounded-lg text-[11px]">
                        <div className="font-bold text-gray-800 mb-1">${date}</div>
                        <div className="text-indigo-600 font-black mb-1">Total: ${total}</div>
                        <div className="space-y-0.5">
                            ${breakdown.map(b => `
                                <div className="flex justify-between gap-4 text-gray-500">
                                    <span>${b.admin_name}</span>
                                    <span className="font-bold">${b.count}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        }
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                    <span className="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                    Produtividade de Revisão (Últimos 14 dias)
                </h3>
                <div className="text-[10px] text-gray-400 font-medium">
                    Clique nos pontos para detalhes
                </div>
            </div>
            <div className="h-[120px]">
                <Chart options={options} series={series} type="area" height={120} />
            </div>
        </div>
    );
};

export default ImportReviewStats;
