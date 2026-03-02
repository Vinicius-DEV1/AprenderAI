import React, { useEffect, useState } from 'react';
import api from '../../api/axios';
import { toast } from 'sonner';

interface XavierStats {
    total_searches: number;
    success_rate: number;
    failed_count: number;
    cache_entries: number;
}

interface RequestItem {
    id: number;
    prompt: string;
    status: string;
    filters: any;
    created_at: string;
    user?: {
        name: string;
    };
}

const AdminXavierInsights: React.FC = () => {
    const [stats, setStats] = useState<XavierStats | null>(null);
    const [history, setHistory] = useState<RequestItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedRequest, setSelectedRequest] = useState<RequestItem | null>(null);

    const fetchData = async () => {
        try {
            setLoading(true);
            const response = await api.get('/api/v1/admin/xavier/insights');
            setStats(response.data.stats);
            setHistory(response.data.recent_requests);
        } catch (error) {
            toast.error('Falha ao carregar insights do Xavier');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed': return <span className="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Sucesso</span>;
            case 'failed': return <span className="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">Falha</span>;
            case 'processing': return <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">Processando</span>;
            default: return <span className="px-2 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">{status}</span>;
        }
    };

    return (
        <div className="p-6 max-w-7xl mx-auto">
            <header className="mb-8">
                <h1 className="text-3xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
                    <span className="text-4xl">🤖</span> Xavier Insights
                </h1>
                <p className="text-slate-600 dark:text-slate-400 mt-2">
                    Monitore a inteligência e o desempenho das buscas semânticas em tempo real.
                </p>
            </header>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div className="text-slate-500 text-sm font-medium">Buscados Totais</div>
                    <div className="text-3xl font-bold text-slate-800 dark:text-white mt-1">{stats?.total_searches || 0}</div>
                </div>
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div className="text-slate-500 text-sm font-medium">Taxa de Sucesso</div>
                    <div className="text-3xl font-bold text-indigo-600 mt-1">{stats?.success_rate || 0}%</div>
                </div>
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div className="text-slate-500 text-sm font-medium">Entradas no Cache</div>
                    <div className="text-3xl font-bold text-emerald-600 mt-1">{stats?.cache_entries || 0}</div>
                </div>
                <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div className="text-slate-500 text-sm font-medium">Falhas Críticas</div>
                    <div className="text-3xl font-bold text-red-500 mt-1">{stats?.failed_count || 0}</div>
                </div>
            </div>

            {/* Recents Table */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div className="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                    <h2 className="font-bold text-slate-800 dark:text-white">Buscas Recentes</h2>
                    <button onClick={fetchData} className="text-sm text-indigo-600 font-medium hover:underline">Atualizar Live</button>
                </div>
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 text-xs uppercase font-bold">
                            <th className="px-6 py-4">Usuário</th>
                            <th className="px-6 py-4">Prompt</th>
                            <th className="px-6 py-4">Status</th>
                            <th className="px-6 py-4">Data</th>
                            <th className="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                        {history.map((item) => (
                            <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                                <td className="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">{item.user?.name || 'Visitante'}</td>
                                <td className="px-6 py-4 text-slate-600 dark:text-slate-400 max-w-xs truncate" title={item.prompt}>
                                    "{item.prompt}"
                                </td>
                                <td className="px-6 py-4">{getStatusBadge(item.status)}</td>
                                <td className="px-6 py-4 text-slate-500 text-sm">
                                    {new Date(item.created_at).toLocaleString('pt-BR')}
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <button
                                        onClick={() => setSelectedRequest(item)}
                                        className="text-indigo-600 hover:text-indigo-800 text-sm font-bold"
                                    >
                                        Ver Filtros
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {history.length === 0 && !loading && (
                    <div className="p-12 text-center text-slate-500">Nenhuma busca registrada ainda.</div>
                )}
            </div>

            {/* Modal de Detalhes dos Filtros */}
            {selectedRequest && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                        <div className="p-6 border-b dark:border-slate-700 flex justify-between items-center bg-indigo-600 text-white">
                            <div>
                                <h3 className="text-xl font-bold">Detalhes da Interpretação</h3>
                                <p className="text-indigo-100 text-sm">Prompt: "{selectedRequest.prompt}"</p>
                            </div>
                            <button onClick={() => setSelectedRequest(null)} className="text-white hover:opacity-70">✕</button>
                        </div>
                        <div className="p-6 overflow-y-auto bg-slate-50 dark:bg-slate-900">
                            <h4 className="text-xs font-bold text-slate-400 uppercase mb-4 tracking-wider">Filtros Interpretados (JSON)</h4>
                            <pre className="p-4 bg-slate-900 text-emerald-400 rounded-2xl overflow-x-auto text-sm font-mono ring-1 ring-slate-800 shadow-inner">
                                {JSON.stringify(selectedRequest.filters, null, 2)}
                            </pre>
                        </div>
                        <div className="p-6 border-t dark:border-slate-700 flex justify-end">
                            <button
                                onClick={() => setSelectedRequest(null)}
                                className="px-6 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-white rounded-xl font-bold"
                            >
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AdminXavierInsights;
