import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';
import { useAuthStore } from '../stores/authStore';
import '../styles/landing-page.css';
import '../styles/hero-section.css';

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

    // Preços de fallback usados quando a API não retorna dados (backend offline).
    // Quando a API retorna dados, esses valores são ignorados.
    const FALLBACK_PRICES: Record<string, { monthly: number; annualMonthly: number; annualTotal: number }> = {
        basic: { monthly: 25.00, annualMonthly: 20.00, annualTotal: 240.00 },
        plus:  { monthly: 49.90, annualMonthly: 40.00, annualTotal: 480.00 },
    };

    const getDisplayPrice = (slugKeyword: string) => {
        const p = getPlanBySlug(slugKeyword);
        const fallback = FALLBACK_PRICES[slugKeyword.toLowerCase()];

        if (!p) {
            // Nenhum dado da API — usa fallback hardcoded
            if (fallback) {
                return periodo === 'anual'
                    ? { monthly: fallback.annualMonthly, total: fallback.annualTotal }
                    : { monthly: fallback.monthly, total: null };
            }
            return { monthly: 0, total: null };
        }

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
        "logo": typeof window !== 'undefined' ? `${window.location.origin}/aprenderai.ico` : '',
        "sameAs": [
            "https://www.instagram.com/aprenderai",
            "https://www.facebook.com/aprenderai"
        ]
    };

    return (
        <div>
            <script type="application/ld+json">
                {JSON.stringify(orgSchema)}
            </script>

            {/* ═══════════════════════════════════════════
                NOVO HEADER — texto apenas, sem ícone/logo
            ═══════════════════════════════════════════ */}
            <header className="hp-header" id="top">
                <div className="hp-header-inner">
                    <Link to="/" className="hp-logo" aria-label="AprenderAI – página inicial">
                        <span className="hp-logo-text">Aprender<span>AI</span></span>
                    </Link>

                    <nav aria-label="Navegação principal">
                        <ul className="hp-nav-links">
                            <li><a href="#enem">ENEM</a></li>
                            <li><a href="#concursos">Concursos</a></li>
                            <li><a href="#depoimentos">Depoimentos</a></li>
                            <li><a href="#plans">Planos</a></li>
                        </ul>
                    </nav>

                    <Link to="/login" className="hp-btn-entrar" id="header-entrar-btn">
                        Entrar
                    </Link>
                </div>
            </header>

            {/* ═══════════════════════════════════════════
                NOVO HERO — fundo escuro premium
            ═══════════════════════════════════════════ */}
            <section className="hp-hero" id="hero" aria-labelledby="hero-headline">
                <div className="hp-hero-glow hp-hero-glow--1" aria-hidden="true" />
                <div className="hp-hero-glow hp-hero-glow--2" aria-hidden="true" />
                <div className="hp-hero-grid-overlay" aria-hidden="true" />

                <div className="hp-hero-inner">
                    {/* Coluna esquerda: copy + CTAs */}
                    <div className="hp-hero-copy">
                        <div className="hp-hero-badge">
                            <span className="hp-hero-badge-dot" />
                            Treino inteligente para aprovação real
                        </div>

                        <h1 className="hp-hero-headline" id="hero-headline">
                            Suba sua nota no{' '}
                            <span className="hp-headline-highlight">ENEM e concursos</span>{' '}
                            com treino prático e correção inteligente.
                        </h1>

                        <p className="hp-hero-sub">
                            Descubra onde está errando, pratique com estratégia e estude
                            com mais clareza para evoluir de verdade.
                        </p>

                        <div className="hp-hero-ctas">
                            <Link
                                to="/register?plan=free"
                                className="hp-btn-primary"
                                id="hero-cta-primary"
                                onClick={() => trackCTA('hero', 'free')}
                            >
                                <span>Comece a Estudar Grátis</span>
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                                    <path d="M3 9h12M10 4l5 5-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </Link>
                            <a href="#features" className="hp-btn-secondary" id="hero-cta-secondary">
                                Ver Como Funciona
                            </a>
                        </div>

                        <div className="hp-hero-trust-row">
                            <span className="hp-trust-pill">✓ Sem cartão de crédito</span>
                            <span className="hp-trust-pill">✓ Acesso imediato</span>
                            <span className="hp-trust-pill">✓ Cancele quando quiser</span>
                        </div>
                    </div>

                    {/* Coluna direita: imagem */}
                    <div className="hp-hero-visual" aria-hidden="true">
                        <div className="hp-hero-img-frame">
                            <img
                                src="/hero-student.png"
                                alt="Estudante focada usando a plataforma AprenderAI"
                                className="hp-hero-img"
                                width="580"
                                height="480"
                                loading="eager"
                            />
                            <div className="hp-hero-badge-float hp-badge-float--score">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 2l2.4 4.9L18 7.6l-4 3.9.9 5.5L10 14.4l-4.9 2.6.9-5.5L2 7.6l5.6-.7L10 2z" fill="#f59e0b" />
                                </svg>
                                <div>
                                    <div className="hp-badge-float-num">920</div>
                                    <div className="hp-badge-float-label">Nota Redação</div>
                                </div>
                            </div>
                            <div className="hp-hero-badge-float hp-badge-float--progress">
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                    <path d="M9 2v14M4 7l5-5 5 5" stroke="#22c55e" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                <div>
                                    <div className="hp-badge-float-num">+38%</div>
                                    <div className="hp-badge-float-label">Evolução 30 dias</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════════════════════════════════════════
                SEGMENTAÇÃO: ENEM / CONCURSOS
            ═══════════════════════════════════════════ */}
            <section className="hp-seg" id="enem" aria-label="Escolha seu objetivo">
                <div className="hp-seg-inner">
                    <Link
                        to="/register?plan=free&objetivo=enem"
                        className="hp-seg-card hp-seg-card--enem"
                        id="seg-enem-card"
                        onClick={() => trackCTA('seg_enem', 'free')}
                    >
                        <div className="hp-seg-card-icon">
                            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                                <rect width="40" height="40" rx="12" fill="rgba(245,158,11,0.15)" />
                                <path d="M20 10l2.9 5.9 6.5.94-4.7 4.58 1.1 6.44L20 24.9l-5.8 3.05 1.1-6.44-4.7-4.58 6.5-.94L20 10z" fill="#f59e0b" />
                            </svg>
                        </div>
                        <div className="hp-seg-card-text">
                            <h2 className="hp-seg-card-title">Foco no ENEM</h2>
                            <p className="hp-seg-card-desc">Aumente sua nota e chegue mais preparado.</p>
                        </div>
                        <div className="hp-seg-card-arrow">
                            <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                                <path d="M5 11h12M12 6l5 5-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </div>
                    </Link>

                    <Link
                        to="/register?plan=free&objetivo=concursos"
                        className="hp-seg-card hp-seg-card--concursos"
                        id="seg-concursos-card"
                        onClick={() => trackCTA('seg_concursos', 'free')}
                    >
                        <div className="hp-seg-card-icon" id="concursos">
                            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                                <rect width="40" height="40" rx="12" fill="rgba(99,179,237,0.15)" />
                                <path d="M20 12c-4.4 0-8 2.7-8 6 0 2.1 1.4 3.9 3.5 5L14 27h12l-1.5-4C26.6 21.9 28 20.1 28 18c0-3.3-3.6-6-8-6z" fill="#63b3ed" />
                                <path d="M17 18h6M17 21h4" stroke="#1a4db8" strokeWidth="1.5" strokeLinecap="round" />
                            </svg>
                        </div>
                        <div className="hp-seg-card-text">
                            <h2 className="hp-seg-card-title">Foco em Concursos</h2>
                            <p className="hp-seg-card-desc">Estude com estratégia para buscar sua aprovação.</p>
                        </div>
                        <div className="hp-seg-card-arrow">
                            <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                                <path d="M5 11h12M12 6l5 5-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </div>
                    </Link>
                </div>
            </section>

            {/* ═══════════════════════════════════════════
                3 CARDS DE BENEFÍCIOS
            ═══════════════════════════════════════════ */}
            <section className="hp-benefits" id="features" aria-labelledby="benefits-title">
                <div className="hp-benefits-inner">
                    <p className="hp-benefits-eyebrow">Como funciona</p>
                    <h2 className="hp-benefits-title" id="benefits-title">
                        Tudo que você precisa para evoluir de verdade
                    </h2>

                    <div className="hp-benefits-grid">
                        <article className="hp-benefit-card" id="benefit-redacao">
                            <div className="hp-benefit-icon-wrap hp-benefit-icon--redacao">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                                    <path d="M4 24V8l10-4 10 4v16" stroke="#f59e0b" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M9 24v-7h10v7" stroke="#f59e0b" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M10 13h8M10 10h5" stroke="#f59e0b" strokeWidth="1.5" strokeLinecap="round" />
                                    <circle cx="22" cy="8" r="5" fill="#22c55e" />
                                    <path d="M19.5 8l1.5 1.5 2.5-2.5" stroke="#fff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </div>
                            <h3 className="hp-benefit-card-title">Redação Corrigida no Estilo ENEM</h3>
                            <p className="hp-benefit-card-desc">
                                Receba avaliação por competências e entenda exatamente onde melhorar.
                            </p>
                            <div className="hp-benefit-card-tag">Competências C1–C5</div>
                        </article>

                        <article className="hp-benefit-card hp-benefit-card--featured" id="benefit-simulados">
                            <div className="hp-benefit-icon-wrap hp-benefit-icon--simulados">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                                    <rect x="3" y="5" width="22" height="16" rx="2" stroke="#63b3ed" strokeWidth="1.8" />
                                    <path d="M8 10h5M8 14h8M8 18h4" stroke="#63b3ed" strokeWidth="1.5" strokeLinecap="round" />
                                    <circle cx="22" cy="9" r="5" fill="#22c55e" />
                                    <path d="M19.5 9l1.5 1.5 2.5-2.5" stroke="#fff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </div>
                            <div className="hp-benefit-popular-badge">Mais usado</div>
                            <h3 className="hp-benefit-card-title">Simulados e Questões Direcionadas</h3>
                            <p className="hp-benefit-card-desc">
                                Treine com foco no que mais cai e evolua com mais precisão.
                            </p>
                            <div className="hp-benefit-card-tag">+200 mil questões</div>
                        </article>

                        <article className="hp-benefit-card" id="benefit-plano">
                            <div className="hp-benefit-icon-wrap hp-benefit-icon--plano">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                                    <rect x="4" y="4" width="20" height="20" rx="3" stroke="#a78bfa" strokeWidth="1.8" />
                                    <path d="M9 4v4M19 4v4M4 12h20" stroke="#a78bfa" strokeWidth="1.5" strokeLinecap="round" />
                                    <path d="M9 17l2 2 5-5" stroke="#22c55e" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </div>
                            <h3 className="hp-benefit-card-title">Plano de Estudos Personalizado</h3>
                            <p className="hp-benefit-card-desc">
                                Monte uma rotina de estudos mais inteligente para o seu objetivo.
                            </p>
                            <div className="hp-benefit-card-tag">Cronograma adaptativo</div>
                        </article>
                    </div>
                </div>
            </section>

            {/* ═══════════════════════════════════════════════════════════════
                SEÇÕES ORIGINAIS RESTAURADAS — a partir daqui, tudo que
                existia antes da alteração indevida é mantido integralmente.
            ═══════════════════════════════════════════════════════════════ */}
            <div className="lp-wrapper">


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

                {/* CTA EMOCIONAL — PREMIUM */}
                <section className="lp-cta-emocional" id="cta-emocional" aria-labelledby="cta-title">
                    {/* Background grain overlay */}
                    <div className="lp-cta-grain" aria-hidden="true" />
                    {/* Glow blobs */}
                    <div className="lp-cta-glow lp-cta-glow--1" aria-hidden="true" />
                    <div className="lp-cta-glow lp-cta-glow--2" aria-hidden="true" />

                    <div className="lp-cta-emocional-inner">
                        {/* ── LEFT: copy ── */}
                        <div className="lp-cta-copy">
                            <span className="lp-cta-badge">✦ Estude com inteligência</span>
                            <h2 className="lp-cta-emocional-title" id="cta-title">
                                Você não precisa<br />estudar mais perdido.
                            </h2>
                            <p className="lp-cta-emocional-sub">
                                Precisa de direção, prática e correção inteligente.
                            </p>
                            <p className="lp-cta-emocional-body">
                                Pare de tentar no escuro. Descubra exatamente onde você erra e evolua com estratégia real.
                            </p>
                            <div className="lp-cta-actions">
                                <Link
                                    to="/register?plan=free"
                                    className="lp-cta-emocional-btn"
                                    id="cta-emocional-btn"
                                    onClick={() => trackCTA('cta_emocional', 'free')}
                                >
                                    Começar grátis agora
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4 9h10M10 5l4 4-4 4" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                                </Link>
                                <p className="lp-cta-emocional-note">
                                    Sem cartão de crédito&nbsp;•&nbsp;Comece em menos de 1 minuto
                                </p>
                            </div>
                        </div>

                        {/* ── RIGHT: dashboard mockup ── */}
                        <div className="lp-cta-mockup" aria-hidden="true">
                            {/* Floating score badge */}
                            <div className="lp-cta-float lp-cta-float--score">
                                <span className="lp-cta-float-num">920</span>
                                <span className="lp-cta-float-lbl">Nota Redação</span>
                            </div>
                            {/* Floating improvement badge */}
                            <div className="lp-cta-float lp-cta-float--improve">
                                <span className="lp-cta-float-icon">↑</span>
                                <span className="lp-cta-float-num">+38%</span>
                                <span className="lp-cta-float-lbl">em 30 dias</span>
                            </div>

                            <div className="lp-cta-dashboard">
                                {/* Dashboard header */}
                                <div className="lp-cta-db-header">
                                    <span className="lp-cta-db-dot lp-cta-db-dot--red" />
                                    <span className="lp-cta-db-dot lp-cta-db-dot--yellow" />
                                    <span className="lp-cta-db-dot lp-cta-db-dot--green" />
                                    <span className="lp-cta-db-title">Painel de Progresso</span>
                                </div>

                                {/* Stats row */}
                                <div className="lp-cta-db-stats">
                                    <div className="lp-cta-db-stat">
                                        <span className="lp-cta-db-stat-num">87%</span>
                                        <span className="lp-cta-db-stat-lbl">Acertos hoje</span>
                                    </div>
                                    <div className="lp-cta-db-stat">
                                        <span className="lp-cta-db-stat-num">12</span>
                                        <span className="lp-cta-db-stat-lbl">Questões</span>
                                    </div>
                                    <div className="lp-cta-db-stat">
                                        <span className="lp-cta-db-stat-num">3</span>
                                        <span className="lp-cta-db-stat-lbl">Redações</span>
                                    </div>
                                </div>

                                {/* SVG line chart — evolução */}
                                <div className="lp-cta-db-chart-wrap">
                                    <span className="lp-cta-db-chart-label">Evolução — últimas 4 semanas</span>
                                    <svg className="lp-cta-db-chart" viewBox="0 0 260 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        {/* Grid lines */}
                                        <line x1="0" y1="60" x2="260" y2="60" stroke="rgba(255,255,255,0.07)" strokeWidth="1"/>
                                        <line x1="0" y1="40" x2="260" y2="40" stroke="rgba(255,255,255,0.07)" strokeWidth="1"/>
                                        <line x1="0" y1="20" x2="260" y2="20" stroke="rgba(255,255,255,0.07)" strokeWidth="1"/>
                                        {/* Area fill */}
                                        <defs>
                                            <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.25"/>
                                                <stop offset="100%" stopColor="#f59e0b" stopOpacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M0 65 L40 55 L80 48 L120 38 L160 28 L200 18 L260 8 L260 80 L0 80 Z" fill="url(#chartGrad)"/>
                                        {/* Line */}
                                        <path className="lp-cta-chart-line" d="M0 65 L40 55 L80 48 L120 38 L160 28 L200 18 L260 8" stroke="#f59e0b" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"/>
                                        {/* Dots */}
                                        <circle cx="0"   cy="65" r="3.5" fill="#f59e0b"/>
                                        <circle cx="80"  cy="48" r="3.5" fill="#f59e0b"/>
                                        <circle cx="160" cy="28" r="3.5" fill="#f59e0b"/>
                                        <circle cx="260" cy="8"  r="4.5" fill="#f59e0b" className="lp-cta-chart-dot-pulse"/>
                                    </svg>
                                </div>

                                {/* Competências C1–C5 */}
                                <div className="lp-cta-db-comp">
                                    <span className="lp-cta-db-comp-title">Redação — Competências</span>
                                    {[
                                        { label: 'C1 — Língua Portuguesa',     pct: 88, color: '#22c55e' },
                                        { label: 'C2 — Tema e Repertório',      pct: 76, color: '#3b82f6' },
                                        { label: 'C3 — Argumentação',           pct: 92, color: '#f59e0b' },
                                        { label: 'C4 — Coesão',                 pct: 80, color: '#a78bfa' },
                                        { label: 'C5 — Proposta de Intervenção', pct: 84, color: '#34d399' },
                                    ].map(({ label, pct, color }) => (
                                        <div key={label} className="lp-cta-db-comp-row">
                                            <span className="lp-cta-db-comp-lbl">{label}</span>
                                            <div className="lp-cta-db-comp-track">
                                                <div
                                                    className="lp-cta-db-comp-bar"
                                                    style={{ width: `${pct}%`, background: color }}
                                                />
                                            </div>
                                            <span className="lp-cta-db-comp-pct" style={{ color }}>{pct}%</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
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

            </div>{/* fim lp-wrapper */}
        </div>
    );
}