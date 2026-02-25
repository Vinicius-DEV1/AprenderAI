<x-layouts.admin>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Analytics: Visão Geral</h1>
                <p class="text-gray-500 text-sm mt-1">Métricas diárias e consolidado dos acessos ao sistema.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.analytics.realtime') }}" class="px-4 py-2 bg-green-50 text-green-700 font-medium rounded-lg hover:bg-green-100 transition-colors flex items-center gap-2 text-sm border border-green-200">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Tempo Real
                </a>
            </div>
        </div>
    </x-slot>

    @if(!\App\Models\Configuration::get('analytics_enabled'))
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        O Analytics avançado não está configurado completamente. 
                        <a href="{{ route('admin.integrations') }}" class="font-bold underline">Vá para configurações</a> para inserir o JSON Service Account e ativar o sync.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if(count($insights) > 0)
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8 shadow-sm">
        <h3 class="flex items-center gap-2 text-blue-800 font-bold mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            Insights Automáticos
        </h3>
        <ul class="space-y-2">
            @foreach($insights as $insight)
                <li class="flex items-start gap-2 text-sm text-blue-700">
                    <span class="mt-1 flex-shrink-0 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    {{ $insight }}
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Menu Interno de Analytics -->
    <div class="flex overflow-x-auto gap-2 pb-4 mb-6 border-b border-gray-100">
        <a href="{{ route('admin.analytics.index') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">Visão Geral</a>
        <a href="{{ route('admin.analytics.behavior') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Comportamento</a>
        <a href="{{ route('admin.analytics.acquisition') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Aquisição</a>
        <a href="{{ route('admin.analytics.conversion') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Conversão</a>
        <a href="{{ route('admin.analytics.monetization') }}" class="whitespace-nowrap px-4 py-2 font-medium text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Monetização</a>
    </div>

    <!-- Cards Principais -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Usuários Ativos -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-indigo-300 transition-colors">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Usuários Ativos (Hoje)</p>
                    <h3 class="text-3xl font-bold text-gray-800 mt-2">{{ $todayData ? number_format($todayData->active_users, 0, ',', '.') : 0 }}</h3>
                </div>
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </div>
            </div>
            @if($todayData && $yesterdayData && $yesterdayData->active_users > 0)
                @php 
                    $pct = (($todayData->active_users - $yesterdayData->active_users) / $yesterdayData->active_users) * 100;
                    $isPos = $pct >= 0;
                @endphp
                <div class="mt-4 flex items-center gap-1.5 text-xs font-medium {{ $isPos ? 'text-green-600' : 'text-red-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $isPos ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}" />
                    </svg>
                    <span>{{ number_format(abs($pct), 1, ',', '.') }}% vs Ontem</span>
                </div>
            @else
                <div class="mt-4 text-xs font-medium text-gray-400">Dados insuficientes</div>
            @endif
        </div>

        <!-- Sessões -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-blue-300 transition-colors">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sessões (Hoje)</p>
                    <h3 class="text-3xl font-bold text-gray-800 mt-2">{{ $todayData ? number_format($todayData->sessions, 0, ',', '.') : 0 }}</h3>
                </div>
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </div>
            </div>
            @if($todayData && $yesterdayData && $yesterdayData->sessions > 0)
                @php 
                    $pct = (($todayData->sessions - $yesterdayData->sessions) / $yesterdayData->sessions) * 100;
                    $isPos = $pct >= 0;
                @endphp
                <div class="mt-4 flex items-center gap-1.5 text-xs font-medium {{ $isPos ? 'text-green-600' : 'text-red-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $isPos ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}" />
                    </svg>
                    <span>{{ number_format(abs($pct), 1, ',', '.') }}% vs Ontem</span>
                </div>
            @else
                <div class="mt-4 text-xs font-medium text-gray-400">Dados insuficientes</div>
            @endif
        </div>

        <!-- Bounce Rate -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-orange-300 transition-colors">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Taxa de Rejeição</p>
                    <h3 class="text-3xl font-bold text-gray-800 mt-2">{{ $todayData ? number_format($todayData->bounce_rate * 100, 1, ',', '.') : 0 }}%</h3>
                </div>
                <div class="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center group-hover:bg-orange-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                </div>
            </div>
        </div>

        <!-- Tempo Sessão -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between group hover:border-emerald-300 transition-colors">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo Médio</p>
                    <h3 class="text-3xl font-bold text-gray-800 mt-2">
                        @php
                            $seconds = $todayData ? $todayData->avg_session_duration : 0;
                            echo gmdate(($seconds >= 3600 ? "H:i:s" : "i:s"), intval($seconds));
                        @endphp
                    </h3>
                </div>
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-6">Tendência de Sessões (Últimos Dias)</h3>
            <div class="relative h-64 w-full">
                <!-- Usar biblioteca de gráfico se existir (Chart.js ou ApexCharts). Como a regra proibe novas libs além das já existentes e não sei se tem, vou construir um pure CSS sparkline para não quebrar regras ou falhar de JS -->
                
                <div class="flex items-end justify-between h-48 w-full gap-1 border-b border-l border-gray-200 pl-2 pb-2">
                    @php
                        $maxSessions = $dailyMetrics->max('sessions') ?: 1;
                    @endphp
                    @foreach($dailyMetrics as $day)
                        @php
                            $height = ($day->sessions / $maxSessions) * 100;
                        @endphp
                        <div class="w-full bg-blue-500 hover:bg-blue-600 transition-all rounded-t-sm group relative flex flex-col justify-end" style="height: {{ $height }}%">
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-10">
                                {{ \Carbon\Carbon::parse($day->date)->format('d/m') }}: {{ $day->sessions }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 mt-2 pl-2">
                    <span>{{ $dailyMetrics->first() ? \Carbon\Carbon::parse($dailyMetrics->first()->date)->format('d/m') : '' }}</span>
                    <span>Hoje</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-6">Horários de Pico (Média Global)</h3>
            <div class="relative h-64 w-full">
                <div class="flex items-end justify-between h-48 w-full gap-1 border-b border-l border-gray-200 pl-2 pb-2">
                    @php
                        $maxHourly = $hourlyData->max('avg_sessions') ?: 1;
                        $hours = range(0, 23);
                        $mappedHours = collect($hours)->mapWithKeys(function($h) use ($hourlyData) {
                            $item = $hourlyData->where('hour', $h)->first();
                            return [$h => $item ? $item->avg_sessions : 0];
                        });
                    @endphp
                    @foreach($mappedHours as $hour => $avg)
                        @php
                            $height = ($avg / $maxHourly) * 100;
                        @endphp
                        <div class="w-full bg-indigo-500 hover:bg-indigo-600 transition-all rounded-t-sm group relative flex flex-col justify-end" style="height: {{ $height }}%">
                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-10">
                                {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00 - {{ number_format($avg, 0) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 mt-2 pl-2">
                    <span>00:00</span>
                    <span>12:00</span>
                    <span>23:00</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
