<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Redação') }}: {{ $essay->title }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ tab: 'general' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Status / Alert -->
            <div
                class="mb-6 p-4 rounded-lg text-center font-bold text-lg
                {{ $essay->status === 'completed' ? 'bg-green-100 text-green-800 border border-green-200' : '' }}
                {{ $essay->status === 'evaluating' ? 'bg-purple-100 text-purple-800 border border-purple-200 animate-pulse' : '' }}
                {{ $essay->status === 'error' ? 'bg-red-100 text-red-800 border border-red-200' : '' }}
                {{ $essay->status === 'pending' || $essay->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                {{ $statusMessage }}
            </div>

            @if($essay->simulation_id)
                <div class="mb-4 text-center">
                    <a href="{{ route('simulations.show', $essay->simulation_id) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                        &larr; Voltar para a prova
                    </a>
                </div>
            @endif

            @if($essay->status === 'error')
                <div class="mb-6 flex justify-center">
                    <form action="{{ route('essays.retry-evaluation', $essay) }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                            Tentar novamente
                        </button>
                    </form>
                </div>
            @endif

            @if($essay->status === 'completed')
                <!-- Score Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                        <h3 class="text-sm uppercase tracking-widest text-gray-500 font-bold mb-2">Nota Xavier</h3>
                        <div class="text-6xl font-extrabold text-blue-600">
                            {{ $essay->score }}
                            <span class="text-2xl text-gray-400 font-normal">/
                                {{ $essay->type === 'enem' ? '1000' : '100' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ============================================================
                BLOCO: Nota por Competência
                Accordion minimalista · Alpine.js · 100% view-only
                Posição: imediatamente abaixo da Nota Xavier, acima das abas
                ============================================================ --}}
                @php
                    /*
                     * --- Dados das Competências ---
                     * Estratégia:
                     *   1) Se feedback_json['competencies'] existir (estrutura gerada pela IA),
                     *      usa diretamente.
                     *   2) Senão, distribui proporcionalmente a nota geral para cada critério.
                     *      Isso garante que o bloco sempre renderize sem alterar nada no backend.
                     */

                    $essayType = $essay->type;          // 'enem' ou 'concurso'
                    $totalScore = (int) $essay->score;
                    $aiCompetencies = $essay->feedback_json['competencies'] ?? null;

                    if ($essayType === 'enem') {
                        // ── ENEM: 5 competências, nota 0–200 cada, total 1000 ──────────────
                        $maxPerComp = 200;
                        $maxTotal = 1000;
                        $defaultLabels = [
                            'C1' => 'Domínio da Norma Culta',
                            'C2' => 'Compreensão do Tema e Repertório',
                            'C3' => 'Argumentação',
                            'C4' => 'Coesão e Coerência',
                            'C5' => 'Proposta de Intervenção',
                        ];

                        /*
                         * A IA retorna competências no formato OBJETO: { c1: {score, justification}, … }
                         * Suporta tanto o formato objeto (novo) quanto array numérico (legado).
                         */
                        $isAiObject = is_array($aiCompetencies) && isset($aiCompetencies['c1']);
                        $isAiArray  = is_array($aiCompetencies) && array_is_list($aiCompetencies) && count($aiCompetencies) === 5;

                        if ($isAiObject || $isAiArray) {
                            // ── Caminho principal: usar dados reais da IA ──────────────
                            $keys  = array_keys($defaultLabels);
                            $competencies = [];
                            foreach ($defaultLabels as $code => $label) {
                                $key = strtolower($code); // 'C1' → 'c1'
                                if ($isAiObject) {
                                    $c = $aiCompetencies[$key] ?? [];
                                } else {
                                    $pos = array_search($code, $keys);
                                    $c   = $aiCompetencies[$pos] ?? [];
                                }
                                $competencies[] = [
                                    'code'          => $code,
                                    'label'         => $label,
                                    'score'         => min(200, max(0, (int) ($c['score'] ?? 0))),
                                    'max'           => 200,
                                    'justification' => $c['justification'] ?? $c['comment'] ?? '',
                                ];
                            }
                        } else {
                            /*
                             * Fallback ENEM — distribuição exata:
                             * base  = floor(totalScore / 5)
                             * resto = totalScore % 5   → primeiros critérios recebem +1
                             * Garante: sum(competencies) === $totalScore SEMPRE
                             * Cap individual: 200 (regra oficial ENEM)
                             */
                            $base      = (int) floor($totalScore / 5);
                            $remainder = $totalScore % 5;
                            $competencies = [];
                            $idx = 0;
                            foreach ($defaultLabels as $code => $label) {
                                $compScore = min(200, $base + ($idx < $remainder ? 1 : 0));
                                $competencies[] = [
                                    'code'          => $code,
                                    'label'         => $label,
                                    'score'         => $compScore,
                                    'max'           => 200,
                                    'justification' => '',
                                ];
                                $idx++;
                            }
                        }
                    } else {
                        // ── Concurso: 5 critérios discursivos, proporção da nota geral ──────
                        $maxTotal = 100;
                        $defaultLabels = [
                            'C1' => 'Domínio da Norma Culta',
                            'C2' => 'Clareza Argumentativa',
                            'C3' => 'Estrutura Textual',
                            'C4' => 'Adequação ao Tema',
                            'C5' => 'Objetividade',
                        ];

                        $isAiObject = is_array($aiCompetencies) && isset($aiCompetencies['c1']);
                        $isAiArray  = is_array($aiCompetencies) && array_is_list($aiCompetencies) && count($aiCompetencies) === 5;

                        if ($isAiObject || $isAiArray) {
                            // ── Caminho principal: usar dados reais da IA ──────────────
                            $keys = array_keys($defaultLabels);
                            $competencies = [];
                            foreach ($defaultLabels as $code => $label) {
                                $key = strtolower($code); // 'C1' → 'c1'
                                if ($isAiObject) {
                                    $c = $aiCompetencies[$key] ?? [];
                                } else {
                                    $pos = array_search($code, $keys);
                                    $c   = $aiCompetencies[$pos] ?? [];
                                }
                                $competencies[] = [
                                    'code'          => $code,
                                    'label'         => $label,
                                    'score'         => min(20, max(0, (int) ($c['score'] ?? 0))),
                                    'max'           => 20,
                                    'justification' => $c['justification'] ?? $c['comment'] ?? '',
                                ];
                            }
                        } else {
                            /*
                             * Fallback Concurso — distribuição exata:
                             * base  = floor(totalScore / 5)
                             * resto = totalScore % 5   → primeiros critérios recebem +1
                             * Garante: sum(competencies) === $totalScore SEMPRE
                             * Max por critério: 20 (escala 0–100 ÷ 5)
                             */
                            $maxPerComp = 20;
                            $base       = (int) floor($totalScore / 5);
                            $remainder  = $totalScore % 5;
                            $competencies = [];
                            $idx = 0;
                            foreach ($defaultLabels as $code => $label) {
                                $compScore = min($maxPerComp, $base + ($idx < $remainder ? 1 : 0));
                                $competencies[] = [
                                    'code'          => $code,
                                    'label'         => $label,
                                    'score'         => $compScore,
                                    'max'           => $maxPerComp,
                                    'justification' => '',
                                ];
                                $idx++;
                            }
                        }
                    }
                @endphp

                {{-- Accordion: Nota por Competência --}}
                <div x-data="{ open: false }"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6 border border-gray-100 dark:border-gray-700 overflow-hidden"
                    {{-- Isolado: não interfere em nenhum outro x-data da página --}}>
                    {{-- Cabeçalho do acordeão --}}
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 tracking-wide">
                            Nota por Competência
                        </span>

                        {{-- Botão ver mais / ver menos --}}
                        <button type="button" @click="open = !open"
                            class="text-xs font-medium text-blue-400 hover:text-blue-500 transition-colors duration-150 cursor-pointer select-none focus:outline-none"
                            :aria-expanded="open" aria-controls="competencias-body">
                            <span x-show="!open">ver mais</span>
                            <span x-show="open" style="display:none;">ver menos</span>
                        </button>
                    </div>

                    {{-- Corpo expansível --}}
                    <div id="competencias-body" x-show="open" x-transition:enter="transition-all duration-300 ease-out"
                        x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-all duration-200 ease-in" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0 max-h-0" style="display:none;">
                        <div class="border-t border-gray-100 dark:border-gray-700 px-6 py-4 space-y-3">
                            @foreach ($competencies as $comp)
                                <div class="flex items-start gap-3">
                                    {{-- Código e badge de nota --}}
                                    <div class="flex-shrink-0 flex items-center gap-2 min-w-[110px]">
                                        <span
                                            class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                                            {{ $comp['code'] }}
                                        </span>
                                        <span
                                            class="inline-flex items-center bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-semibold px-2 py-0.5 rounded-full">
                                            {{ $comp['score'] }}<span
                                                class="text-blue-300 dark:text-blue-600 font-normal">/{{ $comp['max'] }}</span>
                                        </span>
                                    </div>

                                    {{-- Label e justificativa --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 leading-snug">
                                            {{ $comp['label'] }}
                                        </p>
                                        @if(!empty($comp['justification']))
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                                                {{ $comp['justification'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                {{-- /Nota por Competência --}}

                <!-- Tabs Navigation -->
                <div class="flex space-x-2 mb-6 overflow-x-auto pb-2">
                    <button @click="tab = 'general'"
                        :class="{'bg-blue-600 text-white': tab === 'general', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'general'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Resumo</button>
                    <button @click="tab = 'points'"
                        :class="{'bg-blue-600 text-white': tab === 'points', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'points'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Pontos Fortes/Fracos</button>
                    <button @click="tab = 'corrections'"
                        :class="{'bg-blue-600 text-white': tab === 'corrections', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'corrections'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Correções</button>
                    <button @click="tab = 'improved'"
                        :class="{'bg-blue-600 text-white': tab === 'improved', 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300': tab !== 'improved'}"
                        class="px-4 py-2 rounded-md font-bold shadow transition">Versão Melhorada</button>
                </div>

                <!-- Tab Contents -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg min-h-[400px]">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- General -->
                        <div x-show="tab === 'general'">
                            <h3 class="text-xl font-bold mb-4">Resumo da Avaliação</h3>
                            <p class="mb-4 text-lg leading-relaxed">
                                {{ $essay->feedback_json['summary'] ?? $essay->feedback ?? 'Sem resumo disponível.' }}
                            </p>

                            @if(isset($essay->feedback_json['checklist']))
                                <h4 class="font-bold mt-6 mb-2">Checklist Rápido</h4>
                                <ul class="space-y-2">
                                    @foreach($essay->feedback_json['checklist'] as $item)
                                        <li class="flex items-center">
                                            @if(strtolower($item['status']) === 'ok')
                                                <span class="text-green-500 mr-2">✔</span>
                                            @else
                                                <span class="text-yellow-500 mr-2">⚠</span>
                                            @endif
                                            <span class="font-semibold mr-2">{{ $item['item'] }}:</span> {{ $item['status'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <!-- Points -->
                        <div x-show="tab === 'points'" style="display:none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-green-600 font-bold text-lg mb-3">Pontos Fortes</h3>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($essay->feedback_json['strengths'] ?? [] as $s)
                                            <li>{{ $s }}</li>
                                        @endforeach
                                        @if(empty($essay->feedback_json['strengths']))
                                            <li class="text-gray-500 italic">Nenhum ponto forte destacado.</li>
                                        @endif
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="text-red-500 font-bold text-lg mb-3">A Melhorar</h3>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($essay->feedback_json['weaknesses'] ?? [] as $w)
                                            <li>{{ $w }}</li>
                                        @endforeach
                                        @if(empty($essay->feedback_json['weaknesses']))
                                            <li class="text-gray-500 italic">Nenhum ponto de melhoria destacado.</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Corrections -->
                        <div x-show="tab === 'corrections'" style="display:none;">
                            <h3 class="text-xl font-bold mb-4">Correções Pontuais</h3>
                            <div class="space-y-4">
                                @foreach($essay->feedback_json['corrections'] ?? [] as $c)
                                    <div class="border-l-4 border-yellow-400 pl-4 py-2 bg-gray-50 dark:bg-gray-700">
                                        <p class="font-mono text-sm text-red-600 mb-1">"{{ $c['excerpt'] ?? 'Trecho' }}"</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $c['issue'] ?? 'Problema' }}
                                        </p>
                                        <p class="text-green-600 italic">Sugestão: {{ $c['suggestion'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Improved -->
                        <div x-show="tab === 'improved'" style="display:none;">
                            <h3 class="text-xl font-bold mb-4">Versão Sugerida por Xavier</h3>
                            <div
                                class="prose dark:prose-invert max-w-none bg-gray-50 dark:bg-gray-900 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
                                {!! nl2br(e($essay->feedback_json['improved_version'] ?? 'Versão melhorada indisponível.')) !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Original Text (Always visible at bottom) -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-bold text-gray-500 uppercase tracking-widest text-sm mb-4">Seu Texto Original</h3>
                    <div
                        class="prose dark:prose-invert max-w-none whitespace-pre-line text-gray-700 dark:text-gray-300">
                        {{ $essay->content }}
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if($essay->status === 'evaluating')
        <script>
            setTimeout(() => {
                window.location.reload();
            }, 10000); // Auto reload every 10s to check status
        </script>
    @endif
</x-app-layout>
