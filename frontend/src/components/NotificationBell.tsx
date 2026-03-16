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

/**
 * NotificationBell — bell icon with unread badge + dropdown list.
 *
 * Uses React Query to poll for new notifications every 60 seconds.
 * The bell badge shows the unread count.
 */
export default function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);
    const queryClient = useQueryClient();

    const { data } = useQuery({
        queryKey: ['user-notifications'],
        queryFn: () => getNotifications().then(r => r.data),
        refetchInterval: 60000, // Poll every 60s
    });

    const notifications: Notification[] = data?.notifications ?? [];
    const unreadCount: number = data?.unread_count ?? 0;

    // Close on outside click
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
            {/* Bell Button */}
            <button
                onClick={() => setIsOpen(o => !o)}
                className="relative p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Notificações"
                aria-label="Notificações"
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

            {/* Dropdown Panel */}
            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 z-50 overflow-hidden">
                    {/* Header */}
                    <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                        <h3 className="text-sm font-semibold text-slate-800 dark:text-slate-200">Notificações</h3>
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllRead}
                                className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium"
                            >
                                Marcar tudo como lido
                            </button>
                        )}
                    </div>

                    {/* List */}
                    <div className="max-h-80 overflow-y-auto divide-y divide-slate-50 dark:divide-slate-800">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-slate-400">
                                <div className="text-3xl mb-2">🔔</div>
                                Nenhuma notificação ainda.
                            </div>
                        ) : notifications.map(n => (
                            <div
                                key={n.id}
                                className={`px-4 py-3 transition-colors ${!n.is_read ? 'bg-blue-50/60 dark:bg-blue-950/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'}`}
                            >
                                <div className="flex items-start gap-3">
                                    <span className="text-lg flex-shrink-0 mt-0.5">
                                        {typeIcons[n.type]?.icon ?? 'ℹ️'}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-sm font-semibold truncate ${!n.is_read ? 'text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300'}`}>
                                            {n.title}
                                        </p>
                                        {n.body && (
                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                                {n.body}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-2 mt-1.5">
                                            <span className="text-[10px] text-slate-400">{formatDate(n.created_at)}</span>
                                            {n.action_url && (
                                                <a href={n.action_url} className="text-[10px] text-blue-500 hover:underline font-medium">
                                                    Ver →
                                                </a>
                                            )}
                                            {!n.is_read && (
                                                <button
                                                    onClick={() => markRead(n.id)}
                                                    className="text-[10px] text-slate-400 hover:text-blue-500 ml-auto"
                                                >
                                                    Marcar como lida
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    {!n.is_read && (
                                        <div className="w-2 h-2 mt-1.5 rounded-full bg-blue-500 flex-shrink-0"></div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
