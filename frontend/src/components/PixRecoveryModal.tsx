import { useState, useEffect, useCallback } from 'react';
import { toast } from 'sonner';
import { getPendingPixSubscription, regeneratePixPayment, checkSubscriptionStatus } from '../api/subscriptions';
import { markNotificationRead } from '../api/notifications';

interface PixRecoveryModalProps {
    subscriptionId: number;
    notificationId?: number;
    pixPayload?: string | null;
    pixImage?: string | null;
    pixExpiresAt?: string | null;
    isExpired?: boolean;
    planName?: string;
    onClose: () => void;
    onPaymentConfirmed: () => void;
}

export default function PixRecoveryModal({
    subscriptionId,
    notificationId,
    pixPayload: initialPayload,
    pixImage: initialImage,
    pixExpiresAt: initialExpiry,
    isExpired: initialExpired,
    planName,
    onClose,
    onPaymentConfirmed,
}: PixRecoveryModalProps) {
    const [pixPayload, setPixPayload] = useState(initialPayload ?? '');
    const [pixImage, setPixImage] = useState(initialImage ?? '');
    const [pixExpiresAt, setPixExpiresAt] = useState(initialExpiry ?? null);
    const [isExpired, setIsExpired] = useState(initialExpired ?? false);
    const [timeLeft, setTimeLeft] = useState(0);
    const [copied, setCopied] = useState(false);
    const [isRegenerating, setIsRegenerating] = useState(false);
    const [confirmed, setConfirmed] = useState(false);

    // ── Countdown timer ────────────────────────────────
    useEffect(() => {
        const calcTime = () => {
            if (!pixExpiresAt) return 0;
            const diff = Math.max(0, Math.floor((new Date(pixExpiresAt).getTime() - Date.now()) / 1000));
            return diff;
        };

        setTimeLeft(calcTime());
        setIsExpired(calcTime() === 0);

        const timer = setInterval(() => {
            const t = calcTime();
            setTimeLeft(t);
            if (t === 0) setIsExpired(true);
        }, 1000);

        return () => clearInterval(timer);
    }, [pixExpiresAt]);

    // ── Payment confirmation polling ───────────────────
    const pollPayment = useCallback(async () => {
        try {
            const res = await checkSubscriptionStatus();
            if (res.data?.active) {
                setConfirmed(true);
                toast.success('Pagamento confirmado! Sua assinatura foi ativada. 🎉');
                if (notificationId) await markNotificationRead(notificationId);
                onPaymentConfirmed();
            }
        } catch { /* silent */ }
    }, [notificationId, onPaymentConfirmed]);

    useEffect(() => {
        if (isExpired || confirmed) return;
        const interval = setInterval(pollPayment, 5000);
        return () => clearInterval(interval);
    }, [isExpired, confirmed, pollPayment]);

    // ── Regenerate expired QR Code ────────────────────
    const handleRegenerate = async () => {
        setIsRegenerating(true);
        try {
            const res = await regeneratePixPayment(subscriptionId);
            const d = res.data;
            setPixPayload(d.pix_payload ?? '');
            setPixImage(d.pix_image ?? '');
            setPixExpiresAt(d.pix_expires_at ?? null);
            setIsExpired(false);
            toast.success('Novo QR Code gerado com sucesso!');
        } catch (e: any) {
            toast.error('Erro ao gerar novo QR Code. Tente novamente.');
        } finally {
            setIsRegenerating(false);
        }
    };

    // ── Copy to clipboard ─────────────────────────────
    const handleCopy = () => {
        if (!pixPayload) return;
        navigator.clipboard.writeText(pixPayload);
        setCopied(true);
        setTimeout(() => setCopied(false), 3000);
    };

    // ── Format time ───────────────────────────────────
    const formatTime = (secs: number) => {
        const m = Math.floor(secs / 60).toString().padStart(2, '0');
        const s = (secs % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg overflow-hidden">
                {/* Header */}
                <div className={`px-6 py-4 flex items-center justify-between ${isExpired ? 'bg-red-50 dark:bg-red-950/30 border-b border-red-100 dark:border-red-800' : 'bg-amber-50 dark:bg-amber-950/30 border-b border-amber-100 dark:border-amber-800'}`}>
                    <div className="flex items-center gap-3">
                        <span className="text-2xl">{isExpired ? '⏰' : '💳'}</span>
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100">
                                {isExpired ? 'QR Code expirado' : 'Pagamento Pix pendente'}
                            </h2>
                            {planName && (
                                <p className="text-sm text-slate-500 dark:text-slate-400">Plano {planName}</p>
                            )}
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        aria-label="Fechar"
                    >
                        ✕
                    </button>
                </div>

                {/* Body */}
                <div className="p-6">
                    {isExpired ? (
                        // ── Expired state ──────────────────────────────────────
                        <div className="text-center space-y-4">
                            <div className="w-20 h-20 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto text-4xl">
                                ⏰
                            </div>
                            <p className="text-slate-600 dark:text-slate-400 leading-relaxed">
                                Seu código Pix expirou antes da confirmação do pagamento.
                                Gere um novo código para concluir sua assinatura.
                            </p>
                            <button
                                onClick={handleRegenerate}
                                disabled={isRegenerating}
                                className="w-full py-3 px-6 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold rounded-xl hover:from-amber-600 hover:to-orange-600 transition-all shadow-lg disabled:opacity-60"
                            >
                                {isRegenerating ? (
                                    <span className="flex items-center justify-center gap-2">
                                        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Gerando...
                                    </span>
                                ) : '🔄 Gerar novo QR Code'}
                            </button>
                        </div>
                    ) : (
                        // ── Active Pix QR Code ─────────────────────────────────
                        <div className="space-y-5">
                            {/* Timer */}
                            <div className="flex items-center justify-center gap-2">
                                <span className="text-sm text-slate-500 dark:text-slate-400">Expira em:</span>
                                <span className={`font-mono font-bold text-lg px-3 py-1 rounded-lg ${timeLeft < 300 ? 'text-red-600 bg-red-50 dark:bg-red-900/30' : 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30'}`}>
                                    {formatTime(timeLeft)}
                                </span>
                            </div>

                            {/* QR Code */}
                            {pixImage && (
                                <div className="flex justify-center">
                                    <div className="p-3 bg-white rounded-xl border-2 border-slate-200 dark:border-slate-600 shadow-sm">
                                        <img
                                            src={`data:image/png;base64,${pixImage}`}
                                            alt="QR Code Pix"
                                            className="w-52 h-52 object-contain"
                                            onError={(e) => {
                                                // Fallback to jpeg if png fails
                                                (e.target as HTMLImageElement).src = `data:image/jpeg;base64,${pixImage}`;
                                            }}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Pix copia e cola */}
                            {pixPayload && (
                                <div className="space-y-2">
                                    <label className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                        Pix Copia e Cola
                                    </label>
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            readOnly
                                            value={pixPayload}
                                            className="flex-1 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 font-mono text-slate-600 dark:text-slate-300 truncate"
                                        />
                                        <button
                                            onClick={handleCopy}
                                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg transition whitespace-nowrap"
                                        >
                                            {copied ? '✓ Copiado!' : 'Copiar'}
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Status */}
                            <div className="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800">
                                <div className="w-2 h-2 rounded-full bg-blue-500 animate-pulse" />
                                <p className="text-sm text-blue-700 dark:text-blue-300">
                                    Aguardando confirmação do pagamento...
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="px-6 pb-5 pt-1">
                    <button
                        onClick={onClose}
                        className="w-full py-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition"
                    >
                        Fechar e voltar às notificações
                    </button>
                </div>
            </div>
        </div>
    );
}
