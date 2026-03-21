import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import AdminUserStats from './components/AdminUserStats';

import { UserProfileCard } from './user-detail/UserProfileCard';
import { UserUsageMetrics } from './user-detail/UserUsageMetrics';
import { UserEditForms } from './user-detail/UserEditForms';
import { UserGrantAccess } from './user-detail/UserGrantAccess';
import { UserHistoryLogs } from './user-detail/UserHistoryLogs';
import { UserSecurityActions } from './user-detail/UserSecurityActions';
import { UserDangerZone } from './user-detail/UserDangerZone';

export default function UserDetail() {
    const { id } = useParams();
    const queryClient = useQueryClient();

    const [activeTab, setActiveTab] = useState<'overview' | 'settings' | 'academic' | 'history' | 'security'>('overview');

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

    const [grantForm, setGrantForm] = useState({
        plan_id: '',
        duration_type: 'days',
        duration_value: 30,
        reason: ''
    });

    const [manualPassword, setManualPassword] = useState('');
    const [validationErrors, setValidationErrors] = useState<any>({});

    const { data: userData, isLoading, error } = useQuery({
        queryKey: ['admin-user', id],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/users/${id}`);
            return res.data;
        },
        enabled: !!id,
        retry: 1
    });

    const { data: plansData } = useQuery({
        queryKey: ['admin-plans-short'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/plans');
            return res.data;
        }
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
            toast.success('Alterações salvas com sucesso!');
        },
        onError: (error: any) => {
            if (error.response?.data?.errors) {
                setValidationErrors(error.response.data.errors);
            } else {
                toast.error(error.response?.data?.message || 'Erro ao atualizar usuário.');
            }
        }
    });

    const toggleStatusMutation = useMutation({
        mutationFn: async () => {
            const res = await api.patch(`/api/v1/admin/users/${id}/toggle-status`);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            toast.success(data.message || 'Status de acesso alterado!');
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao alterar status.');
        }
    });

    const refundMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post(`/api/v1/admin/users/${id}/refund`);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            toast.success(data.message || 'Assinatura cancelada e limites revogados!');
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
            toast.success(data.message || 'Senha redefinida com sucesso!');
            setManualPassword('');
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao redefinir senha.');
        }
    });

    const grantMutation = useMutation({
        mutationFn: async (payload: any) => {
            const res = await api.post(`/api/v1/admin/users/${id}/grant-plan`, payload);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            toast.success(data.message || 'Plano concedido com sucesso!');
            setGrantForm({ plan_id: '', duration_type: 'days', duration_value: 30, reason: '' });
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao conceder plano.');
        }
    });

    const revokeGrantMutation = useMutation({
        mutationFn: async () => {
            const res = await api.delete(`/api/v1/admin/users/${id}/revoke-grant`);
            return res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['admin-user', id] });
            toast.success(data.message || 'Plano manual revogado com sucesso!');
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao revogar plano.');
        }
    });

    const deleteMutation = useMutation({
        mutationFn: async () => {
            const res = await api.delete(`/api/v1/admin/users/${id}`);
            return res.data;
        },
        onSuccess: (data) => {
            toast.success(data.message || 'Usuário excluído permanentemente!');
            setTimeout(() => {
                window.location.href = '/admin/users';
            }, 1000);
        },
        onError: (error: any) => {
            toast.error(error.response?.data?.message || 'Erro ao excluir usuário.');
        }
    });

    const handleDeleteUser = () => {
        const confirmText = prompt('ALERTA MÁXIMO DE SEGURANÇA!\n\nEsta ação excluirá PERMANENTEMENTE o usuário e TODOS os seus rastros (redações, simulados, histórico financeiro e logs).\n\nPara confirmar, DIGITE O E-MAIL do usuário abaixo:');
        
        if (confirmText === user.email) {
            if (window.confirm('ÚLTIMA CHANCE: Você tem certeza ABSOLUTA? Esta ação é irreversível.')) {
                deleteMutation.mutate();
            }
        } else if (confirmText !== null) {
            toast.error('O e-mail digitado não confere. Operação cancelada por segurança.');
        }
    };

    const handleUpdateProfile = (e: React.FormEvent) => {
        e.preventDefault();
        setValidationErrors({});
        updateMutation.mutate(formState);
    };

    if (isLoading) return <AdminPageSkeleton />;

    if (error || !userData?.user) {
        return (
            <div className="p-12 text-center h-[60vh] flex flex-col items-center justify-center">
                <svg className="w-16 h-16 text-red-400 mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <div className="text-red-500 font-black text-2xl mb-2">OPERAÇÃO FALHOU</div>
                <p className="text-gray-500 mb-6 font-medium max-w-md mx-auto">{error ? (error as any).message : 'Usuário não encontrado no banco de dados. Ele pode ter sido excluído.'}</p>
                <Link to="/admin/users" className="bg-gray-800 hover:bg-gray-900 text-white px-8 py-3 rounded-xl font-bold uppercase text-xs transition-colors">Voltar para Listagem</Link>
            </div>
        );
    }

    const { user, stats, promptHistory } = userData;
    const getError = (field: string) => validationErrors[field] ? validationErrors[field][0] : null;

    const hasActiveGrant = user.subscriptions?.some((s: any) => s.status === 'active' && s.is_manual_grant);
    const activeGrant = user.subscriptions?.find((s: any) => s.status === 'active' && s.is_manual_grant);

    const tabs = [
        { id: 'overview', label: 'Visão Geral', icon: <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg> },
        { id: 'settings', label: 'Editor e Limites', icon: <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg> },
        { id: 'academic', label: 'Desempenho Acadêmico', icon: <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg> },
        { id: 'history', label: 'Histórico', icon: <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> },
        { id: 'security', label: 'Segurança', icon: <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> },
    ];

    return (
        <div className="p-4 md:p-6 w-full">
            {/* Header section */}
            <div className="mb-6 flex items-center gap-4">
                <Link to="/admin/users" className="p-2 bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-all text-gray-600 hover:text-gray-900">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Detalhes do Usuário</h1>
                    <p className="text-gray-500 text-sm mt-0.5">Gerenciando perfil e acessos de <span className="font-semibold">{user.name}</span></p>
                </div>
            </div>

            <div className="flex flex-col lg:flex-row gap-8">
                {/* Left Column - Fixed Profile Card */}
                <UserProfileCard 
                    user={user} 
                    hasActiveGrant={hasActiveGrant} 
                    activeGrant={activeGrant} 
                />

                {/* Right Column - Tabs and Content */}
                <div className="w-full lg:w-3/4">
                    {/* Navigation Tabs */}
                    <div className="flex overflow-x-auto hide-scrollbar mb-6 bg-white p-1.5 rounded-xl shadow-sm border border-gray-100 gap-1">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id as any)}
                                className={`flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap transition-all flex-1 ${activeTab === tab.id
                                    ? 'bg-indigo-50 text-indigo-700 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                                    }`}
                            >
                                {tab.icon}
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Tab Contents */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 min-h-[500px]">

                        {/* TAB: VISÃO GERAL */}
                            <UserUsageMetrics 
                                stats={stats} 
                                monthly_simulation_used={userData.monthly_simulation_used} 
                                monthly_essay_used={userData.monthly_essay_used} 
                            />

                        {activeTab === 'settings' && (
                            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <UserEditForms 
                                    formState={formState}
                                    setFormState={setFormState}
                                    getError={getError}
                                    handleUpdateProfile={handleUpdateProfile}
                                    isPending={updateMutation.isPending}
                                    userPlan={user.plan}
                                />
                                <UserGrantAccess 
                                    hasActiveGrant={hasActiveGrant}
                                    activeGrant={activeGrant}
                                    grantForm={grantForm}
                                    setGrantForm={setGrantForm}
                                    plansData={plansData}
                                    grantMutation={grantMutation}
                                    revokeGrantMutation={revokeGrantMutation}
                                />
                            </div>
                        )}

                        {/* TAB: DESEMPENHO ACADÊMICO */}
                        {activeTab === 'academic' && (
                            <AdminUserStats userId={user.id} />
                        )}

                        {activeTab === 'history' && (
                            <UserHistoryLogs 
                                promptHistory={promptHistory}
                                subscriptions={user.subscriptions}
                                systemLogs={user.logs}
                            />
                        )}

                        {activeTab === 'security' && (
                            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <UserSecurityActions 
                                    user={user}
                                    toggleStatusMutation={toggleStatusMutation}
                                    resetPasswordMutation={resetPasswordMutation}
                                    manualPassword={manualPassword}
                                    setManualPassword={setManualPassword}
                                />
                                <UserDangerZone 
                                    user={user}
                                    refundMutation={refundMutation}
                                    handleDeleteUser={handleDeleteUser}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
