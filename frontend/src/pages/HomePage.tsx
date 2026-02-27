import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useConfigStore } from '../stores/configStore';
import '../styles/landing-page.css';

export default function HomePage() {
    const { appName } = useConfigStore();
    const [faqOpen, setFaqOpen] = useState<number | null>(null);
    const [periodo, setPeriodo] = useState<'mensal' | 'anual'>('mensal');

    const faqs = [
        { q: 'A plataforma é totalmente online?', a: 'Sim. O AprenderAI funciona 100% online. Você pode acessar de qualquer lugar, pelo computador ou celular, sem necessidade de instalação.' },
        { q: 'O AprenderAI serve para ENEM e concursos?', a: 'Sim. A plataforma foi desenvolvida tanto para preparação para o ENEM quanto para concursos públicos, com simulados, questões e plano de estudos personalizados.' },
        { q: 'Como funciona a correção por IA?', a: 'Nossa inteligência analisa suas respostas e redações, identifica padrões de erro e fornece explicações detalhadas para acelerar sua evolução.' },
        { q: 'Posso testar gratuitamente antes de assinar?', a: 'Sim. O plano gratuito permite que você conheça a plataforma e resolva provas antes de optar por um plano pago.' },
        { q: 'Os simulados seguem o padrão oficial das provas?', a: 'Sim. Os simulados são estruturados para replicar o formato real do ENEM e de concursos, incluindo controle de tempo.' },
        { q: 'Como funciona o plano anual com desconto?', a: 'Ao optar pelo plano anual, você recebe 20% de desconto em relação ao valor mensal, mantendo todos os benefícios do plano escolhido.' },
        { q: 'A plataforma acompanha meu desempenho?', a: 'Sim. Você pode acompanhar sua evolução por disciplina, identificar pontos fracos e visualizar seu progresso ao longo do tempo.' },
        { q: 'Posso cancelar quando quiser?', a: 'Sim. Você pode gerenciar sua assinatura conforme as regras do plano contratado.' }
    ];

    const toggleFaq = (index: number) => {
        setFaqOpen(faqOpen === index ? null : index);
    };

    return (
        <div className="lp-wrapper">
            {/* NAVBAR */}
            <nav className="lp-nav">
                <div className="lp-nav-inner">
                    <Link to="/" className="lp-logo">
                        {appName.replace('AI', '')}<span>AI</span>
                    </Link>
                    <ul className="lp-nav-links">
                        <li><a href="#depoimentos">Depoimentos</a></li>
                        <li><Link to="/uso-justo">Política de Uso</Link></li>
                        <li><Link to="/privacidade">Privacidade</Link></li>
                        <li><a href="#plans" className="lp-btn-cta">Assine Agora</a></li>
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
                            <Link to="/register?plan=free" className="lp-btn-primary">
                                Comece Gratuitamente
                            </Link>
                            <Link to="/login" className="lp-btn-outline">
                                Já tenho conta
                            </Link>
                        </div>
                    </div>

                    <div className="lp-hero-illus">
                        <img src="/hero.png" alt="Painel Inteligente" className="w-full h-auto rounded-3xl shadow-2xl" />
                    </div>
                </div>
            </section>

            {/* METRICS */}
            <section className="lp-metrics">
                <div className="lp-metrics-inner">
                    <div className="lp-metrics-divider">
                        <div className="lp-metric-num">200k+</div>
                        <div className="lp-metric-label">QUESTÕES <strong>PARA PRATICAR</strong></div>
                    </div>
                    <div className="lp-metrics-divider">
                        <div className="lp-metric-num">1.200+</div>
                        <div className="lp-metric-label">REDAÇÕES <strong>NOTA 900+</strong></div>
                    </div>
                    <div>
                        <div className="lp-metric-num">35%</div>
                        <div className="lp-metric-label">MAIS ACERTOS EM <strong>30 DIAS</strong></div>
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

                    <div className="flex justify-center mb-10">
                        <div className="flex items-center gap-4 bg-slate-100 rounded-full px-4 py-2">
                            <span
                                onClick={() => setPeriodo('mensal')}
                                className={`text-sm font-bold cursor-pointer ${periodo === 'mensal' ? 'text-blue-900' : 'text-slate-400'}`}
                            >
                                Mensal
                            </span>
                            <div
                                onClick={() => setPeriodo(periodo === 'mensal' ? 'anual' : 'mensal')}
                                className="w-12 h-6 bg-slate-300 rounded-full relative cursor-pointer"
                            >
                                <div className={`absolute top-1 left-1 w-4 h-4 bg-white rounded-full transition-transform ${periodo === 'anual' ? 'translate-x-6 bg-blue-600' : ''}`}></div>
                            </div>
                            <span
                                onClick={() => setPeriodo('anual')}
                                className={`text-sm font-bold cursor-pointer flex items-center gap-2 ${periodo === 'anual' ? 'text-blue-900' : 'text-slate-400'}`}
                            >
                                Anual <span className="bg-green-100 text-green-700 text-[10px] px-2 py-0.5 rounded-full">-20% OFF</span>
                            </span>
                        </div>
                    </div>

                    <div className="lp-plans-grid">
                        <div className="lp-plan-free">
                            <div className="text-blue-900 font-bold text-2xl mb-1">Gratuito</div>
                            <div className="lp-plan-tagline-free mb-4">Para começar e testar a plataforma.</div>
                            <div className="mb-4">
                                <span className="lp-plan-price-free">R$ 0</span>
                                <span className="text-slate-500 text-sm">/mês</span>
                            </div>
                            <p className="text-slate-400 text-[11px] mb-6">Sempre gratuito, sem cartão de crédito.</p>
                            <ul className="lp-plan-list-free space-y-3 mb-8">
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-500">✓</span> 5 provas/mês</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-500">✓</span> Correção básica</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-500">✓</span> Estatísticas simples</li>
                            </ul>
                            <Link to="/register?plan=free" className="lp-plan-btn-free">Começar Agora</Link>
                        </div>

                        <div className="lp-plan-basic">
                            <div className="text-white font-bold text-2xl mb-1">Básico</div>
                            <div className="lp-plan-tagline-paid mb-4">Plano ideal para quem busca aprovação completa e redação guiada.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-paid">R$ {periodo === 'anual' ? '20,00' : '25,00'}</span>
                                <span className="text-blue-200 text-sm">/mês</span>
                            </div>
                            <p className="text-blue-100 text-[11px] mb-6">
                                {periodo === 'anual' ? 'R$ 240,00/ano — economize R$ 60,00' : 'Ou R$ 240,00 no plano anual (20% OFF)'}
                            </p>
                            <ul className="space-y-3 mb-8">
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> 10 provas/mês</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> Correção detalhada</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> 4 redações/mês</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> Radar de concursos</li>
                            </ul>
                            <Link to={`/register?plan=${periodo === 'anual' ? 'basic-annual' : 'basic'}`} className="lp-plan-btn-basic">Assinar Agora</Link>
                        </div>

                        <div className="lp-plan-plus">
                            <div className="bg-yellow-500 text-blue-900 text-[10px] font-black uppercase px-3 py-1 rounded-full inline-block mb-4">⭐ Mais Popular</div>
                            <div className="text-white font-bold text-2xl mb-1">Plus</div>
                            <div className="lp-plan-tagline-paid mb-4">Para acelerar no máximo com estratégia e simulados ilimitados.</div>
                            <div className="mb-2">
                                <span className="lp-plan-price-paid">R$ {periodo === 'anual' ? '40,00' : '49,90'}</span>
                                <span className="text-blue-200 text-sm">/mês</span>
                            </div>
                            <p className="text-blue-100 text-[11px] mb-6">
                                {periodo === 'anual' ? 'R$ 480,00/ano — economize R$ 118,80' : 'Ou R$ 480,00 no plano anual (20% OFF)'}
                            </p>
                            <ul className="space-y-3 mb-8">
                                <li className="flex items-center gap-2 text-sm font-bold text-white"><span className="text-green-300">✓</span> Simulados ilimitados</li>
                                <li className="flex items-center gap-2 text-sm font-bold text-white"><span className="text-green-300">✓</span> 15 redações/mês</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> Estatísticas completas</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> Análise estratégica</li>
                                <li className="flex items-center gap-2 text-sm"><span className="text-green-300">✓</span> Radar de concursos</li>
                            </ul>
                            <Link to={`/register?plan=${periodo === 'anual' ? 'plus-annual' : 'plus'}`} className="lp-plan-btn-plus">Assinar Agora</Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* TESTIMONIALS */}
            <section className="lp-testimonials" id="depoimentos">
                <div className="max-w-[1100px] mx-auto text-center">
                    <h2 className="text-3xl font-black mb-2 text-white">Histórias de Sucesso</h2>
                    <p className="text-blue-300 mb-12">Quem estudou com a gente, passou <strong>de verdade.</strong></p>
                    <div className="lp-test-grid">
                        <div className="lp-test-card text-left">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=12" alt="Lucas" />
                                <div>
                                    <div className="font-bold text-white text-sm">Lucas Andrade</div>
                                    <div className="text-slate-400 text-[10px] uppercase font-bold">ENEM</div>
                                    <div className="text-yellow-500 text-xs">★★★★★</div>
                                </div>
                            </div>
                            <p className="text-blue-100 text-xs italic leading-relaxed">"Eu sempre estudava muito, mas não sabia exatamente onde estava errando. Quando comecei a usar as análises da plataforma, consegui organizar melhor minha revisão e minha nota subiu de forma consistente."</p>
                        </div>
                        <div className="lp-test-card text-left">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=32" alt="Mary" />
                                <div>
                                    <div className="font-bold text-white text-sm">Mary S.</div>
                                    <div className="text-slate-400 text-[10px] uppercase font-bold">Concurso Administrativo</div>
                                    <div className="text-yellow-500 text-xs">★★★★★</div>
                                </div>
                            </div>
                            <p className="text-blue-100 text-xs italic leading-relaxed">"O que mais me ajudou foi conseguir visualizar meu desempenho por disciplina. Antes eu estudava no escuro, agora sei exatamente onde preciso melhorar."</p>
                        </div>
                        <div className="lp-test-card text-left">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=45" alt="Feeh" />
                                <div>
                                    <div className="font-bold text-white text-sm">Feeh Costa</div>
                                    <div className="text-slate-400 text-[10px] uppercase font-bold">Redação</div>
                                    <div className="text-yellow-500 text-xs">★★★★★</div>
                                </div>
                            </div>
                            <p className="text-blue-100 text-xs italic leading-relaxed">"Eu travava muito na redação. Depois que comecei a receber o feedback por competência, consegui entender meus erros estruturais e evoluir muito mais rápido."</p>
                        </div>
                        <div className="lp-test-card text-left">
                            <div className="lp-test-avatar">
                                <img src="https://i.pravatar.cc/96?img=8" alt="Rafael" />
                                <div>
                                    <div className="font-bold text-white text-sm">Rafael Mendes</div>
                                    <div className="text-slate-400 text-[10px] uppercase font-bold">Polícia Militar</div>
                                    <div className="text-yellow-500 text-xs">★★★★★</div>
                                </div>
                            </div>
                            <p className="text-blue-100 text-xs italic leading-relaxed">"O cronômetro e os simulados completos mudaram minha preparação. Hoje consigo administrar o tempo muito melhor na hora da prova."</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* VANTAGEM COMPETITIVA (Restored) */}
            <section className="lp-vantagem bg-white dark:bg-slate-950 py-24 px-6 md:px-12 border-y border-slate-100 dark:border-slate-900">
                <div className="max-w-[1100px] mx-auto">
                    <h2 className="text-center text-3xl md:text-4xl font-black text-blue-900 dark:text-white mb-3">Mais do que estudar. É criar vantagem competitiva.</h2>
                    <p className="text-center text-slate-500 dark:text-slate-400 italic mb-12">Quem estuda com método evolui. Quem estuda com estratégia passa.</p>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {[
                            { icon: '⚙️', title: 'Clareza Estratégica', desc: 'Não é sobre estudar mais. É sobre estudar certo. Descubra exatamente onde você perde pontos e transforme erros em progresso real.' },
                            { icon: '▶️', title: 'Segurança no Dia da Prova', desc: 'Simule sob pressão, cronometre seu desempenho e chegue no dia decisivo com confiança construída na prática.' },
                            { icon: '⏫', title: 'Evolução Baseada em Dados', desc: 'Nada de achismo. Acompanhe métricas claras, histórico de desempenho e crescimento contínuo em cada disciplina.' },
                            { icon: '✨', title: 'Inteligência que Trabalha por Você', desc: 'A IA analisa seus padrões, identifica fragilidades e ajusta sua preparação automaticamente.' },
                            { icon: '🎯', title: 'Alto Retorno Sobre o Seu Tempo', desc: 'Cada hora de estudo passa a ter direção. Menos desperdício. Mais resultado.' },
                            { icon: '☀️', title: 'Acesso Real, Sem Barreiras', desc: 'Preparação estruturada, acessível e disponível 24/7 — para quem decide levar a aprovação a sério.' }
                        ].map((item, id) => (
                            <div key={id} className="bg-white dark:bg-slate-900 p-8 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-100/50 dark:shadow-none hover:-translate-y-2 transition-transform group">
                                <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-900 flex items-center justify-center text-xl text-white shadow-lg shadow-blue-500/30 mb-6 group-hover:scale-110 transition-transform">
                                    {item.icon}
                                </div>
                                <h3 className="text-lg font-black text-blue-900 dark:text-white mb-2 leading-tight">{item.title}</h3>
                                <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section className="lp-faq" id="faq">
                <div className="lp-faq-inner">
                    <h2 className="lp-faq-title">Perguntas Frequentes</h2>
                    <p className="lp-faq-subtitle text-center mb-10">Tire suas dúvidas sobre a plataforma {appName}.</p>
                    <div className="space-y-1">
                        {faqs.map((faq, idx) => (
                            <div key={idx} className="lp-faq-item border-b border-slate-200">
                                <button
                                    onClick={() => toggleFaq(idx)}
                                    className="w-full flex justify-between items-center py-4 text-left"
                                >
                                    <span className="font-bold text-blue-900">{faq.q}</span>
                                    <span className={`text-xl transition-transform ${faqOpen === idx ? 'rotate-45 text-yellow-600' : 'text-blue-900'}`}>+</span>
                                </button>
                                <div className={`overflow-hidden transition-all duration-300 ${faqOpen === idx ? 'max-h-40 pb-4 opacity-100' : 'max-h-0 opacity-0'}`}>
                                    <p className="text-slate-600 text-sm leading-relaxed">{faq.a}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* FOOTER */}
            <footer className="lp-footer">
                <div className="lp-footer-inner">
                    <div className="lp-footer-top">
                        <div>
                            <span className="lp-footer-logo">{appName.replace('AI', '')}<span>AI</span></span>
                            <p className="text-slate-500 text-xs leading-relaxed max-w-xs mt-4">
                                A plataforma que usa tecnologia para democratizar o acesso à aprovação. Experiência premium focada em performance.
                            </p>
                        </div>
                        <div className="space-y-4">
                            <h4 className="text-white text-xs font-black uppercase tracking-widest">Produto</h4>
                            <ul className="space-y-2 text-slate-500 text-sm">
                                <li><a href="#features">Recursos</a></li>
                                <li><a href="#plans">Planos</a></li>
                                <li><a href="#depoimentos">Depoimentos</a></li>
                            </ul>
                        </div>
                        <div className="space-y-4">
                            <h4 className="text-white text-xs font-black uppercase tracking-widest">Legal</h4>
                            <ul className="space-y-2 text-slate-500 text-sm">
                                <li><Link to="/privacidade">Privacidade</Link></li>
                                <li><Link to="/uso-justo">Uso Justo</Link></li>
                                <li><a href="/contato">Contato</a></li>
                            </ul>
                        </div>
                    </div>
                    <div className="pt-8 border-t border-slate-800 text-center text-slate-600 text-[10px]">
                        &copy; 2026 {appName}. Todos os direitos reservados.
                    </div>
                </div>
            </footer>
        </div>
    );
}
