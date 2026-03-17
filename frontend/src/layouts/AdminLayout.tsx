import { useState, useEffect } from 'react';
import { NavLink, Outlet, useLocation, Navigate } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';
import { useAuthStore } from '../stores/authStore';
import { useUIStore } from '../stores/uiStore';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/axios';
import AdminBatchModal from '../pages/admin/components/AdminBatchModal';
import NotificationBell from '../components/NotificationBell';

export default function AdminLayout() {
    const config = useConfigStore();
    const location = useLocation();
    const ui = useUIStore();
    const queryClient = useQueryClient();
    const [toastMessage, setToastMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    const { user, isAuthenticated, isLoading } = useAuthStore();

    // BUG FIX: Aguardando carregamento da sessão antes de decidir redirecionamento.
    // Sem este guard, no reload o componente tentava verificar auth quando
    // isAuthenticated=false ainda (estado inicial), causando redirect prematuro e tela branca.
    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    // Redireciona se não for admin (só após saber o estado real de auth)
    if (isAuthenticated && user && user.role !== 'admin') {
        return <Navigate to="/dashboard" replace />;
    }


    // Handle toast timeout
    useEffect(() => {
        if (toastMessage) {
            const timer = setTimeout(() => setToastMessage(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [toastMessage]);

    const isRouteActive = (pattern: string) => {
        return location.pathname.startsWith(pattern);
    };

    const getNavLinkClass = (pattern: string, activeColorClass = 'bg-purple-50 text-purple-700 shadow-sm') => {
        const active = isRouteActive(pattern);
        return `flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all ${active ? activeColorClass : 'text-gray-600 hover:bg-gray-100'
            }`;
    };

    // Global AI Batch Polling
    const { data: activeBatchData } = useQuery({
        queryKey: ['admin-triage-active'],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/triage/active');
            return res.data;
        },
        refetchInterval: ui.isBatchModalOpen ? false : 30000
    });

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 text-slate-800 font-sans antialiased h-full">
            <div className="flex">
                {/* Sidebar */}
                <aside className="w-64 bg-white shadow-lg min-h-screen fixed lg:static z-50">
                    <div className="p-6">
                        <div className="flex items-center gap-3 mb-8">
                            <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-800">Painel Admin</h3>
                                <p className="text-xs text-gray-500">{config.appName || 'AprenderAI'}</p>
                            </div>
                        </div>

                        <nav className="space-y-6">
                            {/* SEÇÃO: OPERACIONAL */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Operacional</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/dashboard" className={getNavLinkClass('/admin/dashboard')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                        Dashboard
                                    </NavLink>

                                    <NavLink to="/admin/curadoria" className={getNavLinkClass('/admin/curadoria')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Portal Curadoria
                                    </NavLink>

                                    <NavLink to="/admin/questions" className={getNavLinkClass('/admin/questions')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Banco de Questões
                                    </NavLink>

                                    <NavLink to="/admin/provas" className={getNavLinkClass('/admin/provas')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Provas (PDFs)
                                    </NavLink>

                                    <NavLink to="/admin/users" className={getNavLinkClass('/admin/users')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                        Usuários
                                    </NavLink>

                                </div>
                            </div>

                            {/* SEÇÃO: NEGÓCIOS */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Financeiro</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/plans" className={getNavLinkClass('/admin/plans')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                        Planos
                                    </NavLink>

                                    <NavLink to="/admin/subscriptions" className={getNavLinkClass('/admin/subscriptions')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Assinaturas
                                    </NavLink>

                                    <NavLink to="/admin/coupons" className={getNavLinkClass('/admin/coupons')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                                        Cupons
                                    </NavLink>
                                </div>
                            </div>

                            {/* SEÇÃO: ENGAJAMENTO */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Engajamento</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/suporte" className={getNavLinkClass('/admin/suporte')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 8h2a2 2 0 06l-4 4-2-2m-2-4h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                        Suporte / Tickets
                                    </NavLink>

                                    <NavLink to="/admin/comunicados" className={getNavLinkClass('/admin/comunicados')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                                        Comunicados
                                    </NavLink>

                                    <NavLink to="/admin/sugestoes" className={getNavLinkClass('/admin/sugestoes')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        Sugestões
                                    </NavLink>
                                </div>
                            </div>

                            {/* SEÇÃO: CONFIGURAÇÕES */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Configurações</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/payment-settings" className={getNavLinkClass('/admin/payment-settings')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        Pagamentos
                                    </NavLink>
                                </div>
                            </div>

                            {/* SEÇÃO: ESTRATÉGICO */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Inteligência</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/prompts" className={getNavLinkClass('/admin/prompts')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        Prompts ({config.aiName})
                                    </NavLink>

                                    <NavLink to="/admin/xavier/insights" className={getNavLinkClass('/admin/xavier/insights')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.989-2.386l-.548-.547z" />
                                        </svg>
                                        Xavier Insights
                                    </NavLink>

                                    <NavLink to="/admin/semantic-dashboard" className={getNavLinkClass('/admin/semantic-dashboard')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        Xavier Semantic
                                    </NavLink>



                                    <NavLink to="/admin/simulations/builder" className={getNavLinkClass('/admin/simulations/builder')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                        Motor de Simulados
                                    </NavLink>

                                    <NavLink to="/admin/analytics" className={getNavLinkClass('/admin/analytics')} end>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                        Analytics
                                    </NavLink>

                                    <NavLink to="/admin/analytics/checkout" className={getNavLinkClass('/admin/analytics/checkout')} end>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        Observabilidade
                                    </NavLink>

                                    <NavLink to="/admin/platform-monitor" className={getNavLinkClass('/admin/platform-monitor')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                        Uso da Plataforma
                                    </NavLink>

                                    <NavLink to="/admin/monitor" className={getNavLinkClass('/admin/monitor')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M22 12h-4l-3 9L9 3l-3 9H2" /></svg>
                                        Monitoramento
                                    </NavLink>

                                    <NavLink to="/admin/api-keys" className={getNavLinkClass('/admin/api-keys')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                        Chaves API
                                    </NavLink>

                                    <NavLink to="/admin/api-pricing" className={getNavLinkClass('/admin/api-pricing')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Custos de API
                                    </NavLink>
                                </div>
                            </div>

                            {/* SEÇÃO: SISTEMA */}
                            <div>
                                <p className="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Sistema</p>
                                <div className="space-y-1">
                                    <NavLink to="/admin/settings" className={getNavLinkClass('/admin/settings')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        Configurações
                                    </NavLink>

                                    <NavLink to="/admin/integrations" className={getNavLinkClass('/admin/integrations')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z" /></svg>
                                        Integrações
                                    </NavLink>

                                    <NavLink to="/admin/backups" className={getNavLinkClass('/admin/backups')}>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                        </svg>
                                        Backup do Banco
                                    </NavLink>

                                    <NavLink to="/dashboard" className="flex items-center gap-3 px-4 py-2.5 text-gray-500 rounded-lg font-medium transition-all hover:bg-gray-100 italic">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                        Voltar ao Site
                                    </NavLink>
                                </div>
                            </div>
                        </nav>
                    </div>
                    <div className="p-6">
                        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div className="h-10 w-10 flex-shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm">
                                {user?.name?.substring(0, 2)?.toUpperCase() || 'AD'}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-xs font-bold text-gray-800 truncate">{user?.name || 'Administrador'}</p>
                                <span className={`inline-block mt-0.5 px-2 py-0.5 text-[9px] font-black rounded-md uppercase tracking-wider border ${(user?.plan?.name || '').toLowerCase().includes('plus')
                                    ? 'bg-blue-900 text-white border-blue-800'
                                    : (user?.plan?.name || '').toLowerCase().includes('básico') || (user?.plan?.name || '').toLowerCase().includes('basico')
                                        ? 'bg-blue-100 text-blue-800 border-blue-200'
                                        : user?.role === 'admin'
                                            ? 'bg-purple-100 text-purple-800 border-purple-200'
                                            : 'bg-white text-gray-500 border-gray-200'
                                    }`}>
                                    {user?.plan?.name || (user?.role === 'admin' ? 'Administrador' : 'Gratuito')}
                                </span>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Main Content */}
                <main className="flex-1 p-4 md:p-6 lg:ml-0">
                    <div className="lg:hidden mb-4">
                        <NavLink to="/dashboard" className="text-gray-500 flex items-center gap-2 text-sm font-medium">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Voltar ao App
                        </NavLink>
                    </div>
                    <div className="flex justify-between items-start mb-8">
                        <header>
                            {/* Slot for Header/Title */}
                        </header>

                        {/* Notifications Bell */}
                        <NotificationBell />
                    </div>

                    {/* Success/Error Toast */}
                    {toastMessage && (
                        <div className="fixed bottom-4 right-4 z-[100] flex flex-col gap-3">
                            <div className={`pointer-events-auto flex items-start gap-3 min-w-[300px] p-4 bg-white border-l-4 rounded-lg shadow-xl shrink-0 ${toastMessage.type === 'success' ? 'border-green-500' : 'border-red-500'}`}>
                                <div className="flex-1">
                                    <p className="text-gray-800 text-sm font-medium">{toastMessage.text}</p>
                                </div>
                                <button onClick={() => setToastMessage(null)} className="text-gray-400 hover:text-gray-600 transition-colors">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                    )}

                    <Outlet />
                </main>
            </div>

            {/* Global AI Batch Modal */}
            <AdminBatchModal
                isOpen={ui.isBatchModalOpen}
                onClose={() => ui.closeBatchModal()}
                pendingCount={ui.batchModalConfig?.pendingCount || 0}
                onBatchStarted={() => {
                    queryClient.invalidateQueries({ queryKey: ['admin-triage-active'] });
                }}
            />

            {/* Global AI Batch Indicator */}
            {!ui.isBatchModalOpen && activeBatchData?.success && activeBatchData?.batch_id && !ui.dismissedBatches.includes(activeBatchData.batch_id) && (
                <div
                    className={`fixed bottom-6 right-6 z-50 ${activeBatchData.status === 'processing' ? 'bg-indigo-600 animate-bounce cursor-pointer' : activeBatchData.status === 'failed' || activeBatchData.status === 'cancelled' ? 'bg-red-600' : 'bg-green-600'
                        } text-white pl-5 pr-2 py-2 rounded-full shadow-2xl hover:scale-105 transition-all flex items-center gap-3 border-2 border-white group`}
                >
                    <div className="flex items-center gap-3 cursor-pointer" onClick={() => ui.openBatchModal()}>
                        <span className="text-xl">
                            {activeBatchData.status === 'processing' ? '⏳' : activeBatchData.status === 'failed' ? '❌' : activeBatchData.status === 'cancelled' ? '🛑' : '✅'}
                        </span>
                        <span className="font-black text-sm tracking-wide">
                            {activeBatchData.status === 'processing' ? 'PAINEL IA' : activeBatchData.status === 'failed' ? 'LOTE COM FALHA' : activeBatchData.status === 'cancelled' ? 'LOTE CANCELADO' : 'LOTE CONCLUÍDO'}
                        </span>
                    </div>

                    {activeBatchData.status !== 'processing' && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                ui.dismissBatch(activeBatchData.batch_id);
                            }}
                            className="ml-2 w-8 h-8 flex items-center justify-center rounded-full bg-black/10 hover:bg-black/20 text-white transition-colors"
                            title="Ocultar aviso"
                        >
                            ✕
                        </button>
                    )}
                    <div className="absolute inset-0 rounded-full border-4 border-white opacity-20 -z-10 group-hover:animate-ping pointer-events-none"></div>
                </div>
            )}

            <style>{`
        @keyframes bounce-subtle { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .animate-bounce-subtle { animation: bounce-subtle 2s ease-in-out infinite; }
      `}</style>
        </div>
    );
}
