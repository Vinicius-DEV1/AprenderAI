import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';

export default function UserDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();

    const [formState, setFormState] = useState({
        name: '',
        email: '',
        phone: '',
        role: 'user',
        ai_questions_count: 0,
        max_ai_questions_override: '',
        max_simulations_override: '',
        max_essays_override: ''
    });

    const [manualPassword, setManualPassword] = useState('');
    const [validationErrors, setValidationErrors] = useState<any>({});
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    const { data: userData, isLoading, error } = useQuery({
        queryKey: ['admin-user', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/users/${id}`);
            return res.data;
        },
        enabled: !!id
    });

    useEffect(() => {
        if (userData?.user) {
            const u = userData.user;
            setFormState({
                name: u.name || '',
                email: u.email || '',
                phone: u.phone || '',
                role: u.role || 'user',
                ai_questions_count: u.ai_questions_count || 0,
                max_ai_questions_override: u.max_ai_questions_override ?? '',
                max_simulations_override: u.max_simulations_override ?? '',
                max_essays_override: u.max_essays_override ?? ''
            });
        }
    }, [userData]);

    const updateMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.put(`/api/v1/admin/users/${id}`, payload);
            return res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            setSuccessMessage('Alterações salvas com sucesso!');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                toast.error('Erro ao atualizar usuário.');
            }
        }
    });

    const toggleStatusMutation = useMutation({
        mutationFn: async () => {
            const res = await api.patch(`/api/v1/admin/users/${id}/toggle-status`);
            return res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            setSuccessMessage('Status de acesso alterado!');
        }
    });

    const refundMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post(`/api/v1/admin/users/${id}/refund`);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            setSuccessMessage(data.message || 'Assinatura cancelada e limites revogados!');
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao cancelar assinatura.');
        }
    });

    const resetPasswordMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.post(`/api/v1/admin/users/${id}/reset-password`, payload);
            return res.data;
        },
        onSuccess: (data) => {
            toast.info(data.message || 'Senha redefinida!');
            setManualPassword('');
        }
    });

    const handleUpdateProfile = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});
        updateMutation.mutate(formState);
    };

    if (isLoading) return <AdminPageSkeleton />;

    if (error || !userData?.user) {
        return (
            <div className="p-12 text-center">
                <div className="text-red-500 font-black text-2xl mb-4">OPERAÇÃO FALHOU</div>
                <p className="text-gray-600 mb-6 font-bold uppercase tracking-widest italic">{error ? (error as any).message : 'Usuário não encontrado no banco de dados.'}</p>
                <Link to="/admin/users" className="bg-gray-800 text-white px-8 py-3 rounded-xl font-black uppercase text-xs">Voltar para Listagem</Link>
            </div>
        );
    }

    const { user, stats, promptHistory } = userData;
    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    return (
        <div className="p-4 md:p-8 max-w-7xl mx-auto">
            <div className="mb-6 flex items-center gap-4">
                <Link to="/admin/users" className="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Detalhes do Usuário</h1>
                    <p className="text-gray-500">Gerenciando {user.name}</p>
                </div>
            </div>

            {successMessage && (
                <div className="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r shadow-sm flex items-center justify-between">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm text-green-700 font-bold uppercase tracking-tight">{successMessage}</p>
                        </div>
                    </div>
                    <button onClick={() => setSuccessMessage(null)} className="text-green-800 hover:text-green-900">
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" /></svg>
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 text-left">
                {/* Left Column */}
                <div className="space-y-6">
                    {/* Profile Card */}
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center border border-gray-100">
                        <div className="relative inline-block">
                            <img
                                src={user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}`}
                                alt={user.name}
                                className="w-24 h-24 rounded-full mx-auto border-4 border-gray-100 shadow-sm"
                            />
                            <span className={`absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`}></span>
                        </div>
                        <h2 className="mt-4 text-xl font-bold text-gray-800">{user.name}</h2>
                        <p className="text-gray-500 text-sm">{user.email}</p>
                        <div className="mt-4 flex justify-center gap-2">
                            <span className="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold">
                                {user.plan?.name || 'Free'}
                            </span>
                            <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                ID: {user.id}
                            </span>
                        </div>
                    </div>

                    {/* Edit Profile Form */}
                    <div className="bg-white rounded-2xl shadow-sm p-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4">Editar Perfil</h3>
                        <form onSubmit={handleUpdateProfile} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 font-bold">Nome Completo</label>
                                <input type="text" value={formState.name} onChange={(e) => setFormState({ ...formState, name: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
                                {getError('name') && <span className="text-xs text-red-500">{getError('name')}</span>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 font-bold">Email</label>
                                <input type="email" value={formState.email} onChange={(e) => setFormState({ ...formState, email: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
                                {getError('email') && <span className="text-xs text-red-500">{getError('email')}</span>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 font-bold">Telefone</label>
                                <input type="text" value={formState.phone} onChange={(e) => setFormState({ ...formState, phone: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
                                {getError('phone') && <span className="text-xs text-red-500">{getError('phone')}</span>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 font-bold">Cargo (Acesso)</label>
                                <select value={formState.role} onChange={(e) => setFormState({ ...formState, role: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="user">Usuário Comum</option>
                                    <option value="admin">Administrador</option>
                                </select>
                                <p className="text-[10px] text-amber-600 mt-1 font-semibold">⚠️ Administradores têm acesso total ao painel admin.</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 flex justify-between font-bold">
                                    <span>Consumo de IA (O que já usou)</span>
                                    <span className="text-xs text-gray-500">Limite Atual: {user.max_ai_questions_override ?? user.plan?.max_ai_questions ?? 'N/A'}</span>
                                </label>
                                <div className="flex items-center gap-2">
                                    <input type="number" value={formState.ai_questions_count} onChange={(e) => setFormState({ ...formState, ai_questions_count: parseInt(e.target.value) || 0 })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" min="0" />
                                    <button type="button" onClick={() => setFormState({ ...formState, ai_questions_count: 0 })} className="text-xs text-blue-600 hover:underline">Zerar</button>
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 flex justify-between font-bold">
                                    <span>Limite Individual de IA (Override)</span>
                                    <span className="text-xs text-gray-500">Plano: {user.plan?.max_ai_questions ?? 'N/A'}</span>
                                </label>
                                <input type="number" value={formState.max_ai_questions_override} onChange={(e) => setFormState({ ...formState, max_ai_questions_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" min="0" placeholder="Vazio = usar limite do plano" />
                                <p className="text-xs text-gray-400 mt-1">0 = ilimitado. Vazio = usar padrão do plano.</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 flex justify-between font-bold">
                                    <span>Limite Individual de Simulados (Override)</span>
                                    <span className="text-xs text-gray-500">
                                        Plano: {user.plan?.simulations_limit ?? 'N/A'} | Usado: {userData.monthly_simulation_used || 0}
                                    </span>
                                </label>
                                <input type="number" value={formState.max_simulations_override} onChange={(e) => setFormState({ ...formState, max_simulations_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" min="0" placeholder="Vazio = usar limite do plano" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1 flex justify-between font-bold">
                                    <span>Limite Individual de Redações (Override)</span>
                                    <span className="text-xs text-gray-500">
                                        Plano: {user.plan?.essays_limit ?? 'N/A'} | Usado: {userData.monthly_essay_used || 0}
                                    </span>
                                </label>
                                <input type="number" value={formState.max_essays_override} onChange={(e) => setFormState({ ...formState, max_essays_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" min="0" placeholder="Vazio = usar limite do plano" />
                            </div>
                            <button type="submit" disabled={updateMutation.isPending} className="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors disabled:opacity-50 uppercase text-xs font-black">
                                {updateMutation.isPending ? 'SALVANDO...' : 'Salvar Alterações'}
                            </button>
                        </form>
                    </div>

                    {/* Security Actions */}
                    <div className="bg-white rounded-2xl shadow-sm p-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center text-red-600">
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            Segurança
                        </h3>

                        <div className="bg-gray-50 p-4 rounded-xl mb-6">
                            <div className="flex items-center justify-between mb-2">
                                <span className="font-medium text-gray-700">Acesso ao Sistema</span>
                                <span className={`text-xs font-bold ${user.is_banned ? 'text-red-500' : 'text-green-500'}`}>
                                    {user.is_banned ? 'BANIDO' : 'ATIVO'}
                                </span>
                            </div>
                            <button
                                onClick={() => { if (window.confirm('Confirmar alteração?')) toggleStatusMutation.mutate(); }}
                                className={`w-full py-2 px-4 rounded-lg border ${user.is_banned ? 'border-green-500 text-green-600 hover:bg-green-50' : 'border-red-500 text-red-600 hover:bg-red-50'} transition-colors text-sm font-semibold uppercase`}
                            >
                                {user.is_banned ? 'Desbloquear Usuário' : 'Banir Usuário'}
                            </button>
                        </div>

                        <div className="bg-red-50 border border-red-200 p-4 rounded-xl mb-6">
                            <div className="flex items-start mb-2 gap-2">
                                <svg className="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <div>
                                    <span className="font-bold text-red-800 block text-sm">Cancelamento / Estorno (CDC)</span>
                                    <span className="text-xs text-red-600 leading-tight block mt-1">Mata os ciclos premium e saldos instantaneamente usando o QuotaService (Roolback de Plano).</span>
                                </div>
                            </div>
                            <button
                                onClick={() => { if (window.confirm('Tem certeza absoluta? O ciclo atual será marcado como expirado hoje e o saldo de IA / Redações extra do plano será revogado sumariamente. O Asaas não fará parte disso automaticamente, o reembolso financeiro é manual lá!\n\nDeseja realizar o bloqueio do perfil agora?')) refundMutation.mutate(); }}
                                disabled={refundMutation.isPending || !user.subscriptions?.some((s: any) => s.status === 'active')}
                                className="w-full mt-2 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white disabled:opacity-50 transition-colors text-[11px] font-black tracking-wider uppercase shadow-sm"
                            >
                                {refundMutation.isPending ? 'ACELERANDO...' : 'EXECUTAR CANCELAMENTO IMEDIATO'}
                            </button>
                            {!user.subscriptions?.some((s: any) => s.status === 'active') && (
                                <p className="text-[10px] text-red-400 mt-2 text-center uppercase font-bold">O usuário não possui assinatura ativa para estornar aqui.</p>
                            )}
                        </div>

                        <hr className="border-gray-100 my-4" />

                        <h4 className="text-sm font-semibold text-gray-600 mb-3">Redefinir Senha</h4>
                        <div className="space-y-3">
                            <button
                                onClick={() => { if (window.confirm('Enviar email?')) resetPasswordMutation.mutate({ send_email: 1 }); }}
                                className="w-full flex items-center justify-center gap-2 bg-white border border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Enviar Email de Reset
                            </button>

                            <div className="mt-2">
                                <label className="text-xs text-gray-500 mb-1 block">Ou defina manualmente:</label>
                                <div className="flex gap-2">
                                    <input
                                        type="password"
                                        value={manualPassword}
                                        onChange={(e) => setManualPassword(e.target.value)}
                                        placeholder="Nova senha"
                                        className="flex-1 rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <button
                                        onClick={() => { if (manualPassword) resetPasswordMutation.mutate({ new_password: manualPassword }); }}
                                        className="bg-gray-800 text-white px-3 rounded-lg hover:bg-gray-900 text-sm font-medium"
                                    >
                                        Salvar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right Column */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                            <div className="text-gray-400 text-[10px] uppercase font-bold mb-1">Simulados</div>
                            <div className="text-2xl font-bold text-gray-800">{stats?.simulations || 0}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
                            <div className="text-gray-400 text-[10px] uppercase font-bold mb-1">Redações</div>
                            <div className="text-2xl font-bold text-gray-800">{stats?.essays || 0}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                            <div className="text-gray-400 text-[10px] uppercase font-bold mb-1">Investimento Aluno</div>
                            <div className="text-2xl font-bold text-gray-800">
                                R$ {(stats?.investment || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                            </div>
                        </div>
                        <div className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-amber-500">
                            <div className="text-gray-400 text-[10px] uppercase font-bold mb-1">Consumo IA (PROMETIDO)</div>
                            <div className="text-2xl font-bold text-slate-900">R$ {Number(stats?.ai?.total_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                            <div className="text-[10px] text-slate-500 mt-1">Gasto total acumulado</div>
                        </div>
                    </div>

                    {/* IA Metrics */}
                    <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-indigo-100">
                        <div className="p-6 border-b border-indigo-50 bg-indigo-50/30 flex justify-between items-center text-left">
                            <h3 className="text-lg font-bold text-indigo-900 flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                Métricas de IA
                            </h3>
                            <div className="flex gap-4">
                                <div className="text-center">
                                    <p className="text-[10px] text-gray-400 uppercase font-bold">Requisições</p>
                                    <p className="text-sm font-bold text-indigo-600">{stats?.ai?.request_count || 0}</p>
                                </div>
                                <div className="text-center">
                                    <p className="text-[10px] text-gray-400 uppercase font-bold">Sucesso</p>
                                    <p className="text-sm font-bold text-green-600">{Number(stats?.ai?.success_rate || 0).toFixed(1)}%</p>
                                </div>
                                <div className="text-center">
                                    <p className="text-[10px] text-gray-400 uppercase font-bold">Pico Uso</p>
                                    <p className="text-sm font-bold text-orange-600">{stats?.ai?.peak_hour || 'N/A'}</p>
                                </div>
                            </div>
                        </div>

                        <div className="p-6 overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead>
                                    <tr className="text-xs text-gray-400 uppercase tracking-wider border-b">
                                        <th className="pb-3 font-bold">Data</th>
                                        <th className="pb-3 font-bold">Modelo</th>
                                        <th className="pb-3 font-bold">Tokens (I/O)</th>
                                        <th className="pb-3 font-bold text-right">Custo (R$)</th>
                                        <th className="pb-3 font-bold text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {promptHistory?.data?.map((log: any) => (
                                        <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="py-3 text-gray-500 whitespace-nowrap">
                                                {new Date(log.created_at).toLocaleString('pt-BR')}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-gray-800">{log.provider}</span>
                                                    <span className="text-[10px] text-gray-400">{log.model}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 text-gray-500 font-mono text-xs">
                                                {log.tokens_used_input} / {log.tokens_used_output}
                                            </td>
                                            <td className="py-3 text-right font-bold text-gray-800">
                                                R$ {Number(log.estimated_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}
                                            </td>
                                            <td className="py-3 text-center">
                                                <button className="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full text-xs font-semibold" onClick={() => toast.info('Funcionalidade em manutenção')}>
                                                    Ver Chat
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {(!promptHistory?.data || promptHistory.data.length === 0) && (
                                        <tr>
                                            <td colSpan={5} className="py-8 text-center text-gray-500 italic font-bold uppercase tracking-widest text-[10px]">Nenhum uso de IA registrado.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Subscription History */}
                    <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
                        <div className="p-6 border-b border-gray-100 bg-gray-50/50">
                            <h3 className="text-lg font-bold text-gray-800 text-left">Histórico de Assinaturas</h3>
                        </div>
                        <div className="overflow-x-auto text-left">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr className="text-xs uppercase font-bold text-gray-400">
                                        <th className="px-6 py-3 text-left">Plano</th>
                                        <th className="px-6 py-3 text-left">Status</th>
                                        <th className="px-6 py-3 text-left">Data Início</th>
                                        <th className="px-6 py-3 text-left">Fim/Renovação</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {user.subscriptions?.map((sub: any) => (
                                        <tr key={sub.id}>
                                            <td className="px-6 py-3 font-medium text-gray-800">{sub.plan?.name || 'Desconhecido'}</td>
                                            <td className="px-6 py-3">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase ${sub.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                                    {sub.status === 'active' ? 'Ativa' : sub.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-gray-600">{new Date(sub.created_at).toLocaleDateString('pt-BR')}</td>
                                            <td className="px-6 py-3 text-gray-600">{sub.current_period_end ? new Date(sub.current_period_end).toLocaleDateString('pt-BR') : '-'}</td>
                                        </tr>
                                    ))}
                                    {(!user.subscriptions || user.subscriptions.length === 0) && (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-8 text-center text-gray-500 italic uppercase font-black text-[10px] tracking-widest">Nenhuma assinatura registrada.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Audit Logs */}
                    <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
                        <div className="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
                            <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                            <h3 className="text-lg font-bold text-gray-800">Log de Auditoria</h3>
                        </div>
                        <div className="overflow-x-auto text-left">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr className="text-xs uppercase font-black text-gray-400">
                                        <th className="px-6 py-3 text-left">Ação</th>
                                        <th className="px-6 py-3 text-left">Descrição</th>
                                        <th className="px-6 py-3 text-left">IP</th>
                                        <th className="px-6 py-3 text-right">Data</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {user.logs?.map((log: any) => (
                                        <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-3 font-bold text-indigo-600 text-[10px] uppercase">{log.action}</td>
                                            <td className="px-6 py-3 text-gray-600 truncate max-w-xs text-xs" title={log.description}>{log.description}</td>
                                            <td className="px-6 py-3 text-gray-400 font-mono text-[9px]">{log.ip_address}</td>
                                            <td className="px-6 py-3 text-right text-gray-500 text-xs">{new Date(log.created_at).toLocaleString('pt-BR')}</td>
                                        </tr>
                                    ))}
                                    {(!user.logs || user.logs.length === 0) && (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-8 text-center text-gray-500 font-bold uppercase text-[10px] tracking-widest italic">Nenhum registro de log encontrado.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
