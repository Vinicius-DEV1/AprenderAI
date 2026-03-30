import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../../api/axios';
import Chart from 'react-apexcharts';

import BatchResultModal from '../components/BatchResultModal';

interface BatchHistoryTabProps {
    batchHistoryPage: number;
    setBatchHistoryPage: (page: number) => void;
    SmartPagination: any;
}

export default function BatchHistoryTab({
    batchHistoryPage,
    setBatchHistoryPage,
    SmartPagination
}: BatchHistoryTabProps) {
    const [selectedBatchId, setSelectedBatchId] = useState<string | null>(null);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);

    const { data: historyData, isLoading } = useQuery({
        queryKey: ['admin-batch-history', batchHistoryPage],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions-batch/history', {
                params: { page: batchHistoryPage, limit: 15 }
            });
            return res.data;
        }
    });

    const openDetails = (batchId: string) => {
        setSelectedBatchId(batchId);
        setIsDetailsModalOpen(true);
    };

    if (isLoading) {
        return <div className="p-12 text-center text-gray-400 font-bold animate-pulse">Carregando histórico...</div>;
    }

    const { batches, charts } = historyData || { batches: { data: [] }, charts: { weekly: [], monthly: [] } };

    return (
        <div className="flex flex-col gap-6 p-6">
            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-4">
                {/* Weekly Chart */}
                <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Processamento Diário (7 dias)</h3>
                    <div className="h-48">
                        <Chart
                            options={{
                                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
                                colors: ['#818cf8'],
                                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                                dataLabels: { enabled: false },
                                stroke: { width: 0 },
                                xaxis: {
                                    categories: charts?.weekly?.map((c: any) => c.date) || [],
                                    labels: { style: { colors: '#9CA3AF', fontSize: '10px', fontWeight: 700 } },
                                    axisBorder: { show: false }, axisTicks: { show: false }
                                },
                                yaxis: {
                                    labels: { style: { colors: '#9CA3AF', fontSize: '10px', fontWeight: 700 } }
                                },
                                grid: { borderColor: '#E5E7EB', strokeDashArray: 3, padding: { top: 0, right: 0, bottom: 0, left: 10 } }
                            }}
                            series={[{ name: 'Questões', data: charts?.weekly?.map((c: any) => c.total) || [] }]}
                            type="bar"
                            height="100%"
                        />
                    </div>
                </div>

                {/* Monthly Chart */}
                <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Volume Mensal (6 meses)</h3>
                    <div className="h-48">
                        <Chart
                            options={{
                                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
                                colors: ['#34d399'],
                                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                                dataLabels: { enabled: false },
                                stroke: { width: 0 },
                                xaxis: {
                                    categories: charts?.monthly?.map((c: any) => c.month) || [],
                                    labels: { style: { colors: '#9CA3AF', fontSize: '10px', fontWeight: 700 } },
                                    axisBorder: { show: false }, axisTicks: { show: false }
                                },
                                yaxis: {
                                    labels: { style: { colors: '#9CA3AF', fontSize: '10px', fontWeight: 700 } }
                                },
                                grid: { borderColor: '#E5E7EB', strokeDashArray: 3, padding: { top: 0, right: 0, bottom: 0, left: 10 } }
                            }}
                            series={[{ name: 'Questões', data: charts?.monthly?.map((c: any) => c.total) || [] }]}
                            type="bar"
                            height="100%"
                        />
                    </div>
                </div>
            </div>

            {/* History Table */}
            <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                <div className="p-4 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                    <h3 className="text-xs font-black text-gray-600 uppercase tracking-widest">Registros de Processamento ({batches.total || 0})</h3>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-50/80 sticky top-0 z-10">
                            <tr className="border-b border-gray-100">
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">ID Lote</th>
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">Data</th>
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Progresso</th>
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Custo Est.</th>
                                <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {batches.data.map((batch: any) => (
                                <tr key={batch.id} className="hover:bg-indigo-50/30 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-black text-gray-700 font-mono">{batch.batch_id.split('-').shift()}-...</span>
                                            <span className="text-[9px] text-gray-400 font-bold mt-0.5">{batch.type}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-bold text-gray-600">{new Date(batch.created_at).toLocaleDateString('pt-BR')}</span>
                                            <span className="text-[10px] text-gray-400 font-bold">{new Date(batch.created_at).toLocaleTimeString('pt-BR')}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <div className="flex items-center justify-center gap-2">
                                            <div className="w-full max-w-[100px] bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                                <div 
                                                    className={`h-full rounded-full ${batch.status === 'failed' ? 'bg-red-500' : 'bg-green-500'}`} 
                                                    style={{ width: `${batch.total_items > 0 ? (batch.processed_items / batch.total_items) * 100 : 0}%` }}
                                                ></div>
                                            </div>
                                            <span className="text-[10px] font-black text-gray-500">{batch.processed_items}/{batch.total_items}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <span className={`px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest ${
                                            batch.status === 'completed' ? 'bg-green-100 text-green-700' :
                                            batch.status === 'processing' ? 'bg-blue-100 text-blue-700 animate-pulse' :
                                            batch.status === 'cancelled' ? 'bg-amber-100 text-amber-700' :
                                            'bg-red-100 text-red-700'
                                        }`}>
                                            {batch.status === 'processing' ? 'Processando' : 
                                            batch.status === 'completed' ? 'Concluído' : 
                                            batch.status === 'cancelled' ? 'Cancelado' : 'Falha'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <span className="text-xs font-bold text-gray-600">
                                            {batch.usage_stats?.estimated_cost ? `$${parseFloat(batch.usage_stats.estimated_cost).toFixed(4)}` : '-'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => openDetails(batch.batch_id)}
                                            className="px-3 py-1.5 bg-white border border-gray-200 text-indigo-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-50 transition shadow-sm"
                                        >
                                            Detalhes 👀
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {batches.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-gray-400 font-bold text-sm">
                                        Nenhum lote processado encontrado.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                
                {batches.last_page > 1 && (
                    <div className="p-4 bg-gray-50/50 border-t border-gray-50">
                        <SmartPagination
                            currentPage={batchHistoryPage}
                            lastPage={batches.last_page || 1}
                            onPageChange={setBatchHistoryPage}
                        />
                    </div>
                )}
            </div>

            <BatchResultModal
                isOpen={isDetailsModalOpen}
                onClose={() => setIsDetailsModalOpen(false)}
                batchId={selectedBatchId}
            />
        </div>
    );
}
