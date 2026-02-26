import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { useConfigStore } from '../../stores/configStore';

export default function AdminApiKeys() {
    const { aiName } = useConfigStore();
    const { data, isLoading } = useQuery({
        queryKey: ['admin-api-keys'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/api-keys');
            return res.data;
        }
    });

    if (isLoading) return <div className="p-8">Carregando chaves API e monitoramento...</div>;
    if (!data) return null;

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto">
            <header className="mb-10 flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-white mb-2">Infraestrutura de IA 🔑</h1>
                    <p className="text-gray-600 dark:text-slate-400">Gerencie provedores e monitore o consumo do {aiName}.</p>
                </div>
                <button className="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg">
                    Adicionar Provedor
                </button>
            </header>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Available Capabilities */}
                <div className="lg:col-span-2 space-y-8">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100">
                        <h3 className="font-bold mb-6 flex items-center gap-2">🌐 Status dos Provedores</h3>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead className="text-xs text-slate-400 uppercase">
                                    <tr>
                                        <th className="pb-4">Provedor</th>
                                        <th className="pb-4">Modelo</th>
                                        <th className="pb-4">Status</th>
                                        <th className="pb-4 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {data.vault_keys?.map((v: any) => (
                                        <tr key={v.id}>
                                            <td className="py-4 font-semibold text-sm capitalize">{v.provider}</td>
                                            <td className="py-4 text-xs text-slate-500">{v.nickname}</td>
                                            <td className="py-4 text-sm">
                                                <span className="flex items-center gap-1 text-green-500">
                                                    <span className="w-2 h-2 rounded-full bg-green-500"></span> Online
                                                </span>
                                            </td>
                                            <td className="py-4 text-right">
                                                <button className="text-xs text-slate-400 hover:text-indigo-600">Ver detalhes</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100">
                        <h3 className="font-bold mb-6">📊 Histórico de Requisições</h3>
                        <div className="space-y-4">
                            {data.ai_logs?.map((log: any) => (
                                <div key={log.id} className="flex justify-between items-center text-sm p-3 hover:bg-slate-50 rounded-lg">
                                    <div>
                                        <span className="font-bold text-indigo-600">{log.user?.name || 'Anon'}</span>
                                        <span className="mx-2 text-slate-400">•</span>
                                        <span className="text-slate-500">{log.model}</span>
                                    </div>
                                    <div className="text-slate-400 text-xs text-right">
                                        <p>{log.tokens_used_total} tokens</p>
                                        <p>{new Date(log.created_at).toLocaleTimeString()}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Consumer Ranking */}
                <div>
                    <div className="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                        <h3 className="font-bold mb-6 text-indigo-400">🏆 Maiores Consumidores</h3>
                        <div className="space-y-6">
                            {data.ai_ranking?.map((rank: any, idx: number) => (
                                <div key={idx} className="flex items-center gap-4">
                                    <span className="text-xl font-black text-slate-700">0{idx + 1}</span>
                                    <div className="flex-1">
                                        <p className="text-sm font-bold truncate w-32">{rank.user?.name}</p>
                                        <p className="text-[10px] text-slate-400 uppercase">{rank.total_tokens.toLocaleString()} tokens</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-bold text-green-400">R$ {rank.total_cost.toFixed(2)}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
