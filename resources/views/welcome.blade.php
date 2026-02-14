<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AprovadoAI - Prepare-se para o ENEM e Concursos</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                <a href="{{ route('register') }}"
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
                    <div class="text-3xl font-bold text-white">50k+</div>
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
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Por que escolher o AprovadoAI?</h2>
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

    <!-- Plans Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Escolha seu plano</h2>
                <p class="text-lg text-slate-600">Investimento acessível para o seu futuro.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Free Plan -->
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200">
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">Gratuito</h3>
                    <div class="flex items-baseline mb-6">
                        <span class="text-4xl font-extrabold text-slate-900">R$ 0</span>
                        <span class="text-slate-500 ml-1">/mês</span>
                    </div>
                    <ul class="space-y-4 mb-8 text-slate-600">
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> 5 provas por mês</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Correção básica por IA</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Estatísticas simples</li>
                        <li class="flex items-center text-slate-400"><svg class="w-5 h-5 text-slate-300 mr-2"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg> Sem redações</li>
                    </ul>
                    <a href="{{ route('register') }}"
                        class="block w-full py-3 px-4 bg-white border border-slate-300 rounded-lg text-slate-700 font-bold text-center hover:bg-slate-50 transition">Começar
                        Agora</a>
                </div>

                <!-- Basic Plan -->
                <div
                    class="bg-white rounded-2xl p-8 border-2 border-blue-600 shadow-xl transform md:-translate-y-4 relative">
                    <div
                        class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-bold uppercase tracking-wide">
                        Mais Popular</div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">Básico</h3>
                    <div class="flex items-baseline mb-6">
                        <span class="text-4xl font-extrabold text-slate-900">R$ 20</span>
                        <span class="text-slate-500 ml-1">/mês</span>
                    </div>
                    <ul class="space-y-4 mb-8 text-slate-600">
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> 10 provas por mês</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Correção detalhada por IA</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> 2 redações por mês</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Estatísticas completas</li>
                    </ul>
                    <a href="{{ route('register') }}"
                        class="block w-full py-3 px-4 bg-blue-600 rounded-lg text-white font-bold text-center hover:bg-blue-700 transition shadow-lg">Assinar
                        Agora</a>
                </div>

                <!-- Plus Plan -->
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200">
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">Plus</h3>
                    <div class="flex items-baseline mb-6">
                        <span class="text-4xl font-extrabold text-slate-900">R$ 49,90</span>
                        <span class="text-slate-500 ml-1">/mês</span>
                    </div>
                    <ul class="space-y-4 mb-8 text-slate-600">
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Provas ilimitadas</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Correção premium por IA</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> 20 redações por mês</li>
                        <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg> Plano de estudos personalizado</li>
                    </ul>
                    <a href="{{ route('register') }}"
                        class="block w-full py-3 px-4 bg-slate-800 rounded-lg text-white font-bold text-center hover:bg-slate-900 transition">Assinar
                        Agora</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="mb-4">
                <span class="text-2xl font-bold">AprovadoAI</span>
            </div>
            <p class="text-slate-400 mb-8 max-w-lg mx-auto">A plataforma que usa tecnologia para democratizar o acesso à
                aprovação.</p>
            <div class="border-t border-slate-800 pt-8 text-sm text-slate-500">
                &copy; {{ date('Y') }} AprovadoAI. Todos os direitos reservados.
            </div>
        </div>
    </footer>
</body>

</html>