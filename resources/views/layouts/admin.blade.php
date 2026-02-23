<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $siteName }} - Admin</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
    <!-- Slot para CSS adicionais de páginas específicas (ex: Cropper.js) -->
    @stack('head')
</head>

<body class="font-sans antialiased h-full text-slate-800">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div class="flex">
            <!-- Sidebar -->
            <aside class="w-64 bg-white shadow-lg min-h-screen fixed lg:static z-50">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-8">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Admin</h3>
                            <p class="text-xs text-gray-500">{{ $siteName }}</p>
                        </div>
                    </div>

                    <nav class="space-y-6">
                        <!-- SEÇÃO: OPERACIONAL -->
                        <div>
                            <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Operacional</p>
                            <div class="space-y-1">
                                <a href="{{ route('admin.dashboard') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                    Dashboard
                                </a>

                                <a href="{{ route('admin.curadoria') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.curadoria') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Portal Curadoria
                                    @php $totalPend = \App\Models\Question::where('review_status','review')->count(); @endphp
                                    @if($totalPend > 0)
                                        <span class="ml-auto text-[10px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded-full">{{ $totalPend }}</span>
                                    @endif
                                </a>

                                <a href="{{ route('admin.questions.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.questions.*') && !request()->routeIs('admin.import.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Banco de Questões
                                </a>

                                <a href="{{ route('admin.users.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.users.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    Usuários
                                </a>

                                <a href="{{ route('concursos.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('concursos.index') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    Concursos
                                </a>
                            </div>
                        </div>

                        <!-- SEÇÃO: NEGÓCIOS -->
                        <div>
                            <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Financeiro</p>
                            <div class="space-y-1">
                                <a href="{{ route('admin.plans.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.plans.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    Planos
                                </a>

                                <a href="{{ route('admin.coupons.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.coupons.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                                    Cupons
                                </a>

                                <a href="{{ route('admin.payment-settings') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.payment-settings') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    Pagamentos
                                </a>
                            </div>
                        </div>

                        <!-- SEÇÃO: ESTRATÉGICO -->
                        <div>
                            <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Inteligência</p>
                            <div class="space-y-1">
                                <a href="{{ route('admin.prompts.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.prompts.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    Prompts (Xavier)
                                </a>

                                <a href="{{ route('admin.monitor.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.monitor.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                    Monitoramento
                                </a>

                                <a href="{{ route('admin.api-keys') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.api-keys') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                    Chaves API
                                </a>
                            </div>
                        </div>

                        <!-- SEÇÃO: SISTEMA -->
                        <div>
                            <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Sistema</p>
                            <div class="space-y-1">
                                <a href="{{ route('admin.settings.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.settings.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Configurações
                                </a>

                                <a href="{{ route('admin.integrations') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.integrations') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>
                                    Integrações
                                </a>

                                <a href="{{ route('dashboard') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-gray-500 rounded-lg font-medium transition-all hover:bg-gray-100 italic">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                    Voltar ao Site
                                </a>
                            </div>
                        </div>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 p-8 lg:ml-0 ml-64">
                @isset($header)
                    <header class="mb-8">
                        {{ $header }}
                    </header>
                @endisset

                <!-- Success/Error Messages (Toast) -->
                @if (session('success') || session('error'))
                    <div x-data="{ show: true }" 
                         x-show="show" 
                         x-init="setTimeout(() => show = false, 5000)"
                         x-transition.duration.500ms
                         class="fixed bottom-4 right-4 z-50 flex flex-col gap-3 pointer-events-none">
                        @if (session('success'))
                        <div class="pointer-events-auto flex items-start gap-3 min-w-[300px] p-4 bg-white border-l-4 border-green-500 rounded-lg shadow-xl shrink-0">
                            <div class="flex-1">
                                <p class="text-gray-800 text-sm font-medium">{{ session('success') }}</p>
                            </div>
                            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        @endif

                        @if (session('error'))
                        <div class="pointer-events-auto flex items-start gap-3 min-w-[300px] p-4 bg-white border-l-4 border-red-500 rounded-lg shadow-xl shrink-0">
                            <div class="flex-1">
                                <p class="text-gray-800 text-sm font-medium">{{ session('error') }}</p>
                            </div>
                            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>
