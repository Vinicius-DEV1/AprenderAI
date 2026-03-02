import { useNavigate } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import PlanConfirmationModal from '../../components/PlanConfirmationModal';
import Accordion from '../../components/Accordion';
import '../../styles/landing-page.css';

export default function PlanList() {
    const navigate = useNavigate();
    const { plans } = useConfigStore();
    const { user } = useAuthStore();
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedPlanForModal, setSelectedPlanForModal] = useState<any>(null);

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
        if (String(cardPlan.id) === String(getPlanBySlug('gratuito')?.id)) {
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

    const getPlanPrice = (name: string) => {
        const p = plans?.find(p => p.name.toLowerCase().includes(name.toLowerCase()) && (periodo === 'anual' ? p.interval === 'yearly' : p.interval === 'monthly'));
        return p?.price || 0;
    };

    const getPlanBySlug = (slug: string) => {
        return plans?.find(p => p.name.toLowerCase().includes(slug.toLowerCase()) && (periodo === 'anual' ? p.interval === 'yearly' : p.interval === 'monthly'));
    };

    return (
        <div className="lp-wrapper bg-transparent py-8">
            <div className="max-w-7xl mx-auto px-4">
                <div className="text-center mb-10">
                    <h3 className="text-3xl font-black text-slate-800 dark:text-white mb-2 uppercase tracking-tighter">Sua Aprovação Começa Aqui</h3>
                    <p className="text-slate-500 dark:text-slate-400">Escolha o plano que melhor se adapta aos seus objetivos.</p>
                </div>

                {userPlanId && (
                    <div className="mb-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
                        <div className="flex items-center gap-4">
                            <div className="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 dark:text-blue-400 text-xl shadow-inner">
                                ⭐️
                            </div>
                            <div>
                                <p className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Minha Assinatura</p>
                                <h4 className="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    Plano {currentPlan?.name || 'Gratuito'}
                                    <span className="text-[10px] bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 px-2 py-0.5 rounded-full uppercase tracking-widest font-black">Ativo</span>
                                </h4>
                            </div>
                        </div>
                        <div className="text-left md:text-right">
                            <p className="text-sm text-slate-600 dark:text-slate-400 font-medium">Você tem acesso aos recursos do plano atual.</p>
                        </div>
                    </div>
                )}

                <div className="lp-plans-toggle-wrap mb-10">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', background: '#f1f5f9', borderRadius: '50px', padding: '.35rem .75rem' }}>
                        <span
                            style={{ fontSize: '.85rem', fontWeight: 600, cursor: 'pointer', transition: 'color .2s', color: periodo === 'mensal' ? '#0f2b6e' : '#94a3b8' }}
                            onClick={() => setPeriodo('mensal')}
                        >
                            Mensal
                        </span>

                        <button
                            type="button"
                            onClick={() => setPeriodo(periodo === 'mensal' ? 'anual' : 'mensal')}
                            className="relative inline-flex h-6 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none"
                            style={{ background: periodo === 'anual' ? '#1d4ed8' : '#cbd5e1' }}
                            role="switch"
                            aria-checked={periodo === 'anual' ? 'true' : 'false'}
                        >
                            <span
                                className="pointer-events-none inline-block h-4 w-4 mt-px ml-px transform rounded-full bg-white shadow ring-0 transition-transform duration-300"
                                style={{ transform: periodo === 'anual' ? 'translateX(1.25rem)' : 'translateX(0)' }}
                            ></span>
                        </button>

                        <span
                            style={{ fontSize: '.85rem', fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '.4rem', transition: 'color .2s', color: periodo === 'anual' ? '#0f2b6e' : '#94a3b8' }}
                            onClick={() => setPeriodo('anual')}
                        >
                            Anual
                            <span style={{ background: '#dcfce7', color: '#15803d', fontSize: '.7rem', fontWeight: 800, padding: '.15rem .5rem', borderRadius: '99px' }}>-20% OFF</span>
                        </span>
                    </div>
                </div>

                <div className="lp-plans-grid">
                    {/* FREE */}
                    <div className={`lp-plan-free flex flex-col justify-between border-2 ${String(userPlanId) === String(getPlanBySlug('gratuito')?.id) ? 'border-green-500 bg-green-50/30' : 'border-gray-100'}`}>
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
                        <button disabled className="lp-plan-btn-free bg-gray-300 text-gray-600 cursor-not-allowed">
                            {getButtonLabel(getPlanBySlug('gratuito'))}
                        </button>
                    </div>

                    {/* BÁSICO */}
                    <div className={`lp-plan-basic flex flex-col justify-between ${String(userPlanId) === String(getPlanBySlug('básico')?.id) ? 'ring-4 ring-blue-400' : ''}`}>
                        <div>
                            <div className="lp-plan-name-paid">Básico</div>
                            <div className="lp-plan-tagline-paid">Para evoluir com correção completa e IA.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-paid">R$&nbsp;{new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(getPlanPrice('básico'))}</span>
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
                            onClick={() => handlePlanClick(getPlanBySlug('básico'))}
                            disabled={String(userPlanId) === String(getPlanBySlug('básico')?.id)}
                            className={`lp-plan-btn-basic ${String(userPlanId) === String(getPlanBySlug('básico')?.id) ? 'bg-blue-400 opacity-50 cursor-not-allowed' : 'hover:scale-105 transition-transform'}`}
                        >
                            {getButtonLabel(getPlanBySlug('básico'))}
                        </button>
                    </div>

                    {/* PLUS */}
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
            </div>
        </div>
    );
}
