import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';
import { useAuthStore } from '../stores/authStore';
import '../styles/landing-page.css';

export default function HomePage() {
    const [faqOpen, setFaqOpen] = useState<number | null>(null);
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');
    const { plans } = useConfigStore();
    const { isAuthenticated, isLoading } = useAuthStore();
    const navigate = useNavigate();

    // Redirect authenticated users away from the landing page to the dashboard
    useEffect(() => {
        if (!isLoading && isAuthenticated) {
            navigate('/dashboard', { replace: true });
        }
    }, [isAuthenticated, isLoading, navigate]);

    const getPlanBySlug = (slugKeyword: string) => {
        if (!plans || plans.length === 0) return null;
        const isAnual = periodo === 'anual';
        return plans.find(p => {
            const slug = p.slug?.toLowerCase() ?? '';
            const isExactMatch = slug === slugKeyword.toLowerCase();
            const isAnnualVariant = slug === `${slugKeyword.toLowerCase()}-annual`;
            const isMatchSlug = isAnual ? (isExactMatch || isAnnualVariant) : isExactMatch;
            const isMatchInterval = isAnual
                ? (p.interval === 'year' || p.interval === 'yearly')
                : (p.interval === 'month' || p.interval === 'monthly');
            return isMatchSlug && isMatchInterval;
        }) ?? plans.find(p => p.slug?.toLowerCase().includes(slugKeyword.toLowerCase()));
    };

    const getDisplayPrice = (slugKeyword: string) => {
        const p = getPlanBySlug(slugKeyword);
        if (!p) return { monthly: 0, total: null };
        if (periodo === 'anual') {
            const monthly = p.monthly_price ? Number(p.monthly_price) : Number(p.price);
            const total = p.annual_price ? Number(p.annual_price) : Number(p.price) * 12;
            return { monthly, total };
        }
        const monthly = p.monthly_price ? Number(p.monthly_price) : Number(p.price);
        return { monthly, total: null };
    };

    const formatPrice = (price: number | null): string => {
        if (price === null) return '--';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(price);
    };

    const faqs = [
        { q: 'A plataforma é totalmente online?', a: 'Sim. O AprenderAI funciona 100% online. Você pode acessar de qualquer lugar, pelo computador ou celular, sem necessidade de instalação.' },
        { q: 'O AprenderAI serve para ENEM e concursos?', a: 'Sim. A plataforma foi desenvolvida tanto para preparação para o ENEM quanto para concursos públicos, com simulados, questões e plano de estudos personalizados.' },
        { q: 'Como funciona a correção por IA?', a: 'Nossa inteligência analisa suas respostas e redações, identifica padrões de erro e fornece explicações detalhadas para acelerar sua evolução.' },
        { q: 'Posso testar gratuitamente antes de assinar?', a: 'Sim. O plano gratuito permite que você conheça a plataforma e resolva provas antes de optar por um plano pago.' },
        { q: 'Os simulados seguem o padrão oficial das provas?', a: 'Sim. Os simulados são estruturados para replicar o formato real do ENEM e de concursos, incluindo controle de tempo.' },
        { q: 'Como funciona o plano anual com desconto?', a: 'Ao optar pelo plano anual, você recebe 20% de desconto em relação ao valor mensal, mantendo todos os benefícios do plano escolhido.' },
        { q: 'A plataforma acompanha meu desempenho?', a: 'Sim. Você pode acompanhar sua evolução por disciplina, identificar pontos fracos e visualizar seu progresso ao longo do time.' },
        { q: 'Posso cancelar quando quiser?', a: 'Sim. Você pode gerenciar sua assinatura conforme as regras do plano contratado.' },
    ];

    const toggleFaq = (index: number) => {
        setFaqOpen(faqOpen === index ? null : index);
    };

    const trackCTA = (location: string, plan?: string) => {
        if (plan) {
            localStorage.setItem('intended_plan', plan);
        }
        if (typeof (window as any).gtag === 'function') {
            (window as any).gtag('event', 'cta_click', {
                'button_location': location,
                'plan_name': plan || 'n/a'
            });
        }
    };

    const orgSchema = {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "AprenderAI",
        "url": typeof window !== 'undefined' ? window.location.origin : '',
        "logo": typeof window !== 'undefined' ? `${window.location.origin}/logo.png` : '',
        "sameAs": [
            "https://www.instagram.com/aprenderai",
            "https://www.facebook.com/aprenderai"
        ]
    };

    return (
        <div className="lp-wrapper">
            <script type="application/ld+json">
                {JSON.stringify(orgSchema)}
            </script>

            {/* NAVBAR */}
            <nav className="lp-nav" id="top">
                <div className="lp-nav-inner">
                    <Link to="/" className="lp-logo">Aprender<span>AI</span></Link>

                    <ul className="lp-nav-links">
                        <li><a href="#plans">Planos</a></li>
                        <li><a href="#depoimentos">Depoimentos</a></li>
                        <li><Link to="/uso-justo">Política de Uso</Link></li>
                        <li><Link to="/privacidade">Política de Privacidade</Link></li>
                        <li><a href="#plans" className="lp-btn-cta" onClick={() => trackCTA('navbar', 'plans')}>Assine Agora</a></li>
                    </ul>
                </div>
            </nav>

            {/* HERO */}
            <section className="lp-hero">
                <div className="lp-hero-inner">
                    <div>
                        <h1 className="lp-hero-title">
                            Estude com IA.<br />
                            Passe na <span>Frente!</span>
                        </h1>
                        <p className="lp-hero-subtitle">
                            A preparação inteligente para o ENEM e Concursos Públicos.
                            Comece hoje e conquiste a sua aprovação.
                        </p>
                        <div className="lp-hero-btns">
                            <Link
                                to="/register?plan=free"
                                className="lp-btn-primary"
                                onClick={() => trackCTA('hero', 'free')}
                            >
                                Comece Gratuitamente
                            </Link>
                            <Link to="/login" className="lp-btn-outline">
                                Já tenho conta
                            </Link>
                        </div>

                        {/* micro-copy de confiança */}
                        <p className="lp-hero-trust">✔ Cartão de crédito · ✔ Acesso imediato · ✔ Cancele quando quiser</p>
                    </div>

                    <div className="lp-hero-illus">
                        <img src="/hero.png" alt="Painel Inteligente" width="500" height="400" />
                    </div>
                </div>
            </section>

            {/* METRICS */}
            <section className="lp-metrics">
                <div className="lp-metrics-inner">
                    <div className="lp-metrics-divider">
                        <div className="lp-metric-num">35%</div>
                        <div className="lp-metric-label">MAIS ACERTOS EM <strong>30 DIAS</strong></div>
                    </div>
                    <div className="lp-metrics-divider">
                        <div className="lp-metric-num">1.200+</div>
                        <div className="lp-metric-label">REDAÇÕES <strong>NOTA 900+</strong></div>
                    </div>
                    <div>
                        <div className="lp-metric-num">100 MIL</div>
                        <div className="lp-metric-label"><strong>ALUNOS IMPACTADOS</strong></div>
                    </div>
                </div>
            </section>

            {/* FEATURES */}
            <section className="lp-features" id="features">
                <div className="lp-section-inner">
                    <h2 className="lp-section-title">A melhor plataforma de simulados e estudos</h2>
                    <div className="lp-features-grid">
                        <div className="lp-feat-card">
                            <div className="lp-feat-icon">💬</div>
                            <h3>Questões Comentadas</h3>
                            <p>Banco de questões atualizado com explicações para acelerar sua aprendizagem.</p>
                        </div>
                        <div className="lp-feat-card">
                            <div className="lp-feat-icon">🤖</div>
                            <h3>Correção Inteligente por IA</h3>
                            <p>Correção automática e análises detalhadas para você entender cada erro.</p>
                        </div>
                        <div className="lp-feat-card">
                            <div className="lp-feat-icon">✍️</div>
                            <h3>Redação Nota 1000</h3>
                            <p>Envie suas redações e receba feedback por competência em poucos segundos.</p>
                        </div>
                        <div className="lp-feat-card">
                            <div className="lp-feat-icon">📅</div>
                            <h3>Plano de Estudos Personalizado</h3>
                            <p>Monte seu plano de acordo com seu objetivo, tempo e desempenho.</p>
                        </div>
                        <div className="lp-feat-card">
                            <div className="lp-feat-icon">📊</div>
                            <h3>Desempenho em Tempo Real</h3>
                            <p>Acompanhe sua evolução em gráficos e métricas por disciplina e prova.</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* PLANS */}
            <section className="lp-plans" id="plans">
                <div style={{ maxWidth: '980px', margin: '0 auto' }}>
                    <h2 className="lp-plans-title">
                        Escolha seu plano e dê o primeiro passo<br />
                        <span>para a aprovação</span>
                    </h2>
                    <p className="lp-plans-subtitle">Planos mensais e opção de plano anual com <strong>20% de desconto</strong>.</p>

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

                        {/* FREE */}
                        <div className="lp-plan-free">
                            <div className="lp-plan-name-free">Gratuito</div>
                            {/* tagline */}
                            <div className="lp-plan-tagline-free">Para começar e testar a plataforma.</div>

                            <div style={{ marginBottom: '.25rem' }}>
                                <span className="lp-plan-price-free">R$ 0</span>
                                <span className="lp-plan-price-unit lp-plan-price-unit-free">/mês</span>
                            </div>
                            <div className="lp-plan-annual-note lp-plan-annual-note-free" style={{ marginBottom: '1.25rem' }}>
                                Sempre gratuito, sem cartão de crédito.
                            </div>

                            <ul className="lp-plan-list lp-plan-list-free">
                                <li><span className="lp-check-free">✓</span> 5 provas/mês</li>
                                <li><span className="lp-check-free">✓</span> Correção básica</li>
                                <li><span className="lp-check-free">✓</span> Estatísticas simples</li>
                                <li><span className="lp-check-free">✓</span> 30 questões para praticar todos os dias</li>

                                {/* Extras */}
                                <li className="lp-plan-extras lp-plan-extras-free">
                                    <span style={{ fontWeight: 800, color: '#0f2b6e' }}>+ Benefícios</span>
                                </li>
                                <li><span className="lp-check-free">✓</span> Gabarito Comentado</li>
                                <li><span className="lp-check-free">✓</span> Modo noturno</li>
                            </ul>

                            <Link
                                to="/register?plan=free"
                                className="lp-plan-btn-free"
                                onClick={() => trackCTA('pricing_free', 'free')}
                            >
                                Começar Agora
                            </Link>
                        </div>

                        {/* BÁSICO */}
                        {(() => {
                            const basicPrice = getDisplayPrice('basic');
                            return (
                                <div className="lp-plan-basic">
                                    <div className="lp-plan-name-paid">Básico</div>
                                    <div className="lp-plan-tagline-paid">Para evoluir com correção completa e redação guiada.</div>

                                    <div style={{ marginBottom: '.25rem' }}>
                                        <span className="lp-plan-price-paid">
                                            R$&nbsp;{formatPrice(basicPrice.monthly)}
                                        </span>
                                        <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                    </div>

                                    {periodo === 'anual' ? (
                                        <div className="lp-plan-annual-note lp-plan-annual-note-paid">
                                            Ou R$ {basicPrice.total !== null ? formatPrice(basicPrice.total) : formatPrice(basicPrice.monthly * 12)}/ano
                                        </div>
                                    ) : (
                                        <div className="lp-plan-annual-note lp-plan-annual-note-paid">
                                            Assine agora e acelere seus estudos
                                        </div>
                                    )}

                                    <ul className="lp-plan-list lp-plan-list-paid">
                                        <li><span className="lp-check-paid">✓</span> Correção detalhada (IA)</li>
                                        <li><span className="lp-check-paid">✓</span> 5 redações/mês</li>
                                        <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência (C1–C5)</li>
                                        <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                                        <li><span className="lp-check-paid">✓</span> 10 provas/mês</li>
                                        <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                        <li><span className="lp-check-paid">✓</span> Acesso ilimitado a todas as questões</li>

                                        {/* Extras */}
                                        <li className="lp-plan-extras">
                                            <span style={{ fontWeight: 800, color: '#fff' }}>+ Benefícios</span>
                                        </li>
                                        <li><span className="lp-check-paid">✓</span> Estatísticas simples</li>
                                        <li><span className="lp-check-paid">✓</span> Gabarito Comentado</li>
                                        <li><span className="lp-check-paid">✓</span> Modo noturno</li>
                                    </ul>

                                    <Link
                                        to={`/register?plan=${periodo === 'anual' ? 'basic-annual' : 'basic'}`}
                                        className="lp-plan-btn-basic"
                                        onClick={() => trackCTA('pricing_basic', periodo === 'anual' ? 'basic-annual' : 'basic')}
                                    >
                                        Assinar Agora
                                    </Link>

                                    {/* não tirar o anual */}
                                    <Link
                                        to="/register?plan=basic-annual"
                                        className="lp-plan-btn-annual"
                                        onClick={() => trackCTA('pricing_basic_annual', 'basic-annual')}
                                    >
                                        Assinar Plano Anual (20% OFF)
                                    </Link>
                                </div>
                            );
                        })()}

                        {/* PLUS */}
                        {(() => {
                            const plusPrice = getDisplayPrice('plus');
                            return (
                                <div className="lp-plan-plus">
                                    <div className="lp-plan-badge lp-badge-popular">⭐ Mais Popular</div>
                                    <div className="lp-plan-name-paid">Plus</div>
                                    <div className="lp-plan-tagline-paid">Para acelerar no máximo com estratégia e simulados ilimitados.</div>

                                    <div style={{ marginBottom: '.25rem' }}>
                                        <span className="lp-plan-price-paid">
                                            R$&nbsp;{formatPrice(plusPrice.monthly)}
                                        </span>
                                        <span className="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                                    </div>

                                    {periodo === 'anual' ? (
                                        <div className="lp-plan-annual-note lp-plan-annual-note-paid">
                                            Ou R$ {plusPrice.total !== null ? formatPrice(plusPrice.total) : formatPrice(plusPrice.monthly * 12)}/ano
                                        </div>
                                    ) : (
                                        <div className="lp-plan-annual-note lp-plan-annual-note-paid">
                                            O plano definitivo para aprovação
                                        </div>
                                    )}

                                    <ul className="lp-plan-list lp-plan-list-paid">
                                        <li><span className="lp-check-paid">✓</span> <strong style={{ color: '#fff' }}>Análise estratégica</strong></li>
                                        <li><span className="lp-check-paid">✓</span> Cronograma de Estudos personalizado</li>
                                        <li><span className="lp-check-paid">✓</span> <strong style={{ color: '#fff' }}>Simulados ilimitados</strong></li>
                                        <li><span className="lp-check-paid">✓</span> <strong style={{ color: '#fff' }}>15 redações/mês</strong></li>
                                        <li><span className="lp-check-paid">✓</span> Redação com Nota por Competência (C1–C5)</li>
                                        <li><span className="lp-check-paid">✓</span> Estatísticas completas</li>
                                        <li><span className="lp-check-paid">✓</span> Radar de concursos</li>
                                        <li><span className="lp-check-paid">✓</span> +200 mil questões para praticar</li>
                                        <li><span className="lp-check-paid">✓</span> Acesso ilimitado a todas as questões</li>

                                        {/* Extras */}
                                        <li className="lp-plan-extras">
                                            <span style={{ fontWeight: 800, color: '#fff' }}>+ Benefícios</span>
                                        </li>
                                        <li><span className="lp-check-paid">✓</span> Gabarito Comentado</li>
                                        <li><span className="lp-check-paid">✓</span> Modo noturno</li>
                                    </ul>

                                    <Link
                                        to={`/register?plan=${periodo === 'anual' ? 'plus-annual' : 'plus'}`}
                                        className="lp-plan-btn-plus"
                                        onClick={() => trackCTA('pricing_plus', periodo === 'anual' ? 'plus-annual' : 'plus')}
                                    >
                                        Assinar Agora
                                    </Link>

                                    {/* não tirar o anual */}
                                    <Link
                                        to="/register?plan=plus-annual"
                                        className="lp-plan-btn-annual"
                                        onClick={() => trackCTA('pricing_plus_annual', 'plus-annual')}
                                    >
                                        Assinar Plano Anual (20% OFF)
                                    </Link>
                                </div>
                            );
                        })()}

                    </div>
                </div>
            </section>

            {/* VANTAGEM COMPETITIVA */}
            <section className="lp-vantagem">
                <div className="lp-vantagem-inner">
                    <h2 className="lp-vantagem-title">Mais do que estudar. &Eacute; criar vantagem competitiva.</h2>
                    <p className="lp-vantagem-sub">Quem estuda com m&eacute;todo evolui. Quem estuda com estrat&eacute;gia passa.</p>

                    <div className="lp-vantagem-grid">

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#9881;</div>
                            <h3>Clareza Estrat&eacute;gica</h3>
                            <p>N&atilde;o &eacute; sobre estudar mais. &Eacute; sobre estudar certo. Descubra exatamente onde
                                voc&ecirc; perde pontos e transforme erros em progresso real.</p>
                        </div>

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#9654;</div>
                            <h3>Seguran&ccedil;a no Dia da Prova</h3>
                            <p>Simule sob press&atilde;o, cronometre seu desempenho e chegue no dia decisivo com
                                confian&ccedil;a constru&iacute;da na pr&aacute;tica.</p>
                        </div>

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#9650;</div>
                            <h3>Evolu&ccedil;&atilde;o Baseada em Dados</h3>
                            <p>Nada de achismo. Acompanhe m&eacute;tricas claras, hist&oacute;rico de desempenho e crescimento
                                cont&iacute;nuo em cada disciplina.</p>
                        </div>

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#10024;</div>
                            <h3>Intelig&ecirc;ncia que Trabalha por Voc&ecirc;</h3>
                            <p>A IA analisa seus padr&otilde;es, identifica fragilidades e ajusta sua prepara&ccedil;&atilde;o
                                automaticamente.</p>
                        </div>

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#9679;</div>
                            <h3>Alto Retorno Sobre o Seu Tempo</h3>
                            <p>Cada hora de estudo passa a ter dire&ccedil;&atilde;o. Menos desperd&iacute;cio. Mais resultado.</p>
                        </div>

                        <div className="lp-vantagem-card">
                            <div className="lp-vantagem-icon">&#9788;</div>
                            <h3>Acesso Real, Sem Barreiras</h3>
                            <p>Prepara&ccedil;&atilde;o estruturada, acess&iacute;vel e dispon&iacute;vel 24/7 &mdash; para quem
                                decide levar a aprova&ccedil;&atilde;o a s&eacute;rio.</p>
                        </div>

                    </div>
                </div>
            </section>

            {/* TESTIMONIALS */}
            <section className="lp-testimonials" id="depoimentos">
                <div>
                    <h2 className="lp-test-title">Histórias de Sucesso</h2>
                    <p className="lp-test-subtitle">Quem estudou com a gente, passou <strong>de verdade.</strong></p>
                    <div className="lp-test-grid">
                        <div className="lp-test-card">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=12" alt="Lucas Andrade" width="48" height="48" loading="lazy" />
                                <div>
                                    <div className="lp-test-name">Lucas Andrade</div>
                                    <div className="lp-test-tag">ENEM</div>
                                    <div className="lp-test-stars">★★★★★</div>
                                </div>
                            </div>
                            <p className="lp-test-quote">"Eu sempre estudava muito, mas não sabia exatamente onde estava errando.
                                Quando comecei a usar as análises da plataforma, consegui organizar melhor minha revisão e minha
                                nota subiu de forma consistente."</p>
                        </div>
                        <div className="lp-test-card">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=32" alt="Mary S." width="48" height="48" loading="lazy" />
                                <div>
                                    <div className="lp-test-name">Marian Silva</div>
                                    <div className="lp-test-tag">Concurso Administrativo</div>
                                    <div className="lp-test-stars">★★★★★</div>
                                </div>
                            </div>
                            <p className="lp-test-quote">"O que mais me ajudou foi conseguir visualizar meu desempenho por
                                disciplina. Antes eu estudava no escuro, agora sei exatamente onde preciso melhorar."</p>
                        </div>
                        <div className="lp-test-card">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=45" alt="Feeh Costa" width="48" height="48" loading="lazy" />
                                <div>
                                    <div className="lp-test-name">Fernanda Costa</div>
                                    <div className="lp-test-tag">Redação</div>
                                    <div className="lp-test-stars">★★★★★</div>
                                </div>
                            </div>
                            <p className="lp-test-quote">"Eu travava muito na redação. Depois que comecei a receber o feedback por
                                competência, consegui entender meus erros estruturais e evoluir muito mais rápido."</p>
                        </div>
                        <div className="lp-test-card">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=8" alt="Rafael Mendes" width="48" height="48" loading="lazy" />
                                <div>
                                    <div className="lp-test-name">Rafael Mendes</div>
                                    <div className="lp-test-tag">Polícia Militar</div>
                                    <div className="lp-test-stars">★★★★★</div>
                                </div>
                            </div>
                            <p className="lp-test-quote">"O cronômetro e os simulados completos mudaram minha preparação. Hoje
                                consigo administrar o tempo muito melhor na hora da prova."</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section className="lp-faq" id="faq">
                <div className="lp-faq-inner">
                    <h2 className="lp-faq-title">Perguntas Frequentes</h2>
                    <p className="lp-faq-subtitle">Tire suas dúvidas sobre a plataforma AprenderAI.</p>

                    {faqs.map((faq, idx) => (
                        <div key={idx} className="lp-faq-item">
                            <button
                                className="lp-faq-btn"
                                onClick={() => toggleFaq(idx)}
                                type="button"
                            >
                                <span className="lp-faq-question">{faq.q}</span>
                                <span className={`lp-faq-icon${faqOpen === idx ? ' open' : ''}`}>+</span>
                            </button>
                            <div className={`lp-faq-answer${faqOpen === idx ? ' open' : ''}`}>
                                <p>{faq.a}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* FOOTER */}
            <footer className="lp-footer">
                <div className="lp-footer-inner">
                    <div className="lp-footer-top">
                        <div>
                            <span className="lp-footer-logo">Aprender<span>AI</span></span>
                            <p className="lp-footer-desc">A plataforma que usa tecnologia para democratizar o acesso à aprovação.
                                Experiência premium focada em performance.</p>
                        </div>
                        <div className="lp-footer-col">
                            <h4>Produto</h4>
                            <ul>
                                <li><a href="#features">Recursos</a></li>
                                <li><a href="#plans">Planos</a></li>
                                <li><a href="#depoimentos">Depoimentos</a></li>
                            </ul>
                        </div>
                        <div className="lp-footer-col">
                            <h4>Uso Legal</h4>
                            <ul>
                                <li><Link to="/privacidade">Política de Privacidade</Link></li>
                                <li><Link to="/uso-justo">Política de Uso Justo</Link></li>
                                <li><Link to="/uso-justo">Política de Uso</Link></li>
                            </ul>
                        </div>
                    </div>
                    <div className="lp-footer-border">
                        &copy; {new Date().getFullYear()} aprenderAI. Todos os direitos reservados.
                    </div>
                </div>
            </footer>
        </div>
    );
}