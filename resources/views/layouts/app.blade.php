{{--
|--------------------------------------------------------------------------
| Layout Principal — app.blade.php
|--------------------------------------------------------------------------
|
| Layout master da plataforma {{ $siteName }}. Inclui:
| - Script de persistência de tema (dark mode) no

<head>
    | - Sidebar com navegação principal
    | - Header mobile
    | - Toggle de tema 🌙/☀️
    | - Suporte completo a dark mode com paleta "Deep Blue"
    |
    | DARK MODE:
    | A classe .dark é aplicada na tag <html> pelo script de persistência.
    | Tailwind v4 usa @custom-variant para habilitar o prefixo dark:.
    | A preferência é salva em localStorage('theme') e, se não houver,
    | detecta automaticamente via prefers-color-scheme.
    --}}
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', $siteName))</title>
        <meta name="description" content="Estude com a inteligência artificial do {{ $siteName }}.">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="@yield('title', $siteName)">
        <meta property="og:description" content="Plataforma de estudos com correção por IA.">
        <meta property="og:image" content="{{ asset('favicon.ico') }}">

        {{--
        |----------------------------------------------------------------------
        | Script de Persistência de Tema (EXECUTADO ANTES DO PAINT)
        |----------------------------------------------------------------------
        |
        | Este script roda SINCRONAMENTE no

        <head>, antes do body ser renderizado,
            | para evitar o "flash of unstyled content" (FOUC) — o piscar branco
            | que ocorre quando o dark mode é aplicado depois do carregamento.
            |
            | Hierarquia de decisão:
            | 1. localStorage.getItem('theme') → preferência explícita do usuário
            | 2. window.matchMedia('(prefers-color-scheme: dark)') → OS preference
            | 3. Fallback: light mode
            --}}
            <script>
                /**
                 * SCRIPT DE PERSISTÊNCIA DE TEMA (DARK MODE)
                 * 
                 * Este script é executado IMEDIATAMENTE no <head> para prevenir o "flash" de 
                 * fundo branco antes do CSS/Vite ser carregado.
                 * 
                 * Hierarquia:
                 * 1. Verifica localStorage (escolha explícita do usuário)
                 * 2. Verifica preferência do Sistema Operacional (matchMedia)
                 */
                (function () {
                    const saved = localStorage.getItem('theme');
                    if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        // Adiciona a classe .dark na raiz (<html>) para ativar as variantes dark: do Tailwind
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                })();
            </script>

            <!-- Fonts -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
                rel="stylesheet">

            <!-- Alpine.js -->
            <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

            <!-- Scripts -->
            @vite(['resources/css/app.css', 'resources/js/app.js'])
            @include('partials.analytics')
        </head>

        {{--
        |--------------------------------------------------------------------------
        | Body — Deep Blue Dark Mode
        |--------------------------------------------------------------------------
        |
        | Light: bg-slate-50, text-slate-800
        | Dark: bg-slate-950 (azul profundíssimo), text-slate-200
        |
        | O x-data do wrapper principal gerencia:
        | - sidebarOpen: estado do menu mobile
        | - sidebarCollapsed: sidebar compacta (desktop)
        | - darkMode: estado do tema (sincronizado com <html> e localStorage)
        --}}

    <body
        class="font-sans antialiased h-full text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <div x-data="{
            sidebarOpen: false,
            sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
            // Sincroniza o estado inicial do Alpine com a classe presente no <html>
            darkMode: document.documentElement.classList.contains('dark'),

            /**
             * Alterna entre light e dark mode.
             * 
             * A correção do bug estrutural consistiu em garantir que a classe .dark
             * seja aplicada no elemento <html>, permitindo que TODOS os componentes
             * (incluindo Sidebar e Modais) herdem o contexto dark corretamente.
             */
            toggleTheme() {
                this.darkMode = !this.darkMode;
                // Alterna a classe na raiz do DOM
                document.documentElement.classList.toggle('dark', this.darkMode);
                // Persiste a escolha para futuras visitas
                localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            }
        }" x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebar_collapsed', val))"
            class="min-h-screen flex">

            {{-- ===== MOBILE SIDEBAR OVERLAY ===== --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false"
                x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-900/80 z-40 lg:hidden" style="display: none;"></div>

            {{--
            |------------------------------------------------------------------
            | Sidebar — Deep Blue Dark
            |------------------------------------------------------------------
            |
            | Light: bg-white, border-slate-200
            | Dark: bg-slate-900 (card escuro), border-slate-700
            |
            | Os links de navegação usam estados adaptativos:
            | - Ativo light: bg-blue-50 text-blue-700
            | - Ativo dark: bg-blue-950/50 text-blue-300
            | - Hover light: hover:bg-slate-50
            | - Hover dark: dark:hover:bg-slate-800
            --}}
            <aside :class="{
                'translate-x-0': sidebarOpen,
                '-translate-x-full': !sidebarOpen,
                'lg:w-72': !sidebarCollapsed,
                'lg:w-20': sidebarCollapsed
            }"
                class="fixed inset-y-0 left-0 z-50 bg-white dark:bg-slate-900 shadow-xl dark:shadow-slate-950/50 transform transition-all duration-300 ease-in-out lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:inset-auto lg:flex lg:flex-col border-r border-slate-200 dark:border-slate-700">

                {{-- Toggle Button (Desktop) --}}
                <button @click="sidebarCollapsed = !sidebarCollapsed"
                    class="hidden lg:flex absolute -right-3 top-10 w-6 h-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-full items-center justify-center shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors z-[60]">
                    <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 transform transition-transform duration-300"
                        :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {{-- Logo Area --}}
                <div class="flex items-center h-20 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-300 overflow-hidden"
                    :class="sidebarCollapsed ? 'justify-center px-0' : 'justify-between px-4'">
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center gap-2 text-white font-bold text-xl whitespace-nowrap">
                        <svg class="w-8 h-8 flex-shrink-0 text-white" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-x-1"
                            x-transition:enter-end="opacity-100 translate-x-0">{{ $siteName }}</span>
                    </a>
                    <button @click="sidebarOpen = false" class="lg:hidden text-white hover:text-slate-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 py-6 space-y-1 overflow-y-auto overflow-x-hidden transition-all duration-300"
                    :class="sidebarCollapsed ? 'px-2' : 'px-3'">

                    <p x-show="!sidebarCollapsed"
                        class="px-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">
                        Menu Principal</p>

                    {{-- Dashboard --}}
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('dashboard')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Dashboard">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                    </a>

                    {{-- Simulados --}}
                    <a href="{{ route('simulations.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('simulations.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Simulados">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('simulations.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Simulados</span>
                    </a>

                    {{-- Questões --}}
                    <a href="{{ route('questions.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('questions.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Questões">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('questions.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Questões</span>
                    </a>

                    {{-- Redações --}}
                    <a href="{{ route('essays.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('essays.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'" title="Redações">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('essays.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Redações</span>
                    </a>

                    {{-- Plano de Estudos --}}
                    <a href="{{ route('study-plan.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('study-plan.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Plano de Estudos">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('study-plan.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Plano de Estudos</span>
                    </a>

                    {{-- Concursos --}}
                    <a href="{{ route('concursos.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('concursos.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Concursos">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('concursos.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Concursos</span>
                    </a>

                    <p x-show="!sidebarCollapsed"
                        class="px-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mt-6 mb-2">
                        Conta</p>

                    {{-- Perfil --}}
                    <a href="{{ route('profile.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('profile.*')
    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Meu Perfil">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('profile.*') ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Meu Perfil</span>
                    </a>

                    {{-- Meu Plano --}}
                    <a href="{{ route('plans.index') }}"
                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                        {{ request()->routeIs('plans.*')
    ? 'bg-discord-50 text-discord-700 dark:bg-purple-950/50 dark:text-purple-300'
    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                        title="Meu Plano">
                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('plans.*') ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400 dark:text-slate-500' }}"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Meu Plano</span>
                    </a>

                    {{-- Admin --}}
                    @if(in_array(auth()->user()->email, config('admin.emails', [])))
                                    <a href="{{ route('admin.dashboard') }}"
                                        class="flex items-center rounded-lg text-sm font-medium transition-all duration-300
                                            {{ request()->routeIs('admin.dashboard')
                        ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                        :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                                        title="Painel Admin">
                                        <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.dashboard') ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500' }}"
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

                {{--
                |--------------------------------------------------------------
                | User Footer + Theme Toggle
                |--------------------------------------------------------------
                |
                | O botão de tema fica ao lado do nome do usuário.
                | 🌙 = clique para ativar dark mode
                | ☀️ = clique para voltar ao light mode
                --}}
                <div class="px-2 py-4 border-t border-slate-200 dark:border-slate-700 transition-all duration-300">
                    <div class="flex items-center gap-2 w-full p-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 overflow-hidden"
                        :class="sidebarCollapsed ? 'justify-center' : ''">
                        <div
                            class="h-8 w-8 flex-shrink-0 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-xs">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <div x-show="!sidebarCollapsed" class="flex-1 min-w-0"
                            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100">
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100 truncate">
                                {{ auth()->user()->name }}
                            </p>
                        </div>

                        {{-- Theme Toggle Button --}}
                        <button @click="toggleTheme()" x-show="!sidebarCollapsed"
                            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
                            :title="darkMode ? 'Modo Claro' : 'Modo Escuro'">
                            <span x-show="!darkMode" class="text-lg">🌙</span>
                            <span x-show="darkMode" class="text-lg">☀️</span>
                        </button>

                        {{-- Logout --}}
                        <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0"
                            x-show="!sidebarCollapsed">
                            @csrf
                            <button type="submit"
                                class="text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 transition-colors p-1"
                                title="Sair">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>

                    {{-- Compact mode: theme toggle only --}}
                    <div x-show="sidebarCollapsed" class="mt-2 flex justify-center">
                        <button @click="toggleTheme()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                            :title="darkMode ? 'Modo Claro' : 'Modo Escuro'">
                            <span x-show="!darkMode" class="text-base">🌙</span>
                            <span x-show="darkMode" class="text-base">☀️</span>
                        </button>
                    </div>
                </div>
            </aside>

            {{-- ===== MAIN CONTENT ===== --}}
            <div class="flex-1 flex flex-col min-w-0 transition-all duration-300" :class="{ 'lg:ml-0': true }">

                {{-- Mobile Header --}}
                <header
                    class="lg:hidden flex items-center justify-between h-16 px-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex items-center gap-3">
                        <button @click="sidebarOpen = true"
                            class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <span class="font-bold text-lg text-slate-800 dark:text-slate-100">{{ $siteName }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Mobile theme toggle --}}
                        <button @click="toggleTheme()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <span x-show="!darkMode" class="text-lg">🌙</span>
                            <span x-show="darkMode" class="text-lg">☀️</span>
                        </button>
                        <div
                            class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-sm">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                    </div>
                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-4 lg:p-8 overflow-y-auto">
                    <div class="max-w-7xl mx-auto">
                        @if (isset($header))
                            <div class="mb-8">
                                {{ $header }}
                            </div>
                        @endif

                        {{-- Success Alert --}}
                        @if (session('success'))
                            <div
                                class="mb-6 bg-green-50 dark:bg-green-950/30 border-l-4 border-green-500 p-4 rounded-r shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Error Alert --}}
                        @if (session('error'))
                            <div
                                class="mb-6 bg-red-50 dark:bg-red-950/30 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Validation Errors Alert --}}
                        @if ($errors->any())
                            <div
                                class="mb-6 bg-red-50 dark:bg-red-950/30 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Encontramos alguns
                                            problemas:</h3>
                                        <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
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