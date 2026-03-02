import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
import Accordion from '../../components/Accordion';

// Interfaces based on expected data

interface Plan {
    id: number;
    slug: string;
    name: string;
    price: number;
    interval: string;
    features: string[]; // From blade: $basicM->features
}

export default function OnboardingWelcome() {
    const [cycle, setCycle] = useState<'monthly' | 'yearly'>('yearly');

    // Fetch user and plans
    const { data } = useQuery({
        queryKey: ['onboarding-welcome'],
        queryFn: async () => {
            const res = await api.get('/api/v1/onboarding/welcome');
            return res.data;
        }
    });

    // Handle loading state or missing data gracefully
    if (!data) return <div className="min-h-screen flex items-center justify-center">Carregando...</div>;

    const { user, plans = [] } = data;
    const firstName = user?.name ? user.name.split(' ')[0] : 'Estudante';

    // Organize plans exactly as in blade
    const basicM = plans.find((p: Plan) => p.slug === 'basic');
    const basicY = plans.find((p: Plan) => p.slug === 'basic-annual');
    const plusM = plans.find((p: Plan) => p.slug === 'plus');
    const plusY = plans.find((p: Plan) => p.slug === 'plus-annual');

    const formatPrice = (price?: number) => {
        if (price === undefined || price === null) return '0';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0 }).format(price);
    };

    return (
        <div className="relative overflow-hidden flex flex-col items-center justify-center min-h-screen py-8 lg:py-16 bg-white">
            <div className="w-full max-w-4xl px-4">
                {/* Header section */}
                <div className="text-center mb-10">
                    <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-slate-800 text-sm font-semibold mb-8 shadow-sm">
                        <span className="flex h-2.5 w-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                        Sua jornada para aprovação começa aqui
                    </div>
                    <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-6 text-slate-900">
                        Olá, <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">{firstName}</span>! 🚀
                    </h1>
                    <p className="text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">
                        Você acaba de entrar na plataforma mais inteligente de estudos do Brasil. Turbine sua preparação com nossas ferramentas exclusivas guiadas por Inteligência Artificial.
                    </p>
                </div>

                {/* Pricing Toggle & Cards Wrap */}
                <div className="mb-14">
                    {/* Toggle Switch */}
                    <div className="flex justify-center mb-10">
                        <div className="inline-flex bg-slate-100 rounded-full p-1 border border-slate-200">
                            <button
                                onClick={() => setCycle('monthly')}
                                className={`px-6 py-2 rounded-full text-sm font-bold transition-all duration-300 ${cycle === 'monthly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}>
                                Mensal
                            </button>
                            <button
                                onClick={() => setCycle('yearly')}
                                className={`px-6 py-2 rounded-full text-sm font-bold transition-all duration-300 flex items-center gap-2 ${cycle === 'yearly' ? 'bg-white text-purple-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}>
                                Anual
                                <span className={`px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-extrabold ${cycle === 'yearly' ? 'bg-purple-100 text-purple-700' : 'bg-slate-200 text-slate-600'}`}>
                                    Economize
                                </span>
                            </button>
                        </div>
                    </div>

                    {/* Cards limitados a 2 colunas */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">

                        {/* Card Básico */}
                        {(basicM || basicY) && (
                            <div className="relative p-8 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                                <div className="relative z-10 flex-1 flex flex-col">
                                    <h3 className="text-2xl font-black mb-2 text-slate-900 uppercase tracking-wide">Básico</h3>

                                    <div className="flex items-end gap-1 mb-8">
                                        <span className="text-5xl font-black tracking-tight text-slate-900">
                                            {cycle === 'monthly' ? (
                                                <span>R${formatPrice(basicM?.price)}</span>
                                            ) : (
                                                <span>R${formatPrice(basicY?.price)}</span>
                                            )}
                                        </span>
                                        <span className="text-slate-500 font-medium mb-1">
                                            {cycle === 'monthly' ? <span>/mês</span> : <span>/ano</span>}
                                        </span>
                                    </div>

                                    <div className="flex-1">
                                        <ul className="space-y-4 pt-2 mb-6">
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Correção detalhada (IA)</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">5 redações/mês</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Redação com Nota por Competência</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Radar de concursos</span>
                                            </li>
                                        </ul>

                                        <Accordion title="Ver Lista Completa" defaultExpanded={false} className="mb-10 w-full">
                                            <ul className="space-y-4 pt-2">
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">10 provas/mês</span>
                                                </li>
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">+200 mil questões</span>
                                                </li>
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-blue-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">Gabarito Comentado</span>
                                                </li>
                                            </ul>
                                        </Accordion>
                                    </div>

                                    <Link
                                        to={`/checkout/${cycle === 'monthly' ? basicM?.slug : basicY?.slug}`}
                                        className="block w-full py-4 rounded-xl bg-slate-100 text-center font-bold text-slate-900 hover:bg-slate-200 transition-all duration-300 text-lg border border-transparent">
                                        Assinar o Básico
                                    </Link>
                                </div>
                            </div>
                        )}

                        {/* Card Plus (Destaque) */}
                        {(plusM || plusY) && (
                            <div className="relative p-8 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">

                                <div className="absolute top-0 right-0 py-1 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold uppercase rounded-bl-lg tracking-wider z-20">
                                    Recomendado
                                </div>

                                <div className="relative z-10 flex-1 flex flex-col">
                                    <h3 className="text-2xl font-black mb-2 text-slate-900 uppercase tracking-wide">Plus</h3>

                                    <div className="flex items-end gap-1 mb-8">
                                        <span className="text-5xl font-black tracking-tight text-slate-900">
                                            {cycle === 'monthly' ? (
                                                <span>R${formatPrice(plusM?.price)}</span>
                                            ) : (
                                                <span>R${formatPrice(plusY?.price)}</span>
                                            )}
                                        </span>
                                        <span className="text-slate-500 font-medium mb-1">
                                            {cycle === 'monthly' ? <span>/mês</span> : <span>/ano</span>}
                                        </span>
                                    </div>

                                    <div className="flex-1">
                                        <ul className="space-y-4 pt-2 mb-6">
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Análise estratégica</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Cronograma de Estudos personalizado</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">Simulados ilimitados</span>
                                            </li>
                                            <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                    <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <span className="leading-tight">15 redações/mês</span>
                                            </li>
                                        </ul>

                                        <Accordion title="Ver Todas as Vantagens" defaultExpanded={false} className="mb-10 w-full shadow-sm border-blue-200">
                                            <ul className="space-y-4 pt-2">
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">Redação com Nota por Competência</span>
                                                </li>
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">Estatísticas completas</span>
                                                </li>
                                                <li className="flex items-start gap-3 text-[15px] font-medium text-slate-700">
                                                    <div className="rounded-full bg-indigo-100 p-1 flex-shrink-0 mt-0.5">
                                                        <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <span className="leading-tight">Radar de concursos</span>
                                                </li>
                                            </ul>
                                        </Accordion>
                                    </div>

                                    <Link
                                        to={`/checkout/${cycle === 'monthly' ? plusM?.slug : plusY?.slug}`}
                                        className="block w-full py-4 rounded-xl bg-slate-900 bg-gradient-to-r from-blue-600 to-indigo-600 text-center font-bold text-white hover:bg-slate-800 transition-all duration-300 text-lg">
                                        Assinar o Plus
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Secondary CTA */}
                <div className="text-center">
                    <Link to="/panel" className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-800 text-sm font-semibold transition-colors">
                        Continuar usando o Plano Gratuito por enquanto
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </Link>
                </div>
            </div>
        </div>
    );
}
