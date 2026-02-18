<x-layouts.admin>
    <!-- Welcome Section -->
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Analítico 🚀</h1>
            <p class="text-gray-600">Visão geral da performance do AprovadoAI</p>
        </div>
        <div class="text-sm text-gray-500">
            Atualizado em: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Active Subscriptions -->
        <div
            class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-blue-100 text-sm font-medium uppercase tracking-wider">Assinaturas Ativas</p>
                    <h3 class="text-4xl font-bold mt-2">{{ $activeSubscriptions }}</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-blue-100 text-sm">
                <span class="bg-white/20 px-2 py-0.5 rounded text-white text-xs font-bold mr-2">LIVE</span>
                <span>Monitoramento em tempo real</span>
            </div>
        </div>

        <!-- Revenue -->
        <div
            class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-green-100 text-sm font-medium uppercase tracking-wider">Receita Mensal (Est.)</p>
                    <h3 class="text-4xl font-bold mt-2">R$ {{ number_format($revenue, 2, ',', '.') }}</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-green-100 text-sm">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <span>Baseado em planos ativos</span>
            </div>
        </div>

        <!-- New Users -->
        <div
            class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-purple-100 text-sm font-medium uppercase tracking-wider">Novos Usuários (Semana)</p>
                    <h3 class="text-4xl font-bold mt-2">{{ $newUsersThisWeek }}</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-purple-100 text-sm">
                @if($userGrowthDirection === 'up')
                    <span class="bg-green-400/30 px-2 py-0.5 rounded text-white text-xs font-bold mr-2 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
                                clip-rule="evenodd"></path>
                        </svg>
                        Crescimento
                    </span>
                @else
                    <span class="bg-red-400/30 px-2 py-0.5 rounded text-white text-xs font-bold mr-2 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z"
                                clip-rule="evenodd"></path>
                        </svg>
                        Queda
                    </span>
                @endif
                <span>vs semana anterior</span>
            </div>
        </div>

        <!-- API/System Health (Placeholder for now) -->
        <div
            class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-amber-100 text-sm font-medium uppercase tracking-wider">Status do Sistema</p>
                    <h3 class="text-2xl font-bold mt-2">Operacional</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-amber-100 text-sm">
                <span class="w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
                <span>Todos os serviços ativos</span>
            </div>
        </div>
    </div>

    <!-- Charts & Feed Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Charts Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Line Chart: Subscription Growth -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Evolução de Assinaturas (6 meses)
                </h3>
                <div class="relative h-64 w-full">
                    <canvas id="subscriptionsChart"></canvas>
                </div>
            </div>

            <!-- Doughnut Chart: User Status -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    Distribuição de Usuários
                </h3>
                <div class="flex items-center justify-center h-64">
                    <div class="relative h-56 w-56">
                        <canvas id="userStatusChart"></canvas>
                    </div>
                    <div class="ml-8 space-y-3">
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                            <span class="text-gray-600 dark:text-gray-400 text-sm">Usuários Pagantes
                                ({{ $userStats['active'] }})</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-gray-300 mr-2"></span>
                            <span class="text-gray-600 text-sm">Usuários Gratuitos ({{ $userStats['inactive'] }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Feed Column -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-6">
                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    Últimas Atividades
                </h3>

                <div class="relative border-l-2 border-gray-100 ml-3 space-y-6">
                    @forelse($activityFeed as $activity)
                        <div class="mb-8 ml-6 relative">
                            <!-- Bullet Point -->
                            <span
                                class="absolute -left-[31px] flex items-center justify-center w-8 h-8 rounded-full ring-4 ring-white
                                    {{ $activity['type'] == 'subscription' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600' }}">
                                @if($activity['type'] == 'subscription')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                @endif
                            </span>

                            <!-- Content -->
                            <div class="bg-gray-50 rounded-xl p-4 hover:bg-gray-100 transition-colors">
                                <div class="flex items-center mb-2">
                                    <img src="{{ $activity['user']->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($activity['user']->name) }}"
                                        alt="{{ $activity['user']->name }}" class="w-6 h-6 rounded-full mr-2">
                                    <span class="text-xs font-semibold text-gray-700">{{ $activity['user']->name }}</span>
                                    <span
                                        class="text-xs text-gray-400 ml-auto">{{ $activity['created_at']->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-gray-600 leading-snug">
                                    {{ $activity['message'] }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="ml-6 text-sm text-gray-500">Nenhuma atividade recente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Subscription Growth Chart
            const ctxSubs = document.getElementById('subscriptionsChart').getContext('2d');
            new Chart(ctxSubs, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'Novas Assinaturas',
                        data: @json($subscriptionsGrowth),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#3B82F6',
                        pointHoverBackgroundColor: '#3B82F6',
                        pointHoverBorderColor: '#ffffff',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [2, 4], color: '#f3f4f6' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // User Status Chart (Doughnut)
            const ctxStatus = document.getElementById('userStatusChart').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Pagantes', 'Gratuitos'],
                    datasets: [{
                        data: [{{ $userStats['active'] }}, {{ $userStats['inactive'] }}],
                        backgroundColor: ['#10B981', '#E5E7EB'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        });
    </script>
</x-layouts.admin>