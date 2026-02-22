@extends('layouts.app')

@section('title', 'Bem-vindo | ' . config('app.name'))

@section('content')
<div class="relative overflow-hidden flex flex-col items-center justify-center py-10 lg:py-20">
    <!-- Background Decorators (Apenas Dark Mode) -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10 hidden dark:block">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-purple-600/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-blue-600/10 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-5xl">
        <!-- Header section -->
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-purple-400 text-sm font-semibold mb-8 shadow-sm">
                <span class="flex h-2.5 w-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                Sua jornada para aprovação começa aqui
            </div>
            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-6 text-slate-900 dark:text-white">
                Olá, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-purple-400 dark:to-blue-500">{{ explode(' ', $user->name)[0] }}</span>! 🚀
            </h1>
            <p class="text-lg md:text-xl text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Você acaba de entrar na plataforma mais inteligente de estudos do Brasil. Turbine sua preparação com nossas ferramentas exclusivas guiadas por Inteligência Artificial.
            </p>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-20">
            <!-- Feature 1 -->
            <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md dark:hover:border-purple-500/50 transition-all duration-300 group">
                <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-purple-600/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-blue-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-800 dark:text-white">Triagem com IA</h3>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed">Classificação inteligente de questões por matéria, assunto e nível de dificuldade em segundos.</p>
            </div>
            <!-- Feature 2 -->
            <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md dark:hover:border-blue-500/50 transition-all duration-300 group">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-blue-600/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-indigo-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-800 dark:text-white">Simulados Inéditos</h3>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed">Gere provas focadas nas suas maiores fraquezas para garantir uma evolução contínua.</p>
            </div>
            <!-- Feature 3 -->
            <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md dark:hover:border-purple-500/50 transition-all duration-300 group">
                <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-purple-600/20 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-7 h-7 text-blue-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-800 dark:text-white">Análise de Redação</h3>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed">Correções detalhadas por IA seguindo rigorosamente os critérios oficiais das bancas.</p>
            </div>
        </div>

        <!-- Pricing Section Title -->
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-4">Escolha seu plano e saia na frente</h2>
            <p class="text-slate-600 dark:text-slate-400">Desbloqueie todo o potencial da inteligência artificial.</p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto mb-16">
            @foreach($plans as $plan)
            <div class="relative p-8 md:p-10 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg dark:shadow-none overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                <!-- Highlight Ribbon for Premium/Pro -> can adapt logic -->
                @if($plan->slug === 'premium' || $plan->slug === 'pro')
                <div class="absolute top-0 right-0 py-1 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold uppercase rounded-bl-lg tracking-wider z-20">
                    Mais Popular
                </div>
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-purple-600/10 dark:bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
                @endif
                
                <div class="relative z-10 flex-1 flex flex-col">
                    <h3 class="text-2xl font-black mb-2 text-slate-900 dark:text-white uppercase tracking-wide">{{ $plan->name }}</h3>
                    
                    <div class="flex items-end gap-1 mb-8">
                        <span class="text-5xl font-black tracking-tight text-slate-900 dark:text-white">R${{ number_format($plan->price, 0, ',', '.') }}</span>
                        <span class="text-slate-500 dark:text-slate-400 font-medium mb-1">/{{ $plan->interval === 'monthly' ? 'mês' : 'ano' }}</span>
                    </div>
                    
                    <ul class="space-y-4 mb-10 flex-1">
                        @foreach($plan->features as $feature)
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

                    <a href="{{ route('plans.checkout', $plan->slug) }}" 
                       class="block w-full py-4 rounded-xl bg-slate-900 dark:bg-gradient-to-r dark:from-blue-600 dark:to-indigo-600 text-center font-bold text-white hover:bg-slate-800 dark:hover:shadow-[0_0_20px_rgba(79,70,229,0.4)] transition-all duration-300 text-lg">
                        Assinar o {{ $plan->name }}
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Secondary CTA -->
        <div class="text-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-800 dark:text-slate-500 dark:hover:text-slate-300 text-sm font-semibold transition-colors">
                Continuar com a versão gratuita por enquanto
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
    </div>
</div>
@endsection
