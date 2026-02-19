@extends('layouts.checkout')

@section('content')
@php
    // Find initial index for Plus Annual if available, otherwise find Plus
    $initialPlan = $paidPlans->firstWhere('slug', 'plus-annual') ?? $paidPlans->firstWhere('slug', 'plus') ?? $plan;
@endphp

<div x-data="{ 
    allPlans: {{ $paidPlans->toJson() }},
    currentInterval: 'year',
    currentIndex: 0,
    
    init() {
        // Set initial index to the plan that matches current selection logic
        const initialSlug = '{{ $initialPlan->slug }}';
        const filtered = this.filteredPlans;
        const index = filtered.findIndex(p => p.slug === initialSlug || p.slug === initialSlug.replace('-annual', ''));
        this.currentIndex = index !== -1 ? index : 0;
    },

    get filteredPlans() {
        // Find distinct base plans (by comparing monthly_price/annual_price mapping if we had it, but let's use slug logic)
        // Actually, $paidPlans contains both monthly and yearly versions usually.
        // We want to show the monthly version if currentInterval is 'month' and yearly if 'year'.
        return this.allPlans.filter(p => {
            if (this.currentInterval === 'year') {
                return p.interval === 'yearly';
            }
            return p.interval === 'monthly';
        });
    },

    get activePlan() { 
        const filtered = this.filteredPlans;
        if (this.currentIndex >= filtered.length) this.currentIndex = 0;
        return filtered[this.currentIndex];
    },

    get checkoutUrl() { 
        return '{{ route('plans.checkout', ':id') }}'.replace(':id', this.activePlan.id) 
    },
    
    next() {
        const len = this.filteredPlans.length;
        this.currentIndex = (this.currentIndex + 1) % len;
    },
    prev() {
        const len = this.filteredPlans.length;
        this.currentIndex = (this.currentIndex - 1 + len) % len;
    },
    
    setInterval(val) {
        this.currentInterval = val;
    },

    labels: {
        'basic_correction': 'Correção Básica',
        'detailed_correction': 'Correções Detalhadas',
        'improvement_points': 'Pontos de Melhoria',
        'essay_correction': 'Correção de Redação',
        'advanced_correction': 'Correção Premium',
        'personalized_study_plan': 'Plano de Estudos Personalizado',
        'error_explanation' : 'Explicação de Erros',
        'unlimited_simulations': 'Simulados Ilimitados',
        'essay_examples': 'Exemplos de Redação',
        'performance_analysis': 'Análise de Desempenho',
        'time_analysis': 'Análise de Tempo',
    },
    formatFeature(feature) {
        return this.labels[feature] || feature.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },
    formatPrice(price) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(price);
    },
    getDisplayPrice(plan) {
        if (this.currentInterval === 'year') {
            return this.formatPrice(plan.price / 12);
        }
        return this.formatPrice(plan.price);
    }
}" class="max-w-2xl mx-auto">

    <div class="text-center mb-8">
        <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">
            Escolha Inteligente, {{ Auth::user()->name }}!
        </h2>
        <p class="text-lg text-slate-600 dark:text-slate-400">
            Você está a um passo de transformar sua preparação.
        </p>

        <!-- Interval Toggle -->
        <div class="mt-8 flex justify-center">
            <div class="relative bg-slate-200 dark:bg-slate-800 p-1 rounded-2xl flex items-center shadow-inner">
                <button @click="setInterval('month')" 
                        :class="currentInterval === 'month' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="px-6 py-2 rounded-xl text-sm font-bold transition-all duration-200 focus:outline-none">
                    Mensal
                </button>
                <button @click="setInterval('year')" 
                        :class="currentInterval === 'year' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="px-6 py-2 rounded-xl text-sm font-bold transition-all duration-200 focus:outline-none relative">
                    Anual
                    <template x-if="activePlan && activePlan.discount_percentage > 0">
                        <span class="absolute -top-3 -right-2 bg-green-500 text-white text-[10px] px-2 py-0.5 rounded-full shadow-lg transform rotate-12" x-text="'-' + activePlan.discount_percentage + '% OFF'">
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    <!-- Carousel Container -->
    <div class="relative bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden transition-all duration-300">
        <!-- Navigation Arrows -->
        <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 z-10 p-2 bg-slate-100 dark:bg-slate-800 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900 transition shadow-sm border border-slate-200 dark:border-slate-700">
            <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 z-10 p-2 bg-slate-100 dark:bg-slate-800 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900 transition shadow-sm border border-slate-200 dark:border-slate-700">
            <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Content -->
        <div class="p-8 sm:p-12">
            <template x-if="activePlan">
                <div class="text-center animate-fade-in" :key="activePlan.id + currentInterval">
                    <div class="inline-block px-4 py-1.5 mb-4 rounded-full text-xs font-bold uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300" x-text="activePlan.name"></div>
                    
                    <div class="flex flex-col items-center justify-center mb-8">
                        <div class="flex items-baseline">
                            <span class="text-6xl font-black text-slate-900 dark:text-white">
                                R$&nbsp;<span x-text="currentInterval === 'year' ? (activePlan.price / 12).toFixed(2).replace('.', ',') : activePlan.price.replace('.', ',')"></span>
                            </span>
                            <span class="text-slate-500 dark:text-slate-400 ml-2 text-xl">/mês</span>
                        </div>
                        <template x-if="currentInterval === 'year'">
                            <div class="mt-2 text-green-600 dark:text-green-400 font-semibold flex items-center gap-1 text-sm bg-green-50 dark:bg-green-950/30 px-3 py-1 rounded-full">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1a1 1 0 112 0v1a1 1 0 11-2 0zM14.243 14.243a1 1 0 111.414 1.414l-.707.707a1 1 0 11-1.414-1.414l.707-.707zM16 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z" />
                                </svg>
                                Faturado anualmente (<span x-text="formatPrice(activePlan.price)"></span>)
                            </div>
                        </template>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-6 mb-8 border border-slate-100 dark:border-slate-700">
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-widest text-xs mb-6 text-left border-b border-slate-200 dark:border-slate-700 pb-2">O que você terá acesso:</h3>
                        
                        <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-left">
                            <template x-for="feature in activePlan.features" :key="feature">
                                <li class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-slate-600 dark:text-slate-400 text-sm font-medium" x-text="formatFeature(feature)"></span>
                                </li>
                            </template>
                            
                            <li class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-slate-600 dark:text-slate-400 text-sm font-medium" 
                                      x-text="activePlan.simulations_limit == 0 ? 'Simulados Ilimitados' : activePlan.simulations_limit + ' Simulados/mês'"></span>
                            </li>

                            <template x-if="activePlan.essays_limit > 0">
                                <li class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-slate-600 dark:text-slate-400 text-sm font-medium" 
                                          x-text="activePlan.essays_limit + ' Redações/mês'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-4">
                        <a :href="checkoutUrl" 
                           class="flex justify-center items-center px-8 py-5 bg-blue-600 text-white font-black rounded-2xl text-xl shadow-lg shadow-blue-500/20 hover:bg-blue-700 hover:shadow-blue-600/30 transition transform hover:-translate-y-1">
                            Confirmar Assinatura <span class="ml-2" x-text="currentInterval === 'year' ? 'Anual ⚡' : 'Mensal ⚡'"></span>
                        </a>
                        
                        <a href="{{ route('checkout.skip') }}" 
                           class="text-sm font-bold text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition uppercase tracking-widest mt-2 py-2">
                            Aproveitar o plano gratuito por enquanto &rarr;
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-8">
        Garantia total de satisfação. Cancele sua assinatura com um clique a qualquer momento.
    </p>
</div>


<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.4s ease-out forwards;
    }
</style>
@endsection


