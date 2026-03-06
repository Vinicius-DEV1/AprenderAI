import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import { validateCoupon, processCheckout, getUpgradePreview } from '../../api/subscriptions';
import { getUser } from '../../api/auth';
import { IMaskInput } from 'react-imask';

interface PlanCheckoutProps {
    embeddedPlanId?: string | number;
    onSuccess?: () => void;
    onCancel?: () => void;
}

export default function PlanCheckout({ embeddedPlanId, onSuccess, onCancel }: PlanCheckoutProps) {
    const { planId: paramPlanId } = useParams();
    const navigate = useNavigate();
    const { plans } = useConfigStore();
    const { user, setUser } = useAuthStore();

    const resolvedPlanId = embeddedPlanId || paramPlanId;
    const plan = plans.find((p: any) => p.id === Number(resolvedPlanId) || p.slug === String(resolvedPlanId));

    const [method, setMethod] = useState<'credit_card' | 'pix'>('credit_card');
    const [couponCode, setCouponCode] = useState('');
    const [couponMessage, setCouponMessage] = useState('');
    const [couponSuccess, setCouponSuccess] = useState(false);
    const [finalPrice, setFinalPrice] = useState<number>(Number(plan?.price) || 0);

    const isAnnualPlan = plan?.interval === 'yearly';
    const isInstallment = isAnnualPlan && method === 'credit_card';
    const annualPrice = Number(plan?.annual_price) || 0;
    const installmentValue = isInstallment && annualPrice > 0 ? annualPrice / 12 : 0;

    const [isLoading, setIsLoading] = useState(false);
    const [checkoutResult, setCheckoutResult] = useState<any>(null);

    const [upgradeData, setUpgradeData] = useState<any>(null);
    const [isLoadingUpgrade, setIsLoadingUpgrade] = useState(false);

    const [formData, setFormData] = useState({
        card_name: '',
        card_number: '',
        card_expiry_month: '',
        card_expiry_year: '',
        card_ccv: '',
        cpf: '',
        postal_code: '',
        address_number: '',
        phone: ''
    });

    const handleMaskChange = (value: string, name: string) => {
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    useEffect(() => {
        if (plan) {
            setFinalPrice(Number(plan.price) || 0);

            const fetchUpgrade = async () => {
                if (!user) return;
                setIsLoadingUpgrade(true);
                try {
                    const res = await getUpgradePreview(plan.id);
                    if (res.data?.has_active_installment) {
                        setUpgradeData(res.data);
                    }
                } catch (err) {
                    console.error('Erro ao verificar upgrade:', err);
                } finally {
                    setIsLoadingUpgrade(false);
                }
            };
            fetchUpgrade();
        }
    }, [plan, user]);

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        const checkPaymentStatus = async () => {
            try {
                const response = await getUser();
                const freshUser = response.data.user;
                if (freshUser && freshUser.plan_id === plan?.id) {
                    setUser(freshUser);
                    clearInterval(interval);
                    toast.success('Pagamento confirmado com sucesso!');
                    if (onSuccess) {
                        onSuccess();
                    } else {
                        navigate(`/checkout/success?planName=${encodeURIComponent(plan?.name || '')}`);
                    }
                }
            } catch (err) {
                console.error('Falha no polling do pagamento:', err);
            }
        };

        if (checkoutResult?.pix) {
            interval = setInterval(checkPaymentStatus, 5000);
        }
        return () => {
            if (interval) clearInterval(interval);
        };
    }, [checkoutResult, plan, navigate, setUser]);

    if (!plan && plans.length > 0) {
        return (
            <div className="py-20 text-center">
                <h2 className="text-xl font-bold text-slate-800 dark:text-white">Plano não encontrado.</h2>
                <button onClick={() => {
                    if (onCancel) onCancel();
                    else navigate('/plans');
                }} className="mt-4 text-blue-600 hover:underline">Voltar para planos</button>
            </div>
        );
    }

    if (plans.length === 0) {
        return <div className="py-20 text-center animate-pulse text-slate-500 font-medium">Carregando detalhes do checkout...</div>;
    }

    const handleValidateCoupon = async () => {
        if (!couponCode) return;
        setCouponMessage('Validando...');
        try {
            const response = await validateCoupon(plan!.id, couponCode);
            setCouponMessage(response.data.message);
            setCouponSuccess(response.data.valid);
            if (response.data.valid) {
                setFinalPrice(Number(response.data.new_price) || 0);
            }
        } catch (err: any) {
            setCouponMessage(err.response?.data?.message || 'Erro ao validar cupom.');
            setCouponSuccess(false);
            setFinalPrice(Number(plan!.price) || 0);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            const payload: any = {
                ...formData,
                payment_method: method,
                coupon_code: couponSuccess ? couponCode : null
            };
            if (isInstallment) {
                payload.installment_count = 12;
            }
            if (upgradeData?.is_upgrade) {
                payload.is_upgrade = true;
                // Upgrade sempre precisa ser cartão
                payload.payment_method = 'credit_card';
            }

            const response = await processCheckout(plan!.id, payload);
            if (response.data.success) {
                if (method === 'pix' && !upgradeData?.is_upgrade) {
                    setCheckoutResult(response.data);
                } else {
                    if (onSuccess) {
                        onSuccess();
                    } else {
                        navigate(`/checkout/success?planName=${encodeURIComponent(plan!.name)}&upgrade=${upgradeData?.is_upgrade ? 'true' : 'false'}`);
                    }
                }
            }
        } catch (err: any) {
            const message = err.response?.data?.message || err.response?.data?.errors?.[0]?.description || 'Erro ao processar checkout. Verifique os dados.';
            toast.error(message);
            console.error('[PlanCheckout] Erro:', err.response?.data || err.message);
        } finally {
            setIsLoading(false);
        }
    };

    if (checkoutResult?.pix) {
        return (
            <div className="py-8 max-w-lg mx-auto px-4">
                <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-lg border border-slate-200 dark:border-slate-800">
                    <div className="bg-green-600 p-6 text-center text-white rounded-t-2xl">
                        <div className="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 className="text-xl font-bold">Assinatura Quase Pronta!</h2>
                        <p className="mt-1 opacity-90 text-sm">Pague via Pix para ativar instantaneamente.</p>
                    </div>

                    <div className="p-6 text-center">
                        <p className="text-slate-600 dark:text-slate-400 mb-4 text-sm">Escaneie o QR Code abaixo ou copie a chave Pix:</p>
                        <div className="bg-white p-3 inline-block border-2 border-slate-100 dark:border-slate-800 rounded-xl mb-4">
                            <img src={`data:image/png;base64,${checkoutResult.pix.image}`} alt="Pix QR Code" className="w-56 h-56 mx-auto" />
                        </div>

                        <div className="mb-6">
                            <div className="text-[10px] text-slate-400 uppercase font-bold mb-1.5 flex items-center justify-center gap-1">
                                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                Chave Pix (Copia e Cola)
                            </div>
                            <div className="flex gap-2 relative">
                                <input readOnly value={checkoutResult.pix.payload} className="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 py-2.5 px-3 rounded-lg text-xs font-mono w-full truncate pr-20 text-slate-600 dark:text-slate-300" />
                                <button onClick={() => navigator.clipboard.writeText(checkoutResult.pix.payload).then(() => toast.success('Copiado!'))} className="absolute right-1 top-1 bottom-1 bg-blue-600 text-white px-3 rounded-md font-bold text-xs hover:bg-blue-700 transition">Copiar</button>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3">
                            <button onClick={() => window.location.reload()} className="bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 py-2.5 rounded-lg font-bold text-sm transition">Já paguei, verificar agora</button>
                            <button onClick={() => navigate('/dashboard')} className="text-slate-500 hover:text-slate-700 text-xs font-medium">Voltar ao Dashboard</button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="py-8 max-w-5xl mx-auto px-4 md:px-6">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-6">Finalização da Assinatura</h1>

            <div className="flex flex-col lg:flex-row gap-8">
                {/* LADO ESQUERDO: RESUMO E CUPOM (Stripe style) */}
                <div className="w-full lg:w-[400px] flex-shrink-0">
                    <div className="bg-slate-50 dark:bg-slate-800/40 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 lg:sticky lg:top-8">
                        <div className="mb-6">
                            <span className="text-slate-500 dark:text-slate-400 block text-[11px] font-bold uppercase tracking-wider mb-1">Assinatura Escolhida</span>
                            <h2 className="text-xl font-black text-slate-900 dark:text-white">{plan.name}</h2>
                            {isInstallment && (
                                <span className="inline-flex items-center gap-1 mt-2 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold px-2 py-1 rounded-full uppercase">
                                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" /></svg>
                                    Recebimento Garantido em 12x
                                </span>
                            )}
                        </div>

                        <div className="mb-6 pb-6 border-b border-slate-200 dark:border-slate-700">
                            {isLoadingUpgrade ? (
                                <div className="animate-pulse text-sm text-slate-500">Calculando valores...</div>
                            ) : upgradeData?.is_upgrade ? (
                                <>
                                    <span className="text-slate-500 dark:text-slate-400 block text-[11px] font-bold uppercase tracking-wider mb-1">Upgrade Pro-rata ({upgradeData.remaining_months} meses restantes)</span>
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-3xl font-black text-slate-900 dark:text-white">{upgradeData.remaining_months}x de R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(upgradeData.delta_per_month)}</span>
                                    </div>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Diferença total: R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(upgradeData.upgrade_total)}</p>
                                    <p className="text-[10px] text-slate-400 mt-2 bg-slate-100 dark:bg-slate-800 p-2 rounded">
                                        Você pagará apenas a diferença entre o plano <strong>{upgradeData.current_plan_name}</strong> e o <strong>{upgradeData.new_plan_name}</strong> pelos meses que restam na sua assinatura (até {upgradeData.expires_at}).
                                    </p>
                                </>
                            ) : upgradeData?.is_downgrade ? (
                                <div className="bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-xl text-sm font-medium border border-red-100 dark:border-red-800">
                                    {upgradeData.message}
                                </div>
                            ) : (
                                <>
                                    <span className="text-slate-500 dark:text-slate-400 block text-[11px] font-bold uppercase tracking-wider mb-1">{isInstallment ? 'Parcelamento' : 'Total a Pagar'}</span>
                                    {isInstallment ? (
                                        <>
                                            <div className="flex items-baseline gap-1">
                                                <span className="text-3xl font-black text-slate-900 dark:text-white">12x de R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(couponSuccess ? finalPrice / 12 : installmentValue)}</span>
                                            </div>
                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Total: R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(couponSuccess ? finalPrice : annualPrice)}</p>
                                        </>
                                    ) : (
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-3xl font-black text-slate-900 dark:text-white">R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(finalPrice)}</span>
                                            <span className="text-slate-500 dark:text-slate-400 text-sm font-medium">/{plan.interval === 'yearly' ? 'ano' : 'mês'}</span>
                                        </div>
                                    )}
                                    {couponSuccess && (
                                        <span className="inline-block mt-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-bold px-2 py-0.5 rounded">Desconto Aplicado</span>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Cupom embutido harmoniosamente */}
                        {!upgradeData?.is_upgrade && !upgradeData?.is_downgrade && (
                            <div>
                                <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Possui Cupom?</label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={couponCode}
                                        onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                                        placeholder="CÓDIGO"
                                        className="flex-1 block w-full rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white sm:text-sm focus:border-blue-500 focus:ring-blue-500 uppercase"
                                    />
                                    <button
                                        type="button"
                                        onClick={handleValidateCoupon}
                                        className="px-4 py-2 bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 text-sm font-bold rounded-lg transition-colors"
                                    >
                                        Aplicar
                                    </button>
                                </div>
                                {couponMessage && (
                                    <p className={`mt-2 text-xs font-semibold ${couponSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'}`}>
                                        {couponMessage}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="mt-8 flex items-center justify-start gap-2 text-[10px] text-slate-500 font-bold uppercase tracking-wider">
                            <svg className="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zM10 5a1 1 0 011 1v3.586l1.707-1.707a1 1 0 111.414 1.414l-3.414 3.414a1 1 0 01-1.414 0l-3.414-3.414a1 1 0 011.414-1.414L9 9.586V6a1 1 0 011-1z" clipRule="evenodd" />
                            </svg>
                            Conexão Criptografada e Segura
                        </div>
                    </div>
                </div>

                {/* LADO DIREITO: MÉTODO E FORMULÁRIO */}
                <div className="flex-1">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm">

                        {/* Selector de Método */}
                        <div className="flex gap-2 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-lg mb-8">
                            <button
                                type="button"
                                onClick={() => setMethod('credit_card')}
                                className={`flex-1 py-2.5 px-3 rounded-md font-bold text-sm transition-all flex items-center justify-center gap-2 ${method === 'credit_card' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm ring-1 ring-black/5' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                Cartão de Crédito
                            </button>
                            <button
                                type="button"
                                onClick={() => setMethod('pix')}
                                className={`flex-1 py-2.5 px-3 rounded-md font-bold text-sm transition-all flex items-center justify-center gap-2 ${method === 'pix' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm ring-1 ring-black/5' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                Pix
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <fieldset disabled={isLoading} className="space-y-6">
                                {method === 'credit_card' ? (
                                    <div className="space-y-6 animate-in fade-in duration-300">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="md:col-span-2">
                                                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Número do Cartão</label>
                                                <IMaskInput
                                                    mask="0000 0000 0000 0000" unmask={true}
                                                    type="text" name="card_number" required
                                                    value={formData.card_number} onAccept={(val) => handleMaskChange(val, 'card_number')}
                                                    placeholder="0000 0000 0000 0000"
                                                    className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-mono tracking-widest"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nome impresso no Cartão</label>
                                                <input
                                                    type="text" name="card_name" required
                                                    value={formData.card_name} onChange={(e) => setFormData({ ...formData, card_name: e.target.value.toUpperCase() })}
                                                    placeholder="JOAO A SILVA"
                                                    className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold uppercase"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Validade</label>
                                                <div className="flex gap-2">
                                                    <IMaskInput
                                                        mask="00" unmask={true}
                                                        type="text" name="card_expiry_month" required
                                                        value={formData.card_expiry_month} onAccept={(val) => handleMaskChange(val, 'card_expiry_month')}
                                                        placeholder="MM"
                                                        className="w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm text-center focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                    />
                                                    <IMaskInput
                                                        mask="00" unmask={true}
                                                        type="text" name="card_expiry_year" required
                                                        value={formData.card_expiry_year} onAccept={(val) => handleMaskChange(val, 'card_expiry_year')}
                                                        placeholder="AA"
                                                        className="w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm text-center focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Cód. Segurança</label>
                                                <IMaskInput
                                                    mask="0000" unmask={true}
                                                    type="text" name="card_ccv" required
                                                    value={formData.card_ccv} onAccept={(val) => handleMaskChange(val, 'card_ccv')}
                                                    placeholder="CVC"
                                                    className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="text-center py-6 border border-green-100 dark:border-green-900/30 bg-green-50/50 dark:bg-green-900/10 rounded-xl animate-in fade-in duration-300">
                                        <div className="w-14 h-14 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-3 text-white shadow-md shadow-green-200 dark:shadow-none">
                                            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <h3 className="text-base font-bold text-slate-800 dark:text-white">Pagamento Instantâneo via Pix</h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-[200px] mx-auto">Liberação automática do plano após a confirmação.</p>
                                    </div>
                                )}

                                {/* Billing details */}
                                <div className="pt-6 border-t border-slate-100 dark:border-slate-800">
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-1.5">
                                        <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                        Dados de Cobrança / Segurança
                                    </h3>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div className={method === 'pix' ? 'sm:col-span-2' : ''}>
                                            <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">CPF / CNPJ</label>
                                            <IMaskInput
                                                mask={[{ mask: '000.000.000-00' }, { mask: '00.000.000/0000-00' }]} unmask={true}
                                                type="text" name="cpf" required
                                                value={formData.cpf} onAccept={(val) => handleMaskChange(val, 'cpf')}
                                                placeholder="000.000.000-00"
                                                className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                            />
                                        </div>

                                        {method === 'credit_card' && (
                                            <>
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Celular</label>
                                                    <IMaskInput
                                                        mask="(00) 00000-0000" unmask={true}
                                                        type="text" name="phone" required={method === 'credit_card'}
                                                        value={formData.phone} onAccept={(val) => handleMaskChange(val, 'phone')}
                                                        placeholder="(11) 99999-9999"
                                                        className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">CEP</label>
                                                    <IMaskInput
                                                        mask="00000-000" unmask={true}
                                                        type="text" name="postal_code" required={method === 'credit_card'}
                                                        value={formData.postal_code} onAccept={(val) => handleMaskChange(val, 'postal_code')}
                                                        placeholder="00000-000"
                                                        className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1.5">Nº do Endereço</label>
                                                    <input
                                                        type="text" name="address_number" required={method === 'credit_card'}
                                                        value={formData.address_number} onChange={(e) => setFormData({ ...formData, address_number: e.target.value })}
                                                        placeholder="Número ou SN"
                                                        className="block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                                    />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <button
                                    type="submit" disabled={isLoading || isLoadingUpgrade}
                                    className="mt-8 w-full py-4 px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold text-base rounded-xl shadow-sm transition-all flex justify-center items-center gap-2 transform active:scale-[0.99] disabled:opacity-70 disabled:cursor-not-allowed"
                                >
                                    {isLoading ? (
                                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                                    ) : (
                                        <>
                                            {upgradeData?.is_upgrade
                                                ? `Confirmar Upgrade — ${upgradeData.remaining_months}x de R$ ${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(upgradeData.delta_per_month)}`
                                                : isInstallment
                                                    ? `Confirmar 12x de R$ ${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(couponSuccess ? finalPrice / 12 : installmentValue)}`
                                                    : `Confirmar Assinatura — R$ ${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(finalPrice)}`
                                            }
                                            <svg className="w-5 h-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                        </>
                                    )}
                                </button>
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
