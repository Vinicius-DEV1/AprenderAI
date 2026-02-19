@extends('layouts.checkout')

@section('content')
<div x-data="{ 
    plans: {{ $paidPlans->toJson() }},
    currentIndex: {{ $paidPlans->search(fn($p) => $p->id === $plan->id) }},
    get activePlan() { return this.plans[this.currentIndex] },
    get checkoutUrl() { return '{{ route('plans.checkout', ':id') }}'.replace(':id', this.activePlan.id) },
    
    next() {
        this.currentIndex = (this.currentIndex + 1) % this.plans.length;
    },
    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.plans.length) % this.plans.length;
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
    }
}" class="max-w-2xl mx-auto">

    <div class="text-center mb-8">
        <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">
            Escolha Inteligente, {{ Auth::user()->name }}!
        </h2>
        <p class="text-lg text-slate-600 dark:text-slate-400">
            Você está a um passo de transformar sua preparação.
        </p>
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
                <div class="text-center animate-fade-in" :key="activePlan.id">
                    <div class="inline-block px-4 py-1.5 mb-4 rounded-full text-xs font-bold uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300" x-text="activePlan.name"></div>
                    
                    <div class="flex items-baseline justify-center mb-8">
                        <span class="text-6xl font-black text-slate-900 dark:text-white" x-text="formatPrice(activePlan.price)"></span>
                        <span class="text-slate-500 dark:text-slate-400 ml-2 text-xl">/mês</span>
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
                            Confirmar Assinatura ⚡
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


