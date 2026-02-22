@extends('layouts.app')

@section('title', 'Bem-vindo | ' . config('app.name'))

@section('content')
<style>
    /* Oculta a sidebar e header mobile para focar na conversão limpa do onboarding */
    aside, header.lg\:hidden { display: none !important; }
    /* Limpa espaçamentos extras caso o layout adicione padding lateral pelo flex */
    .flex-1 { margin-left: 0 !important; width: 100% !important; }
</style>

<div class="relative overflow-hidden flex flex-col items-center justify-center py-8 lg:py-16">
    <!-- Background Decorators (Apenas Dark Mode) -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10 hidden dark:block">
        <div class="absolute top-[-5%] left-[-5%] w-96 h-96 bg-purple-600/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-5%] right-[-5%] w-96 h-96 bg-blue-600/10 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-4xl">
        <!-- Header section -->
        <div class="text-center mb-14">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-purple-400 text-sm font-semibold mb-8 shadow-sm">
                <span class="flex h-2.5 w-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                Sua jornada para aprovação começa aqui
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-6 text-slate-900 dark:text-white">
                Olá, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-purple-400 dark:to-blue-500">{{ explode(' ', $user->name)[0] }}</span>! 🚀
            </h1>
            <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Você acaba de entrar na plataforma mais inteligente de estudos do Brasil. Turbine sua preparação com nossas ferramentas exclusivas guiadas por Inteligência Artificial.
            </p>
        </div>

        <!-- Features Grid (Reduzido para 2 colunas e feature de Triagem removida) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16 max-w-3xl mx-auto">
            <!-- Feature 1 -->
            <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md dark:hover:border-blue-500/50 transition-all duration-300 group">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-blue-600/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-indigo-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-800 dark:text-white">Simulados Inéditos</h3>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed">Gere provas focadas nas suas maiores fraquezas para garantir uma evolução contínua.</p>
            </div>
            <!-- Feature 2 -->
            <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md dark:hover:border-purple-500/50 transition-all duration-300 group">
                <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-purple-600/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-blue-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-800 dark:text-white">Análise de Redação</h3>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed">Correções detalhadas por IA seguindo rigorosamente os critérios oficiais das bancas.</p>
            </div>
        </div>

        <!-- Pricing Section Title -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-3">Escolha seu plano e saia na frente</h2>
            <p class="text-slate-600 dark:text-slate-400">Desbloqueie todo o potencial da inteligência artificial.</p>
        </div>

        @php
            // Identificação dos Planos Básico e Plus (Mensal e Anual)
            $basicM = $plans->where('slug', 'basic')->first();
            $basicY = $plans->where('slug', 'basic-annual')->first();
            $plusM  = $plans->where('slug', 'plus')->first();
            $plusY  = $plans->where('slug', 'plus-annual')->first();
        @endphp

        <!-- Pricing Toggle & Cards Wrap -->
        <div x-data="{ cycle: 'monthly' }" class="mb-14">
            
            <!-- Toggle Switch -->
            <div class="flex justify-center mb-10">
                <div class="inline-flex bg-slate-100 dark:bg-slate-800 rounded-full p-1 border border-slate-200 dark:border-slate-700">
                    <button @click="cycle = 'monthly'" 
                            class="px-6 py-2 rounded-full text-sm font-bold transition-all duration-300"
                            :class="cycle === 'monthly' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'">
                        Mensal
                    </button>
                    <button @click="cycle = 'yearly'" 
                            class="px-6 py-2 rounded-full text-sm font-bold transition-all duration-300 flex items-center gap-2"
                            :class="cycle === 'yearly' ? 'bg-white dark:bg-slate-700 text-purple-600 dark:text-purple-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'">
                        Anual
                        <span class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-extrabold"
                              :class="cycle === 'yearly' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'">
                            Economize
                        </span>
                    </button>
                </div>
            </div>

            <!-- Cards limitados a 2 clunas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                
                <!-- Card Básico -->
                @if($basicM && $basicY)
                <div class="relative p-8 md:p-10 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg dark:shadow-none overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                    <div class="relative z-10 flex-1 flex flex-col">
                        <h3 class="text-2xl font-black mb-2 text-slate-900 dark:text-white uppercase tracking-wide">Básico</h3>
                        
                        <div class="flex items-end gap-1 mb-8">
                            <span class="text-5xl font-black tracking-tight text-slate-900 dark:text-white">
                                <span x-show="cycle === 'monthly'">R${{ number_format($basicM->price, 0, ',', '.') }}</span>
                                <span x-show="cycle === 'yearly'" style="display: none;">R${{ number_format($basicY->price, 0, ',', '.') }}</span>
                            </span>
                            <span class="text-slate-500 dark:text-slate-400 font-medium mb-1">
                                <span x-show="cycle === 'monthly'">/mês</span>
                                <span x-show="cycle === 'yearly'" style="display: none;">/ano</span>
                            </span>
                        </div>
                        
                        <ul class="space-y-4 mb-10 flex-1">
                            @foreach($basicM->features as $feature)
                            <li class="flex items-start gap-4 text-base font-medium text-slate-700 dark:text-slate-300">
                                <div class="rounded-full bg-green-100 dark:bg-green-900/40 p-1 flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="leading-tight">{{ $feature }}</span>
                            </li>
                            @endforeach
                        </ul>

                        <a :href="cycle === 'monthly' ? '{{ route('plans.checkout', $basicM->slug) }}' : '{{ route('plans.checkout', $basicY->slug) }}'" 
                           class="block w-full py-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-center font-bold text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition-all duration-300 text-lg border border-transparent dark:border-slate-600">
                            Assinar o Básico
                        </a>
                    </div>
                </div>
                @endif

                <!-- Card Plus (Destaque) -->
                @if($plusM && $plusY)
                <div class="relative p-8 md:p-10 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg dark:shadow-none overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                    
                    <div class="absolute top-0 right-0 py-1 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold uppercase rounded-bl-lg tracking-wider z-20">
                        Recomendado
                    </div>
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-purple-600/10 dark:bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <div class="relative z-10 flex-1 flex flex-col">
                        <h3 class="text-2xl font-black mb-2 text-slate-900 dark:text-white uppercase tracking-wide">Plus</h3>
                        
                        <div class="flex items-end gap-1 mb-8">
                            <span class="text-5xl font-black tracking-tight text-slate-900 dark:text-white">
                                <span x-show="cycle === 'monthly'">R${{ number_format($plusM->price, 0, ',', '.') }}</span>
                                <span x-show="cycle === 'yearly'" style="display: none;">R${{ number_format($plusY->price, 0, ',', '.') }}</span>
                            </span>
                            <span class="text-slate-500 dark:text-slate-400 font-medium mb-1">
                                <span x-show="cycle === 'monthly'">/mês</span>
                                <span x-show="cycle === 'yearly'" style="display: none;">/ano</span>
                            </span>
                        </div>
                        
                        <ul class="space-y-4 mb-10 flex-1">
                            @foreach($plusM->features as $feature)
                            <li class="flex items-start gap-4 text-base font-medium text-slate-700 dark:text-slate-300">
                                <div class="rounded-full bg-green-100 dark:bg-green-900/40 p-1 flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="leading-tight">{{ $feature }}</span>
                            </li>
                            @endforeach
                        </ul>

                        <a :href="cycle === 'monthly' ? '{{ route('plans.checkout', $plusM->slug) }}' : '{{ route('plans.checkout', $plusY->slug) }}'" 
                           class="block w-full py-4 rounded-xl bg-slate-900 dark:bg-gradient-to-r dark:from-blue-600 dark:to-indigo-600 text-center font-bold text-white hover:bg-slate-800 dark:hover:shadow-[0_0_20px_rgba(79,70,229,0.4)] transition-all duration-300 text-lg">
                            Assinar o Plus
                        </a>
                    </div>
                </div>
                @endif
            </div>

        </div> <!-- Fim Alpine Toggle Wrap -->

        <!-- Secondary CTA -->
        <div class="text-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-800 dark:text-slate-500 dark:hover:text-slate-300 text-sm font-semibold transition-colors">
                Continuar usando o Plano Gratuito por enquanto
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
    </div>
</div>
@endsection
