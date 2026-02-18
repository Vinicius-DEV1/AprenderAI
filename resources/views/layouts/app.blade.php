<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'AprovadoAI'))</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
</head>

<body class="font-sans antialiased h-full text-slate-800">
    <div x-data="{ 
            sidebarOpen: false, 
            sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true'
        }" x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebar_collapsed', val))"
        class="min-h-screen flex bg-slate-50">

        <!-- Mobile Sidebar Overlay -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/80 z-40 lg:hidden" style="display: none;"></div>

        <!-- Sidebar -->
        <aside :class="{
                'translate-x-0': sidebarOpen,
                '-translate-x-full': !sidebarOpen,
                'lg:w-72': !sidebarCollapsed,
                'lg:w-20': sidebarCollapsed
            }"
            class="fixed inset-y-0 left-0 z-50 bg-white shadow-xl transform transition-all duration-300 ease-in-out lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:inset-auto lg:flex lg:flex-col border-r border-slate-200">

            <!-- Toggle Button (Desktop Only) -->
            <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex absolute -right-3 top-10 w-6 h-6 bg-white border border-slate-200 rounded-full items-center justify-center shadow-sm hover:bg-slate-50 transition-colors z-[60]">
                <svg class="w-4 h-4 text-slate-500 transform transition-transform duration-300"
                    :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Logo Area -->
            <div class="flex items-center h-20 border-b border-slate-100 bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-300 overflow-hidden"
                :class="sidebarCollapsed ? 'justify-center px-0' : 'justify-between px-4'">
                <a href="{{ route('dashboard') }}"
                    class="flex items-center gap-2 text-white font-bold text-xl whitespace-nowrap">
                    <svg class="w-8 h-8 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-1"
                        x-transition:enter-end="opacity-100 translate-x-0">AprovadoAI</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-white hover:text-slate-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 py-6 space-y-1 overflow-y-auto overflow-x-hidden transition-all duration-300"
                :class="sidebarCollapsed ? 'px-2' : 'px-3'">

                <p x-show="!sidebarCollapsed"
                    class="px-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Menu Principal</p>

                <a href="{{ route('dashboard') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Dashboard">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-blue-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                </a>

                <a href="{{ route('simulations.index') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('simulations.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Simulados">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('simulations.*') ? 'text-blue-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Simulados</span>
                </a>

                <a href="{{ route('questions.index') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('questions.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Questões">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('questions.*') ? 'text-blue-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Questões</span>
                </a>


                <a href="{{ route('essays.index') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('essays.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Redações">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('essays.*') ? 'text-blue-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Redações</span>
                </a>

                <a href="{{ route('study-plan.index') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('study-plan.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                    title="Plano de Estudos">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('study-plan.*') ? 'text-blue-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Plano de Estudos</span>
                </a>

                <p x-show="!sidebarCollapsed"
                    class="px-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mt-6 mb-2">Conta</p>

                <a href="{{ route('plans.index') }}"
                    class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('plans.*') ? 'bg-discord-50 text-discord-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Meu Plano">
                    <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('plans.*') ? 'text-purple-600' : 'text-slate-400' }}"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Meu Plano</span>
                </a>

                @if(in_array(auth()->user()->email, config('admin.emails', [])))
                    <a href="{{ route('admin.dashboard') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300 {{ request()->routeIs('admin.*') ? 'bg-red-50 text-red-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Painel Admin">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.*') ? 'text-red-600' : 'text-slate-400' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Admin</span>
                    </a>
                @endif
            </nav>

            <!-- User Footer -->
            <div class="px-2 py-4 border-t border-slate-200 transition-all duration-300">
                <div class="flex items-center gap-2 w-full p-2 rounded-lg bg-slate-50 border border-slate-100 overflow-hidden"
                    :class="sidebarCollapsed ? 'justify-center' : ''">
                    <div
                        class="h-8 w-8 flex-shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div x-show="!sidebarCollapsed" class="flex-1 min-w-0"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100">
                        <p class="text-xs font-semibold text-slate-900 truncate">
                            {{ auth()->user()->name }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0" x-show="!sidebarCollapsed">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors p-1"
                            title="Sair">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                    </form>


                </div>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 bg-slate-50 transition-all duration-300" :class="{
                 'lg:ml-0': true
             }">
            <!-- Mobile Header -->
            <header
                class="lg:hidden flex items-center justify-between h-16 px-4 bg-white border-b border-slate-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="font-bold text-lg text-slate-800">AprovadoAI</span>
                </div>
                <div
                    class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>

            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-8 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    @if (isset($header))
                        <div class="mb-8">
                            {{ $header }}
                        </div>
                    @endif

                    <!-- Alerts -->
                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>