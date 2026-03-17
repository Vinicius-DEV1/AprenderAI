import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/axios';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import AdminUserStats from './components/AdminUserStats';

// Simple Tooltip component
const Tooltip = ({ content }: { content: string }) => (
    <div className="group relative inline-flex items-center ml-1 cursor-help">
        <svg className="w-4 h-4 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-56 p-2 bg-gray-900 text-white text-[11px] font-normal leading-tight rounded shadow-xl z-10 text-center pointer-events-none">
            {content}
            <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
        </div>
    </div>
);

const EmptyState = ({ title, message, icon }: { title: string, message: string, icon?: React.ReactNode }) => (
    <div className="flex flex-col items-center justify-center p-8 text-gray-400 bg-gray-50/50 rounded-xl border border-dashed border-gray-200 my-4">
        <div className="mb-3 text-gray-300">
            {icon || (
                <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
            )}
        </div>
        <h4 className="text-sm font-bold text-gray-600 mb-1">{title}</h4>
        <p className="text-xs text-center">{message}</p>
    </div>
);

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
                <div className="w-full lg:w-1/4 shrink-0">
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center border border-gray-100 sticky top-8">
                        <div className="relative inline-block">
                            <img
                                src={user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=e2e8f0&color=475569`}
                                alt={user.name}
                                className="w-24 h-24 rounded-full mx-auto border-4 border-white shadow-sm object-cover"
                            />
                            <span className={`absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`} title={user.is_banned ? 'Banido' : 'Ativo'}></span>
                        </div>
                        <h2 className="mt-4 text-xl font-bold text-gray-800 break-words">{user.name}</h2>
                        <p className="text-gray-500 text-sm break-words px-2">{user.email}</p>

                        <div className="mt-5 flex flex-col gap-2">
                            <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                                <span className="text-gray-500 font-medium text-xs">Plano Atual</span>
                                <span className="font-bold text-indigo-600 truncate max-w-[100px]" title={user.plan?.name || 'Free'}>{user.plan?.name || 'Free'}</span>
                            </div>
                            <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                                <span className="text-gray-500 font-medium text-xs">ID do Sistema</span>
                                <span className="font-mono font-semibold text-gray-700">#{user.id}</span>
                            </div>
                            <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                                <span className="text-gray-500 font-medium text-xs">Cargo</span>
                                <span className={`font-bold text-xs uppercase ${user.role === 'admin' ? 'text-amber-600' : 'text-gray-600'}`}>{user.role}</span>
                            </div>

                            {hasActiveGrant && (
                                <div className="mt-2 border border-indigo-200 bg-indigo-50 p-2.5 rounded-lg text-left">
                                    <div className="flex items-center text-indigo-700 text-xs font-bold mb-1">
                                        <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                                        Acesso Concedido Manualmente
                                    </div>
                                    <div className="text-[10px] text-indigo-600/80 leading-snug">
                                        Plano <strong>{activeGrant?.plan?.name || 'Válido'}</strong> até {activeGrant?.current_period_end ? new Date(activeGrant.current_period_end).toLocaleDateString() : 'sempre'}
                                    </div>
                                </div>
                            )}

                            <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                                <span className="text-gray-500 font-medium text-xs">Login Via</span>
                                <span className="font-bold text-gray-700 flex items-center gap-1">
                                    {user.google_id ? (
                                        <>
                                            <svg className="w-3 h-3 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.908 3.152-1.928 4.176-1.152 1.152-2.92 2.392-5.912 2.392-4.584 0-8.208-3.712-8.208-8.296s3.624-8.296 8.208-8.296c2.488 0 4.296.976 5.64 2.256l2.328-2.328C18.528 2.216 15.84 0 12.48 0 6.48 0 1.6 4.84 1.6 11.04s4.88 11.04 10.88 11.04c3.24 0 5.68-1.072 7.744-3.232 2.12-2.12 2.792-5.112 2.792-7.536 0-.72-.056-1.4-.16-2.024h-10.376z" />
                                            </svg>
                                            Google
                                        </>
                                    ) : 'E-mail'}
                                </span>
                            </div>
                            <div className="flex justify-between items-center px-4 py-2 bg-gray-50 rounded-lg text-sm">
                                <span className="text-gray-500 font-medium text-xs">Cadastro</span>
                                <span className="font-semibold text-gray-600 text-xs">{new Date(user.created_at).toLocaleDateString('pt-BR')}</span>
                            </div>
                        </div>
                    </div>
                </div>

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
                        {activeTab === 'overview' && (
                            <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Métricas de Uso</h3>

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">Simulados</div>
                                            <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                        </div>
                                        <div className="text-2xl font-black text-slate-800">{stats?.simulations || 0}</div>
                                        <div className="text-xs text-gray-500 mt-1">Neste ciclo: <span className="font-semibold">{userData.monthly_simulation_used || 0}</span></div>
                                    </div>

                                    <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">Redações</div>
                                            <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </div>
                                        <div className="text-2xl font-black text-slate-800">{stats?.essays || 0}</div>
                                        <div className="text-xs text-gray-500 mt-1">Neste ciclo: <span className="font-semibold">{userData.monthly_essay_used || 0}</span></div>
                                    </div>

                                    <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl">
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="text-gray-500 text-xs font-bold uppercase tracking-wider">LTV Aluno</div>
                                            <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </div>
                                        <div className="text-2xl font-black text-slate-800">
                                            R$ {(stats?.investment || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                        </div>
                                        <div className="text-[10px] text-gray-500 mt-1 uppercase">Gasto total acumulado</div>
                                    </div>

                                    <div className="bg-gray-50 border border-gray-100 p-5 rounded-xl text-indigo-900 border-l-4 border-l-indigo-400">
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="text-indigo-800/70 text-xs font-bold uppercase tracking-wider">Custo IA</div>
                                            <svg className="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                        </div>
                                        <div className="text-2xl font-black text-indigo-900">
                                            R$ {Number(stats?.ai?.total_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}
                                        </div>
                                        <div className="text-[10px] uppercase text-indigo-600/70 mt-1 font-bold">{stats?.ai?.request_count || 0} req(s). feitas</div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* TAB: CONFIGURAÇÕES E LIMITES */}
                        {activeTab === 'settings' && (
                            <form onSubmit={handleUpdateProfile} className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Informações Pessoais</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nome Completo</label>
                                            <input type="text" value={formState.name} onChange={(e) => setFormState({ ...formState, name: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" />
                                            {getError('name') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('name')}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">E-mail</label>
                                            <input type="email" value={formState.email} onChange={(e) => setFormState({ ...formState, email: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" />
                                            {getError('email') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('email')}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Telefone</label>
                                            <input type="text" value={formState.phone} onChange={(e) => setFormState({ ...formState, phone: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" placeholder="(00) 00000-0000" />
                                            {getError('phone') && <span className="text-xs text-red-500 mt-1 block font-medium">{getError('phone')}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Nível de Acesso (Cargo)</label>
                                            <select value={formState.role} onChange={(e) => setFormState({ ...formState, role: e.target.value })} className="w-full rounded-lg border-gray-300 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                                <option value="user">Usuário Comum (Aluno)</option>
                                                <option value="admin">Administrador Geral</option>
                                            </select>
                                            {formState.role === 'admin' && (
                                                <p className="text-xs text-amber-600 mt-1.5 font-bold flex items-center">
                                                    <svg className="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" /></svg>
                                                    Acesso total liberado ao painel
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Overrides e Limites</h3>
                                    <p className="text-sm text-gray-500 mb-4">Ajuste os limites individuais deste usuário. Essas configurações sobrepõem os padrões definidos pelo plano original dele.</p>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                                            <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                                                Consumo Atual de IA
                                                <Tooltip content="Zerar este valor permite que o usuário volte a consumir o limite de requisições de IA no ciclo atual." />
                                            </label>
                                            <div className="flex items-center gap-2">
                                                <input type="number" value={formState.ai_questions_count} onChange={(e) => setFormState({ ...formState, ai_questions_count: parseInt(e.target.value) || 0 })} className="flex-1 rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" />
                                                <button type="button" onClick={() => setFormState({ ...formState, ai_questions_count: 0 })} className="px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-bold text-indigo-600 hover:bg-indigo-50 transition-colors">Zerar</button>
                                            </div>
                                            <p className="text-[10px] text-gray-500 mt-2 font-medium">Você pode redefinir manualmente o que ele já usou.</p>
                                        </div>

                                        <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                                            <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                                                Limite Override - IA
                                                <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado. Qualquer outro número será o novo teto de uso." />
                                            </label>
                                            <input type="number" value={formState.max_ai_questions_override} onChange={(e) => setFormState({ ...formState, max_ai_questions_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Ex: 50 (Vazio = Plano)" />
                                            <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {user.plan?.max_ai_questions ?? 'N/A'}</p>
                                        </div>

                                        <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                                            <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                                                Limite Override - Simulados
                                                <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado." />
                                            </label>
                                            <input type="number" value={formState.max_simulations_override} onChange={(e) => setFormState({ ...formState, max_simulations_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Vazio = Usar Plano" />
                                            <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {user.plan?.simulations_limit ?? 'N/A'}</p>
                                        </div>

                                        <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-indigo-200 transition-colors">
                                            <label className="text-sm font-bold text-gray-700 mb-2 flex items-center">
                                                Limite Override - Redações
                                                <Tooltip content="Mantenha vazio para usar o limite do plano. Preencha com 0 para dar acesso Ilimitado." />
                                            </label>
                                            <input type="number" value={formState.max_essays_override} onChange={(e) => setFormState({ ...formState, max_essays_override: e.target.value })} className="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" min="0" placeholder="Vazio = Usar Plano" />
                                            <p className="text-[10px] text-gray-500 mt-2 font-medium">Atual (Plano): {user.plan?.essays_limit ?? 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="border border-indigo-100 bg-indigo-50/30 rounded-2xl p-6 relative">
                                    <div className="absolute top-0 left-0 w-1.5 h-full bg-indigo-500 rounded-l-2xl"></div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-2 flex items-center">
                                        Concessão Manual de Plano (Grant)
                                    </h3>
                                    <p className="text-sm text-gray-500 mb-5">
                                        Dê acesso gratuito a um plano premium para este usuário. A assinatura aparecerá como "Ativa" no perfil do aluno, com todas as cotas do plano, mas não impactará o LTV e KPI Financeiros.
                                    </p>

                                    {hasActiveGrant ? (
                                        <div className="bg-white p-5 rounded-xl border border-indigo-200 shadow-sm">
                                            <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                                <div>
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 mb-2">GRANT ATIVO</span>
                                                    <p className="text-sm text-gray-700 font-medium">Este usuário já possui um acesso manual concedido ao plano <span className="font-bold text-indigo-600">{activeGrant?.plan?.name}</span>.</p>
                                                    <p className="text-xs text-gray-500 mt-1">Data final do acesso: {activeGrant?.current_period_end ? new Date(activeGrant.current_period_end).toLocaleDateString() : 'Infinita'}</p>
                                                    {activeGrant?.granted_reason && <p className="text-xs italic mt-2">"{activeGrant.granted_reason}"</p>}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => { if (window.confirm('Revogar esse passe remove permanentemente os limites extras desse plano (se ele não constar como assinante pagante). Deseja continuar?')) revokeGrantMutation.mutate() }}
                                                    disabled={revokeGrantMutation.isPending}
                                                    className="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg border border-red-200 text-xs font-bold uppercase disabled:opacity-50"
                                                >
                                                    {revokeGrantMutation.isPending ? 'Revogando...' : 'Revogar Acesso'}
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label className="block text-xs font-bold text-gray-700 mb-1.5">Escolha o Plano</label>
                                                    <select
                                                        value={grantForm.plan_id}
                                                        onChange={e => setGrantForm({ ...grantForm, plan_id: e.target.value })}
                                                        className="w-full rounded-lg border-gray-300 text-sm py-2"
                                                    >
                                                        <option value="">Selecione um plano...</option>
                                                        {(Array.isArray(plansData) ? plansData : plansData?.data ?? []).map((p: any) => (
                                                            <option key={p.id} value={p.id}>{p.name}{p.price > 0 ? ` - R$ ${Number(p.price).toFixed(2)}` : ' (Gratuito)'}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="flex gap-2">
                                                    <div className="w-1/3">
                                                        <label className="block text-xs font-bold text-gray-700 mb-1.5">Tipo Duração</label>
                                                        <select
                                                            value={grantForm.duration_type}
                                                            onChange={e => setGrantForm({ ...grantForm, duration_type: e.target.value })}
                                                            className="w-full rounded-lg border-gray-300 text-sm py-2"
                                                        >
                                                            <option value="days">Dias</option>
                                                            <option value="months">Meses</option>
                                                        </select>
                                                    </div>
                                                    <div className="w-2/3">
                                                        <label className="block text-xs font-bold text-gray-700 mb-1.5">Quantidade de {grantForm.duration_type === 'days' ? 'Dias' : 'Meses'}</label>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            value={grantForm.duration_value}
                                                            onChange={e => setGrantForm({ ...grantForm, duration_value: parseInt(e.target.value) || 1 })}
                                                            className="w-full rounded-lg border-gray-300 text-sm py-2"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Motivo (Opcional - Invisível para o usuário)</label>
                                                <input
                                                    type="text"
                                                    placeholder="Ex: Presenteado por participar da live, suporte..."
                                                    value={grantForm.reason}
                                                    onChange={e => setGrantForm({ ...grantForm, reason: e.target.value })}
                                                    className="w-full rounded-lg border-gray-300 text-sm py-2"
                                                />
                                            </div>
                                            <div className="flex justify-end pt-2">
                                                <button
                                                    type="button"
                                                    disabled={!grantForm.plan_id || grantMutation.isPending}
                                                    onClick={() => { if (window.confirm('Tem certeza? Isso ativará um plano sem gerar cobrança.')) grantMutation.mutate(grantForm); }}
                                                    className="px-5 py-2.5 bg-indigo-600 text-white hover:bg-indigo-700 rounded-lg text-sm font-bold uppercase transition-colors disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                                                >
                                                    {grantMutation.isPending ? 'Concedendo...' : 'Conceder Acesso Agora'}
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div className="flex justify-end pt-4 border-t border-gray-100">
                                    <button type="submit" disabled={updateMutation.isPending} className="px-8 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 font-bold transition-colors disabled:opacity-70 disabled:cursor-not-allowed shadow-sm text-sm uppercase tracking-wide">
                                        {updateMutation.isPending ? 'Salvando...' : 'Salvar Informações Normais'}
                                    </button>
                                </div>
                            </form>
                        )}

                        {/* TAB: DESEMPENHO ACADÊMICO */}
                        {activeTab === 'academic' && (
                            <AdminUserStats userId={user.id} />
                        )}

                        {/* TAB: HISTÓRICO */}
                        {activeTab === 'history' && (
                            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">

                                {/* IA Logs */}
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                                        Uso da Inteligência Artificial
                                    </h3>
                                    {(!promptHistory?.data || promptHistory.data.length === 0) ? (
                                        <EmptyState title="Nenhum registro" message="O usuário não solicitou interações ou chats com a IA até o momento." />
                                    ) : (
                                        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                            <table className="w-full text-sm text-left">
                                                <thead className="bg-gray-50 border-b border-gray-200">
                                                    <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                                        <th className="px-4 py-3">Data da Interação</th>
                                                        <th className="px-4 py-3">Provedor/Modelo</th>
                                                        <th className="px-4 py-3">Tokens Gastos (In / Out)</th>
                                                        <th className="px-4 py-3 text-right">Custo Estimado</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {promptHistory.data.map((log: any) => (
                                                        <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                                            <td className="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                                                {new Date(log.created_at).toLocaleString('pt-BR')}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="flex flex-col">
                                                                    <span className="font-semibold text-gray-800">{log.provider || '-'}</span>
                                                                    <span className="text-[10px] text-gray-500">{log.model || '-'}</span>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-gray-600 font-mono text-xs">
                                                                {log.tokens_used_input !== null ? log.tokens_used_input : '-'} / {log.tokens_used_output !== null ? log.tokens_used_output : '-'}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-semibold text-gray-800">
                                                                R$ {Number(log.estimated_cost || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4 })}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>

                                {/* Subscription Logs */}
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                        Ciclos de Assinatura
                                    </h3>
                                    {(!userData.user?.subscriptions || userData.user.subscriptions.length === 0) ? (
                                        <EmptyState title="Nenhuma assinatura" message="O histórico de compras premium está vazio." icon={<svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>} />
                                    ) : (
                                        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                            <table className="w-full text-sm text-left">
                                                <thead className="bg-gray-50 border-b border-gray-200">
                                                    <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                                        <th className="px-4 py-3">Plano Adquirido</th>
                                                        <th className="px-4 py-3">Status Base</th>
                                                        <th className="px-4 py-3">Início</th>
                                                        <th className="px-4 py-3">Data de Renovação/Fim</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {userData.user.subscriptions.map((sub: any) => (
                                                        <tr key={sub.id} className="hover:bg-gray-50 transition-colors">
                                                            <td className="px-4 py-3 font-semibold text-gray-800">
                                                                {sub.plan?.name || 'Desconhecido'}
                                                                {sub.is_manual_grant && <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-indigo-100 text-indigo-700">Grant</span>}
                                                                {sub.installment_count && <span className="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-emerald-100 text-emerald-700">{sub.installment_count}x</span>}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase ${sub.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>
                                                                    {sub.status === 'active' ? 'Ativo' : sub.status}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3 text-gray-600 text-xs">{new Date(sub.created_at).toLocaleDateString('pt-BR')}</td>
                                                            <td className="px-4 py-3 text-gray-600 text-xs">{sub.current_period_end ? new Date(sub.current_period_end).toLocaleDateString('pt-BR') : '-'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>

                                {/* System Audit Logs */}
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                        Auditoria Sistêmica
                                    </h3>
                                    {(!userData.user?.logs || userData.user.logs.length === 0) ? (
                                        <EmptyState title="Auditoria Limpa" message="Sem eventos críticos para exibir no perfil deste aluno." icon={<svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>} />
                                    ) : (
                                        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                                            <table className="w-full text-sm text-left">
                                                <thead className="bg-gray-50 border-b border-gray-200">
                                                    <tr className="text-[11px] text-gray-500 uppercase font-bold tracking-wider">
                                                        <th className="px-4 py-3 w-1/4">Ação</th>
                                                        <th className="px-4 py-3 w-1/2">Informação Extra (Payload)</th>
                                                        <th className="px-4 py-3 text-right w-1/4">Data</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {userData.user.logs.map((log: any) => (
                                                        <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                                            <td className="px-4 py-3 font-semibold text-indigo-700 text-[11px] uppercase tracking-wide">
                                                                {log.action}
                                                                {log.ip_address && <span className="block text-[9px] text-gray-400 font-mono mt-0.5 font-normal">{log.ip_address}</span>}
                                                            </td>
                                                            <td className="px-4 py-3 text-gray-600 text-xs break-words">
                                                                {/* Fallback to JSON details if description is not populated natively */}
                                                                {log.description || log.details || '-'}
                                                            </td>
                                                            <td className="px-4 py-3 text-right text-gray-500 text-xs">
                                                                {new Date(log.created_at).toLocaleString('pt-BR')}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* TAB: SEGURANÇA */}
                        {activeTab === 'security' && (
                            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">

                                <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                    <div className="p-5 border-b border-gray-100 flex justify-between items-center sm:flex-row flex-col gap-4">
                                        <div>
                                            <h4 className="text-base font-bold text-gray-800 flex items-center">
                                                <span className={`w-2.5 h-2.5 rounded-full mr-2 ${user.is_banned ? 'bg-red-500' : 'bg-green-500'}`}></span>
                                                Status de Acesso à Plataforma
                                            </h4>
                                            <p className="text-sm text-gray-500 mt-1">
                                                {user.is_banned ? 'Usuário banido. Atualmente impedido de fazer login no painel.' : 'Conta legítima. Sessão e login permitidos livremente.'}
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => { if (window.confirm(`Tem certeza de que deseja ${user.is_banned ? 'DUBBLOQUEAR' : 'BANIR'} o usuário?`)) toggleStatusMutation.mutate(); }}
                                            className={`px-5 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-colors border shadow-sm shrink-0 ${user.is_banned ? 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' : 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'}`}
                                        >
                                            {user.is_banned ? 'Desbloquear Usuário' : 'Banir Usuário'}
                                        </button>
                                    </div>

                                    <div className="p-5 bg-gray-50/50">
                                        <h4 className="text-sm font-bold text-gray-700 mb-3 block">Recuperação e Reset de Senha</h4>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                            <div className="bg-white p-4 border border-gray-200 rounded-xl shadow-sm">
                                                <p className="text-xs text-gray-500 mb-3">Opção recomendada: Apenas enviar o link mágico de redefinição para a caixa de e-mail cadastrada.</p>
                                                <button
                                                    onClick={() => { if (window.confirm('Habilitar o envio de email para ' + user.email + '?')) resetPasswordMutation.mutate({ send_email: 1 }); }}
                                                    className="w-full flex items-center justify-center gap-2 bg-gray-50 border border-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-100 transition-colors text-xs font-bold shadow-sm"
                                                >
                                                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                    Enviar Email de Reset
                                                </button>
                                            </div>
                                            <div className="bg-white p-4 border border-gray-200 rounded-xl shadow-sm">
                                                <p className="text-xs text-gray-500 mb-3">Opção forçada: Definir uma nova senha de substituição independentemente do usuário e na hora.</p>
                                                <div className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={manualPassword}
                                                        onChange={(e) => setManualPassword(e.target.value)}
                                                        placeholder="Digite uma nova senha segura"
                                                        className="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-colors"
                                                    />
                                                    <button
                                                        onClick={() => { if (manualPassword && window.confirm('Deseja forçar essa nova senha na base de dados?')) resetPasswordMutation.mutate({ new_password: manualPassword }); }}
                                                        disabled={!manualPassword}
                                                        className="bg-gray-800 text-white px-4 rounded-lg hover:bg-gray-900 text-xs font-bold uppercase transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm shrink-0"
                                                    >
                                                        Gravar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Danger Zone: Financial CDC */}
                                <div className="border border-red-200 bg-red-400/5 rounded-2xl p-6 relative overflow-hidden">
                                    <div className="absolute top-0 left-0 w-1.5 h-full bg-red-500"></div>
                                    <div className="flex items-start mb-4 gap-4">
                                        <div className="p-2.5 bg-red-100/80 rounded-xl shrink-0">
                                            <svg className="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        </div>
                                        <div>
                                            <h4 className="font-extrabold text-red-800 text-lg tracking-tight">Destruição de Assinatura (Regra dos 7 Dias / Estorno)</h4>
                                            <p className="text-sm text-red-800/80 mt-1 leading-relaxed max-w-2xl font-medium">
                                                Este recurso irá paralisar imediatamente o plano do usuário, ajustando a data de validade para HOJE. Adicionalmente, <strong>todos os saldos extras, acúmulos e limites premium serão zerados</strong>. Um LOG crítico de segurança será gerado em seu nome.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex justify-end mt-8 border-t border-red-200/50 pt-5">
                                        <button
                                            onClick={() => {
                                                if (window.confirm('ALERTA MÁXIMO:\n\nEsse procedimento é severo e NÃO PODE ser desfeito.\nAs quotas acumuladas do plano desaparecerão das estatísticas.\n\nVocê está CIENTE e deseja ESTORNAR a assinatura base e seus limites?')) {
                                                    refundMutation.mutate();
                                                }
                                            }}
                                            disabled={refundMutation.isPending || !user.subscriptions?.some((s: any) => s.status === 'active')}
                                            className="px-6 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black tracking-widest uppercase text-xs shadow-lg shadow-red-500/20 disabled:opacity-50 disabled:shadow-none transition-all flex items-center justify-center min-w-[280px]"
                                        >
                                            {refundMutation.isPending ? 'ACELERANDO CANCELAMENTO...' : 'CONCORDAR E CANCELAR AGORA'}
                                        </button>
                                    </div>
                                    {!user.subscriptions?.some((s: any) => s.status === 'active') && (
                                        <p className="text-[10px] text-red-500/70 mt-3 text-right uppercase font-bold tracking-widest">Condição não atingida: É necessário ao menos 1 assinatura ativa.</p>
                                    )}
                                </div>

                                {/* CRITICAL ZONE: TOTAL WIPEOUT */}
                                <div className="border-2 border-dashed border-red-300 bg-red-50/30 rounded-2xl p-6 mt-12">
                                    <div className="flex items-center gap-3 mb-4">
                                        <div className="w-10 h-10 rounded-full bg-red-600 flex items-center justify-center text-white font-bold text-xl">!</div>
                                        <div>
                                            <h4 className="font-black text-red-900 uppercase text-sm tracking-tighter">Zona de Exclusão Total (Total Wipeout)</h4>
                                            <p className="text-xs text-red-700 font-bold uppercase">Ação Irreversível — Sem Backup de Tráfego</p>
                                        </div>
                                    </div>
                                    <p className="text-xs text-gray-600 leading-relaxed mb-6 font-medium">
                                        Esta funcionalidade remove o usuário de todas as tabelas do banco de dados, incluindo registros de analytics, logs de IA, arquivos de imagem no storage e interações de suporte. Utilize apenas para solicitações de exclusão via LGPD ou limpeza total de contas de teste.
                                    </p>
                                    <div className="flex justify-end">
                                        <button
                                            onClick={handleDeleteUser}
                                            disabled={deleteMutation.isPending}
                                            className="px-6 py-3 bg-white border-2 border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-xl font-black uppercase text-xs transition-all shadow-sm disabled:opacity-50"
                                        >
                                            {deleteMutation.isPending ? 'EXCLUINDO TUDO...' : 'Excluir Conta e Todos os Dados'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
