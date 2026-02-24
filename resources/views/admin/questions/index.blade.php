<x-layouts.admin>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Banco de Questões') }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.questions.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">
                    Nova Questão
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Mini-Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Total Geral -->
                <a href="{{ route('admin.questions.index') }}" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border-l-4 border-indigo-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-50 text-indigo-600 mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Geral</p>
                            <p class="text-2xl font-bold text-gray-800">{{ number_format($totalQuestions) }}</p>
                        </div>
                    </div>
                </a>

                <!-- Inéditas -->
                <a href="{{ route('admin.questions.index', ['source' => 'ai_generated']) }}" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-50 text-purple-600 mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Inéditas (IA)</p>
                            <p class="text-2xl font-bold text-gray-800">{{ number_format($aiQuestions) }}</p>
                        </div>
                    </div>
                </a>

                <!-- Organizations Dinâmicos -->
                @foreach($questionsByOrganization as $stat)
                     <a href="{{ route('admin.questions.index', ['organization' => $stat->organization]) }}" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border-l-4 border-blue-400">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-50 text-blue-500 mr-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium truncate" title="{{ $stat->organization }}">{{ str($stat->organization)->limit(15) }}</p>
                                <p class="text-2xl font-bold text-gray-800">{{ number_format($stat->total) }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- AI TRIAGE CARD --}}
            @if($pendingCount > 0)
            <div x-data="{}" class="bg-gradient-to-r from-purple-50 to-pink-50 border-l-4 border-purple-500 rounded-lg shadow-md p-6 mb-6">
                {{-- Header with count and sub-counters --}}
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-800">🔴 Triagem de IA</h3>
                        <span id="pending-count-badge" class="px-3 py-1 bg-purple-600 text-white text-sm font-semibold rounded-full">
                            {{ $pendingCount }} aguardando
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="$dispatch('open-batch-modal')" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 flex items-center gap-1">
                            ✨ Processamento em Lote
                        </button>
                    </div>
                </div>

                {{-- Sub-counters --}}
                <div class="flex flex-wrap gap-3 mb-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                        🟠 {{ $missingDifficultyCount }} sem dificuldade
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                        🔵 {{ $missingExplanationCount }} sem explicação
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                        🏷️ {{ $missingClassificationCount }} sem taxonomia
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                        🔴 {{ $bothMissingCount }} incompletas (múltiplos)
                    </span>
                </div>

                {{-- Triage Filters --}}
                <form method="GET" action="{{ route('admin.questions.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4 p-3 bg-white/60 rounded-lg">
                    <div>
                        <input type="text" name="triage_search" value="{{ request('triage_search') }}" placeholder="🔍 Buscar enunciado..." 
                            class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                    </div>
                    <div>
                        <select name="triage_status" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Todas as pendências</option>
                            <option value="missing_difficulty" {{ request('triage_status') == 'missing_difficulty' ? 'selected' : '' }}>🟠 Sem Dificuldade</option>
                            <option value="missing_explanation" {{ request('triage_status') == 'missing_explanation' ? 'selected' : '' }}>🔵 Sem Explicação</option>
                            <option value="missing_classification" {{ request('triage_status') == 'missing_classification' ? 'selected' : '' }}>🏷️ Sem Taxonomia (Matéria/Assunto)</option>
                            <option value="both_missing" {{ request('triage_status') == 'both_missing' ? 'selected' : '' }}>🔴 Incompleta (múltiplos)</option>
                        </select>
                    </div>
                    <div>
                        <select name="triage_subject" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Todas matérias</option>
                            @foreach($availableSubjects as $s)
                                <option value="{{ $s }}" {{ request('triage_subject') == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <select name="triage_organization" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Todas as Bankas/Organizações</option>
                            @foreach($availableOrganizations as $o)
                                <option value="{{ $o }}" {{ request('triage_organization') == $o ? 'selected' : '' }}>{{ $o }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700">
                            Filtrar
                        </button>
                        <a href="{{ route('admin.questions.index') }}" class="px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300" title="Limpar filtros">
                            ✕
                        </a>
                    </div>
                    {{-- Preserve bank filters --}}
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('subject'))<input type="hidden" name="subject" value="{{ request('subject') }}">@endif
                    @if(request('source'))<input type="hidden" name="source" value="{{ request('source') }}">@endif
                    @if(request('organization'))<input type="hidden" name="organization" value="{{ request('organization') }}">@endif
                </form>

                {{-- Compact table --}}
                <div class="bg-white rounded-lg overflow-hidden shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Enunciado</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Matéria</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Organização</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" id="pending-questions-tbody">
                            @foreach($pendingQuestions as $q)
                            @php
                                $missingDiff = empty(trim($q->difficulty_reasoning ?? ''));
                                $missingExpl = empty(trim($q->explanation ?? ''));
                                $missingClass = $q->subjects->isEmpty() || $q->topics->isEmpty();
                            @endphp
                            <tr class="hover:bg-purple-50 transition-colors" data-question-id="{{ $q->id }}">
                                <td class="px-3 py-2.5 align-top text-gray-600 font-medium">{{ $q->id }}</td>
                                <td class="px-3 py-2.5 align-top text-gray-900">
                                    <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;" class="text-xs text-gray-700">
                                        {{ Str::limit(strip_tags($q->statement), 200) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    @if($q->subjects->count() > 0)
                                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded font-medium">
                                            {{ $q->subjects->first()->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">N/A</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ Str::limit($q->organization ?? 'N/A', 15) }}</td>
                                <td class="px-3 py-2 flex flex-col items-start gap-1">
                                    @if($missingDiff && $missingExpl && $missingClass)
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] rounded-full font-medium">🔴 Incompleta</span>
                                    @else
                                        @if($missingDiff)
                                            <span class="px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] rounded-full font-medium">Dificuldade</span>
                                        @endif
                                        @if($missingExpl)
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] rounded-full font-medium">Explicação</span>
                                        @endif
                                        @if($missingClass)
                                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] rounded-full font-medium">Taxonomia</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-1.5 flex-wrap">
                                        @if($missingDiff)
                                        <button onclick="evaluateDifficulty({{ $q->id }})" 
                                            class="px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] rounded hover:bg-orange-200 font-medium flex items-center gap-1" title="Gerar dificuldade">
                                            ⚡ Dificuldade
                                        </button>
                                        @endif
                                        @if($missingExpl)
                                        <button onclick="generateExplanation({{ $q->id }})" 
                                            class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] rounded hover:bg-blue-200 font-medium flex items-center gap-1" title="Gerar explicação">
                                            📝 Explicação
                                        </button>
                                        @endif
                                        @if($missingClass)
                                        <button onclick="classifyQuestion({{ $q->id }})" 
                                            class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] rounded hover:bg-yellow-200 font-medium flex items-center gap-1" title="Classificar">
                                            🏷️ Classificar
                                        </button>
                                        @endif
                                        <button onclick="previewQuestion({{ $q->id }}, {{ json_encode($q->statement_html) }})" 
                                            class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] rounded hover:bg-indigo-200 font-medium flex items-center gap-1" title="Visualizar">
                                            👁️ Ver
                                        </button>
                                        <button onclick="completeQuestion({{ $q->id }})" 
                                            class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] rounded hover:bg-green-200 font-medium flex items-center gap-1" title="Completar tudo">
                                            🚀 IA Full
                                        </button>
                                        <a href="{{ route('admin.questions.edit', $q) }}" 
                                            class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[10px] rounded hover:bg-gray-200 font-medium">
                                            ✏️ Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Triage pagination --}}
                <div class="mt-3">
                    {{ $pendingQuestions->appends(request()->except('triage_page'))->links() }}
                </div>

                {{-- Info text --}}
                <div class="mt-3 text-sm text-gray-600 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Questões sem dificuldade e/ou explicação preenchida. Total: <strong>{{ $pendingCount }}</strong></span>
                </div>
            </div>
            @else
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-green-800 font-medium">
                        ✅ Todas as questões estão completas! Dificuldade e explicação preenchidas.
                    </p>
                </div>
            </div>
            @endif

            {{-- Filtros do Banco Geral --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                <form method="GET" action="{{ route('admin.questions.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="search" value="Busca" />
                        <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="request('search')" placeholder="Enunciado..." />
                    </div>
                    <div>
                        <x-input-label for="subject" value="Matéria" />
                        <select id="subject" name="subject" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todas</option>
                            @foreach($availableSubjects as $subjectName)
                                <option value="{{ $subjectName }}" {{ request('subject') == $subjectName ? 'selected' : '' }}>{{ ucfirst($subjectName) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="source" value="Origem" />
                        <select id="source" name="source" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todas</option>
                            <option value="manual" {{ request('source') == 'manual' ? 'selected' : '' }}>Manual (ENEM)</option>
                            <option value="ai_generated" {{ request('source') == 'ai_generated' ? 'selected' : '' }}>IA Gerada</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="ml-auto px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700 text-sm">
                            Filtrar
                        </button>
                    </div>
                    {{-- Preserve triage filters --}}
                    @if(request('triage_search'))<input type="hidden" name="triage_search" value="{{ request('triage_search') }}">@endif
                    @if(request('triage_status'))<input type="hidden" name="triage_status" value="{{ request('triage_status') }}">@endif
                    @if(request('triage_subject'))<input type="hidden" name="triage_subject" value="{{ request('triage_subject') }}">@endif
                    @if(request('triage_organization'))<input type="hidden" name="triage_organization" value="{{ request('triage_organization') }}">@endif
                </form>
            </div>

            {{-- Tabela (Banco Geral) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-green-500">
                <div class="px-6 pt-4 pb-2 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-800">🟢 Banco Geral (Completas)</h3>
                        <span class="text-sm text-gray-600 ml-2">{{ $questions->total() }} questões</span>
                    </div>
                </div>
                <div class="p-6 text-gray-900 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enunciado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria / Formato</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dificuldade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($questions as $question)
                                <tr>
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm text-gray-500">
                                        {{ $question->id }}
                                    </td>
                                    <td class="px-3 py-2.5 align-top text-gray-900">
                                        <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;" class="text-xs text-gray-700">
                                            {{ Str::limit(strip_tags($question->statement), 200) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @foreach($question->subjects as $subject)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">
                                                {{ $subject->name }}
                                            </span>
                                        @endforeach
                                        <div class="mt-1">
                                            @if($question->format === 'true_false')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 uppercase tracking-tighter">⚖️ Certo/Errado</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 uppercase tracking-tighter">📝 Múltipla Escolha</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @php
                                            $diffColor = match($question->difficulty) {
                                                'easy' => 'bg-green-100 text-green-800',
                                                'hard' => 'bg-red-100 text-red-800',
                                                default => 'bg-yellow-100 text-yellow-800',
                                            };
                                            $diffLabel = match($question->difficulty) {
                                                'easy' => 'Fácil',
                                                'hard' => 'Difícil',
                                                default => 'Média',
                                            };
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $diffColor }}" title="{{ $question->difficulty_reasoning }}">
                                            {{ $diffLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap align-top text-sm font-medium">
                                        <div class="flex items-center gap-2">
                                            <button onclick="previewQuestion({{ $question->id }}, {{ json_encode($question->statement_html) }})" class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] rounded hover:bg-indigo-200 font-medium flex items-center gap-1" title="Visualizar">
                                                👁️ Ver
                                            </button>
                                            <a href="{{ route('admin.questions.edit', $question) }}" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] rounded hover:bg-blue-200 font-medium flex items-center gap-1">
                                                ✏️ Editar
                                            </a>
                                            <button onclick="evaluateDifficulty({{ $question->id }})" class="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] rounded hover:bg-purple-200 font-medium flex items-center gap-1" title="Reavaliar">
                                                ⚡ IA
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $questions->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        const csrfToken = '{{ csrf_token() }}';

        // === BATCH COMPLETE ===
        async function runBatchComplete() {
            if (!confirm('Deseja iniciar o processamento em lote? Dificuldade e explicação serão preenchidas (5 questões por vez).')) {
                return;
            }

            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Despachando...';

            try {
                const response = await fetch('{{ route('admin.questions.batch-complete') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showToast('Erro: ' + (data.message || 'Falha no processamento.'), 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Erro ao processar solicitação.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // === EVALUATE DIFFICULTY (individual) ===
        async function evaluateDifficulty(questionId) {
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⚡ Analisando...';

            try {
                const response = await fetch(`/admin/questions/${questionId}/evaluate-difficulty`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });

                const data = await response.json();

                if (data.success) {
                    showToast('⚡ Avaliação de dificuldade iniciada!', 'success');
                    handleRowUpdate(questionId);
                } else {
                    showToast('Erro: ' + (data.message || 'Falha.'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error(error);
                showToast('Erro ao processar.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // === GENERATE EXPLANATION (individual) ===
        async function generateExplanation(questionId) {
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '📝 Redigindo...';

            try {
                const response = await fetch(`/admin/questions/${questionId}/generate-explanation`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });

                const data = await response.json();

                if (data.success) {
                    showToast('📝 Geração de explicação iniciada!', 'success');
                    handleRowUpdate(questionId);
                } else {
                    showToast('Erro: ' + (data.message || 'Falha.'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error(error);
                showToast('Erro ao processar.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // === CLASSIFY QUESTION (individual) ===
        async function classifyQuestion(questionId) {
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '🏷️ Classificando...';

            try {
                const response = await fetch(`/admin/questions/${questionId}/classify`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });

                const data = await response.json();

                if (data.success) {
                    showToast('🏷️ Classificação N:N iniciada!', 'success');
                    handleRowUpdate(questionId);
                } else {
                    showToast('Erro: ' + (data.message || 'Falha na classificação.'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error(error);
                showToast('Erro ao processar roteiro de integração local.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // === COMPLETE QUESTION (individual - progressive feedback) ===
        async function completeQuestion(questionId) {
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;

            // Progressive feedback UX
            btn.innerHTML = '⚡ Analisando dificuldade...';

            try {
                const response = await fetch(`/admin/questions/${questionId}/complete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });

                btn.innerHTML = '📝 Redigindo explicação...';

                const data = await response.json();

                if (data.success) {
                    btn.innerHTML = '✅ Completa!';
                    btn.classList.remove('bg-green-100', 'text-green-700');
                    btn.classList.add('bg-green-500', 'text-white');
                    showToast('🚀 Questão sendo completada em segundo plano!', 'success');
                    handleRowUpdate(questionId);
                } else {
                    showToast('Erro: ' + (data.message || 'Falha.'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error(error);
                btn.innerHTML = '❌ Erro';
                showToast('Erro ao completar questão.', 'error');
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }, 2000);
            }
        }

        // === HELPER: Handle row fade-out and badge update ===
        function handleRowUpdate(questionId) {
            const row = document.querySelector(`#pending-questions-tbody tr[data-question-id="${questionId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.5s, background-color 0.3s';
                row.style.backgroundColor = '#d1fae5'; // green tint
                setTimeout(() => {
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        updatePendingBadge();
                    }, 500);
                }, 1500);
            } else {
                setTimeout(() => window.location.reload(), 2000);
            }
        }

        // === HELPER: Update pending count badge ===
        function updatePendingBadge() {
            const badge = document.getElementById('pending-count-badge');
            const tbody = document.getElementById('pending-questions-tbody');
            if (badge && tbody) {
                const remaining = tbody.querySelectorAll('tr').length;
                badge.textContent = `${remaining} aguardando`;
                if (remaining === 0) {
                    setTimeout(() => window.location.reload(), 1000);
                }
            }
        }

        // === TOAST NOTIFICATION ===
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transition-opacity duration-300 ${bgColor}`;
            toast.textContent = message;
            toast.style.opacity = '0';
            
            document.body.appendChild(toast);
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    @endpush

    {{-- BATCH CONFIGURATION MODAL (LOCAL) --}}
    <div x-data="batchConfigurator" 
         @open-batch-modal.window="openModal()" 
         x-show="isOpen" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="isOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="absolute inset-0 transition-opacity" 
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-600 opacity-75"></div>
            </div>

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
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Configurar Lote de IA
                            </h3>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Quantidade (Disponível: {{ $pendingCount }})</label>
                                    <input type="number" x-model="quantity" max="{{ $pendingCount }}" min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo de Processamento</label>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex items-center">
                                            <input type="radio" x-model="type" value="difficulty" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                            <label class="ml-3 block text-sm text-gray-700">Apenas Dificuldade</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="radio" x-model="type" value="explanation" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                            <label class="ml-3 block text-sm text-gray-700">Apenas Explicação</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="radio" x-model="type" value="classification" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                            <label class="ml-3 block text-sm text-gray-700">Apenas Classificar</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="radio" x-model="type" value="complete" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                            <label class="ml-3 block text-sm text-gray-700">Completo</label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Modelo de IA</label>
                                    <select x-model="model" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @forelse($aiModels as $ai)
                                            <option value="{{ $ai->preferred_model }}">{{ ucfirst($ai->provider) }} - {{ $ai->preferred_model }}</option>
                                        @empty
                                            <option value="">Nenhum modelo disponível</option>
                                        @endforelse
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button @click="startBatch()" type="button" :disabled="isSubmitting" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                        <span x-show="!isSubmitting">Iniciar Lote</span>
                        <span x-show="isSubmitting">Iniciando...</span>
                    </button>
                    <button @click="isOpen = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('batchConfigurator', () => ({
                isOpen: false,
                isSubmitting: false,
                quantity: 10,
                type: 'complete',
                model: '{{ $aiModels->first()?->preferred_model ?? "" }}',

                openModal() {
                    this.isOpen = true;
                },

                async startBatch() {
                    this.isSubmitting = true;
                    // Filtros da URL
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    try {
                        const response = await fetch('{{ route('admin.questions.batch.start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                quantity: this.quantity,
                                type: this.type,
                                model: this.model,
                                triage_status: urlParams.get('triage_status'),
                                triage_subject: urlParams.get('triage_subject'),
                                triage_origin: urlParams.get('triage_origin')
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            // Dispara evento global para o Monitor
                            window.dispatchEvent(new CustomEvent('batch-started', { 
                                detail: { batchId: data.batch_id } 
                            }));
                            this.isOpen = false;
                        } else {
                            alert(data.message || 'Erro ao iniciar lote.');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        alert('Erro técnico ao iniciar lote.');
                    } finally {
                        this.isSubmitting = false;
                    }
                }
            }));
        });
    </script>
    @endpush
</x-layouts.admin>
