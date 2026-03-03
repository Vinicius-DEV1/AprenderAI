import { useNavigate } from 'react-router-dom';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import { getSubscriptions, getPaymentReceipt } from '../../api/subscriptions';
import { toast } from 'sonner';
import PlanConfirmationModal from '../../components/PlanConfirmationModal';
import Accordion from '../../components/Accordion';
import '../../styles/landing-page.css';

// ───────────────────────────────────────────────────────
// Sub-component: Pix Countdown Modal
// ───────────────────────────────────────────────────────
function PixCountdownModal({ pix, onClose }: {
    pix: { payload: string; image: string; expiresAt: string | null; };
    onClose: () => void;
}) {
    const [secondsLeft, setSecondsLeft] = useState<number>(() => {
        if (!pix.expiresAt) return 30 * 60; // fallback 30min
        const diff = Math.floor((new Date(pix.expiresAt).getTime() - Date.now()) / 1000);
        return Math.max(0, diff);
    });
    const [copied, setCopied] = useState(false);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        intervalRef.current = setInterval(() => {
            setSecondsLeft(prev => {
                if (prev <= 1) {
                    clearInterval(intervalRef.current!);
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);
        return () => clearInterval(intervalRef.current!);
    }, []);

    const handleCopy = useCallback(() => {
        navigator.clipboard.writeText(pix.payload).then(() => {
            setCopied(true);
            toast.success('Chave Pix copiada!');
            setTimeout(() => setCopied(false), 2500);
        });
    }, [pix.payload]);

    const minutes = Math.floor(secondsLeft / 60).toString().padStart(2, '0');
    const seconds = (secondsLeft % 60).toString().padStart(2, '0');
    const isExpired = secondsLeft === 0;
    const urgency = secondsLeft < 300; // últimos 5 min
    const progress = pix.expiresAt
        ? Math.max(0, (secondsLeft / ((new Date(pix.expiresAt).getTime() - Date.now() + secondsLeft * 1000) / 1000)) * 100)
        : (secondsLeft / (30 * 60)) * 100;

    return (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-sm w-full overflow-hidden animate-in zoom-in-95 duration-200">
                {/* Header */}
                <div className={`p-5 text-center transition-colors duration-700 ${isExpired ? 'bg-red-600' : urgency ? 'bg-amber-500' : 'bg-blue-600'
                    }`}>
                    <div className="text-white/80 text-[10px] font-black uppercase tracking-widest mb-1">Pagar via Pix</div>
                    <h2 className="text-white text-xl font-black leading-none">QR Code Gerado</h2>
                    {isExpired ? (
                        <p className="text-white/90 text-xs mt-1">⚠️ QR Code expirado. Gere uma nova assinatura.</p>
                    ) : (
                        <p className="text-white/90 text-xs mt-1">Escaneie ou copie a chave Pix abaixo</p>
                    )}
                </div>

                {/* Countdown bar */}
                <div className="relative h-1.5 bg-slate-100 dark:bg-slate-800">
                    <div
                        className={`h-full transition-all duration-1000 ease-linear ${isExpired ? 'bg-red-500 w-0' : urgency ? 'bg-amber-400' : 'bg-blue-500'
                            }`}
                        style={{ width: isExpired ? '0%' : `${progress}%` }}
                    />
                </div>

                <div className="p-6">
                    {/* Timer */}
                    <div className={`text-center mb-4 py-2 rounded-xl ${isExpired ? 'bg-red-50 dark:bg-red-900/20' : urgency ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-slate-50 dark:bg-slate-800/50'
                        }`}>
                        <span className={`text-3xl font-black tabular-nums ${isExpired ? 'text-red-600 dark:text-red-400' : urgency ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200'
                            }`}>
                            {minutes}:{seconds}
                        </span>
                        <p className="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest mt-0.5">
                            {isExpired ? 'Expirado' : 'Tempo restante'}
                        </p>
                    </div>

                    {!isExpired ? (
                        <>
                            {/* QR Code */}
                            <div className="flex justify-center mb-4">
                                <div className="bg-white p-2 border-2 border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm">
                                    <img
                                        src={`data:image/png;base64,${pix.image}`}
                                        alt="QR Code Pix"
                                        className="w-44 h-44"
                                    />
                                </div>
                            </div>

                            {/* Copy field */}
                            <div className="relative mb-4">
                                <input
                                    readOnly
                                    value={pix.payload}
                                    className="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl py-3 pl-3 pr-24 text-[10px] font-mono text-slate-600 dark:text-slate-300 truncate focus:outline-none"
                                />
                                <button
                                    onClick={handleCopy}
                                    className={`absolute right-1.5 top-1.5 bottom-1.5 px-3 rounded-lg text-xs font-black transition-all duration-200 ${copied
                                            ? 'bg-green-500 text-white'
                                            : 'bg-blue-600 hover:bg-blue-700 text-white'
                                        }`}
                                >
                                    {copied ? '✓ Copiado' : 'Copiar'}
                                </button>
                            </div>

                            <p className="text-[11px] text-slate-400 dark:text-slate-500 text-center mb-4">
                                Após o pagamento, sua conta será ativada automaticamente em alguns minutos.
                            </p>
                        </>
                    ) : (
                        <div className="text-center mb-5">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                O QR Code expirou. Por favor, inicie uma nova assinatura para gerar um novo código.
                            </p>
                        </div>
                    )}

                    <button
                        onClick={onClose}
                        className="w-full bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 py-3 rounded-xl font-bold text-sm transition"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function PlanList() {
    const navigate = useNavigate();
    const { plans } = useConfigStore();
    const { user } = useAuthStore();
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedPlanForModal, setSelectedPlanForModal] = useState<any>(null);
    const [history, setHistory] = useState<any[]>([]);
    const [isLoadingHistory, setIsLoadingHistory] = useState(false);
    const [loadingReceiptId, setLoadingReceiptId] = useState<number | null>(null);
    const [selectedPix, setSelectedPix] = useState<{ payload: string; image: string; expiresAt: string | null } | null>(null);

    const userPlanId = user?.plan_id || (user?.plan as any)?.id;
    const currentPlan = plans?.find((p: any) => String(p.id) === String(userPlanId));

    const getPlanLevel = (name?: string) => {
        if (!name) return 0;
        const low = name.toLowerCase();
        if (low.includes('plus')) return 3;
        if (low.includes('básico') || low.includes('basico')) return 2;
        if (low.includes('gratuito')) return 1;
        return 0;
    };

    const currentPlanLevel = getPlanLevel(currentPlan?.name);

    const getButtonLabel = (cardPlan: any) => {
        if (!cardPlan) return 'Assinar';
        if (String(cardPlan.id) === String(getPlanBySlug('free')?.id)) {
            return String(userPlanId) === String(cardPlan.id) ? 'Plano Ativo' : 'Disponível';
        }
        if (String(userPlanId) === String(cardPlan.id)) return 'Seu Plano Atual';
        const cardLevel = getPlanLevel(cardPlan.name);
        if (currentPlanLevel > cardLevel && currentPlanLevel > 0) return 'Fazer Downgrade';
        if (currentPlanLevel < cardLevel && currentPlanLevel > 0) return 'Fazer Upgrade';
        return 'Assinar';
    };

    const handlePlanClick = (plan: any) => {
        if (!plan) return;

        // Se o plano atual for o novo plano, não faz nada
        if (String(userPlanId) === String(plan.id)) return;

        // Regra Especial: De Gratuito (Preço 0) para qualquer Pago -> Checkout Direto
        // Se for de Pago para Pago -> Abre Modal para explicar que é ACUMULATIVO
        const isCurrentFree = !currentPlan || Number(currentPlan.price) === 0;

        if (isCurrentFree) {
            navigate(`/plans/${plan.id}/checkout`);
        } else {
            setSelectedPlanForModal(plan);
            setIsModalOpen(true);
        }
    };

    const handleConfirm = () => {
        if (selectedPlanForModal) {
            navigate(`/plans/${selectedPlanForModal.id}/checkout`);
        }
    };

    useEffect(() => {
        const fetchHistory = async () => {
            if (!user) return;
            setIsLoadingHistory(true);
            try {
                const response = await getSubscriptions();
                setHistory(response.data);
            } catch (err) {
                console.error('Erro ao buscar histórico:', err);
            } finally {
                setIsLoadingHistory(false);
            }
        };

        fetchHistory();
    }, [user]);

    useEffect(() => {
        const searchParams = new URLSearchParams(window.location.search);
        const autoSelect = searchParams.get('autoSelect');

        if (autoSelect && plans?.length > 0) {
            if (autoSelect.includes('annual')) setPeriodo('anual');

            let matchedPlan = null;
            if (autoSelect.includes('basic')) {
                matchedPlan = plans.find((p: any) => (p.name.toLowerCase().includes('básico') || p.name.toLowerCase().includes('basico')) && (autoSelect.includes('annual') ? p.interval === 'yearly' : p.interval === 'monthly'));
            } else if (autoSelect.includes('plus')) {
                matchedPlan = plans.find((p: any) => p.name.toLowerCase().includes('plus') && (autoSelect.includes('annual') ? p.interval === 'yearly' : p.interval === 'monthly'));
            }

            if (matchedPlan) {
                handlePlanClick(matchedPlan);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    }, [plans, currentPlan]);

    const getPlanPrice = (slugKeyword: string) => {
        const p = plans?.find(p => p.slug?.includes(slugKeyword) && (periodo === 'anual' ? p.interval === 'yearly' : p.interval === 'monthly'));
        return p?.price || 0;
    };

    const getPlanBySlug = (slugKeyword: string) => {
        if (!plans || plans.length === 0) return null;
        return plans.find(p => p.slug?.includes(slugKeyword) && (periodo === 'anual' ? p.interval === 'yearly' : p.interval === 'monthly'));
    };

    useEffect(() => {
        if (plans?.length > 0) {
            if (import.meta.env.DEV) {
                console.log('Available Plans:', plans.map(p => ({ id: p.id, name: p.name, slug: p.slug, interval: p.interval })));
            }
        }
    }, [plans]);

    return (
        <div className="lp-wrapper bg-transparent py-8">
            <div className="max-w-7xl mx-auto px-4">
                <div className="text-center mb-10">
                    <h3 className="text-3xl font-black text-slate-800 dark:text-white mb-2 uppercase tracking-tighter">Sua Aprovação Começa Aqui</h3>
                    <p className="text-slate-500 dark:text-slate-400">Escolha o plano que melhor se adapta aos seus objetivos.</p>
                </div>

                {user && (
                    <div className="mb-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                        <div className="flex flex-col lg:flex-row gap-8">
                            {/* Lado Esquerdo: Info do Plano */}
                            <div className="flex-1">
                                <div className="flex items-center gap-4 mb-6">
                                    <div className="w-14 h-14 rounded-2xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 dark:text-blue-400 text-2xl shadow-inner group transition-transform hover:scale-105">
                                        <span className="group-hover:animate-pulse">⭐️</span>
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-3 mb-1">
                                            <p className="text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Minha Assinatura</p>
                                            <span className="text-[10px] bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 px-2 py-0.5 rounded-full uppercase tracking-widest font-black flex items-center gap-1">
                                                <span className="w-1 w-1 h-1 h-1 bg-green-500 rounded-full animate-ping"></span>
                                                Ativo
                                            </span>
                                        </div>
                                        <h4 className="text-2xl font-black text-slate-900 dark:text-white leading-none">
                                            Plano {currentPlan?.name || (user?.role === 'admin' ? 'Administrador' : 'Gratuito')}
                                            <span className="ml-2 text-sm font-medium text-slate-400 dark:text-slate-500">
                                                • {currentPlan?.interval === 'yearly' ? 'Anual' : 'Mensal'}
                                            </span>
                                        </h4>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                                        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Início do Período</p>
                                        <p className="text-sm font-bold text-slate-700 dark:text-slate-300">
                                            {(user as any).subscription_start ? new Date((user as any).subscription_start).toLocaleDateString() : '--/--/----'}
                                        </p>
                                    </div>
                                    <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                                        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Próxima Renovação</p>
                                        <p className="text-sm font-bold text-slate-700 dark:text-slate-300">
                                            {(user as any).subscription_end ? new Date((user as any).subscription_end).toLocaleDateString() : '--/--/----'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Divisor Vertical (apenas desk) */}
                            <div className="hidden lg:block w-px bg-slate-100 dark:bg-slate-800"></div>

                            {/* Lado Direito: Consumos */}
                            <div className="flex-1 flex flex-col justify-center">
                                <h5 className="text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-4">Consumo Mensal</h5>
                                <div className="space-y-4">
                                    {/* Simulações */}
                                    <div>
                                        <div className="flex justify-between text-[11px] mb-1.5">
                                            <span className="font-bold text-slate-600 dark:text-slate-400">Simulados</span>
                                            <span className="font-black text-blue-600 dark:text-blue-400">
                                                {(user as any).quotas?.simulations?.used || 0} / {(user as any).quotas?.simulations?.limit === 9999 ? '∞' : (user as any).quotas?.simulations?.limit || 0}
                                            </span>
                                        </div>
                                        <div className="h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-blue-500 rounded-full transition-all duration-500"
                                                style={{ width: `${Math.min(100, (((user as any).quotas?.simulations?.used || 0) / ((user as any).quotas?.simulations?.limit || 1)) * 100)}%` }}
                                            ></div>
                                        </div>
                                    </div>

                                    {/* Redações */}
                                    <div>
                                        <div className="flex justify-between text-[11px] mb-1.5">
                                            <span className="font-bold text-slate-600 dark:text-slate-400">Redações</span>
                                            <span className="font-black text-purple-600 dark:text-purple-400">
                                                {(user as any).quotas?.essays?.used || 0} / {(user as any).quotas?.essays?.limit === 9999 ? '∞' : (user as any).quotas?.essays?.limit || 0}
                                            </span>
                                        </div>
                                        <div className="h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-purple-500 rounded-full transition-all duration-500"
                                                style={{ width: `${Math.min(100, (((user as any).quotas?.essays?.used || 0) / ((user as any).quotas?.essays?.limit || 1)) * 100)}%` }}
                                            ></div>
                                        </div>
                                    </div>

                                    {/* Questões Objetivas */}
                                    <div>
                                        <div className="flex justify-between text-[11px] mb-1.5">
                                            <span className="font-bold text-slate-600 dark:text-slate-400">Questões Objetivas</span>
                                            <span className="font-black text-emerald-600 dark:text-emerald-400">
                                                {(user as any).quotas?.daily_questions?.used || 0} / {(user as any).quotas?.daily_questions?.limit === 9999 ? '∞' : (user as any).quotas?.daily_questions?.limit || 0}
                                            </span>
                                        </div>
                                        <div className="h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                                style={{ width: `${Math.min(100, (((user as any).quotas?.daily_questions?.used || 0) / ((user as any).quotas?.daily_questions?.limit || 1)) * 100)}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* HISTÓRICO DE PAGAMENTOS */}
                        {isLoadingHistory && (
                            <div className="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800 text-center text-sm text-slate-400 animate-pulse">
                                Carregando histórico de pagamentos...
                            </div>
                        )}
                        {!isLoadingHistory && history.length > 0 && (
                            <div className="mt-10 pt-8 border-t border-slate-100 dark:border-slate-800">
                                <h5 className="text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-6">Histórico de Pedidos</h5>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left">
                                        <thead>
                                            <tr className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800">
                                                <th className="pb-3 px-2">Data</th>
                                                <th className="pb-3 px-2">Plano</th>
                                                <th className="pb-3 px-2">Método</th>
                                                <th className="pb-3 px-2">Valor</th>
                                                <th className="pb-3 px-2">Status</th>
                                                <th className="pb-3 px-2 text-right">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50 dark:divide-slate-800/50">
                                            {history.map((item) => {
                                                const isExpired = item.status === 'pending' && new Date(item.created_at).getTime() < Date.now() - 24 * 60 * 60 * 1000;
                                                return (
                                                    <tr key={item.id} className="text-sm">
                                                        <td className="py-4 px-2 font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                                            {new Date(item.created_at).toLocaleDateString()}
                                                        </td>
                                                        <td className="py-4 px-2 font-bold text-slate-900 dark:text-white">
                                                            {item.plan?.name}
                                                        </td>
                                                        <td className="py-4 px-2 text-slate-500 dark:text-slate-400 capitalize">
                                                            {item.billing_type === 'pix' ? 'Pix' : (item.billing_type === 'credit_card' ? 'Cartão' : 'Gateway')}
                                                        </td>
                                                        <td className="py-4 px-2 font-bold text-slate-800 dark:text-slate-200">
                                                            {item.amount ? `R$ ${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(Number(item.amount))}` : '--'}
                                                        </td>
                                                        <td className="py-4 px-2">
                                                            {isExpired ? (
                                                                <span className="inline-block bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Expirado</span>
                                                            ) : item.status === 'active' ? (
                                                                <span className="inline-block bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Confirmado</span>
                                                            ) : item.status === 'pending' ? (
                                                                <span className="inline-block bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Pendente</span>
                                                            ) : (
                                                                <span className="inline-block bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">{item.status}</span>
                                                            )}
                                                        </td>
                                                        <td className="py-4 px-2 text-right">
                                                            {/* Pix pending */}
                                                            {item.status === 'pending' && !isExpired && item.pix_payload && (
                                                                <button
                                                                    onClick={() => setSelectedPix({
                                                                        payload: item.pix_payload,
                                                                        image: item.pix_image,
                                                                        expiresAt: item.pix_expires_at ?? null,
                                                                    })}
                                                                    className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 underline"
                                                                >
                                                                    <span>📲</span> Ver QR Pix
                                                                </button>
                                                            )}
                                                            {item.status === 'active' && (
                                                                <button
                                                                    onClick={async () => {
                                                                        setLoadingReceiptId(item.id);
                                                                        try {
                                                                            const res = await getPaymentReceipt(item.id);
                                                                            const url = res.data.receipt_url || res.data.invoice_url;
                                                                            if (url) window.open(url, '_blank');
                                                                            else toast.info('Comprovante ainda não disponível.');
                                                                        } catch {
                                                                            toast.error('Não foi possível obter o comprovante.');
                                                                        } finally {
                                                                            setLoadingReceiptId(null);
                                                                        }
                                                                    }}
                                                                    disabled={loadingReceiptId === item.id}
                                                                    className="text-xs font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 underline disabled:opacity-50"
                                                                >
                                                                    {loadingReceiptId === item.id ? 'Buscando...' : '↓ Comprovante'}
                                                                </button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                        {!isLoadingHistory && history.length === 0 && userPlanId && String(userPlanId) !== '1' && (
                            <div className="mt-10 pt-8 border-t border-slate-100 dark:border-slate-800 text-center">
                                <p className="text-sm text-slate-500 italic">Nenhum registro de pagamento encontrado nesta conta.</p>
                            </div>
                        )}
                    </div>
                )}

                <div className="flex justify-center mb-10">
                    <div className="bg-slate-100 dark:bg-slate-800/80 rounded-full p-1.5 flex items-center gap-4 shadow-sm border border-slate-200 dark:border-slate-700">
                        <span
                            className={`text-sm font-bold cursor-pointer transition-colors px-3 py-1 rounded-full ${periodo === 'mensal' ? 'text-blue-900 bg-white dark:bg-slate-700 dark:text-blue-100 shadow-sm' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'}`}
                            onClick={() => setPeriodo('mensal')}
                        >
                            Mensal
                        </span>

                        <span
                            className={`text-sm font-bold cursor-pointer transition-colors flex items-center gap-2 px-3 py-1 rounded-full ${periodo === 'anual' ? 'text-blue-900 bg-white dark:bg-slate-700 dark:text-blue-100 shadow-sm' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'}`}
                            onClick={() => setPeriodo('anual')}
                        >
                            Anual
                            <span className="bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">-20% OFF</span>
                        </span>
                    </div>
                </div>

                <div className="lp-plans-grid">
                    <div className={`lp-plan-free flex flex-col justify-between border-2 transition-all duration-300 ${String(userPlanId) === String(getPlanBySlug('free')?.id) ? 'border-green-500 bg-green-50/30 dark:bg-green-900/10' : 'border-gray-100 dark:border-slate-800 shadow-sm hover:border-slate-200 dark:hover:border-slate-700'}`}>
                        <div>
                            <div className="lp-plan-name-free">Gratuito</div>
                            <div className="lp-plan-tagline-free">Ideal para começar e testar.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-free">R$ 0</span>
                                <span className="lp-plan-price-unit lp-plan-price-unit-free">/mês</span>
                            </div>
                            <ul className="lp-plan-list lp-plan-list-free">
                                <li><span className="lp-check-free">✓</span> 5 provas/mês</li>
                                <li><span className="lp-check-free">✓</span> Correção básica</li>
                                <li><span className="lp-check-free">✓</span> Estatísticas simples</li>
                                <li><span className="lp-check-free">✓</span> 30 questões para praticar todos os dias</li>
                            </ul>
                            <div className="mt-4 mb-6">
                                <Accordion title={<span className="font-bold text-slate-600">+ Detalhes do Plano</span>} defaultExpanded={false}>
                                    <ul className="lp-plan-list lp-plan-list-free mt-2 !mb-0 text-sm">
                                        <li><span className="lp-check-free">✓</span> Gabarito Comentado</li>
                                        <li><span className="lp-check-free">✓</span> Modo noturno</li>
                                    </ul>
                                </Accordion>
                            </div>
                        </div>
                        <button disabled className="lp-plan-btn-free bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-600 cursor-not-allowed border border-slate-300 dark:border-slate-700">
                            {getButtonLabel(getPlanBySlug('free'))}
                        </button>
                    </div>

                    {/* BÁSICO */}
                    <div className={`lp-plan-basic flex flex-col justify-between ${String(userPlanId) === String(getPlanBySlug('basic')?.id) ? 'ring-4 ring-blue-400' : ''}`}>
                        <div>
                            <div className="lp-plan-name-paid">Básico</div>
                            <div className="lp-plan-tagline-paid">Para evoluir com correção completa e IA.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-paid">R$&nbsp;{new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(getPlanPrice('basic'))}</span>
                                <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                            </div>
                            <ul className="lp-plan-list lp-plan-list-paid">
                                <li><span className="lp-check-paid">✓</span> Correção detalhada (IA)</li>
                                <li><span className="lp-check-paid">✓</span> 5 redações/mês</li>
                                <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência (C1–C5)</li>
                                <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                            </ul>
                            <div className="mt-4 mb-6">
                                <Accordion title={<span className="font-bold text-white opacity-90 hover:opacity-100">+ Lista Completa</span>} defaultExpanded={false} variant="transparent" className="!text-blue-100">
                                    <ul className="lp-plan-list lp-plan-list-paid mt-2 !mb-0 text-sm">
                                        <li><span className="lp-check-paid">✓</span> 10 provas/mês</li>
                                        <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                        <li><span className="lp-check-paid">✓</span> Acesso ilimitado a todas as questões</li>
                                        <li className="pt-2 mt-2 border-t border-blue-400 font-bold">+ Benefícios</li>
                                        <li><span className="lp-check-paid">✓</span> Estatísticas simples</li>
                                        <li><span className="lp-check-paid">✓</span> Gabarito Comentado</li>
                                        <li><span className="lp-check-paid">✓</span> Modo noturno</li>
                                    </ul>
                                </Accordion>
                            </div>
                        </div>
                        <button
                            onClick={() => handlePlanClick(getPlanBySlug('basic'))}
                            disabled={String(userPlanId) === String(getPlanBySlug('basic')?.id)}
                            className={`lp-plan-btn-basic ${String(userPlanId) === String(getPlanBySlug('basic')?.id) ? 'bg-blue-400 opacity-50 cursor-not-allowed' : 'hover:scale-105 transition-transform'}`}
                        >
                            {getButtonLabel(getPlanBySlug('basic'))}
                        </button>
                    </div>

                    <div className={`lp-plan-plus flex flex-col justify-between relative overflow-hidden ${String(userPlanId) === String(getPlanBySlug('plus')?.id) ? 'ring-4 ring-amber-400' : ''}`}>
                        <div className="absolute top-4 right-[-35px] bg-amber-500 text-blue-900 text-[10px] font-black px-10 py-1 rotate-45 shadow-sm">
                            POPULAR
                        </div>
                        <div>
                            <div className="lp-plan-name-paid">Plus</div>
                            <div className="lp-plan-tagline-paid">A estratégia definitiva de aprovação.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-paid">R$&nbsp;{new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(getPlanPrice('plus'))}</span>
                                <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                            </div>
                            <ul className="lp-plan-list lp-plan-list-paid">
                                <li><span className="lp-check-paid">✓</span> <strong className="text-white">Análise estratégica</strong></li>
                                <li><span className="lp-check-paid">✓</span> Cronograma de Estudos personalizado</li>
                                <li><span className="lp-check-paid">✓</span> <strong className="text-white">Simulados ilimitados</strong></li>
                                <li><span className="lp-check-paid">✓</span> <strong className="text-white">15 redações/mês</strong></li>
                            </ul>
                            <div className="mt-4 mb-6">
                                <Accordion title={<span className="font-bold text-white opacity-90 hover:opacity-100">+ Ver Todas as Vantagens</span>} defaultExpanded={false} variant="transparent" className="!text-blue-100">
                                    <ul className="lp-plan-list lp-plan-list-paid mt-2 !mb-0 text-sm">
                                        <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência (C1–C5)</li>
                                        <li><span className="lp-check-paid">✓</span> Estatísticas completas</li>
                                        <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                                        <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                        <li><span className="lp-check-paid">✓</span> Acesso ilimitado a todas as questões</li>
                                        <li className="pt-2 mt-2 border-t border-blue-400 font-bold">+ Benefícios</li>
                                        <li><span className="lp-check-paid">✓</span> Gabarito Comentado</li>
                                        <li><span className="lp-check-paid">✓</span> Modo noturno</li>
                                    </ul>
                                </Accordion>
                            </div>
                        </div>
                        <button
                            onClick={() => handlePlanClick(getPlanBySlug('plus'))}
                            disabled={String(userPlanId) === String(getPlanBySlug('plus')?.id)}
                            className={`lp-plan-btn-plus ${String(userPlanId) === String(getPlanBySlug('plus')?.id) ? 'bg-amber-400 opacity-50 cursor-not-allowed' : 'hover:scale-105 transition-transform'}`}
                        >
                            {getButtonLabel(getPlanBySlug('plus'))}
                        </button>
                    </div>
                </div>

                <PlanConfirmationModal
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    onConfirm={handleConfirm}
                    currentPlan={currentPlan}
                    selectedPlan={selectedPlanForModal}
                />

                {/* MODAL Pix com countdown */}
                {selectedPix && (
                    <PixCountdownModal
                        pix={selectedPix}
                        onClose={() => setSelectedPix(null)}
                    />
                )}
            </div>
        </div>
    );
}
