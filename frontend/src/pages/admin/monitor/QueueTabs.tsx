import React, { useState } from 'react';
import { QueuesData, QueueJob, CompletedBatch, FailedJob, CompletedJob } from './Types';
import BatchDetailsModal from './BatchDetailsModal';

interface QueueTabsProps {
    activeTab: 'pending' | 'failed' | 'completed';
    setActiveTab: (tab: 'pending' | 'failed' | 'completed') => void;
    queuesData?: QueuesData;
}

const QueueTabs: React.FC<QueueTabsProps> = ({ activeTab, setActiveTab, queuesData }) => {
    const [selectedBatch, setSelectedBatch] = useState<CompletedBatch | null>(null);

    return (
        <div className="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="flex border-b border-gray-100 bg-gray-50/50">
                <button
                    onClick={() => setActiveTab('pending')}
                    className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${
                        activeTab === 'pending' ? 'border-blue-500 text-blue-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    }`}
                >
                    Jobs Pendentes/Ativos
                    <span className="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">{queuesData?.jobs?.length || 0}</span>
                </button>
                <button
                    onClick={() => setActiveTab('completed')}
                    className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${
                        activeTab === 'completed' ? 'border-green-500 text-green-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    }`}
                >
                    Lotes Concluídos
                    <span className="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">{queuesData?.completed?.length || 0}</span>
                </button>
                <button
                    onClick={() => setActiveTab('failed')}
                    className={`px-6 py-3.5 text-sm font-medium transition-colors border-b-2 flex items-center gap-2 ${
                        activeTab === 'failed' ? 'border-red-500 text-red-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                    }`}
                >
                    Falhas Recentes
                    <span className="bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs font-bold">{queuesData?.failed?.length || 0}</span>
                </button>
            </div>

            <div className="p-0">
                {/* Active/Pending Jobs Tab */}
                {activeTab === 'pending' && (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Classe (Job)</th>
                                    <th className="px-4 py-3">Fila</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(queuesData?.jobs || []).length > 0 ? (
                                    queuesData?.jobs.map((job: QueueJob) => (
                                        <tr key={job.id} className="border-b hover:bg-gray-50">
                                            <td className="px-4 py-3 font-medium text-gray-900">#{job.id}</td>
                                            <td className="px-4 py-3 font-mono text-blue-600">{job.name}</td>
                                            <td className="px-4 py-3">
                                                <span className="px-2 py-1 text-[10px] font-bold uppercase bg-gray-100 text-gray-600 rounded">
                                                    {job.queue}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {job.is_processing ? (
                                                    <span className="px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full flex items-center w-max gap-1">
                                                        <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        Processando
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-1 text-xs font-semibold bg-amber-100 text-amber-700 rounded-full">
                                                        Pendente (Tentativas: {job.attempts})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-gray-400">
                                                {new Date(job.created_at).toLocaleTimeString('pt-BR')}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-gray-500 italic">Nenhum job pendente no momento. Tudo limpo! 🎉</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Completed Jobs/Batches Tab */}
                {activeTab === 'completed' && (
                    <div className="flex flex-col">
                        {/* 1. Inidividual Recent Successes (New Section) */}
                        <div className="p-4 bg-green-50/30 border-b border-gray-100">
                            <h3 className="text-xs font-bold text-green-700 uppercase tracking-wider flex items-center gap-2">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                Trabalhos Individuais Recentes
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left text-gray-500">
                                <thead className="text-[10px] text-gray-500 uppercase bg-gray-50/50">
                                    <tr>
                                        <th className="px-4 py-2">Job Name</th>
                                        <th className="px-4 py-2">Fila</th>
                                        <th className="px-4 py-2">Duração</th>
                                        <th className="px-4 py-2 text-right">Horário</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(queuesData?.recent_completed || []).length > 0 ? (
                                        queuesData?.recent_completed.map((job: CompletedJob) => (
                                            <tr key={job.id} className="border-b border-gray-50 hover:bg-white transition-colors">
                                                <td className="px-4 py-2.5 font-mono text-gray-700 text-xs">{job.name}</td>
                                                <td className="px-4 py-2.5">
                                                    <span className="px-2 py-0.5 text-[9px] font-bold bg-gray-100 text-gray-500 rounded">
                                                        {job.queue}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-gray-600">
                                                    {job.duration}s
                                                </td>
                                                <td className="px-4 py-2.5 text-right text-gray-400 tabular-nums">
                                                    {new Date(job.finished_at).toLocaleTimeString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-6 text-center text-gray-400 italic text-xs">Aguardando a conclusão de novos jobs...</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* 2. Traditional Batch List */}
                        <div className="p-4 bg-gray-50/50 border-y border-gray-100 mt-4">
                            <h3 className="text-xs font-bold text-gray-600 uppercase tracking-wider">
                                Histórico de Lotes (Batches)
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left text-gray-500">
                                <thead className="text-[10px] text-gray-700 uppercase bg-gray-50/50">
                                    <tr>
                                        <th className="px-4 py-3">Lote ID / Nome</th>
                                        <th className="px-4 py-3 text-center">Jobs Totais</th>
                                        <th className="px-4 py-3 text-center">Falhas no Lote</th>
                                        <th className="px-4 py-3 text-right">Data de Conclusão</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white">
                                    {(queuesData?.completed || []).length > 0 ? (
                                        queuesData?.completed.map((batch: CompletedBatch) => (
                                            <tr 
                                                key={batch.id} 
                                                onClick={() => setSelectedBatch(batch)}
                                                className="border-b hover:bg-blue-50/50 cursor-pointer transition-colors group"
                                                title="Clique para ver detalhes do lote"
                                            >
                                                <td className="px-4 py-3 relative">
                                                    <div className="font-medium text-gray-900 group-hover:text-blue-700 transition-colors">{batch.name}</div>
                                                    <div className="font-mono text-[10px] text-gray-400">{batch.id}</div>
                                                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-transparent group-hover:bg-blue-500 transition-colors"></div>
                                                </td>
                                                <td className="px-4 py-3 text-center font-medium text-gray-700">{batch.total_jobs}</td>
                                                <td className="px-4 py-3 text-center">
                                                    {batch.failed_jobs > 0 ? (
                                                        <span className="text-red-500 font-bold">{batch.failed_jobs}</span>
                                                    ) : (
                                                        <span className="text-green-500">—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-500">
                                                    {new Date(batch.finished_at).toLocaleString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-8 text-center text-gray-500 italic">Nenhum lote foi concluído recentemente.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Failed Jobs Tab */}
                {activeTab === 'failed' && (
                    <div className="overflow-x-auto bg-red-50/20">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-red-800 uppercase bg-red-50">
                                <tr>
                                    <th className="px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Classe</th>
                                    <th className="px-4 py-3">Erro (Exception)</th>
                                    <th className="px-4 py-3 text-right">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(queuesData?.failed || []).length > 0 ? (
                                    queuesData?.failed.map((job: FailedJob) => (
                                        <tr key={job.id} className="border-b border-gray-100 hover:bg-red-50/50">
                                            <td className="px-4 py-3 font-medium text-gray-900">#{job.id}</td>
                                            <td className="px-4 py-3 font-mono text-gray-600">{job.name}</td>
                                            <td className="px-4 py-3 text-red-600 max-w-xl truncate" title={job.exception}>
                                                {job.exception}
                                            </td>
                                            <td className="px-4 py-3 text-right text-gray-500">
                                                {new Date(job.failed_at).toLocaleString('pt-BR')}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-gray-500 italic">Nenhuma falha registrada! Ótimo trabalho. 👍</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <BatchDetailsModal 
                batch={selectedBatch} 
                onClose={() => setSelectedBatch(null)} 
            />
        </div>
    );
};

export default QueueTabs;
