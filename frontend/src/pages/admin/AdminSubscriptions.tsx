import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../api/axios';

export default function AdminSubscriptions() {
    const [activeTab, setActiveTab] = useState<'stats' | 'grants'>('stats');
    const queryClient = useQueryClient();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-analytics-subscriptions'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/analytics/subscriptions');
            return res.data;
        }
    });

    const { data: grantsData, isLoading: isLoadingGrants } = useQuery({
        queryKey: ['admin-manual-grants'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/users/grants');
            return res.data;
        },
        enabled: activeTab === 'grants'
    });

    const revokeGrantMutation = useMutation({
        mutationFn: async (userId: number) => {
            const res = await api.delete(`/api/v1/admin/users/${userId}/revoke-grant`);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-manual-grants'] });
            toast.success(data.message || 'Plano manual revogado com sucesso!');
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao revogar plano.');
        }
    });

    if (isLoading && activeTab === 'stats') return <div className="p-8">Carregando estatísticas...</div>;
    if (isError) return <div className="p-8 text-red-500">Erro ao carregar os dados.</div>;

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
    };

    return (
        <div className="py-6 px-4 md:px-6 w-full">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Assinaturas e Estatísticas</h1>
                    <p className="text-gray-500 text-sm mt-1">Acompanhe a evolução do faturamento e gerencie grants.</p>
                </div>

                <div className="flex bg-gray-100 p-1 rounded-lg">
                    <button
                        onClick={() => setActiveTab('stats')}
                        className={`px-4 py-2 text-sm font-bold rounded-md transition-colors ${activeTab === 'stats' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        Estatísticas e MRR
                    </button>
                    <button
                        onClick={() => setActiveTab('grants')}
                        className={`px-4 py-2 text-sm font-bold rounded-md transition-colors ${activeTab === 'grants' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        Planos Manuais (Grants)
                    </button>
                </div>
            </div>

            {activeTab === 'stats' && (
                <>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        {/* Total de Usuários */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p className="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Total de Usuários</p>
                            <p className="text-3xl font-black text-gray-800">{data?.total_users}</p>
                        </div>

                        {/* Assinaturas Pagas Ativas */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p className="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Assinaturas Pagas</p>
                            <p className="text-3xl font-black text-blue-600">{data?.paid_subscriptions}</p>
                            <p className="text-xs text-gray-500 mt-2"><strong>{data?.conversion_rate}%</strong> de conversão</p>
                        </div>

                        {/* Receita Recorrente - MRR */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 bg-gradient-to-br from-green-50 to-emerald-50">
                            <p className="text-sm font-bold text-green-600 uppercase tracking-widest mb-1">MRR (Estimado)</p>
                            <p className="text-3xl font-black text-green-700">{formatCurrency(data?.mrr)}</p>
                            <p className="text-xs text-green-600/80 mt-2 font-medium">Receita Recorrente Mensal</p>
                        </div>

                        {/* Outro Card */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p className="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">Média por Usuário</p>
                            <p className="text-3xl font-black text-purple-600">
                                {data?.paid_subscriptions > 0 ? formatCurrency(data?.mrr / data?.paid_subscriptions) : 'R$ 0,00'}
                            </p>
                            <p className="text-xs text-gray-500 mt-2 font-medium">ARPU (Paid)</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                                <h3 className="font-bold text-gray-800 mb-6">Usuários por Plano</h3>
                                <div className="space-y-4">
                                    {data?.users_per_plan?.map((p: any, idx: number) => (
                                        <div key={idx}>
                                            <div className="flex justify-between items-center mb-1">
                                                <span className="text-sm font-bold text-gray-700">{p.name}</span>
                                                <div className="flex items-center gap-2">
                                                    {p.sandbox_count > 0 && (
                                                        <span className="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100 uppercase">
                                                            {p.sandbox_count} Sandbox
                                                        </span>
                                                    )}
                                                    <span className="text-sm font-black" style={{ color: p.color }}>{p.count}</span>
                                                </div>
                                            </div>
                                            <div className="w-full bg-gray-100 rounded-full h-2">
                                                <div
                                                    className="h-2 rounded-full"
                                                    style={{
                                                        width: `${data?.total_users > 0 ? (p.count / data?.total_users) * 100 : 0}%`,
                                                        backgroundColor: p.color
                                                    }}></div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <div className="p-6 border-b border-gray-50">
                                <h3 className="font-bold text-gray-800">Assinaturas Recentes</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-gray-50 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                                        <tr>
                                            <th className="px-6 py-4">Usuário</th>
                                            <th className="px-6 py-4">Plano</th>
                                            <th className="px-6 py-4">Status</th>
                                            <th className="px-6 py-4">Data</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {data?.recent_subscriptions?.map((sub: any, idx: number) => (
                                            <tr key={idx} className="hover:bg-gray-50/50 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="font-bold text-gray-800">{sub.user_name}</div>
                                                    <div className="text-xs text-gray-500">{sub.user_email}</div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-gray-700 bg-gray-100 px-2 py-1 rounded">{sub.plan_name}</span>
                                                        {sub.is_sandbox && (
                                                            <span className="text-[9px] font-black bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200">SANDBOX</span>
                                                        )}
                                                        {sub.is_manual_grant && (
                                                            <span className="text-[9px] font-black bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded border border-indigo-200">GRANT</span>
                                                        )}
                                                    </div>
                                                    {sub.amount > 0 && <div className="mt-1 text-xs text-green-600 font-bold">{formatCurrency(sub.amount)}</div>}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ${sub.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                                        {sub.status === 'active' ? 'Ativo' : sub.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-xs text-gray-500 font-mono">
                                                    {new Date(sub.created_at).toLocaleString('pt-BR')}
                                                </td>
                                            </tr>
                                        ))}
                                        {(!data?.recent_subscriptions || data?.recent_subscriptions.length === 0) && (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-10 text-center text-gray-400 italic">Nenhuma assinatura paga encontrada.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </>
            )}

            {activeTab === 'grants' && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-in fade-in">
                    <div className="p-6 border-b border-gray-50">
                        <h3 className="font-bold text-gray-800">Concessões Manuais Ativas</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            Estes usuários possuem planos premiums cedidos de forma gratuita. Eles <strong>não impactam</strong> o cálculo de MRR, Ticket Médio e nem a Receita Bruta.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        {isLoadingGrants ? (
                            <div className="p-8 text-center text-gray-500">Lendo base de dados...</div>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th className="px-6 py-4">Usuário Destino</th>
                                        <th className="px-6 py-4">Plano Concedido</th>
                                        <th className="px-6 py-4">Status & Expiração</th>
                                        <th className="px-6 py-4">Quem Concedeu / Motivo</th>
                                        <th className="px-6 py-4 text-right">Ação</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {grantsData?.data?.map((grant: any) => (
                                        <tr key={grant.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-indigo-900">{grant.user?.name}</div>
                                                <div className="text-xs text-gray-500">{grant.user?.email}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="font-bold text-indigo-700 bg-indigo-50 px-2 py-1 rounded border border-indigo-100">{grant.plan?.name}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-green-100 text-green-700 block w-max mb-1">
                                                    ATIVO
                                                </span>
                                                <div className="text-xs text-gray-500 font-medium">
                                                    Até: {grant.current_period_end ? new Date(grant.current_period_end).toLocaleDateString('pt-BR') : 'Tempo Indefinido'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-gray-700 text-xs">
                                                    Admin: {grant.granter?.name || `#${grant.granted_by}`}
                                                </div>
                                                <div className="text-[10px] text-gray-400 italic break-words max-w-[200px] mt-0.5">
                                                    {grant.granted_reason ? `"${grant.granted_reason}"` : 'Sem motivo registrado'}
                                                </div>
                                                <div className="text-[10px] text-gray-400 font-mono mt-1">Concedido em: {new Date(grant.created_at).toLocaleDateString('pt-BR')}</div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    onClick={() => { if (window.confirm(`Deseja revogar o plano de ${grant.user?.name}? Ele perderá acessos premium caso não seja pagante.`)) revokeGrantMutation.mutate(grant.user_id) }}
                                                    disabled={revokeGrantMutation.isPending}
                                                    className="text-xs font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors disabled:opacity-50"
                                                >
                                                    REVOGAR
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {(!grantsData?.data || grantsData.data.length === 0) && (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-10 text-center text-gray-400 italic">Nenhuma concessão de plano ativa no momento.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
