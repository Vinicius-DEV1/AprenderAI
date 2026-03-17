import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markAllNotificationsRead, markNotificationRead } from '../api/notifications';
import { toast } from 'sonner';
import MetaTags from '../components/MetaTags';

export default function Notifications() {
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['user-notifications-full'],
        queryFn: () => getNotifications().then(r => r.data),
    });

    const notifications = data?.notifications ?? [];
    const unreadCount = data?.unread_count ?? 0;

    const markReadMutation = useMutation({
        mutationFn: (id: number) => markNotificationRead(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['user-notifications-full'] }),
    });

    const markAllReadMutation = useMutation({
        mutationFn: () => markAllNotificationsRead(),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-notifications-full'] });
            toast.success('Todas as notificações foram lidas');
        },
    });

    const typeIcons: Record<string, { icon: string; bg: string; text: string }> = {
        info:    { icon: 'ℹ️', bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600' },
        success: { icon: '✅', bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-600' },
        warning: { icon: '⚠️', bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-600' },
        tip:     { icon: '💡', bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-600' },
    };

    const formatDate = (iso: string) => {
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }) + ' às ' +
               d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <MetaTags title="Minhas Notificações" />
            
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Centro de Notificações</h1>
                    <p className="text-slate-500 dark:text-slate-400">Acompanhe as novidades e avisos da plataforma.</p>
                </div>
                {unreadCount > 0 && (
                    <button 
                        onClick={() => markAllReadMutation.mutate()}
                        className="px-4 py-2 text-sm font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 rounded-lg transition-colors border border-blue-200 dark:border-blue-800"
                    >
                        Limpar notificações não lidas
                    </button>
                )}
            </div>

            <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                {isLoading ? (
                    <div className="p-12 text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p className="text-slate-500">Carregando suas notificações...</p>
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="p-20 text-center">
                        <div className="text-5xl mb-4">🔔</div>
                        <h2 className="text-xl font-bold text-slate-800 dark:text-slate-200 mb-1">Tudo em dia por aqui!</h2>
                        <p className="text-slate-500">Você não possui notificações no momento.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {notifications.map((n: any) => (
                            <div 
                                key={n.id} 
                                className={`p-6 transition-all ${!n.is_read ? 'bg-blue-50/40 dark:bg-blue-950/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'}`}
                            >
                                <div className="flex items-start gap-4">
                                    <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 ${typeIcons[n.type]?.bg || 'bg-slate-100 dark:bg-slate-800'}`}>
                                        {typeIcons[n.type]?.icon ?? 'ℹ️'}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between mb-1">
                                            <h3 className={`text-lg font-bold truncate ${!n.is_read ? 'text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300'}`}>
                                                {n.title}
                                            </h3>
                                            {!n.is_read && <span className="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>}
                                        </div>
                                        <p className="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                                            {n.body || 'Sem conteúdo adicional.'}
                                        </p>
                                        <div className="flex flex-wrap items-center gap-4 text-sm">
                                            <span className="text-slate-400 font-medium">
                                                {formatDate(n.created_at)}
                                            </span>
                                            <div className="flex items-center gap-3">
                                                {n.action_url && (
                                                    <a 
                                                        href={n.action_url}
                                                        className="px-4 py-1.5 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition shadow-sm"
                                                    >
                                                        Ver Detalhes
                                                    </a>
                                                )}
                                                {!n.is_read && (
                                                    <button 
                                                        onClick={() => markReadMutation.mutate(n.id)}
                                                        className="text-blue-600 dark:text-blue-400 font-semibold hover:underline"
                                                    >
                                                        Marcar como lida
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
