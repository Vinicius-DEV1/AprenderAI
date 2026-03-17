import { useState, useEffect, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markNotificationRead, markAllNotificationsRead } from '../api/notifications';

interface Notification {
    id: number;
    title: string;
    body: string | null;
    type: 'info' | 'success' | 'warning' | 'tip';
    action_url: string | null;
    is_read: boolean;
    created_at: string;
}

export default function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);
    const queryClient = useQueryClient();

    const { data } = useQuery({
        queryKey: ['user-notifications'],
        queryFn: () => getNotifications().then((r: any) => r.data),
        refetchInterval: 60000, 
    });

    const notifications: Notification[] = data?.notifications ?? [];
    const unreadCount: number = data?.unread_count ?? 0;

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };
        if (isOpen) document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [isOpen]);

    const markRead = async (id: number) => {
        await markNotificationRead(id);
        queryClient.invalidateQueries({ queryKey: ['user-notifications'] });
    };

    const markAllRead = async () => {
        await markAllNotificationsRead();
        queryClient.invalidateQueries({ queryKey: ['user-notifications'] });
    };

    const typeIcons: Record<string, { icon: string; color: string }> = {
        info:    { icon: 'ℹ️', color: 'text-blue-500' },
        success: { icon: '✅', color: 'text-green-500' },
        warning: { icon: '⚠️', color: 'text-yellow-500' },
        tip:     { icon: '💡', color: 'text-purple-500' },
    };

    const formatDate = (iso: string) => {
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }) + ' ' +
               d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="relative" ref={panelRef}>
            <button
                onClick={() => setIsOpen(o => !o)}
                className="relative p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Notificações"
            >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full shadow-sm">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-[380px] sm:w-[450px] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 z-50 overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200">Notificações</h3>
                            {unreadCount > 0 && (
                                <span className="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 text-[10px] font-bold rounded-md">
                                    {unreadCount} novas
                                </span>
                            )}
                        </div>
                        <div className="flex gap-3">
                            {unreadCount > 0 && (
                                <button
                                    onClick={markAllRead}
                                    className="text-[11px] text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                                >
                                    Ler tudo
                                </button>
                            )}
                            <a href="/notificacoes" className="text-[11px] text-slate-500 hover:text-blue-600 font-semibold">Ver todas</a>
                        </div>
                    </div>

                    <div className="max-h-[450px] overflow-y-auto divide-y divide-slate-50 dark:divide-slate-800">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-12 text-center text-sm text-slate-400">
                                <div className="text-4xl mb-3">🔔</div>
                                <p className="font-medium">Nenhuma notificação por aqui.</p>
                                <p className="text-xs text-slate-500 mt-1">Avisaremos você quando algo novo aparecer.</p>
                            </div>
                        ) : notifications.slice(0, 10).map(n => (
                            <div
                                key={n.id}
                                className={`px-4 py-3.5 transition-colors ${!n.is_read ? 'bg-blue-50/60 dark:bg-blue-950/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'}`}
                            >
                                <div className="flex items-start gap-4">
                                    <span className="text-xl flex-shrink-0 mt-0.5">
                                        {typeIcons[n.type]?.icon ?? 'ℹ️'}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-sm font-bold leading-tight ${!n.is_read ? 'text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300'}`}>
                                            {n.title}
                                        </p>
                                        {n.body && (
                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-3 leading-relaxed">
                                                {n.body}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-3 mt-2">
                                            <span className="text-[10px] font-medium text-slate-400">{formatDate(n.created_at)}</span>
                                            {n.action_url ? (
                                                <a href={n.action_url} className="text-[10px] bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded font-bold hover:bg-blue-200 transition-colors">
                                                    ACESSAR →
                                                </a>
                                            ) : (
                                                <a href="/notificacoes" className="text-[10px] text-blue-500 hover:underline font-bold uppercase tracking-wider">
                                                    Ver detalhes
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                    {!n.is_read && (
                                        <button 
                                            onClick={() => markRead(n.id)}
                                            className="w-2.5 h-2.5 mt-1.5 rounded-full bg-blue-500 flex-shrink-0" 
                                            title="Marcar como lida"
                                        />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    {notifications.length > 10 && (
                        <a 
                            href="/notificacoes" 
                            className="block w-full text-center py-3 text-xs font-bold text-blue-600 bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 transition-colors"
                        >
                            VER TODAS AS NOTIFICAÇÕES
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}
