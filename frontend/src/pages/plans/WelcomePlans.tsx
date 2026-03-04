import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { toast } from 'sonner';
import '../../styles/landing-page.css';
import PlanCheckout from './PlanCheckout';
import Accordion from '../../components/Accordion';

export default function WelcomePlans() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { plans } = useConfigStore();
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');
    const [selectedPlanId, setSelectedPlanId] = useState<number | string | null>(null);

    // Get intended plan from URL or localStorage
    useEffect(() => {
        const urlPlan = searchParams.get('plan');
        const storagePlan = localStorage.getItem('intended_plan');

        if (urlPlan?.includes('annual') || storagePlan?.includes('annual')) {
            setPeriodo('anual');
        }
    }, [searchParams]);

    const handlePlanSelect = (planName: string, interval: 'monthly' | 'yearly') => {
        if (!plans || plans.length === 0) {
            toast.error('Os planos ainda estão carregando. Aguarde um momento.');
            return;
        }

        const matchedPlan = plans.find(p => {
            const name = p.name.toLowerCase();
            const target = planName.toLowerCase();
            // Handle both Básico and Basico
            const nameMatches = name.includes(target) || (target === 'básico' && name.includes('basico'));
            return nameMatches && p.interval === interval;
        });

        if (matchedPlan) {
            localStorage.removeItem('intended_plan');
            setSelectedPlanId(matchedPlan.id);
            // Move scroll to top to see checkout
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            console.error('Plan not found:', planName, interval);
            toast.error(`Plano "${planName}" não encontrado. Tente novamente.`);
        }
    };

    // Helpers para preços dinâmicos (segue a mesma lógica do PlanList)
    const getPlanPrice = (slugKeyword: string) => {
        if (!plans || plans.length === 0) return null;
        const p = plans.find(p => p.slug?.includes(slugKeyword) && (periodo === 'anual' ? p.interval === 'yearly' : p.interval === 'monthly'));
        return p ? Number(p.price) : null;
    };

    const formatPrice = (price: number | null): string => {
        if (price === null) return '--';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(price);
    };

    return (
        <div className="lp-wrapper bg-[#f8fafc] min-h-screen">

            <section className="lp-plans pt-0 pb-12 -mt-[75px]">
                <div style={{ maxWidth: '980px', margin: '0 auto' }}>
                    <div className="text-center mb-6">
                        <span className="bg-indigo-100 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-2 inline-block">
                            Conta criada com sucesso!
                        </span>
                        <h2 className="lp-plans-title text-4xl mb-1">
                            Agora, escolha o seu plano e <span>comece a estudar</span>
                        </h2>
                        <p className="lp-plans-subtitle text-lg" style={{ marginBottom: '1.25rem' }}>
                            Desbloqueie todo o poder da Inteligência Artificial em sua preparação.
                        </p>
                    </div>

                    <div className="lp-plans-toggle-wrap" style={{ marginBottom: '1.5rem' }}>
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

                    {selectedPlanId ? (
                        <div className="w-full mt-4 animate-in fade-in duration-300">
                            <button onClick={() => setSelectedPlanId(null)} className="mb-4 text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Voltar aos planos
                            </button>
                            <PlanCheckout
                                embeddedPlanId={selectedPlanId}
                                onSuccess={() => navigate('/dashboard')}
                                onCancel={() => setSelectedPlanId(null)}
                            />
                        </div>
                    ) : (
                        <div className="lp-plans-grid mt-6">
                            {/* BÁSICO */}
                            <div className="lp-plan-basic flex flex-col justify-between">
                                <div>
                                    <div className="lp-plan-name-paid">Básico</div>
                                    <div className="lp-plan-tagline-paid">Para evoluir com correção completa e redação guiada.</div>

                                    <div style={{ marginBottom: '.25rem' }}>
                                        <span className="lp-plan-price-paid">
                                            R$&nbsp;{formatPrice(getPlanPrice('basic'))}
                                        </span>
                                        <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                    </div>

                                    <div className="lp-plan-annual-note lp-plan-annual-note-paid mb-6">
                                        {periodo === 'anual' ? 'Cobrado anualmente (R$ 240,00)' : 'Ou R$ 240,00 no plano anual (20% OFF)'}
                                    </div>

                                    <ul className="lp-plan-list lp-plan-list-paid">
                                        <li><span className="lp-check-paid">✓</span> Correção detalhada (IA)</li>
                                        <li><span className="lp-check-paid">✓</span> 5 redações/mês</li>
                                        <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência (C1–C5)</li>
                                        <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                                    </ul>

                                    <div className="mt-4">
                                        <Accordion title={<span className="font-bold text-white opacity-90">+ Ver Lista Completa</span>} defaultExpanded={false} variant="transparent" className="!text-blue-100">
                                            <ul className="lp-plan-list lp-plan-list-paid mt-2 !mb-0 text-[13px]">
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
                                    onClick={() => handlePlanSelect('básico', periodo === 'anual' ? 'yearly' : 'monthly')}
                                    className="lp-plan-btn-basic mt-8 cursor-pointer hover:opacity-90 transition-opacity"
                                >
                                    Escolher Plano Básico
                                </button>
                            </div>

                            {/* PLUS */}
                            <div className="lp-plan-plus flex flex-col justify-between relative overflow-hidden shadow-2xl scale-105 z-10">
                                <div className="absolute top-4 right-[-35px] bg-amber-500 text-blue-900 text-[10px] font-black px-10 py-1 rotate-45 shadow-sm">
                                    POPULAR
                                </div>
                                <div>
                                    <div className="lp-plan-name-paid flex items-center gap-2">
                                        Plus
                                    </div>
                                    <div className="lp-plan-tagline-paid">Para acelerar no máximo com estratégia e simulados ilimitados.</div>

                                    <div style={{ marginBottom: '.25rem' }}>
                                        <span className="lp-plan-price-paid">
                                            R$&nbsp;{formatPrice(getPlanPrice('plus'))}
                                        </span>
                                        <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                    </div>

                                    <div className="lp-plan-annual-note lp-plan-annual-note-paid mb-6">
                                        {periodo === 'anual' ? 'Cobrado anualmente (R$ 480,00)' : 'Ou R$ 480,00 no plano anual (20% OFF)'}
                                    </div>

                                    <ul className="lp-plan-list lp-plan-list-paid">
                                        <li><span className="lp-check-paid">✓</span> <strong className="text-white">Análise estratégica</strong></li>
                                        <li><span className="lp-check-paid">✓</span> Cronograma de Estudos personalizado</li>
                                        <li><span className="lp-check-paid">✓</span> <strong className="text-white">Simulados ilimitados</strong></li>
                                        <li><span className="lp-check-paid">✓</span> <strong className="text-white">15 redações/mês</strong></li>
                                    </ul>

                                    <div className="mt-4">
                                        <Accordion title={<span className="font-bold text-white opacity-90">+ Ver Lista Completa</span>} defaultExpanded={false} variant="transparent" className="!text-blue-100">
                                            <ul className="lp-plan-list lp-plan-list-paid mt-2 !mb-0 text-[13px]">
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
                                    onClick={() => handlePlanSelect('plus', periodo === 'anual' ? 'yearly' : 'monthly')}
                                    className="lp-plan-btn-plus mt-8 cursor-pointer hover:opacity-90 transition-opacity"
                                >
                                    Escolher Plano Plus
                                </button>
                            </div>

                            {/* FREE */}
                            <div className="lp-plan-free flex flex-col justify-between border-2 border-gray-100">
                                <div>
                                    <div className="lp-plan-name-free">Gratuito</div>
                                    <div className="lp-plan-tagline-free">Para conhecer e testar a plataforma.</div>

                                    <div style={{ marginBottom: '.25rem' }}>
                                        <span className="lp-plan-price-free">R$ 0</span>
                                        <span className="lp-plan-price-unit lp-plan-price-unit-free">/mês</span>
                                    </div>
                                    <div className="lp-plan-annual-note lp-plan-annual-note-free mb-6">
                                        Sem cartão de crédito necessário.
                                    </div>

                                    <ul className="lp-plan-list lp-plan-list-free">
                                        <li><span className="lp-check-free">✓</span> 5 provas/mês</li>
                                        <li><span className="lp-check-free">✓</span> Correção básica</li>
                                        <li><span className="lp-check-free">✓</span> Estatísticas simples</li>
                                        <li><span className="lp-check-free">✓</span> 30 questões para praticar todos os dias</li>
                                    </ul>

                                    <div className="mt-4">
                                        <Accordion title={<span className="font-bold text-slate-600">+ Detalhes do Plano</span>} defaultExpanded={false}>
                                            <ul className="lp-plan-list lp-plan-list-free mt-2 !mb-0 text-[13px]">
                                                <li><span className="lp-check-free">✓</span> Gabarito Comentado</li>
                                                <li><span className="lp-check-free">✓</span> Modo noturno</li>
                                            </ul>
                                        </Accordion>
                                    </div>
                                </div>

                                <button
                                    onClick={() => navigate('/dashboard')}
                                    className="lp-plan-btn-free mt-8 cursor-pointer hover:opacity-90 transition-opacity bg-gray-500 hover:bg-gray-600"
                                >
                                    Continuar com Grátis
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}
