<x-layouts.admin>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.import.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔍 Painel de Revisão de Questões
                </h2>
                @if($pendingQuestions->total() > 0)
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-sm font-semibold rounded-full">
                        {{ $pendingQuestions->total() }} pendentes
                    </span>
                @else
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full">
                        ✅ Tudo revisado
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                {{-- ============================================================ --}}
                {{-- COLUNA PRINCIPAL: Lista de questões pendentes --}}
                {{-- ============================================================ --}}
                <div class="xl:col-span-2 space-y-4">

                    {{-- Filtros --}}
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <form method="GET" action="{{ route('admin.import.review.index') }}" class="flex flex-wrap gap-3 items-end">
                            {{-- Filtro por Lote de Importação --}}
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Lote</label>
                                <select name="import_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                    <option value="">Todos os lotes</option>
                                    @foreach($imports as $imp)
                                        <option value="{{ $imp->id }}" {{ request('import_id') == $imp->id ? 'selected' : '' }}>
                                            #{{ $imp->id }} — {{ $imp->batch_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Filtro por Banca --}}
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Banca</label>
                                <select name="organization" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                    <option value="">Todas as bancas</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org }}" {{ request('organization') == $org ? 'selected' : '' }}>{{ $org }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white text-sm rounded-md hover:bg-yellow-600 font-medium">
                                    Filtrar
                                </button>
                                <a href="{{ route('admin.import.review.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300">
                                    Limpar
                                </a>
                            </div>
                        </form>
                    </div>

                    {{-- Lista de questões pendentes --}}
                    @forelse($pendingQuestions as $question)
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden border-l-4 border-yellow-400 hover:shadow-md transition-shadow">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    {{-- Metadados da questão e Título do Cargo --}}
                                    <div class="mb-3">
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded font-medium">#{{ $question->id }}</span>
                                            @if($question->organization)
                                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded font-semibold">{{ $question->organization }}</span>
                                            @endif
                                            @if($question->year)
                                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded font-medium">{{ $question->year }}</span>
                                            @endif
                                        </div>
                                        @if($question->role)
                                        <div class="mb-2">
                                            <h4 class="text-gray-800 font-bold text-sm leading-tight">{{ $question->role }}</h4>
                                        </div>
                                        @endif
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($question->subjects->take(3) as $subject)
                                                <span class="px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 text-[11px] uppercase tracking-wider rounded">{{ $subject->name }}</span>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Enunciado --}}
                                    <p class="text-gray-800 text-sm leading-relaxed">
                                        {{ Str::limit($question->statement, 200) }}
                                    </p>

                                    {{-- Indicadores de pendência --}}
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        @if($question->image_path)
                                            <span class="px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full flex items-center gap-1">
                                                🖼️ Tem imagem
                                            </span>
                                        @endif
                                        @if($question->alternatives->isEmpty())
                                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full flex items-center gap-1">
                                                ⚠️ Sem alternativas
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">
                                                {{ $question->alternatives->count() }} alternativas
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Ações da questão --}}
                                <div class="flex flex-col gap-2 flex-shrink-0 min-w-[140px]">
                                    <a href="{{ route('admin.import.review.show', $question) }}"
                                       class="flex items-center justify-center gap-2 px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm rounded-md hover:bg-indigo-100 font-medium transition-colors w-full">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        Inspecionar
                                    </a>
                                    <form method="POST" action="{{ route('admin.import.review.approve', $question) }}" class="w-full">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('Aprovar questão #{{ $question->id }} sem inspeção visual?')"
                                                class="flex items-center justify-center gap-2 w-full px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 font-medium transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Aprovar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                        <div class="text-5xl mb-4">🎉</div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Nenhuma questão pendente!</h3>
                        <p class="text-gray-500 text-sm mb-6">
                            @if(request()->hasAny(['import_id', 'organization']))
                                Tente limpar os filtros para ver todas as questões.
                            @else
                                Todas as questões importadas foram revisadas. Use o menu acima para importar mais.
                            @endif
                        </p>
                        <a href="{{ route('admin.import.index') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                            Importar Novo Lote
                        </a>
                    </div>
                    @endforelse

                    {{-- Paginação --}}
                    @if($pendingQuestions->hasPages())
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        {{ $pendingQuestions->withQueryString()->links() }}
                    </div>
                    @endif
                </div>

                {{-- ============================================================ --}}
                {{-- COLUNA LATERAL: Histórico de revisões (Fase 3 — Auditoria) --}}
                {{-- ============================================================ --}}
                <div class="xl:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden sticky top-4">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                📋 Últimas Revisões
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-50 max-h-[70vh] overflow-y-auto">
                            @forelse($recentActions as $item)
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-700 truncate">
                                            {{-- Exibe um ícone diferente para aprovação vs reversão --}}
                                            @if($item->reverted_at && (!$item->approved_at || $item->reverted_at > $item->approved_at))
                                                ↩️
                                            @else
                                                ✅
                                            @endif
                                            Questão #{{ $item->question_id }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">
                                            {{ Str::limit($item->question->statement ?? '—', 60) }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-gray-400">
                                                por <strong>{{ $item->approver->name ?? 'Sistema' }}</strong>
                                            </span>
                                            <span class="text-xs text-gray-300">•</span>
                                            <span class="text-xs text-gray-400">
                                                {{ ($item->approved_at ?? $item->reverted_at)?->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    {{-- Botão de reversão: só aparece para questões aprovadas --}}
                                    @if($item->question && $item->question->review_status === 'approved')
                                    <form method="POST" action="{{ route('admin.import.review.revert', $item->question) }}" class="flex-shrink-0">
                                        @csrf
                                        <button type="submit"
                                                title="Retornar para revisão"
                                                class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded hover:bg-orange-100 hover:text-orange-700 transition-colors font-medium">
                                            ↩️
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="p-8 text-center text-gray-400 flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                <p class="text-sm font-medium">Nenhuma revisão ainda.</p>
                                <p class="text-xs text-gray-400 mt-1">As questões aprovadas aparecerão aqui.</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layouts.admin>
