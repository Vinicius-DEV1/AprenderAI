import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import '../../styles/landing-page.css';

export default function WelcomePlans() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { plans } = useConfigStore();
    const { user } = useAuthStore();
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');

    // Get intended plan from URL or localStorage
    useEffect(() => {
        const urlPlan = searchParams.get('plan');
        const storagePlan = localStorage.getItem('intended_plan');

        if (urlPlan?.includes('annual') || storagePlan?.includes('annual')) {
            setPeriodo('anual');
        }
    }, [searchParams]);

    const handlePlanSelect = (planName: string, interval: 'monthly' | 'yearly') => {
        const matchedPlan = plans.find(p =>
            p.name.toLowerCase().includes(planName.toLowerCase()) &&
            p.interval === interval
        );

        if (matchedPlan) {
            localStorage.removeItem('intended_plan');
            navigate(`/plans/${matchedPlan.id}/checkout`);
        }
    };

    return (
        <div className="lp-wrapper bg-white min-h-screen">

            <section className="lp-plans py-16">
                <div style={{ maxWidth: '980px', margin: '0 auto' }}>
                    <div className="text-center mb-12">
                        <span className="bg-indigo-100 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-4 inline-block">
                            Conta criada com sucesso!
                        </span>
                        <h2 className="lp-plans-title text-4xl mb-4">
                            Agora, escolha o seu plano e <span>comece a estudar</span>
                        </h2>
                        <p className="lp-plans-subtitle text-lg">
                            Desbloqueie todo o poder da Inteligência Artificial em sua preparação.
                        </p>
                    </div>

                    <div className="lp-plans-toggle-wrap">
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
                        {/* BÁSICO */}
                        <div className="lp-plan-basic flex flex-col justify-between">
                            <div>
                                <div className="lp-plan-name-paid">Básico</div>
                                <div className="lp-plan-tagline-paid">Para evoluir com correção completa e redação guiada.</div>

                                <div style={{ marginBottom: '.25rem' }}>
                                    <span className="lp-plan-price-paid">
                                        R$&nbsp;{periodo === 'anual' ? '20,00' : '25,00'}
                                    </span>
                                    <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                </div>

                                <div className="lp-plan-annual-note lp-plan-annual-note-paid mb-6">
                                    {periodo === 'anual' ? 'Cobrado anualmente (R$ 240,00)' : 'Ou R$ 240,00 no plano anual (20% OFF)'}
                                </div>

                                <ul className="lp-plan-list lp-plan-list-paid">
                                    <li><span className="lp-check-paid">✓</span> Correção detalhada (IA)</li>
                                    <li><span className="lp-check-paid">✓</span> 5 redações/mês</li>
                                    <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência</li>
                                    <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                                    <li><span className="lp-check-paid">✓</span> 10 provas/mês</li>
                                    <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                    <li><span className="lp-check-paid">✓</span> Acesso ilimitado às questões</li>
                                </ul>
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
                                        R$&nbsp;{periodo === 'anual' ? '40,00' : '49,90'}
                                    </span>
                                    <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                </div>

                                <div className="lp-plan-annual-note lp-plan-annual-note-paid mb-6">
                                    {periodo === 'anual' ? 'Cobrado anualmente (R$ 480,00)' : 'Ou R$ 480,00 no plano anual (20% OFF)'}
                                </div>

                                <ul className="lp-plan-list lp-plan-list-paid">
                                    <li><span className="lp-check-paid">✓</span> <strong>Análise estratégica</strong></li>
                                    <li><span className="lp-check-paid">✓</span> Cronograma de Estudos IA</li>
                                    <li><span className="lp-check-paid">✓</span> <strong>Simulados ilimitados</strong></li>
                                    <li><span className="lp-check-paid">✓</span> <strong>15 redações/mês</strong></li>
                                    <li><span className="lp-check-paid">✓</span> Xavier Tutor Ilimitado</li>
                                    <li><span className="lp-check-paid">✓</span> Tira-Dúvidas Instantâneo AI</li>
                                    <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                    <li><span className="lp-check-paid">✓</span> Acesso ilimitado às questões</li>
                                </ul>
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
                                    <li><span className="lp-check-free">✓</span> Acesso ilimitado às questões</li>
                                    <li><span className="lp-check-free">✓</span> Gabarito Comentado</li>
                                    <li><span className="lp-check-free">✓</span> 30 questões todos os dias para praticar</li>
                                    <li><span className="lp-check-free">✓</span> Modo noturno</li>
                                </ul>
                            </div>

                            <button
                                onClick={() => navigate('/dashboard')}
                                className="lp-plan-btn-free mt-8 cursor-pointer hover:opacity-90 transition-opacity bg-gray-500 hover:bg-gray-600"
                            >
                                Continuar com Grátis
                            </button>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    );
}
