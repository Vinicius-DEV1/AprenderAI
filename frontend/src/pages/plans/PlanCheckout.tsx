import { toast } from 'sonner';
import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { validateCoupon, processCheckout } from '../../api/subscriptions';
import { IMaskInput } from 'react-imask';

export default function PlanCheckout() {
    const { planId } = useParams();
    const navigate = useNavigate();
    const { plans } = useConfigStore();

    const plan = plans.find(p => p.id === Number(planId));

    const [method, setMethod] = useState<'credit_card' | 'pix'>('credit_card');
    const [couponCode, setCouponCode] = useState('');
    const [couponMessage, setCouponMessage] = useState('');
    const [couponSuccess, setCouponSuccess] = useState(false);
    const [finalPrice, setFinalPrice] = useState(plan?.price || 0);

    const [isLoading, setIsLoading] = useState(false);
    const [checkoutResult, setCheckoutResult] = useState<any>(null); // To store Pix QR Code or Success data

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
            setFinalPrice(plan.price);
        }
    }, [plan]);

    if (!plan && plans.length > 0) {
        return (
            <div className="py-20 text-center">
                <h2 className="text-xl font-bold text-slate-800 dark:text-white">Plano não encontrado.</h2>
                <button onClick={() => navigate('/plans')} className="mt-4 text-blue-600 hover:underline">Voltar para planos</button>
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
                setFinalPrice(response.data.new_price);
            }
        } catch (err: any) {
            setCouponMessage(err.response?.data?.message || 'Erro ao validar cupom.');
            setCouponSuccess(false);
            setFinalPrice(plan!.price);
        }
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            const payload = {
                ...formData,
                payment_method: method,
                coupon_code: couponSuccess ? couponCode : null
            };
            const response = await processCheckout(plan!.id, payload);
            if (response.data.success) {
                if (method === 'pix') {
                    setCheckoutResult(response.data);
                } else {
                    // Success for credit card
                    navigate('/dashboard', { state: { message: 'Assinatura realizada com sucesso!' } });
                }
            }
        } catch (err: any) {
            toast.info(err.response?.data?.message || 'Erro ao processar checkout. Verifique os dados.');
        } finally {
            setIsLoading(false);
        }
    };

    // Pix View
    if (checkoutResult?.pix) {
        return (
            <div className="py-12 max-w-2xl mx-auto px-4">
                <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-xl overflow-hidden border border-slate-200 dark:border-slate-800">
                    <div className="bg-green-600 p-8 text-center text-white">
                        <div className="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-bold">Assinatura Quase Pronta!</h2>
                        <p className="mt-2 opacity-90">Pague via Pix para ativar instantaneamente.</p>
                    </div>

                    <div className="p-8 text-center">
                        <p className="text-slate-600 dark:text-slate-400 mb-6">Escaneie o QR Code abaixo ou copie a chave Pix:</p>

                        <div className="bg-white p-4 inline-block border-4 border-slate-100 dark:border-slate-800 rounded-xl mb-6">
                            <img src={`data:image/png;base64,${checkoutResult.pix.image}`} alt="Pix QR Code" className="w-64 h-64" />
                        </div>

                        <div className="mt-4 mb-8">
                            <div className="text-xs text-slate-400 uppercase font-bold mb-2">Chave Pix (Copia e Cola)</div>
                            <div className="flex gap-2">
                                <input readOnly value={checkoutResult.pix.payload} className="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-3 rounded-lg text-xs font-mono w-full truncate text-slate-600 dark:text-slate-300" />
                                <button onClick={() => navigator.clipboard.writeText(checkoutResult.pix.payload)} className="bg-blue-600 text-white px-4 rounded-lg font-bold text-sm">Copiar</button>
                            </div>
                        </div>

                        <div className="flex flex-col gap-4">
                            <button onClick={() => window.location.reload()} className="bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 py-3 rounded-xl font-bold">Já paguei, verificar agora</button>
                            <button onClick={() => navigate('/dashboard')} className="text-slate-500 hover:text-slate-700 text-sm">Voltar ao Dashboard</button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="py-12 max-w-4xl mx-auto px-4">
            <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-xl overflow-hidden border border-slate-200 dark:border-slate-800">
                <div className="p-8 md:p-10">
                    <h2 className="text-2xl font-extrabold text-slate-900 dark:text-white mb-8">Finalizar Assinatura</h2>

                    {/* Plan Summary */}
                    <div className="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-2xl mb-10 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <span className="text-blue-600 dark:text-blue-400 block text-xs font-bold uppercase tracking-wider mb-1">Você está assinando:</span>
                            <span className="text-2xl font-black text-blue-900 dark:text-white">{plan.name}</span>
                        </div>
                        <div className="text-right flex flex-col items-center sm:items-end">
                            <span className="text-slate-500 dark:text-slate-400 block text-xs font-bold uppercase tracking-wider mb-1">Valor do Investimento:</span>
                            <div className="flex items-baseline gap-1">
                                <span className="text-3xl font-black text-blue-700 dark:text-blue-400">R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(finalPrice)}</span>
                                <span className="text-slate-500 dark:text-slate-400 text-sm font-medium">/{plan.interval === 'yearly' ? 'ano' : 'mês'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Payment Method Tabs */}
                    <div>
                        <div className="flex gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-8">
                            <button
                                onClick={() => setMethod('credit_card')}
                                className={`flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all ${method === 'credit_card' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                            >
                                Cartão de Crédito
                            </button>
                            <button
                                onClick={() => setMethod('pix')}
                                className={`flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all ${method === 'pix' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'}`}
                            >
                                Pix (Instantâneo)
                            </button>
                        </div>

                        {/* Coupon Section */}
                        <div className="mb-10 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <label className="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 underline decoration-blue-500 decoration-2 underline-offset-4">Possui um cupom de desconto?</label>
                            <div className="flex gap-3">
                                <input
                                    type="text"
                                    value={couponCode}
                                    onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                                    placeholder="INSIRA SEU CÓDIGO"
                                    className="flex-1 block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 font-bold tracking-widest placeholder:font-normal placeholder:tracking-normal"
                                />
                                <button
                                    type="button"
                                    onClick={handleValidateCoupon}
                                    className="px-6 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm font-black rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm"
                                >
                                    APLICAR
                                </button>
                            </div>
                            {couponMessage && (
                                <p className={`mt-3 text-sm font-bold ${couponSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                    {couponMessage}
                                </p>
                            )}
                        </div>

                        <form onSubmit={handleSubmit}>
                            {/* Credit Card Form */}
                            {method === 'credit_card' && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-top-4 duration-300">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Nome no Cartão</label>
                                        <input
                                            type="text" name="card_name" required
                                            value={formData.card_name} onChange={handleInputChange}
                                            placeholder="Ex: JOAO A SILVA"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-medium"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Número do Cartão</label>
                                        <IMaskInput
                                            mask="0000 0000 0000 0000" unmask={true}
                                            type="text" name="card_number" required
                                            value={formData.card_number} onAccept={(val) => handleMaskChange(val, 'card_number')}
                                            placeholder="0000 0000 0000 0000"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-medium font-mono"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-6">
                                        <div className="col-span-2">
                                            <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Validade (MM/AA)</label>
                                            <div className="flex gap-3">
                                                <IMaskInput
                                                    mask="00" unmask={true}
                                                    type="text" name="card_expiry_month" required
                                                    value={formData.card_expiry_month} onAccept={(val) => handleMaskChange(val, 'card_expiry_month')}
                                                    placeholder="MM"
                                                    className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-center font-bold"
                                                />
                                                <IMaskInput
                                                    mask="00" unmask={true}
                                                    type="text" name="card_expiry_year" required
                                                    value={formData.card_expiry_year} onAccept={(val) => handleMaskChange(val, 'card_expiry_year')}
                                                    placeholder="AA"
                                                    className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-center font-bold"
                                                />
                                            </div>
                                        </div>
                                        <div className="col-span-2 sm:col-span-1">
                                            <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Cód. Segurança</label>
                                            <IMaskInput
                                                mask="0000" unmask={true}
                                                type="text" name="card_ccv" required
                                                value={formData.card_ccv} onAccept={(val) => handleMaskChange(val, 'card_ccv')}
                                                placeholder="CVV"
                                                className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-center font-bold"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Pix Info */}
                            {method === 'pix' && (
                                <div className="text-center py-12 px-6 bg-green-50/50 dark:bg-green-900/10 rounded-2xl border border-green-100 dark:border-green-900/30 animate-in zoom-in-95 duration-300">
                                    <div className="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-green-200 dark:shadow-none">
                                        <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-xl font-black text-slate-800 dark:text-white mb-2">Simplicidade com Pix</h3>
                                    <p className="text-slate-600 dark:text-slate-400 max-w-sm mx-auto text-sm leading-relaxed">
                                        Assinatura liberada instantaneamente após o pagamento. Seguro, rápido e sem burocracia.
                                    </p>
                                </div>
                            )}

                            {/* Shared Info (Anti-Fraud) */}
                            <div className="mt-10 space-y-6 border-t border-slate-100 dark:border-slate-800 pt-8">
                                <h3 className="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2">
                                    🛡️ Dados de Cobrança / Anti-Fraude
                                </h3>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">CPF / CNPJ</label>
                                        <IMaskInput
                                            mask={[{ mask: '000.000.000-00' }, { mask: '00.000.000/0000-00' }]} unmask={true}
                                            type="text" name="cpf" required
                                            value={formData.cpf} onAccept={(val) => handleMaskChange(val, 'cpf')}
                                            placeholder="000.000.000-00"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Telefone Celular</label>
                                        <IMaskInput
                                            mask="(00) 00000-0000" unmask={true}
                                            type="text" name="phone" required
                                            value={formData.phone} onAccept={(val) => handleMaskChange(val, 'phone')}
                                            placeholder="(11) 99999-9999"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">CEP</label>
                                        <IMaskInput
                                            mask="00000-000" unmask={true}
                                            type="text" name="postal_code" required
                                            value={formData.postal_code} onAccept={(val) => handleMaskChange(val, 'postal_code')}
                                            placeholder="00000-000"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Número (Endereço)</label>
                                        <input
                                            type="text" name="address_number" required
                                            value={formData.address_number} onChange={handleInputChange}
                                            placeholder="123"
                                            className="mt-1 block w-full border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 font-bold"
                                        />
                                    </div>
                                </div>
                                <p className="mt-2 text-[11px] text-slate-400 font-medium">Os dados acima são obrigatórios pela instituição financeira para prevenir recusas por suspeita de fraude.</p>
                            </div>

                            <div className="mt-10">
                                <button
                                    type="submit" disabled={isLoading}
                                    className="w-full h-14 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black rounded-2xl shadow-xl shadow-blue-200 dark:shadow-none transition-all duration-300 flex items-center justify-center gap-2 transform active:scale-[0.98] disabled:opacity-50"
                                >
                                    {isLoading ? (
                                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                                    ) : (
                                        <>
                                            CONFIRMAR ASSINATURA — R$ {new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(finalPrice)}
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </>
                                    )}
                                </button>
                                <div className="mt-6 flex items-center justify-center gap-2 text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                                    <svg className="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zM10 5a1 1 0 011 1v3.586l1.707-1.707a1 1 0 111.414 1.414l-3.414 3.414a1 1 0 01-1.414 0l-3.414-3.414a1 1 0 011.414-1.414L9 9.586V6a1 1 0 011-1z" clipRule="evenodd" />
                                    </svg>
                                    Transação Criptografada & Segura
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
