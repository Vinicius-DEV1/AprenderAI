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

                                <!-- Custos de API (Adicionado para alinhar com React e resolver bug de roteamento) -->
                                <a href="/admin/api-pricing"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->path() === 'admin/api-pricing' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Custos de API
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

                                <a href="{{ route('admin.analytics.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all {{ request()->routeIs('admin.analytics.*') ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100' }}">
                                    <!-- Icon: Chart Bar/Analytics -->
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                    Analytics
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
                <div class="flex justify-between items-start mb-8">
                    @isset($header)
                        <header>
                            {{ $header }}
                        </header>
                    @else
                        <div></div>
                    @endisset

                    <!-- Notifications Bell -->
                    <div x-data="{ open: false }" class="relative">
                        @php 
                            $unreadAlerts = \App\Models\AnalyticsAlert::whereNull('read_at')->orderByDesc('created_at')->take(5)->get(); 
                            $unreadCount = $unreadAlerts->count();
                        @endphp
                        
                        <button @click="open = !open" @click.away="open = false" class="relative p-2 text-gray-400 hover:text-gray-500 transition-colors focus:outline-none bg-white rounded-full shadow-sm border border-gray-100">
                            <span class="sr-only">Ver notificações</span>
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount > 0)
                                <span class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-red-500 ring-2 ring-white text-[9px] font-bold text-white flex items-center justify-center">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                        </button>

                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-80 rounded-xl shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 overflow-hidden" style="display: none;">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                <p class="text-sm font-semibold text-gray-800">Notificações</p>
                                @if($unreadCount > 0)
                                    <span class="bg-red-100 text-red-800 text-[10px] px-2 py-0.5 rounded-full font-bold">{{ $unreadCount }} novas</span>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse($unreadAlerts as $alert)
                                    <a href="{{ route('admin.analytics.alerts', ['highlight' => $alert->id]) }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50 transition-colors">
                                        <p class="text-sm text-gray-800 font-medium truncate">{{ $alert->type == 'drop' ? '📉 Queda Detectada' : ($alert->type == 'growth' ? '🚀 Crescimento' : '⚠️ Alerta') }}</p>
                                        <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $alert->message }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <div class="px-4 py-6 text-center text-sm text-gray-500">
                                        Nenhuma notificação nova.
                                    </div>
                                @endforelse
                            </div>
                            <div class="px-4 py-2 border-t border-gray-100 bg-gray-50 text-center">
                                <a href="{{ route('admin.analytics.alerts') }}" class="text-xs font-medium text-blue-600 hover:text-blue-800">Ver todo o histórico</a>
                            </div>
                        </div>
                    </div>
                </div>

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

    {{-- BATCH MONITOR GLOBAL --}}
    <div x-data="batchMonitor" 
         x-on:batch-started.window="startMonitoring($event.detail.batchId)" 
         x-on:open-batch-monitor.window="startMonitoring($event.detail.batchId)"
         class="relative z-50">
        
        <div x-show="isOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isOpen = false" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="isOpen" 
                     x-transition:enter="ease-out duration-300" 
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                     x-transition:leave="ease-in duration-200" 
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full z-10">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Processamento em Lote Inteligente
                                </h3>

                                <div class="mt-4 space-y-4" x-show="!isProcessing && status === 'completed'">
                                    <p class="text-sm text-green-600 font-medium">O processamento foi concluído com sucesso. Você pode fechar este monitor ou revisar os resultados no histórico.</p>
                                </div>
                                
                                <div class="mt-4 space-y-4" x-show="!isProcessing && status === 'cancelled'">
                                    <p class="text-sm text-red-600 font-medium">O lote foi cancelado. Nenhum novo registro será processado.</p>
                                </div>

                                <div class="mt-4" x-show="isProcessing">
                                    <div class="relative pt-1">
                                        <div class="flex mb-2 items-center justify-between">
                                            <div>
                                                <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-indigo-600 bg-indigo-200" x-text="statusMessage">
                                                    Processando...
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-semibold inline-block text-indigo-600" x-text="progress + '%'"></span>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-indigo-200">
                                            <div :style="'width: ' + progress + '%'" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-500"></div>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <p class="text-xs text-gray-500" x-text="'Sucessos: ' + processed"></p>
                                                <button type="button" 
                                                    class="text-xs font-bold transition-colors"
                                                    :class="errors > 0 ? 'text-red-500 hover:text-red-700 underline' : 'text-gray-400 cursor-default'"
                                                    @click="errors > 0 ? $dispatch('show-batch-errors', { errors: errorsLog }) : null"
                                                    x-text="'Erros: ' + errors"></button>
                                            </div>
                                            <template x-if="lastError">
                                                <p class="text-[10px] text-red-500 font-bold truncate max-w-[200px]" :title="lastError" x-text="'Erro: ' + lastError"></p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button x-show="isProcessing && progress < 100 && status !== 'failed' && status !== 'cancelled'" @click="minify()" type="button" class="w-full inline-flex justify-center rounded-md border border-indigo-200 shadow-sm px-4 py-2 bg-indigo-50 text-base font-medium text-indigo-700 hover:bg-indigo-100 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Minimizar
                        </button>

                        <button x-show="isProcessing && progress >= 100" @click="isOpen = false; removePersistence(); window.location.reload();" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Concluído
                        </button>

                        <div x-show="isProcessing && (retryCount >= 5 || status === 'failed')" class="flex gap-2 w-full sm:w-auto">
                            <button @click="connectSSE()" type="button" class="flex-1 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-500 text-base font-medium text-white hover:bg-yellow-600 focus:outline-none sm:w-auto sm:text-sm">
                                Tentar Reconectar
                            </button>
                        </div>

                        <button x-show="isProcessing && progress < 100 && status === 'processing'" 
                                @click="cancelBatch()" 
                                type="button" 
                                class="w-full inline-flex justify-center rounded-md border border-red-200 shadow-sm px-4 py-2 bg-red-50 text-base font-medium text-red-700 hover:bg-red-100 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Cancelar Lote
                        </button>

                        <button @click="isOpen = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Floating Bar --}}
        <div x-data="floatingBatchMonitor" 
             x-show="show" 
             @batch-update.window="update($event.detail)"
             class="fixed bottom-4 right-4 z-50 animate-bounce-subtle" 
             style="display: none;">
            <div class="bg-white border-2 border-indigo-500 rounded-xl shadow-2xl p-4 w-72">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-indigo-700 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
                        Processamento Ativo...
                    </span>
                    <span class="text-xs font-bold text-indigo-600" x-text="progress + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" :style="'width: ' + progress + '%'"></div>
                </div>
                <div class="flex justify-between">
                    <p class="text-[10px] text-gray-500" x-text="processed + ' concluídos'"></p>
                    <button @click="maximize()" class="text-[10px] font-bold text-indigo-600 hover:underline">Ver Detalhes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Error Modal --}}
    <div x-data="{ isOpen: false, errors: [] }" 
         x-on:show-batch-errors.window="isOpen = true; errors = $event.detail.errors"
         x-show="isOpen" 
         class="fixed inset-0 z-[60] overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="isOpen = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 text-left">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Log de Erros do Lote</h3>
                <div class="max-h-96 overflow-y-auto space-y-2">
                    <template x-for="(error, index) in errors" :key="index">
                        <div class="p-3 rounded-lg border" :class="error.type === 'fatal' ? 'bg-red-50 border-red-200' : 'bg-orange-50 border-orange-200'">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] font-bold uppercase" :class="error.type === 'fatal' ? 'text-red-700' : 'text-orange-700'" x-text="error.type"></span>
                                <span class="text-[10px] text-gray-500" x-text="error.time"></span>
                            </div>
                            <p class="text-xs text-gray-800 break-words" x-text="error.error"></p>
                        </div>
                    </template>
                </div>
                <div class="mt-6 flex justify-end">
                    <button @click="isOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('batchMonitor', () => ({
                isOpen: false,
                isProcessing: false,
                batchId: null,
                total: 0,
                processed: 0,
                errors: 0,
                progress: 0,
                statusMessage: 'Iniciando...',
                status: 'processing',
                lastError: null,
                errorsLog: [],
                eventSource: null,
                retryCount: 0,

                init() {
                    const saved = localStorage.getItem('active_batch_triage');
                    if (saved) {
                        const data = JSON.parse(saved);
                        if (data.status === 'processing') {
                            this.batchId = data.batchId;
                            this.isProcessing = true;
                            this.connectSSE();
                        }
                    }
                },

                startMonitoring(batchId) {
                    this.isOpen = true;
                    this.batchId = batchId;
                    this.isProcessing = true;
                    this.connectSSE();
                },

                savePersistence() {
                    localStorage.setItem('active_batch_triage', JSON.stringify({
                        batchId: this.batchId,
                        isProcessing: this.isProcessing,
                        status: this.status
                    }));
                },

                removePersistence() {
                    localStorage.removeItem('active_batch_triage');
                },

                minify() {
                    this.isOpen = false;
                    this.savePersistence();
                },

                async cancelBatch() {
                    if (!confirm('Tem certeza que deseja cancelar este lote? O processamento será interrompido.')) return;
                    
                    try {
                        const response = await fetch(`/admin/questions-batch/cancel/${this.batchId}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.status = 'cancelled';
                            this.statusMessage = 'Lote cancelado pelo usuário.';
                            if (this.eventSource) this.eventSource.close();
                            this.removePersistence();
                        }
                    } catch (e) {
                        console.error('Erro ao cancelar lote:', e);
                    }
                },

                connectSSE() {
                    if (this.eventSource) this.eventSource.close();
                    this.eventSource = new EventSource(`/admin/questions-batch/progress/${this.batchId}`);
                    this.eventSource.onmessage = (event) => {
                        try {
                            const data = JSON.parse(event.data);
                            if (data.status === 'not_found') {
                                this.eventSource.close();
                                this.statusMessage = 'Erro: Lote não encontrado.';
                                return;
                            }
                            this.processed = data.processed;
                            this.errors = data.errors;
                            this.total = data.total;
                            this.status = data.status;
                            this.lastError = data.last_error || null;
                            this.errorsLog = data.errors_log || [];
                            const completedCount = this.processed + this.errors;
                            this.progress = Math.min(100, Math.round((completedCount / this.total) * 100));

                            window.dispatchEvent(new CustomEvent('batch-update', { 
                                detail: { progress: this.progress, processed: completedCount, status: this.status, batchId: this.batchId } 
                            }));

                            if (this.status === 'completed' || this.status === 'failed' || this.status === 'cancelled') {
                                this.eventSource.close();
                                this.savePersistence();
                                if (this.status === 'completed') this.statusMessage = 'Finalizado!';
                            } else {
                                this.statusMessage = `Processando (${completedCount}/${this.total})...`;
                                this.savePersistence();
                            }
                        } catch (e) {
                            console.error('Erro SSE:', e);
                        }
                    };
                    this.eventSource.onerror = (e) => {
                        this.eventSource.close();
                        if (this.isProcessing && this.progress < 100 && this.retryCount < 5) {
                            this.retryCount++;
                            setTimeout(() => this.connectSSE(), 3000);
                        }
                    };
                }
            }));

            Alpine.data('floatingBatchMonitor', () => ({
                show: false,
                progress: 0,
                processed: 0,
                status: '',
                init() {
                    const saved = localStorage.getItem('active_batch_triage');
                    if (saved) {
                        const data = JSON.parse(saved);
                        if (data.status === 'processing') this.show = true;
                    }
                },
                update(detail) {
                    this.progress = detail.progress;
                    this.processed = detail.processed;
                    this.status = detail.status;
                    this.show = this.status === 'processing';
                },
                maximize() {
                    const saved = JSON.parse(localStorage.getItem('active_batch_triage'));
                    window.dispatchEvent(new CustomEvent('open-batch-monitor', { detail: { batchId: saved ? saved.batchId : this.batchId } }));
                }
            }));
        });
    </script>
    <style>
        @keyframes bounce-subtle { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .animate-bounce-subtle { animation: bounce-subtle 2s ease-in-out infinite; }
    </style>
</body>

</html>
