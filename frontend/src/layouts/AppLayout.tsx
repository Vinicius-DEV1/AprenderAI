import { useState } from 'react';
import { NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';
import { useAuthStore } from '../stores/authStore';
import { logout as apiLogout } from '../api/auth';
import { useUIStore } from '../stores/uiStore';
import { useQueryClient } from '@tanstack/react-query';
import EmailVerificationBanner from '../components/EmailVerificationBanner';

export default function AppLayout() {
    const config = useConfigStore();
    const { user, logout } = useAuthStore();
    const { sidebarCollapsed, setSidebarCollapsed } = useUIStore();
    const navigate = useNavigate();
    const location = useLocation();

    const [sidebarOpen, setSidebarOpen] = useState(false);

    const [darkMode, setDarkMode] = useState(() => {
        return document.documentElement.classList.contains('dark');
    });

    const toggleTheme = () => {
        const newDarkMode = !darkMode;
        setDarkMode(newDarkMode);
        document.documentElement.classList.toggle('dark', newDarkMode);
        localStorage.setItem('theme', newDarkMode ? 'dark' : 'light');
    };

    const queryClient = useQueryClient();

    const handleLogout = async (e: React.FormEvent) => {
        e.preventDefault();
        // Clear local state FIRST to prevent data leakage (favorites, history) if API fails
        queryClient.clear();
        logout();
        navigate('/login');
        try {
            await apiLogout();
        } catch (error) {
            console.error('Logout API failed, but local state was cleared.');
        }
    };

    const isRouteActive = (pattern: string) => {
        if (pattern === '/dashboard' && location.pathname === '/dashboard') return true;
        if (pattern !== '/dashboard' && location.pathname.startsWith(pattern)) return true;
        return false;
    };

    const getNavLinkClass = (pattern: string) => {
        const active = isRouteActive(pattern);
        const baseClass = 'flex items-center rounded-lg text-sm font-medium transition-all duration-300';

        if (pattern === '/planos') {
            return `${baseClass} ${active
                ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'
                } ${sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'}`;
        }

        // Special case for admin
        if (pattern === '/admin') {
            return `${baseClass} ${active
                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'
                } ${sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'}`;
        }

        return `${baseClass} ${active
            ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'
            } ${sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'}`;
    };

    const getIconClass = (pattern: string, colorClass = 'blue') => {
        const active = isRouteActive(pattern);
        if (active) return `w-5 h-5 flex-shrink-0 text-${colorClass}-600 dark:text-${colorClass}-400`;
        return 'w-5 h-5 flex-shrink-0 text-slate-400 dark:text-slate-500';
    };

    // Safe checks for user attributes
    const userInitials = user?.name ? user.name.substring(0, 1).toUpperCase() : 'U';
    const isAdmin = user?.role === 'admin';

    return (
        <div className="min-h-screen flex text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">

            {/* MOBILE SIDEBAR OVERLAY */}
            {sidebarOpen && (
                <div
                    onClick={() => setSidebarOpen(false)}
                    className="fixed inset-0 bg-slate-900/80 z-40 lg:hidden"
                ></div>
            )}

            {/* SIDEBAR */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 bg-white dark:bg-slate-900 shadow-xl dark:shadow-slate-950/50 transform transition-all duration-300 ease-in-out lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:inset-auto lg:flex lg:flex-col border-r border-slate-200 dark:border-slate-700 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    } ${sidebarCollapsed ? 'lg:w-20' : 'lg:w-72'}`}
            >
                {/* Toggle Button (Desktop) */}
                <button
                    onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                    className="hidden lg:flex absolute -right-3 top-10 w-6 h-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-full items-center justify-center shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors z-[60]"
                >
                    <svg className={`w-4 h-4 text-slate-500 dark:text-slate-400 transform transition-transform duration-300 ${sidebarCollapsed ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {/* Logo Area */}
                <div className={`flex items-center h-20 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-300 overflow-hidden ${sidebarCollapsed ? 'justify-center px-0' : 'justify-between px-4'
                    }`}>
                    <NavLink to="/dashboard" className="flex items-center gap-2 text-white font-bold text-xl whitespace-nowrap">
                        <svg className="w-8 h-8 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {!sidebarCollapsed && <span>{config.appName}</span>}
                    </NavLink>
                    <button onClick={() => setSidebarOpen(false)} className="lg:hidden text-white hover:text-slate-200">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Navigation */}
                <nav className={`flex-1 py-6 space-y-1 overflow-y-auto overflow-x-hidden transition-all duration-300 ${sidebarCollapsed ? 'px-2' : 'px-3'}`}>

                    {!sidebarCollapsed && <p className="px-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Menu Principal</p>}

                    <NavLink to="/dashboard" className={getNavLinkClass('/dashboard')} title="Dashboard">
                        <svg className={getIconClass('/dashboard')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Dashboard</span>}
                    </NavLink>

                    <NavLink to="/simulados" className={getNavLinkClass('/simulados')} title="Simulados">
                        <svg className={getIconClass('/simulados')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Simulados</span>}
                    </NavLink>

                    <NavLink to="/questoes" className={getNavLinkClass('/questoes')} title="Questões">
                        <svg className={getIconClass('/questoes')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Questões</span>}
                    </NavLink>


                    <NavLink to="/redacoes" className={getNavLinkClass('/redacoes')} title="Redações">
                        <svg className={getIconClass('/redacoes')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Redações</span>}
                    </NavLink>

                    <NavLink to="/plano-de-estudo" className={getNavLinkClass('/plano-de-estudo')} title="Plano de Estudos">
                        <svg className={getIconClass('/plano-de-estudo')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Plano de Estudos</span>}
                    </NavLink>

                    <NavLink to="/concursos" className={getNavLinkClass('/concursos')} title="Concursos">
                        <svg className={getIconClass('/concursos')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Concursos</span>}
                    </NavLink>

                    {!sidebarCollapsed && <p className="px-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mt-6 mb-2">Conta</p>}

                    <NavLink to="/perfil" className={getNavLinkClass('/perfil')} title="Meu Perfil">
                        <svg className={getIconClass('/perfil')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Meu Perfil</span>}
                    </NavLink>

                    <NavLink to="/planos" className={getNavLinkClass('/planos')} title="Meu Plano">
                        <svg className={getIconClass('/planos', 'purple')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        {!sidebarCollapsed && <span className="whitespace-nowrap">Meu Plano</span>}
                    </NavLink>

                    {isAdmin && (
                        <NavLink to="/admin" className={getNavLinkClass('/admin')} title="Painel Admin">
                            <svg className={getIconClass('/admin', 'purple')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37.996.608 2.296.07 2.572-1.065z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {!sidebarCollapsed && <span className="whitespace-nowrap">Admin</span>}
                        </NavLink>
                    )}
                </nav>

                {/* User Footer + Theme Toggle */}
                <div className="px-2 py-4 border-t border-slate-200 dark:border-slate-700 transition-all duration-300">
                    <div className={`flex items-center gap-2 w-full p-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 overflow-hidden ${sidebarCollapsed ? 'justify-center' : ''}`}>
                        <div className="h-8 w-8 flex-shrink-0 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-xs">
                            {userInitials}
                        </div>

                        {!sidebarCollapsed && (
                            <div className="flex-1 min-w-0">
                                <p className="text-xs font-semibold text-slate-900 dark:text-slate-100 truncate">
                                    {user?.name || 'User'}
                                </p>
                                <span className={`inline-block mt-0.5 px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wide border ${(user?.plan?.name || '').toLowerCase().includes('plus')
                                    ? 'bg-blue-900 text-white border-blue-800'
                                    : (user?.plan?.name || '').toLowerCase().includes('básico') || (user?.plan?.name || '').toLowerCase().includes('basico')
                                        ? 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800'
                                        : isAdmin
                                            ? 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-800'
                                            : 'bg-white text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
                                    }`}>
                                    {user?.plan?.name || (isAdmin ? 'Administrador' : 'Gratuito')}
                                </span>
                            </div>
                        )}

                        {!sidebarCollapsed && (
                            <button onClick={toggleTheme} className="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors" title={darkMode ? 'Modo Claro' : 'Modo Escuro'}>
                                {darkMode ? '☀️' : '🌙'}
                            </button>
                        )}

                        {!sidebarCollapsed && (
                            <form onSubmit={handleLogout} className="flex-shrink-0">
                                <button type="submit" className="text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 transition-colors p-1" title="Sair">
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </button>
                            </form>
                        )}
                    </div>

                    {sidebarCollapsed && (
                        <div className="mt-2 flex justify-center">
                            <button onClick={toggleTheme} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" title={darkMode ? 'Modo Claro' : 'Modo Escuro'}>
                                {darkMode ? '☀️' : '🌙'}
                            </button>
                        </div>
                    )}
                </div>
            </aside>

            {/* MAIN CONTENT */}
            <div className="flex-1 flex flex-col min-w-0 transition-all duration-300 lg:ml-0">

                {/* Mobile Header */}
                <header className="lg:hidden flex items-center justify-between h-16 px-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <div className="flex items-center gap-3">
                        <button onClick={() => setSidebarOpen(true)} className="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <span className="font-bold text-lg text-slate-800 dark:text-slate-100">{config.appName}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={toggleTheme} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <span className="text-lg">{darkMode ? '☀️' : '🌙'}</span>
                        </button>
                        <div className="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-sm">
                            {userInitials}
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto">
                    {/* <EmailVerificationBanner /> */}
                    <div className="px-4 pt-2 pb-4 lg:px-8 lg:pt-2 lg:pb-8">
                        <div className="max-w-7xl mx-auto">
                            <Outlet />
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
