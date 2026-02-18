<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 leading-tight">
                    Plano de Estudos
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">
                    Diagnóstico personalizado baseado no seu desempenho real
                </p>
            </div>

            {{-- Update Button (14-day rule) --}}
            <form action="{{ route('study-plan.update') }}" method="POST">
                @csrf
                @if($can_update)
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Atualizar Plano
                    </button>
                @else
                    <div class="text-right">
                        <button type="button" disabled
                            class="inline-flex items-center gap-2 px-4 py-2 bg-slate-200 text-slate-400 text-sm font-semibold rounded-lg cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Plano Protegido
                        </button>
                        <p class="text-xs text-slate-400 mt-1">
                            Disponível em
                            @if($next_update_at)
                                {{ $next_update_at->format('d/m/Y') }}
                                ({{ $days_until_update }} {{ $days_until_update === 1 ? 'dia' : 'dias' }})
                            @else
                                em breve
                            @endif
                        </p>
                    </div>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <p class="text-sm text-green-800 font-medium">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <p class="text-sm text-red-800 font-medium">{{ session('error') }}</p>
                </div>
            @endif

            {{-- Low confidence warning --}}
            @if($confidence['warning'])
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-800">{{ $confidence['warning'] }}</p>
                        <p class="text-xs text-amber-600 mt-0.5">Questões respondidas: {{ $confidence['total'] }} / 100
                            mínimas</p>
                    </div>
                </div>
            @endif

            {{-- Introductory Message --}}
            @if(!empty($plan->plan_json['overview']))
                <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm mt-6">
                    <p class="text-slate-800 leading-relaxed text-base">
                        @php
                            $overviewText = trim($plan->plan_json['overview']);
                            // Ensure it starts with "Olá!" as per user recommendation if not already there
                            if (!str_starts_with($overviewText, 'Olá')) {
                                $overviewText = 'Olá! ' . $overviewText;
                            }
                        @endphp
                        {{ $overviewText }}
                    </p>
                </div>
            @endif

            {{-- =====================================================
            SECTION 1 — DIAGNÓSTICO ATUAL
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">Diagnóstico Atual</h3>
                            <p class="text-xs text-slate-500">Atualizado em tempo real</p>
                        </div>
                    </div>
                    <span
                        class="text-xs px-2.5 py-1 rounded-full font-medium
                        {{ $confidence['level'] === 'high' ? 'bg-green-100 text-green-700' : ($confidence['level'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                        {{ $confidence['label'] }}
                    </span>
                </div>

                <div class="p-6">
                    @if(count($diagnostics['subjects']) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($diagnostics['subjects'] as $subj)
                                @php
                                    $statusColors = [
                                        'critico' => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'badge' => 'bg-red-100 text-red-700', 'bar' => 'bg-red-500', 'label' => 'Crítico'],
                                        'observacao' => ['bg' => 'bg-slate-50', 'border' => 'border-slate-200', 'badge' => 'bg-slate-100 text-slate-700', 'bar' => 'bg-slate-400', 'label' => 'Em observação'],
                                        'atencao' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-700', 'bar' => 'bg-amber-500', 'label' => 'Atenção'],
                                        'estavel' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'badge' => 'bg-blue-100 text-blue-700', 'bar' => 'bg-blue-500', 'label' => 'Estável'],
                                        'bom' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'badge' => 'bg-green-100 text-green-700', 'bar' => 'bg-green-500', 'label' => 'Bom'],
                                    ];
                                    $sc = $statusColors[$subj['status']] ?? $statusColors['estavel'];
                                @endphp
                                <div class="rounded-xl border {{ $sc['border'] }} {{ $sc['bg'] }} p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <span class="font-semibold text-slate-800 text-sm">{{ $subj['label'] }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $sc['badge'] }}">
                                            {{ $sc['label'] }}
                                        </span>
                                    </div>
                                    <div class="flex items-end gap-2 mb-2">
                                        <span
                                            class="text-2xl font-bold text-slate-900">{{ $subj['accuracy'] !== null ? number_format((float)$subj['accuracy'], 1, ',', '.') : '—' }}{{ $subj['accuracy'] !== null ? '%' : '' }}</span>
                                        <span class="text-xs text-slate-500 mb-1">meta {{ $subj['target'] }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-1.5 mb-2">
                                        <div class="{{ $sc['bar'] }} h-1.5 rounded-full transition-all"
                                            style="width: {{ $subj['accuracy'] !== null ? min(100, $subj['accuracy']) : 0 }}%">
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-1">{{ $subj['attempts'] }} questões respondidas
                                        @if($subj['gap'] !== null)
                                            @if($subj['gap'] >= 0)
                                                · <span class="text-green-600">+{{ number_format($subj['gap'], 1, ',', '.') }}% acima da meta</span>
                                            @else
                                                · <span class="text-red-600">{{ number_format($subj['gap'], 1, ',', '.') }}% abaixo da meta</span>
                                            @endif
                                        @endif
                                    </p>

                                    {{-- UX Premium for 0% Accuracy --}}
                                    @if($subj['accuracy'] !== null && $subj['accuracy'] == 0 && $subj['attempts'] >= 20)
                                        <div class="mt-2 p-2 bg-white/50 rounded-lg border border-red-100">
                                            <p class="text-[10px] leading-tight text-red-600 font-medium">
                                                🚨 Você errou todas as {{ $subj['attempts'] }} questões. Recomendamos exercícios guiados e revisão de fundamentos nesta disciplina.
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Summary metrics --}}
                        <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @if($diagnostics['sim_avg'])
                                <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                                    <p class="text-xl font-bold text-slate-900">{{ $diagnostics['sim_avg'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Nota média simulados</p>
                                </div>
                            @endif
                            @if($diagnostics['essay_avg'])
                                <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                                    <p class="text-xl font-bold text-slate-900">{{ $diagnostics['essay_avg'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Média redações (/1000)</p>
                                </div>
                            @endif
                            @if($diagnostics['perf_7days'])
                                <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                                    <p class="text-xl font-bold text-slate-900">{{ $diagnostics['perf_7days'] }}%</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Acerto últimos 7 dias</p>
                                </div>
                            @endif
                            @if($diagnostics['perf_14days'])
                                <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
                                    <p class="text-xl font-bold text-slate-900">{{ $diagnostics['perf_14days'] }}%</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Acerto últimos 14 dias</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="text-sm text-slate-500">Nenhum dado de desempenho disponível ainda.</p>
                            <p class="text-xs text-slate-400 mt-1">Complete simulados para gerar seu diagnóstico.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- =====================================================
            SECTION 2 — PROJEÇÃO DE DESEMPENHO
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Projeção de Desempenho</h3>
                        <p class="text-xs text-slate-500">Baseada em tendência real das últimas 2 semanas</p>
                    </div>
                </div>

                <div class="p-6">
                    @if($projection['unavailable'])
                        <div class="flex items-center gap-3 bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm text-slate-600">{{ $projection['reason'] }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-slate-900 rounded-2xl p-5 text-center">
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-2">Nota Estimada Atual</p>
                                <p class="text-4xl font-black text-white">{{ $projection['current'] }}</p>
                                <p class="text-xs text-slate-500 mt-2">Média das últimas simulações</p>
                            </div>
                            <div class="bg-blue-600 rounded-2xl p-5 text-center">
                                <p class="text-xs text-blue-200 uppercase tracking-wider mb-2">Projeção em 3 Meses</p>
                                <p class="text-4xl font-black text-white">{{ $projection['three_months'] }}</p>
                                <p class="text-xs text-blue-200 mt-2">
                                    @if($projection['trend_delta'] > 0)
                                        Tendência: +{{ $projection['trend_delta'] }} pts/período
                                    @elseif($projection['trend_delta'] < 0)
                                        Tendência: {{ $projection['trend_delta'] }} pts/período
                                    @else
                                        Mantendo ritmo atual
                                    @endif
                                </p>
                            </div>
                            @if($projection['plus_one_hour'])
                                <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-5 text-center">
                                    <p class="text-xs text-emerald-100 uppercase tracking-wider mb-2">Com +1h Diária</p>
                                    <p class="text-4xl font-black text-white">{{ $projection['plus_one_hour'] }}</p>
                                    <p class="text-xs text-emerald-100 mt-2">Estimativa com dedicação extra</p>
                                </div>
                            @else
                                <div class="bg-slate-100 rounded-2xl p-5 text-center relative overflow-hidden">
                                    <div
                                        class="absolute inset-0 bg-slate-100 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                                        <svg class="w-6 h-6 text-slate-400 mb-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <p class="text-xs text-slate-500 text-center px-2">Disponível com mais dados históricos
                                        </p>
                                    </div>
                                    <p class="text-xs text-slate-400 uppercase tracking-wider mb-2">Com +1h Diária</p>
                                    <p class="text-4xl font-black text-slate-300">---</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- =====================================================
            SECTION 3 — PONTOS FRACOS E FORTES
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Pontos Fracos e Fortes</h3>
                        <p class="text-xs text-slate-500">Mínimo 5 questões por tópico para classificação</p>
                    </div>
                </div>

                <div class="p-6 grid md:grid-cols-2 gap-6">
                    {{-- Weak --}}
                    <div>
                        <h4
                            class="text-sm font-bold text-red-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                            Pontos Fracos
                            <span class="text-xs font-normal text-slate-400 normal-case tracking-normal">(abaixo de
                                60%)</span>
                        </h4>
                        @forelse($weak_strong['weak'] as $item)
                            <div class="mb-3 p-3 bg-red-50 border border-red-100 rounded-xl">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ $item['topic'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $item['subject'] }}</p>
                                    </div>
                                    <span
                                        class="text-sm font-bold text-red-600 ml-2 flex-shrink-0">{{ $item['accuracy'] }}%</span>
                                </div>
                                <div class="mt-2 w-full bg-red-100 rounded-full h-1">
                                    <div class="bg-red-500 h-1 rounded-full" style="width: {{ $item['accuracy'] }}%"></div>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">{{ $item['attempts'] }} questões respondidas</p>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                                <p class="text-sm text-slate-500">Nenhum tópico fraco identificado com dados suficientes.
                                </p>
                                <p class="text-xs text-slate-400 mt-1">Complete mais questões por tópico.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Strong --}}
                    <div>
                        <h4
                            class="text-sm font-bold text-green-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                            Pontos Fortes
                            <span class="text-xs font-normal text-slate-400 normal-case tracking-normal">(acima de
                                75%)</span>
                        </h4>
                        @forelse($weak_strong['strong'] as $item)
                            <div class="mb-3 p-3 bg-green-50 border border-green-100 rounded-xl">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ $item['topic'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $item['subject'] }}</p>
                                    </div>
                                    <span
                                        class="text-sm font-bold text-green-600 ml-2 flex-shrink-0">{{ $item['accuracy'] }}%</span>
                                </div>
                                <div class="mt-2 w-full bg-green-100 rounded-full h-1">
                                    <div class="bg-green-500 h-1 rounded-full" style="width: {{ $item['accuracy'] }}%">
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">{{ $item['attempts'] }} questões respondidas</p>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                                <p class="text-sm text-slate-500">Nenhum ponto forte consolidado ainda.</p>
                                <p class="text-xs text-slate-400 mt-1">Continue praticando para identificar suas forças.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- =====================================================
            SECTION 4 — ESTRATÉGIA DE PROVA
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Estratégia de Prova</h3>
                        <p class="text-xs text-slate-500">Ordem e tempo sugeridos baseados no seu perfil</p>
                    </div>
                </div>

                <div class="p-6">
                    @if($exam_strategy['time_warning'])
                        <div class="mb-4 bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm text-amber-800">{{ $exam_strategy['time_warning'] }}</p>
                        </div>
                    @endif

                    @if(count($exam_strategy['suggested_order']) > 0)
                        <div class="space-y-2 mb-4">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Ordem sugerida
                                para a prova</p>
                            @foreach($exam_strategy['suggested_order'] as $i => $subj)
                                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                                    <span
                                        class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center flex-shrink-0">
                                        {{ $i + 1 }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">{{ $subj['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $subj['accuracy'] }}% de acerto</p>
                                    </div>
                                    @php
                                        $timeMap = [
                                            'Matemática' => '54 min',
                                            'Natureza' => '54 min',
                                            'Humanas' => '45 min',
                                            'Português' => '45 min',
                                            'Linguagens' => '45 min',
                                        ];
                                        $suggestedTime = $timeMap[$subj['label']] ?? '45 min';
                                    @endphp
                                    <span class="text-xs text-slate-400 flex-shrink-0">{{ $suggestedTime }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($exam_strategy['tip'])
                        <div class="bg-violet-50 border border-violet-100 rounded-xl p-3">
                            <p class="text-sm text-violet-800">
                                <span class="font-semibold">💡 Dica estratégica:</span>
                                {{ $exam_strategy['tip'] }}
                            </p>
                        </div>
                    @endif

                    @if(!$exam_strategy['time_warning'] && count($exam_strategy['suggested_order']) === 0)
                        <p class="text-sm text-slate-500 text-center py-4">Complete mais simulados para gerar sua estratégia
                            personalizada.</p>
                    @endif
                </div>
            </div>

            {{-- =====================================================
            SECTION 5 — CRONOGRAMA SEMANAL (PROTEGIDO)
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">Cronograma Semanal</h3>
                            <p class="text-xs text-slate-500">
                                @if(!$can_update && $next_update_at)
                                    Plano fixo até {{ $next_update_at->format('d/m/Y') }} · Disponível em
                                    {{ $days_until_update }} {{ $days_until_update === 1 ? 'dia' : 'dias' }}
                                @else
                                    Plano vigente
                                @endif
                            </p>
                        </div>
                    </div>
                    @if(!$can_update)
                        <span
                            class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Protegido
                        </span>
                    @endif
                </div>

                <div class="p-6">
                    @php
                        $schedule = $plan->plan_json['weekly_schedule'] ?? [];

                        $weekOrder = [
                            'segunda' => 1,
                            'terca' => 2,
                            'terça' => 2,
                            'quarta' => 3,
                            'quinta' => 4,
                            'sexta' => 5,
                            'sabado' => 6,
                            'sábado' => 6,
                            'domingo' => 7,
                        ];

                        uksort($schedule, function ($a, $b) use ($weekOrder) {
                            $aSlug = \Illuminate\Support\Str::slug($a);
                            $bSlug = \Illuminate\Support\Str::slug($b);
                            $aWeight = 999;
                            $bWeight = 999;
                            foreach ($weekOrder as $day => $weight) {
                                if (str_contains($aSlug, $day)) {
                                    $aWeight = $weight;
                                    break;
                                }
                            }
                            foreach ($weekOrder as $day => $weight) {
                                if (str_contains($bSlug, $day)) {
                                    $bWeight = $weight;
                                    break;
                                }
                            }
                            return $aWeight <=> $bWeight;
                        });

                        $dayIcons = [
                            'segunda' => '📘',
                            'terca' => '📗',
                            'terça' => '📗',
                            'quarta' => '📙',
                            'quinta' => '📕',
                            'sexta' => '📓',
                            'sabado' => '📔',
                            'sábado' => '📔',
                            'domingo' => '🔄',
                        ];
                    @endphp

                    @if(count($schedule) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($schedule as $day => $tasks)
                                @php
                                    $daySlug = \Illuminate\Support\Str::slug($day);
                                    $icon = '📅';
                                    foreach ($dayIcons as $key => $emoji) {
                                        if (str_contains($daySlug, $key)) {
                                            $icon = $emoji;
                                            break;
                                        }
                                    }
                                @endphp
                                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                                    <h4 class="text-sm font-bold text-slate-700 mb-4 capitalize flex items-center gap-2">
                                        <span>{{ $icon }}</span>
                                        {{ $day }}
                                    </h4>
                                    <ul class="space-y-2.5">
                                        @foreach((array) $tasks as $task)
                                            <li class="flex gap-2.5 items-start text-sm text-slate-600">
                                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-blue-400 flex-shrink-0"></span>
                                                <span>{{ $task }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-sm text-slate-500">Cronograma sendo gerado. Aguarde alguns instantes.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- =====================================================
            SECTION 6 — RECOMENDAÇÕES DA SEMANA
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Recomendações da Semana</h3>
                        <p class="text-xs text-slate-500">Ações práticas baseadas nos seus dados recentes · Não altera o
                            cronograma</p>
                    </div>
                </div>

                <div class="p-6">
                    @if(count($recommendations) > 0)
                        <div class="space-y-3">
                            @foreach($recommendations as $rec)
                                @php
                                    $iconSvgs = [
                                        'target' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
                                        'book' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                                        'pen' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>',
                                        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                                    ];
                                    $svgPath = $iconSvgs[$rec['icon']] ?? $iconSvgs['target'];
                                @endphp
                                <div class="flex gap-4 p-4 bg-slate-50 border border-slate-100 rounded-xl">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            {!! $svgPath !!}
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-bold text-teal-600 uppercase tracking-wider">
                                                Prioridade #{{ $loop->iteration }}
                                            </span>
                                        </div>
                                        <p class="text-sm font-semibold text-slate-800">{{ $rec['title'] }}</p>
                                        <p class="text-sm text-slate-600 mt-0.5">{{ $rec['detail'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 text-center py-4">Complete mais atividades para gerar recomendações
                            personalizadas.</p>
                    @endif
                </div>
            </div>

            {{-- =====================================================
            SECTION 7 — JANELA DE ATUALIZAÇÃO
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-base">Janela de Atualização do Plano</h3>
                </div>

                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                        <div class="flex-1">
                            @if($can_update)
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
                                    <p class="text-sm font-semibold text-green-700">Atualização disponível agora</p>
                                </div>
                                <p class="text-sm text-slate-600">
                                    Você pode regenerar o cronograma semanal com base no seu desempenho mais recente.
                                    Após a atualização, o plano ficará protegido por mais 14 dias.
                                </p>
                            @else
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                                    <p class="text-sm font-semibold text-slate-700">Plano protegido</p>
                                </div>
                                <p class="text-sm text-slate-600">
                                    O cronograma semanal permanece fixo até
                                    <strong>{{ $next_update_at?->format('d/m/Y') ?? 'em breve' }}</strong>.
                                    Faltam <strong>{{ $days_until_update }}
                                        {{ $days_until_update === 1 ? 'dia' : 'dias' }}</strong>.
                                    Diagnóstico e recomendações continuam sendo atualizados em tempo real.
                                </p>
                            @endif
                        </div>

                        @if($can_update)
                            <form action="{{ route('study-plan.update') }}" method="POST" class="flex-shrink-0">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Atualizar Plano Agora
                                </button>
                            </form>
                        @else
                            <div class="flex-shrink-0 text-center">
                                <div class="text-3xl font-black text-slate-300">{{ $days_until_update }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ $days_until_update === 1 ? 'dia restante' : 'dias restantes' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- =====================================================
            SECTION 8 — METODOLOGIA + MENSAGEM MOTIVACIONAL
            ===================================================== --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8">
                    <div class="grid md:grid-cols-2 gap-6">
                        {{-- Methodology --}}
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3">
                                Metodologia Sugerida
                            </h4>
                            <p class="text-slate-700 leading-relaxed text-sm">
                                {{ $plan->plan_json['methodology'] ?? 'Metodologia baseada em revisão espaçada, prática deliberada e foco nos pontos de maior impacto no seu desempenho.' }}
                            </p>
                        </div>

                        {{-- Motivational message --}}
                        @if($motivation)
                            <div class="border-l border-slate-100 pl-6">
                                <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3">
                                    Sua Evolução
                                </h4>
                                <p class="text-slate-700 leading-relaxed text-sm font-medium">
                                    "{{ $motivation }}"
                                </p>
                            </div>
                        @endif
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>