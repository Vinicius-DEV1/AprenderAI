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
    <div class="w-full max-w-4xl">
        <!-- Header section -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-slate-800 text-sm font-semibold mb-8 shadow-sm">
                <span class="flex h-2.5 w-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                Sua jornada para aprovação começa aqui
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-6 text-slate-900">
                Olá, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">{{ explode(' ', $user->name)[0] }}</span>! 🚀
            </h1>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">
                Você acaba de entrar na plataforma mais inteligente de estudos do Brasil. Turbine sua preparação com nossas ferramentas exclusivas guiadas por Inteligência Artificial.
            </p>
        </div>

        @php
            // Identificação dos Planos Básico e Plus (Mensal e Anual)
            $basicM = $plans->where('slug', 'basic')->first();
            $basicY = $plans->where('slug', 'basic-annual')->first();
            $plusM  = $plans->where('slug', 'plus')->first();
            $plusY  = $plans->where('slug', 'plus-annual')->first();
        @endphp

        <!-- Pricing Toggle & Cards Wrap -->
        <div x-data="{ cycle: 'yearly' }" class="mb-14">
            
            <!-- Toggle Switch -->
            <div class="flex justify-center mb-10">
                <div class="inline-flex bg-slate-100 rounded-full p-1 border border-slate-200">
                    <button @click="cycle = 'monthly'" 
                            class="px-6 py-2 rounded-full text-sm font-bold transition-all duration-300"
                            :class="cycle === 'monthly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Mensal
                    </button>
                    <button @click="cycle = 'yearly'" 
                            class="px-6 py-2 rounded-full text-sm font-bold transition-all duration-300 flex items-center gap-2"
                            :class="cycle === 'yearly' ? 'bg-white text-purple-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Anual
                        <span class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-extrabold"
                              :class="cycle === 'yearly' ? 'bg-purple-100 text-purple-700' : 'bg-slate-200 text-slate-600'">
                            Economize
                        </span>
                    </button>
                </div>
            </div>

            <!-- Cards limitados a 2 clunas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                
                <!-- Card Básico -->
                @if($basicM && $basicY)
                <div class="relative p-8 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                    <div class="relative z-10 flex-1 flex flex-col">
                        <h3 class="text-2xl font-black mb-2 text-slate-900 uppercase tracking-wide">Básico</h3>
                        
                        <div class="flex items-end gap-1 mb-8">
                            <span class="text-5xl font-black tracking-tight text-slate-900">
                                <span x-show="cycle === 'monthly'" style="display: none;">R${{ number_format($basicM->price, 0, ',', '.') }}</span>
                                <span x-show="cycle === 'yearly'">R${{ number_format($basicY->price, 0, ',', '.') }}</span>
                            </span>
                            <span class="text-slate-500 font-medium mb-1">
                                <span x-show="cycle === 'monthly'" style="display: none;">/mês</span>
                                <span x-show="cycle === 'yearly'">/ano</span>
                            </span>
                        </div>
                        
                        <ul class="space-y-4 mb-10 flex-1">
                            @foreach($basicM->features as $feature)
                            <li class="flex items-start gap-4 text-base font-medium text-slate-700">
                                <div class="rounded-full bg-green-100 p-1 flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="leading-tight">{{ $feature }}</span>
                            </li>
                            @endforeach
                        </ul>

                        <a :href="cycle === 'monthly' ? '{{ route('plans.checkout', $basicM->slug) }}' : '{{ route('plans.checkout', $basicY->slug) }}'" 
                           class="block w-full py-4 rounded-xl bg-slate-100 text-center font-bold text-slate-900 hover:bg-slate-200 transition-all duration-300 text-lg border border-transparent">
                            Assinar o Básico
                        </a>
                    </div>
                </div>
                @endif

                <!-- Card Plus (Destaque) -->
                @if($plusM && $plusY)
                <div class="relative p-8 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg overflow-hidden group hover:scale-[1.02] transition-transform duration-300 flex flex-col">
                    
                    <div class="absolute top-0 right-0 py-1 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold uppercase rounded-bl-lg tracking-wider z-20">
                        Recomendado
                    </div>
                    
                    <div class="relative z-10 flex-1 flex flex-col">
                        <h3 class="text-2xl font-black mb-2 text-slate-900 uppercase tracking-wide">Plus</h3>
                        
                        <div class="flex items-end gap-1 mb-8">
                            <span class="text-5xl font-black tracking-tight text-slate-900">
                                <span x-show="cycle === 'monthly'" style="display: none;">R${{ number_format($plusM->price, 0, ',', '.') }}</span>
                                <span x-show="cycle === 'yearly'">R${{ number_format($plusY->price, 0, ',', '.') }}</span>
                            </span>
                            <span class="text-slate-500 font-medium mb-1">
                                <span x-show="cycle === 'monthly'" style="display: none;">/mês</span>
                                <span x-show="cycle === 'yearly'">/ano</span>
                            </span>
                        </div>
                        
                        <ul class="space-y-4 mb-10 flex-1">
                            @foreach($plusM->features as $feature)
                            <li class="flex items-start gap-4 text-base font-medium text-slate-700">
                                <div class="rounded-full bg-green-100 p-1 flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="leading-tight">{{ $feature }}</span>
                            </li>
                            @endforeach
                        </ul>

                        <a :href="cycle === 'monthly' ? '{{ route('plans.checkout', $plusM->slug) }}' : '{{ route('plans.checkout', $plusY->slug) }}'" 
                           class="block w-full py-4 rounded-xl bg-slate-900 bg-gradient-to-r from-blue-600 to-indigo-600 text-center font-bold text-white hover:bg-slate-800 transition-all duration-300 text-lg">
                            Assinar o Plus
                        </a>
                    </div>
                </div>
                @endif
            </div>

        </div> <!-- Fim Alpine Toggle Wrap -->

        <!-- Secondary CTA -->
        <div class="text-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-800 text-sm font-semibold transition-colors">
                Continuar usando o Plano Gratuito por enquanto
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
    </div>
</div>
@endsection
