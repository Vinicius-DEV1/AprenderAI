<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-[#0b0e14]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bem-vindo ao {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-white selection:bg-purple-500/30">
    <div class="min-h-screen relative overflow-hidden flex flex-col items-center justify-center p-6">
        <!-- Background Orbs -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-purple-600/20 rounded-full blur-[100px] animate-pulse"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-600/20 rounded-full blur-[100px] animate-pulse" style="animation-delay: 2s"></div>
        </div>

        <div class="max-w-4xl w-full">
            <!-- Header section -->
            <div class="text-center mb-12">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-purple-400 text-sm font-medium mb-6">
                    <span class="flex h-2 w-2 rounded-full bg-purple-500"></span>
                    Sua jornada para aprovação começa aqui
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">
                    Olá, <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-blue-500">{{ explode(' ', $user->name)[0] }}</span>! 🚀
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Você acaba de entrar na plataforma mais inteligente de estudos do Brasil. Turbine sua preparação com nossas ferramentas exclusivas.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                <!-- Feature 1 -->
                <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-purple-500/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-purple-600/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Triagem com IA</h3>
                    <p class="text-sm text-slate-400">Classificação inteligente de questões por matéria e assunto em segundos.</p>
                </div>
                <!-- Feature 2 -->
                <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-blue-500/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-blue-600/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Simulados Personalizados</h3>
                    <p class="text-sm text-slate-400">Gere provas focadas nas suas fraquezas para uma evolução constante.</p>
                </div>
                <!-- Feature 3 -->
                <div class="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-purple-500/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-purple-600/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Análise de Redação</h3>
                    <p class="text-sm text-slate-400">Correções detalhadas por inteligência artificial seguindo os critérios oficiais.</p>
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-3xl mx-auto mb-12">
                @foreach($plans as $plan)
                <div class="relative p-8 rounded-3xl bg-gradient-to-b from-white/10 to-white/[0.02] border border-white/10 overflow-hidden group hover:scale-[1.02] transition-transform">
                    @if($plan->slug === 'premium' || $plan->slug === 'pro')
                    <div class="absolute -top-12 -right-12 w-24 h-24 bg-purple-600/20 rounded-full blur-2xl"></div>
                    @endif
                    
                    <div class="relative z-10">
                        <h3 class="text-2xl font-bold mb-2">{{ $plan->name }}</h3>
                        <div class="flex items-baseline gap-1 mb-6">
                            <span class="text-4xl font-black">R${{ number_format($plan->price, 0, ',', '.') }}</span>
                            <span class="text-slate-400 text-sm">/{{ $plan->interval === 'monthly' ? 'mês' : 'ano' }}</span>
                        </div>
                        
                        <ul class="space-y-3 mb-8">
                            @foreach($plan->features as $feature)
                            <li class="flex items-center gap-3 text-sm text-slate-300">
                                <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('plans.checkout', $plan->slug) }}" 
                           class="block w-full py-4 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-center font-bold text-white hover:shadow-[0_0_20px_rgba(147,51,234,0.4)] transition-all">
                            Assinar Agora
                        </a>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Secondary CTA -->
            <div class="text-center">
                <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-300 text-sm font-medium transition-colors">
                    Continuar para versão gratuita →
                </a>
            </div>
        </div>
    </div>
</body>
</html>
