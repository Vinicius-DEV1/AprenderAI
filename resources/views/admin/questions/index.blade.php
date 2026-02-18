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

                <!-- Origins Dinâmicos -->
                @foreach($questionsByOrigin as $stat)
                     <a href="{{ route('admin.questions.index', ['origin' => $stat->origin]) }}" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border-l-4 border-blue-400">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-50 text-blue-500 mr-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium truncate" title="{{ $stat->origin }}">{{ str($stat->origin)->limit(15) }}</p>
                                <p class="text-2xl font-bold text-gray-800">{{ number_format($stat->total) }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- AI TRIAGE CARD (Card 1) --}}
            @if($pendingCount > 0)
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-l-4 border-purple-500 rounded-lg shadow-md p-6 mb-6">
                {{-- Header with count and actions --}}
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-800">🔴 Triagem de IA</h3>
                        <span id="pending-count-badge" class="px-3 py-1 bg-purple-600 text-white text-sm font-semibold rounded-full">
                            {{ $pendingCount }} aguardando
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="runBatchEvaluation()" class="px-3 py-1.5 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Processar Lote (10)
                        </button>
                    </div>
                </div>

                {{-- Compact table --}}
                <div class="bg-white rounded-lg overflow-hidden shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Enunciado</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Matéria</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Origem</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" id="pending-questions-tbody">
                            @foreach($pendingQuestions as $q)
                            <tr class="hover:bg-purple-50 transition-colors" data-question-id="{{ $q->id }}">
                                <td class="px-3 py-2 text-gray-600 font-medium">{{ $q->id }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ Str::limit($q->statement, 50) }}</td>
                                <td class="px-3 py-2">
                                    @if($q->subjects->count() > 0)
                                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded font-medium">
                                            {{ $q->subjects->first()->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">N/A</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ Str::limit($q->origin ?? 'N/A', 15) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex gap-2">
                                        <button onclick="evaluateDifficulty({{ $q->id }})" 
                                            class="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded hover:bg-purple-200 font-medium flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            IA
                                        </button>
                                        <a href="{{ route('admin.questions.edit', $q) }}" 
                                            class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded hover:bg-gray-200 font-medium">
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Info text --}}
                <div class="mt-3 text-sm text-gray-600 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Mostrando as 5 questões mais recentes sem avaliação de IA. Total: <strong>{{ $pendingCount }}</strong></span>
                </div>
            </div>
            @else
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-green-800 font-medium">
                        ✅ Todas as questões foram avaliadas pela IA!
                    </p>
                </div>
            </div>
            @endif

            {{-- Filtros --}}
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
                            <option value="matemática" {{ request('subject') == 'matemática' ? 'selected' : '' }}>Matemática</option>
                            <option value="português" {{ request('subject') == 'português' ? 'selected' : '' }}>Português</option>
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
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="missing_explanation" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ request('missing_explanation') ? 'checked' : '' }}>
                            <span class="ml-2 text-sm text-gray-600">Sem Explicação</span>
                        </label>
                        <button type="submit" class="ml-auto px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700 text-sm">
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Tabela (Card 2 - Banco Geral) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-green-500">
                <div class="px-6 pt-4 pb-2 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-800">🟢 Banco Geral (Avaliadas)</h3>
                        <span class="text-sm text-gray-600 ml-2">{{ $questions->total() }} questões</span>
                    </div>
                </div>
                <div class="p-6 text-gray-900 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enunciado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dificuldade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($questions as $question)
                                <tr class="{{ empty($question->explanation) ? 'bg-amber-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $question->id }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ Str::limit($question->statement, 60) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @foreach($question->subjects as $subject)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">
                                                {{ $subject->name }}
                                            </span>
                                        @endforeach
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('admin.questions.edit', $question) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                            <button onclick="evaluateDifficulty({{ $question->id }})" class="text-purple-600 hover:text-purple-900 flex items-center gap-1" title="Reavaliar com IA">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                IA
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
        async function runBatchEvaluation() {
            if (!confirm('Deseja iniciar o processamento em lote das questões pendentes? (10 questões por vez)')) {
                return;
            }

            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processando...';

            try {
                const response = await fetch('{{ route('admin.questions.batch-evaluate-difficulty') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Erro: ' + (data.message || 'Falha no processamento.'));
                }
            } catch (error) {
                console.error(error);
                alert('Erro ao processar solicitação.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        async function evaluateDifficulty(questionId) {
            if (!confirm('Deseja reavaliar a dificuldade desta questão usando IA? Isso consumirá tokens da API.')) {
                return;
            }

            // Reference event target before async starts
            const btn = event.currentTarget;
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            try {
                const response = await fetch(`/admin/questions/${questionId}/evaluate-difficulty`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Show success toast for job initiation
                    showToast('✅ processamento iniciado em segundo plano.', 'success');
                    
                    // Remove row from pending table if it exists
                    const row = document.querySelector(`#pending-questions-tbody tr[data-question-id="${questionId}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            
                            // Update pending count badge
                            const badge = document.getElementById('pending-count-badge');
                            if (badge) {
                                const currentCount = parseInt(badge.textContent.split(' ')[0]);
                                const newCount = currentCount - 1;
                                badge.textContent = `${newCount} aguardando`;
                                
                                // If no more pending questions, reload to show green checkmark
                                if (newCount === 0) {
                                    setTimeout(() => window.location.reload(), 1000);
                                }
                            }
                        }, 300);
                    } else {
                        // If not in pending table, just reload to update the main table
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } else {
                    alert('Erro: ' + (data.message || 'Falha na comunicação com a IA.'));
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error(error);
                alert('Erro ao processar solicitação.');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transition-opacity duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                'bg-blue-600'
            }`;
            toast.textContent = message;
            toast.style.opacity = '0';
            
            document.body.appendChild(toast);
            
            // Fade in
            setTimeout(() => toast.style.opacity = '1', 10);
            
            // Fade out and remove
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    @endpush
</x-layouts.admin>
