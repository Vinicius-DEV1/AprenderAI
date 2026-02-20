<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }} - Prepare-se para o ENEM e Concursos</title>
    <meta name="description" content="A plataforma completa de preparação para ENEM e Concursos Públicos com correção instantânea por IA e plano de estudos personalizado.">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $siteName }} - Inteligência Artificial para sua Aprovação">
    <meta property="og:description" content="Prepare-se para o ENEM e Concursos com correção instantânea e feedbacks personalizados da nossa IA.">
    <meta property="og:image" content="{{ asset('img/social-preview.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ $siteName }} - Inteligência Artificial para sua Aprovação">
    <meta property="twitter:description" content="Prepare-se para o ENEM e Concursos com correção instantânea e feedbacks personalizados da nossa IA.">
    <meta property="twitter:image" content="{{ asset('img/social-preview.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js (necessário para interatividade nesta página standalone) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
</head>

<body class="font-sans antialiased text-slate-800 bg-white">

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-blue-600 to-indigo-700 text-white overflow-hidden">
        <div class="absolute inset-0 bg-[url('/img/pattern.png')] opacity-10"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32 relative z-10 text-center">
            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-6 leading-tight">
                Sua aprovação começa com <br class="hidden md:block" />
                <span class="text-blue-200">Inteligência Artificial</span>
            </h1>
            <p class="text-xl md:text-2xl text-blue-100 mb-10 max-w-3xl mx-auto">
                A plataforma completa de preparação para ENEM e Concursos Públicos com correção instantânea e plano de
                estudos personalizado.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register', ['plan' => 'free']) }}"
                    class="px-8 py-4 bg-white text-blue-700 font-bold rounded-lg text-lg shadow-lg hover:bg-blue-50 transition transform hover:-translate-y-1">
                    Começar Gratuitamente
                </a>
                <a href="{{ route('login') }}"
                    class="px-8 py-4 bg-transparent border-2 border-white text-white font-bold rounded-lg text-lg hover:bg-white/10 transition">
                    Já tenho conta
                </a>
            </div>

            <!-- Stats -->
            <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-8 text-center border-t border-white/20 pt-8">
                <div>
                    <div class="text-3xl font-bold text-white">200k+</div>
                    <div class="text-blue-200 text-sm">Questões</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-white">24/7</div>
                    <div class="text-blue-200 text-sm">Correção IA</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-white">100%</div>
                    <div class="text-blue-200 text-sm">Online</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-white">4.8/5</div>
                    <div class="text-blue-200 text-sm">Avaliação</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-slate-50" id="features">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Por que escolher o {{ $siteName }}?</h2>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">Tecnologia de ponta aliada à metodologia de ensino
                    comprovada para acelerar seus resultados.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center text-3xl mb-6">📝
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Provas Ilimitadas</h3>
                    <p class="text-slate-600 leading-relaxed">Pratique quanto quiser com nosso banco de questões
                        atualizado do ENEM e principais concursos.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center text-3xl mb-6">🤖
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Correção por IA</h3>
                    <p class="text-slate-600 leading-relaxed">Correção instantânea com explicações detalhadas de cada
                        questão, entendendo onde você errou.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center text-3xl mb-6">✍️
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Redações Corrigidas</h3>
                    <p class="text-slate-600 leading-relaxed">Envie suas redações e receba feedback detalhado por
                        competência em segundos.</p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center text-3xl mb-6">📊
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Estatísticas Completas</h3>
                    <p class="text-slate-600 leading-relaxed">Acompanhe seu desempenho e evolução com gráficos
                        detalhados por disciplina e tema.</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-3xl mb-6">🎯</div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Plano de Estudos</h3>
                    <p class="text-slate-600 leading-relaxed">Receba um plano personalizado baseado nas suas
                        necessidades e tempo disponível.</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition">
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center text-3xl mb-6">⚡
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Cronômetro Oficial</h3>
                    <p class="text-slate-600 leading-relaxed">Simule exatamente como será no dia da prova, treinando sua
                        gestão de tempo.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Depoimentos Section (MESMO CARD, só adiciona FOTO + ESTRELAS) -->
    <section id="depoimentos" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Quem usa, recomenda</h2>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">
                    Resultados reais e uma rotina de estudo mais estratégica com o {{ $siteName }}.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="https://i.pravatar.cc/96?img=12" alt="Mariana Silva"
                            class="w-12 h-12 rounded-full object-cover border border-slate-200" />
                        <div>
                            <div class="font-bold text-slate-900">Mariana Silva</div>
                            <div class="text-sm text-slate-500">ENEM</div>
                            <div class="flex gap-1 text-amber-500 text-sm leading-none mt-1" aria-label="5 estrelas">
                                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-700 leading-relaxed">
                        “Fiz 900 pontos depois que comecei a revisar meus erros com a IA.”
                    </p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="https://i.pravatar.cc/96?img=32" alt="Lucas Andrade"
                            class="w-12 h-12 rounded-full object-cover border border-slate-200" />
                        <div>
                            <div class="font-bold text-slate-900">Lucas Andrade</div>
                            <div class="text-sm text-slate-500">Concurso Administrativo</div>
                            <div class="flex gap-1 text-amber-500 text-sm leading-none mt-1" aria-label="5 estrelas">
                                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-700 leading-relaxed">
                        “O histórico de evolução me ajudou a organizar meu estudo de verdade.”
                    </p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="https://i.pravatar.cc/96?img=45" alt="Fernanda Costa"
                            class="w-12 h-12 rounded-full object-cover border border-slate-200" />
                        <div>
                            <div class="font-bold text-slate-900">Fernanda Costa</div>
                            <div class="text-sm text-slate-500">Redação</div>
                            <div class="flex gap-1 text-amber-500 text-sm leading-none mt-1" aria-label="5 estrelas">
                                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-700 leading-relaxed">
                        “Melhorei minha nota porque finalmente entendi minhas falhas estruturais.”
                    </p>
                </div>

                <!-- Rafael Mendes (APENAS FOTO ALTERADA) -->
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 hover:shadow-md transition">
                    <div class="flex items-center gap-4 mb-4">
                        <!-- troquei img=22 -> img=8 -->
                        <img src="https://i.pravatar.cc/96?img=8" alt="Rafael Mendes"
                            class="w-12 h-12 rounded-full object-cover border border-slate-200" />
                        <div>
                            <div class="font-bold text-slate-900">Rafael Mendes</div>
                            <div class="text-sm text-slate-500">Polícia Militar</div>
                            <div class="flex gap-1 text-amber-500 text-sm leading-none mt-1" aria-label="5 estrelas">
                                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-700 leading-relaxed">
                        “Os simulados cronometrados fizeram diferença na minha preparação.”
                    </p>
                </div>

                <div
                    class="bg-slate-50 rounded-2xl p-8 border border-slate-200 hover:shadow-md transition md:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="https://i.pravatar.cc/96?img=28" alt="Beatriz Rocha"
                            class="w-12 h-12 rounded-full object-cover border border-slate-200" />
                        <div>
                            <div class="font-bold text-slate-900">Beatriz Rocha</div>
                            <div class="text-sm text-slate-500">Concurso</div>
                            <div class="flex gap-1 text-amber-500 text-sm leading-none mt-1" aria-label="5 estrelas">
                                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-700 leading-relaxed">
                        “O plano de estudos adaptativo deixou minha rotina muito mais estratégica e consegui passar no
                        concurso.”
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Plans Section -->
    @php
        $freePlan = $plans->where('slug', 'free')->first();
        $basicPlan = $plans->where('slug', 'basic')->first();
        $plusPlan = $plans->where('slug', 'plus')->first();
        $basicAnual = $plans->where('slug', 'basic-annual')->first();
        $plusAnual = $plans->where('slug', 'plus-annual')->first();

        // Calcular economia anual de forma segura para evitar erro 500
        $basicSaving = ($basicPlan && $basicAnual) ? ($basicPlan->price * 12) - $basicAnual->price : 0;
        $plusSaving = ($plusPlan && $plusAnual) ? ($plusPlan->price * 12) - $plusAnual->price : 0;
    @endphp
    
    <section class="py-20 bg-white" id="plans" x-data="{ 
        periodo: 'anual',
        basic: { 
            monthly: '{{ number_format($basicPlan->price, 2, ',', '.') }}',
            annual_monthly: '{{ number_format($basicAnual->price / 12, 2, ',', '.') }}',
            total_annual: '{{ number_format($basicAnual->price, 2, ',', '.') }}',
            saving: '{{ number_format($basicSaving, 2, ',', '.') }}',
            discount: {{ $basicAnual->discount_percentage }}
        },
        plus: {
            monthly: '{{ number_format($plusPlan->price, 2, ',', '.') }}',
            annual_monthly: '{{ number_format($plusAnual->price / 12, 2, ',', '.') }}',
            total_annual: '{{ number_format($plusAnual->price, 2, ',', '.') }}',
            saving: '{{ number_format($plusSaving, 2, ',', '.') }}',
            discount: {{ $plusAnual->discount_percentage }}
        }
    }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Escolha seu plano</h2>
                <p class="text-lg text-slate-600 mb-8">Investimento acessível para o seu futuro.</p>

                <!-- Toggle -->
                <div class="flex justify-center items-center gap-4 mb-12">
                    <span class="text-sm font-medium transition-colors duration-200" :class="periodo === 'mensal' ? 'text-slate-900 font-bold' : 'text-slate-400'">Mensal</span>

                    <!-- Toggle Switch -->
                    <button 
                        type="button"
                        @click="periodo = (periodo === 'mensal' ? 'anual' : 'mensal')"
                        class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        :class="periodo === 'anual' ? 'bg-blue-600' : 'bg-slate-300'"
                        role="switch" 
                        :aria-checked="periodo === 'anual' ? 'true' : 'false'">
                        <span 
                            class="pointer-events-none inline-block h-5 w-5 mt-px ml-px transform rounded-full bg-white shadow-md ring-0 transition-transform duration-300 ease-in-out"
                            :class="periodo === 'anual' ? 'translate-x-6' : 'translate-x-0'">
                        </span>
                    </button>

                    <span class="text-sm font-medium flex items-center gap-2 transition-colors duration-200" :class="periodo === 'anual' ? 'text-slate-900 font-bold' : 'text-slate-400'">
                        Anual
                        <template x-if="periodo === 'anual'">
                            <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full">-{{ $basicAnual->discount_percentage }}% OFF</span>
                        </template>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                <!-- Gratuito -->
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-lg text-green-500">🟢</span>
                            <h3 class="text-2xl font-bold text-slate-900">Gratuito</h3>
                        </div>

                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-extrabold text-slate-900">R$ 0</span>
                            <span class="text-slate-500 ml-1">/mês</span>
                        </div>

                        <ul class="space-y-4 mb-8 text-slate-600">
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                5 provas/mês
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Correção básica
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Estatísticas simples
                            </li>
                        </ul>
                    </div>

                    <a href="{{ route('register', ['plan' => 'free']) }}"
                        class="block w-full py-3 px-4 bg-white border border-slate-300 rounded-lg text-slate-700 font-bold text-center hover:bg-slate-50 transition">
                        Começar Agora
                    </a>
                </div>

                <!-- Básico -->
                <div class="bg-white rounded-2xl p-8 border-2 border-slate-200 hover:border-blue-600 transition-all duration-300 flex flex-col justify-between shadow-sm hover:shadow-xl">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-lg text-blue-500">🔵</span>
                            <h3 class="text-2xl font-bold text-slate-900">Básico</h3>
                        </div>

                        <div class="flex flex-col mb-6">
                            <div class="flex items-baseline">
                                <span class="text-4xl font-extrabold text-slate-900">R$&nbsp;<span x-text="periodo === 'anual' ? basic.annual_monthly : basic.monthly">{{ number_format($basicAnual->price / 12, 2, ',', '.') }}</span></span>
                                <span class="text-slate-500 ml-1">/mês</span>
                            </div>
                            <!-- Legenda Dinâmica -->
                            <template x-if="periodo === 'anual'">
                                <div class="mt-1">
                                    <p class="text-xs text-green-600 font-bold mb-0.5">Economize R$ <span x-text="basic.saving"></span>/ano</p>
                                    <p class="text-xs text-slate-400">Pagamento único de R$ <span x-text="basic.total_annual"></span></p>
                                </div>
                            </template>
                            <template x-if="periodo === 'mensal'">
                                <p class="text-xs text-slate-400 mt-1">Cobrança mensal recorrente</p>
                            </template>
                        </div>

                        <ul class="space-y-4 mb-8 text-slate-600">
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                10 provas/mês
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Correção detalhada
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                2 redações/mês
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Estatísticas completas
                            </li>
                        </ul>
                    </div>

                    <a :href="'{{ route('register') }}?plan=' + (periodo === 'anual' ? 'basic-annual' : 'basic')"
                        class="block w-full py-3 px-4 bg-blue-600 rounded-lg text-white font-bold text-center hover:bg-blue-700 transition shadow-lg">
                        Assinar Agora
                    </a>
                </div>

                <!-- Plus -->
                <div class="bg-white rounded-2xl p-8 border-2 border-blue-600 shadow-xl transform md:-translate-y-4 relative flex flex-col justify-between">
                    <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-bold uppercase tracking-wide">
                        Melhor Valor
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-lg text-purple-500">🟣</span>
                            <h3 class="text-2xl font-bold text-slate-900">Plus</h3>
                        </div>

                        <div class="flex flex-col mb-6">
                            <div class="flex items-baseline">
                                <span class="text-4xl font-extrabold text-slate-900">R$&nbsp;<span x-text="periodo === 'anual' ? plus.annual_monthly : plus.monthly">{{ number_format($plusAnual->price / 12, 2, ',', '.') }}</span></span>
                                <span class="text-slate-500 ml-1">/mês</span>
                                <!-- Badge de Vantagem -->
                                <template x-if="periodo === 'anual'">
                                    <span class="ml-2 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-tighter">Melhor Preço</span>
                                </template>
                            </div>
                            <!-- Legenda Dinâmica -->
                            <template x-if="periodo === 'anual'">
                                <div class="mt-1">
                                    <p class="text-xs text-green-600 font-bold mb-0.5">Economize R$ <span x-text="plus.saving"></span>/ano</p>
                                    <p class="text-xs text-slate-400">Pagamento único de R$ <span x-text="plus.total_annual"></span></p>
                                </div>
                            </template>
                            <template x-if="periodo === 'mensal'">
                                <p class="text-xs text-slate-400 mt-1">Cobrança mensal recorrente</p>
                            </template>
                        </div>

                        <ul class="space-y-4 mb-8 text-slate-600">
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <strong>Simulados ilimitados</strong>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <strong>15 redações/mês</strong>
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Plano personalizado
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Análise estratégica
                            </li>
                        </ul>
                    </div>

                    <a :href="'{{ route('register') }}?plan=' + (periodo === 'anual' ? 'plus-annual' : 'plus')"
                        class="block w-full py-3 px-4 bg-slate-800 text-white rounded-lg font-bold text-center hover:bg-slate-900 transition">
                        Assinar Agora
                    </a>
                </div>
            </div>
        </div>
    </section>




    <!-- Footer (Produto + Uso Legal) -->
    <footer class="bg-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                <div class="md:col-span-2">
                    <h3 class="text-2xl font-extrabold tracking-tight">{{ $siteName }}</h3>
                    <p class="mt-4 text-slate-400 max-w-md leading-relaxed">
                        A plataforma que usa tecnologia para democratizar o acesso à aprovação.
                    </p>
                </div>

                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-300">
                        Produto
                    </h4>
                    <ul class="mt-4 space-y-3 text-slate-400 text-sm">
                        <li><a href="#features" class="hover:text-white transition">Recursos</a></li>
                        <li><a href="#plans" class="hover:text-white transition">Planos</a></li>
                        <li><a href="#depoimentos" class="hover:text-white transition">Depoimentos</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-300">
                        Uso Legal
                    </h4>
                    <ul class="mt-4 space-y-3 text-slate-400 text-sm">
                        <li>
                            <a href="{{ route('privacy') }}" class="hover:text-white transition">
                                Política de Privacidade
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('fair-use') }}" class="hover:text-white transition">
                                Política de Uso Justo
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-12 border-t border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-slate-500 text-sm">
                    &copy; {{ date('Y') }} {{ $siteName }}. Todos os direitos reservados.
                </p>
                <p class="text-slate-600 text-xs">
                    Experiência premium focada em performance.
                </p>
            </div>

        </div>
    </footer>

</body>

</html>