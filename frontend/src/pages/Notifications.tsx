import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { getNotifications, markAllNotificationsRead, markNotificationRead } from '../api/notifications';
import { getPendingPixSubscription } from '../api/subscriptions';
import { toast } from 'sonner';
import MetaTags from '../components/MetaTags';
import PixRecoveryModal from '../components/PixRecoveryModal';

// ── Types ────────────────────────────────────────────────────────────────────

interface NotificationMeta {
    plan_name?: string;
    amount?: number;
    subscription_id?: number;
    pix_expires_at?: string;
    action?: 'resume_pix' | 'regenerate_pix' | 'resume_essay';
    essay_id?: number;
}

interface AppNotification {
    id: number;
    title: string;
    body: string | null;
    type: string;
    action_url: string | null;
    related_payment_id: number | null;
    meta: NotificationMeta | null;
    is_read: boolean;
    created_at: string;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const PIX_TYPES = ['payment_pending', 'payment_expired'];
const ESSAY_TYPES = ['essay_pending'];

const typeConfig: Record<string, { icon: string; bg: string; text: string; border: string }> = {
    info:            { icon: 'ℹ️',  bg: 'bg-blue-100 dark:bg-blue-900/30',     text: 'text-blue-600',   border: '' },
    success:         { icon: '✅',  bg: 'bg-green-100 dark:bg-green-900/30',    text: 'text-green-600',  border: '' },
    warning:         { icon: '⚠️',  bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-600', border: '' },
    tip:             { icon: '💡',  bg: 'bg-purple-100 dark:bg-purple-900/30',  text: 'text-purple-600', border: '' },
    payment_pending: { icon: '💳',  bg: 'bg-amber-100 dark:bg-amber-900/30',    text: 'text-amber-600',  border: 'border-l-4 border-amber-400' },
    payment_expired: { icon: '⏰',  bg: 'bg-red-100 dark:bg-red-900/30',        text: 'text-red-600',    border: 'border-l-4 border-red-400' },
    essay_pending:   { icon: '📝',  bg: 'bg-indigo-100 dark:bg-indigo-900/30',  text: 'text-indigo-600', border: 'border-l-4 border-indigo-400' },
};

const formatDate = (iso: string) => {
    const d = new Date(iso);
    return (
        d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }) +
        ' às ' +
        d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    );
};

// ── Component ─────────────────────────────────────────────────────────────────

