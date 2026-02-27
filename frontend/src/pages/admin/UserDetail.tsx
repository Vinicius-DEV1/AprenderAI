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

    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: userData, isLoading } = useQuery({
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
            alert('Perfil atualizado com sucesso!');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                alert('Erro ao atualizar usuário.');
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
            alert('Status de acesso alterado!');
        }
    });

    const handleUpdateProfile = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});
        updateMutation.mutate(formState);
    };

    if (isLoading) return <AdminPageSkeleton />;
    if (!userData || !userData.user) return <div className="p-8 text-center text-red-500 font-bold">Usuário não encontrado.</div>;

    const { user, stats, promptHistory } = userData;
    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    return (
        <div className="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto animate-in fade-in duration-500">
            <div className="mb-6 flex items-center gap-4">
                <Link to="/admin/users" className="p-2 bg-white rounded-lg shadow-sm hover:shadow-md transition-all text-gray-600">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800 tracking-tight">Gestão de Aluno</h1>
                    <p className="text-gray-500 font-medium">Auditoria e controle para {user.name}</p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 text-left">
                {/* Left Column: Profile & Actions */}
                <div className="space-y-6">
                    {/* Profile Card */}
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center border border-gray-100">
                        <div className="relative inline-block">
                            <img
                                src={user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=random`}
                                alt={user.name}
                                className="w-24 h-24 rounded-3xl mx-auto border-4 border-gray-50 shadow-sm object-cover"
                            />
                            <span className={`absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-4 border-white ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`}></span>
                        </div>
                        <h2 className="mt-4 text-xl font-black text-gray-900">{user.name}</h2>
                        <p className="text-gray-500 text-sm font-medium">{user.email}</p>
                        <div className="mt-4 flex justify-center gap-2">
                            <span className="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                {user.plan?.name || 'PLANO GRATUITO'}
                            </span>
                            <span className="px-3 py-1 bg-gray-50 text-gray-400 rounded-lg text-[10px] font-black uppercase tracking-wider font-mono">
                                ID: #{user.id}
                            </span>
                        </div>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center">
                            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Simulados</span>
                            <span className="text-xl font-black text-gray-900">{stats?.simulations || 0}</span>
                        </div>
                        <div className="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center">
                            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Redações</span>
                            <span className="text-xl font-black text-gray-900">{stats?.essays || 0}</span>
                        </div>
                    </div>

                    {/* Edit Profile Form */}
                    <div className="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                        <h3 className="text-sm font-black text-gray-900 mb-6 uppercase tracking-widest border-b border-gray-50 pb-2">Configurações de Perfil</h3>
                        <form onSubmit={handleUpdateProfile} className="space-y-6">
                            <div className="space-y-1">
                                <label className="text-[10px] font-black text-gray-400 uppercase ml-1">Nome Completo</label>
                                <input
                                    type="text"
                                    value={formState.name}
                                    onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                />
                                {getError('name') && <span className="text-[10px] text-red-500 ml-1">{getError('name')}</span>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-[10px] font-black text-gray-400 uppercase ml-1">Email Principal</label>
                                <input
                                    type="email"
                                    value={formState.email}
                                    onChange={(e) => setFormState({ ...formState, email: e.target.value })}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                />
                                {getError('email') && <span className="text-[10px] text-red-500 ml-1">{getError('email')}</span>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-[10px] font-black text-gray-400 uppercase ml-1">Nível de Acesso</label>
                                <select
                                    value={formState.role}
                                    onChange={(e) => setFormState({ ...formState, role: e.target.value })}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-black text-[10px] uppercase focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3">
                                    <option value="user">Aluno (Padrão)</option>
                                    <option value="admin">Administrador (Total)</option>
                                    <option value="editor">Editor (Conteúdo)</option>
                                </select>
                            </div>

                            <div className="pt-4">
                                <button
                                    type="submit"
                                    disabled={updateMutation.isPending}
                                    className="w-full bg-indigo-600 text-white py-3 rounded-xl hover:bg-indigo-700 font-black text-xs uppercase tracking-widest transition-all shadow-md shadow-indigo-100 disabled:opacity-50">
                                    {updateMutation.isPending ? 'PROCESSANDO...' : 'ATUALIZAR DADOS'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Security Actions */}
                    <div className="bg-white rounded-2xl shadow-sm p-6 border-2 border-red-50 hover:border-red-100 transition-colors">
                        <h3 className="text-xs font-black text-red-600 mb-4 uppercase tracking-widest flex items-center">
                            <span className="mr-2">🛡️</span> Zona de Segurança
                        </h3>

                        <div className="bg-red-50/30 p-4 rounded-xl mb-4">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-[10px] font-black text-gray-500 uppercase tracking-widest">Status de Acesso</span>
                                <span className={`text-[10px] font-black px-2 py-0.5 rounded ${user.is_banned ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}`}>
                                    {user.is_banned ? 'BLOQUEADO' : 'ATIVO'}
                                </span>
                            </div>
                            <button
                                onClick={() => { if (window.confirm('Confirmar alteração de acesso?')) toggleStatusMutation.mutate(); }}
                                disabled={toggleStatusMutation.isPending}
                                className={`w-full py-3 rounded-xl border-2 font-black text-[10px] uppercase tracking-widest transition-all ${user.is_banned ? 'border-green-500 text-green-600 hover:bg-green-500 hover:text-white' : 'border-red-500 text-red-600 hover:bg-red-500 hover:text-white'} disabled:opacity-50`}>
                                {user.is_banned ? 'DESBLOQUEAR ALUNO' : 'REVOGAR ACESSO'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Right Column: AI Usage & Quotas */}
                <div className="lg:col-span-2 space-y-8">
                    {/* Quotas & Overrides */}
                    <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex justify-between items-center">
                            <h3 className="text-sm font-black text-gray-900 uppercase tracking-tighter">Cotas e Limites Customizados</h3>
                            <span className="text-[10px] text-gray-400 font-medium">Os overrides ignoram as regras do plano atual</span>
                        </div>
                        <div className="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div className="space-y-1">
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Override: Créditos de IA</label>
                                <input
                                    type="number"
                                    value={formState.max_ai_questions_override}
                                    onChange={(e) => setFormState({ ...formState, max_ai_questions_override: e.target.value })}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                    placeholder="Vazio = Padrão do Plano"
                                />
                                <p className="text-[10px] text-gray-400 font-medium mt-1">Atual no plano: {user.plan?.max_ai_questions || 0}</p>
                            </div>
                            <div className="space-y-1">
                                <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Override: Simulados Mensais</label>
                                <input
                                    type="number"
                                    value={formState.max_simulations_override}
                                    onChange={(e) => setFormState({ ...formState, max_simulations_override: e.target.value })}
                                    className="w-full rounded-xl border-gray-100 bg-gray-50 font-bold text-sm focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                                    placeholder="Vazio = Padrão do Plano"
                                />
                                <p className="text-[10px] text-gray-400 font-medium mt-1">Usado: {userData.monthly_simulation_used || 0} / {user.plan?.simulations_limit || 0}</p>
                            </div>
                        </div>
                    </div>

                    {/* IA Usage History */}
                    <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 border-b border-gray-50 flex justify-between items-center">
                            <h3 className="text-sm font-black text-gray-900 uppercase tracking-tighter">Histórico de Requisições de IA</h3>
                            <button className="text-[10px] font-black text-indigo-600 uppercase hover:underline">Ver tudo</button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead className="bg-gray-50/50">
                                    <tr>
                                        <th className="px-6 py-4 text-[9px] font-black text-gray-400 uppercase">Data</th>
                                        <th className="px-6 py-4 text-[9px] font-black text-gray-400 uppercase">Tipo</th>
                                        <th className="px-6 py-4 text-[9px] font-black text-gray-400 uppercase">Status</th>
                                        <th className="px-6 py-4 text-[9px] font-black text-gray-400 uppercase text-right">Custo Estimado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {promptHistory?.data?.length > 0 ? promptHistory.data.map((log: any) => (
                                        <tr key={log.id} className="hover:bg-gray-50/50 transition-colors group">
                                            <td className="px-6 py-4 text-xs font-medium text-gray-500">{new Date(log.created_at).toLocaleString()}</td>
                                            <td className="px-6 py-4 font-mono text-[10px] text-gray-700 font-bold">
                                                {log.prompt_slug || 'CUSTOM'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`text-[8px] font-black px-2 py-0.5 rounded-full ${log.status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                    }`}>
                                                    {log.status === 'success' ? 'OK' : 'FAIL'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <span className="text-xs font-black text-gray-900 group-hover:text-indigo-600 transition-colors">
                                                    R$ {Number(log.estimated_cost || 0).toFixed(4)}
                                                </span>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-12 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">Nenhuma atividade registrada</td>
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