export default function Notifications() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    // Modal state
    const [activeModal, setActiveModal] = useState<{
        subscriptionId: number;
        notificationId: number;
        pixPayload?: string;
        pixImage?: string;
        pixExpiresAt?: string;
        isExpired?: boolean;
        planName?: string;
    } | null>(null);
    const [loadingPixId, setLoadingPixId] = useState<number | null>(null);

    // ── Data fetching ──────────────────────────────────────────────────────
    const { data, isLoading } = useQuery({
        queryKey: ['user-notifications-full'],
        queryFn: () => getNotifications().then(r => r.data),
    });

    const rawNotifications: AppNotification[] = data?.notifications ?? [];
    const unreadCount = data?.unread_count ?? 0;

    // Sort: Pix + essay types first, then by created_at desc
    const notifications = [...rawNotifications].sort((a, b) => {
        const aIsSpecial = (PIX_TYPES.includes(a.type) || ESSAY_TYPES.includes(a.type)) ? 0 : 1;
        const bIsSpecial = (PIX_TYPES.includes(b.type) || ESSAY_TYPES.includes(b.type)) ? 0 : 1;
        if (aIsSpecial !== bIsSpecial) return aIsSpecial - bIsSpecial;
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    });

    // ── Mutations ──────────────────────────────────────────────────────────
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

    // ── Pix action handler ─────────────────────────────────────────────────
    const handlePixAction = async (notification: AppNotification) => {
        if (loadingPixId) return;
        setLoadingPixId(notification.id);

        if (!notification.is_read) {
            markReadMutation.mutate(notification.id);
        }

        try {
            const res = await getPendingPixSubscription();
            const pix = res.data;

            if (pix?.pending && pix.subscription_id) {
                setActiveModal({
                    subscriptionId: pix.subscription_id,
                    notificationId: notification.id,
                    pixPayload: pix.pix_payload ?? undefined,
                    pixImage: pix.pix_image ?? undefined,
                    pixExpiresAt: pix.pix_expires_at ?? undefined,
                    isExpired: pix.is_expired ?? false,
                    planName: pix.plan_name ?? notification.meta?.plan_name,
                });
            } else {
                toast.info('Nenhuma cobrança Pix pendente encontrada. Gere uma nova na página de planos.');
                navigate('/planos');
            }
        } catch {
            const meta = notification.meta;
            if (meta?.subscription_id) {
                setActiveModal({
                    subscriptionId: meta.subscription_id,
                    notificationId: notification.id,
                    pixExpiresAt: meta.pix_expires_at,
                    isExpired: notification.type === 'payment_expired',
                    planName: meta.plan_name,
                });
            } else {
                toast.info('Nenhuma cobrança Pix pendente. Acesse a página de planos para gerar uma nova.');
                navigate('/planos');
            }
        } finally {
            setLoadingPixId(null);
        }
    };

    const handleModalClose = () => {
        setActiveModal(null);
        queryClient.invalidateQueries({ queryKey: ['user-notifications-full'] });
    };

    const handlePaymentConfirmed = () => {
        setActiveModal(null);
        queryClient.invalidateQueries({ queryKey: ['user-notifications-full'] });
        queryClient.invalidateQueries({ queryKey: ['subscription-status'] });
    };

    // ── Essay action handler ───────────────────────────────────────────────
    const handleEssayAction = (notification: AppNotification) => {
        if (!notification.is_read) {
            markReadMutation.mutate(notification.id);
        }
        const essayId = notification.meta?.essay_id;
        if (essayId) {
            navigate(`/redacoes/${essayId}/continuar`);
        } else {
            navigate('/redacoes');
        }
    };

    // ── Render ─────────────────────────────────────────────────────────────
    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <MetaTags title="Minhas Notificações" />

            {/* Modal */}
            {activeModal && (
                <PixRecoveryModal
                    {...activeModal}
                    onClose={handleModalClose}
                    onPaymentConfirmed={handlePaymentConfirmed}
                />
            )}

            {/* Header */}
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

            {/* List */}
            <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                {isLoading ? (
                    <div className="p-12 text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4" />
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
                        {notifications.map((n: AppNotification) => {
                            const cfg = typeConfig[n.type] ?? typeConfig['info'];
                            const isPixType = PIX_TYPES.includes(n.type);
                            const isEssayType = ESSAY_TYPES.includes(n.type);
                            const isPixPending = n.type === 'payment_pending';

                            return (
                                <div
                                    key={n.id}
                                    className={`p-6 transition-all ${cfg.border} ${!n.is_read
                                        ? isEssayType
                                            ? 'bg-indigo-50/60 dark:bg-indigo-950/10'
                                            : isPixType
                                                ? 'bg-amber-50/60 dark:bg-amber-950/10'
                                                : 'bg-blue-50/40 dark:bg-blue-950/10'
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'}`}
                                >
                                    <div className="flex items-start gap-4">
                                        {/* Icon */}
                                        <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 ${cfg.bg}`}>
                                            {cfg.icon}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between mb-1">
                                                <h3 className={`text-lg font-bold truncate ${!n.is_read ? 'text-slate-900 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300'}`}>
                                                    {n.title}
                                                </h3>
                                                {!n.is_read && (
                                                    <span className={`w-2.5 h-2.5 rounded-full flex-shrink-0 ml-2 shadow-sm ${
                                                        isEssayType ? 'bg-indigo-500 shadow-indigo-400/50'
                                                        : isPixType ? 'bg-amber-500 shadow-amber-400/50'
                                                        : 'bg-blue-500 shadow-blue-400/50'
                                                    }`} />
                                                )}
                                            </div>

                                            <p className="text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                                                {n.body || 'Sem conteúdo adicional.'}
                                            </p>

                                            {/* Plan/amount badge for Pix notifications */}
                                            {isPixType && n.meta?.plan_name && (
                                                <div className="flex items-center gap-2 mb-3">
                                                    <span className="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                        📦 {n.meta.plan_name}
                                                    </span>
                                                    {n.meta.amount && (
                                                        <span className="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                            R$ {n.meta.amount.toFixed(2).replace('.', ',')}
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Theme badge for essay notifications */}
                                            {isEssayType && n.meta?.essay_id && (
                                                <div className="flex items-center gap-2 mb-3">
                                                    <span className="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                        📝 Redação #{n.meta.essay_id}
                                                    </span>
                                                </div>
                                            )}

                                            <div className="flex flex-wrap items-center gap-4 text-sm">
                                                <span className="text-slate-400 font-medium">
                                                    {formatDate(n.created_at)}
                                                </span>

                                                <div className="flex items-center gap-3">
                                                    {/* Essay pending action */}
                                                    {isEssayType ? (
                                                        <button
                                                            onClick={() => handleEssayAction(n)}
                                                            className="px-4 py-1.5 text-white rounded-lg font-bold transition shadow-sm text-sm bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600"
                                                        >
                                                            📝 Abrir redação
                                                        </button>
                                                    ) : isPixType ? (
                                                        <button
                                                            onClick={() => handlePixAction(n)}
                                                            disabled={loadingPixId === n.id}
                                                            className={`px-4 py-1.5 text-white rounded-lg font-bold transition shadow-sm text-sm disabled:opacity-60 ${isPixPending
                                                                ? 'bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600'
                                                                : 'bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600'}`}
                                                        >
                                                            {loadingPixId === n.id ? (
                                                                <span className="flex items-center gap-1">
                                                                    <svg className="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                                    </svg>
                                                                    Carregando...
                                                                </span>
                                                            ) : isPixPending ? '💳 Concluir pagamento' : '🔄 Gerar novo QR Code'}
                                                        </button>
                                                    ) : n.action_url ? (
                                                        <a
                                                            href={n.action_url}
                                                            className="px-4 py-1.5 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition shadow-sm"
                                                        >
                                                            Ver Detalhes
                                                        </a>
                                                    ) : null}

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
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}
