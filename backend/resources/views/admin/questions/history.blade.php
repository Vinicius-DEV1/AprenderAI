@extends('layouts.admin')

@section('content')
<div class="p-6" x-data="batchHistory()">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Histórico de Triagem IA</h1>
            <p class="text-gray-600">Monitore lotes processados, veja detalhes e reverta alterações.</p>
        </div>
        <a href="{{ route('admin.questions.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all font-medium">
            Voltar ao Banco
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 text-gray-600 text-sm uppercase font-semibold">
                <tr>
                    <th class="px-6 py-4 border-b">Data</th>
                    <th class="px-6 py-4 border-b">Modelo</th>
                    <th class="px-6 py-4 border-b">Tipo</th>
                    <th class="px-6 py-4 border-b text-center">Progresso</th>
                    <th class="px-6 py-4 border-b">Status</th>
                    <th class="px-6 py-4 border-b text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($batches as $batch)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $batch->created_at->format('d/m/Y') }}</div>
                        <div class="text-xs text-gray-500">{{ $batch->created_at->format('H:i:s') }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                            {{ $batch->model }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ ucfirst($batch->type) }}
                    </td>
                    <td class="px-6 py-4">
                        @php 
                            $perc = $batch->total_count > 0 ? round((($batch->processed_count + $batch->error_count) / $batch->total_count) * 100) : 0;
                        @endphp
                        <div class="flex flex-col items-center">
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mb-1">
                                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $perc }}%"></div>
                            </div>
                            <span class="text-[10px] text-gray-500">{{ $batch->processed_count + $batch->error_count }}/{{ $batch->total_count }} ({{ $perc }}%)</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @switch($batch->status)
                            @case('processing')
                                @if($batch->updated_at < now()->subMinutes(15))
                                    <span class="flex items-center gap-1.5 text-orange-600 font-medium text-sm" title="O lote não teve atualizações há mais de 15 minutos">
                                        ⚠️ Provável Falha
                                    </span>
                                @else
                                    <span class="flex items-center gap-1.5 text-blue-600 font-medium text-sm">
                                        <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                                        Processando
                                    </span>
                                @endif
                                @break
                            @case('completed')
                                <span class="flex items-center gap-1.5 text-green-600 font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Concluído
                                </span>
                                @break
                            @case('failed')
                                <span class="flex items-center gap-1.5 text-red-600 font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Falhou
                                </span>
                                @break
                            @case('reverted')
                                <span class="flex items-center gap-1.5 text-amber-600 font-medium text-sm">
                                    ↩️ Revertido
                                </span>
                                @break
                            @case('cancelled')
                                <span class="flex items-center gap-1.5 text-gray-500 font-medium text-sm">
                                    🚫 Cancelado
                                </span>
                                @break
                            @default
                                <span class="text-gray-400 text-sm">{{ $batch->status }}</span>
                        @endswitch
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        {{-- View Batch Details (always available) --}}
                        <button @click="loadDetails('{{ $batch->batch_id }}')"
                                class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                            Ver Detalhes
                        </button>

                        {{-- Undo Batch (only for completed/processed batches) --}}
                        @if(in_array($batch->status, ['completed']))
                        <button @click="undoBatch('{{ $batch->batch_id }}')"
                                class="text-amber-600 hover:text-amber-900 font-medium text-sm">
                            Desfazer Lote
                        </button>
                        @endif

                        {{-- Retry (only for failed/stagnated batches) --}}
                        @if(in_array($batch->status, ['failed']) || ($batch->status === 'processing' && $batch->updated_at < now()->subMinutes(15)))
                        <button @click="retryBatch('{{ $batch->batch_id }}')"
                                class="text-blue-600 hover:text-blue-900 font-medium text-sm">
                            Tentar Novamente
                        </button>
                        @endif

                        {{-- Monitor active batches --}}
                        @if($batch->status === 'processing' && $batch->updated_at >= now()->subMinutes(15))
                        <button @click="$dispatch('open-batch-monitor', { batchId: '{{ $batch->batch_id }}' })" 
                                class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                            Monitorar
                        </button>
                        @endif

                        {{-- View error log --}}
                        @if($batch->error_count > 0 || $batch->status === 'failed')
                        <button @click="$dispatch('show-batch-errors', { errors: @js($batch->errors_log ?? []) })" 
                                class="text-red-600 hover:text-red-900 font-medium text-sm">
                            Ver Erros
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        Nenhum lote de processamento encontrado.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($batches->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $batches->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Batch Details Modal (before/after comparison per question) --}}
<div x-data="{ isOpen: false }"
     x-show="isOpen"
     x-on:open-batch-details.window="isOpen = true"
     x-on:close-batch-details.window="isOpen = false"
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" @click="isOpen = false"></div>
        
        <div class="relative bg-white rounded-xl shadow-xl max-w-4xl w-full p-6" @click.stop>
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Detalhes do Lote</h3>
                <button @click="isOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- Batch summary header --}}
            <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600" x-show="$root._batchData?.batch">
                <span class="font-medium">Tipo:</span> <span x-text="$root._batchData?.batch?.type"></span> •
                <span class="font-medium">Modelo:</span> <span x-text="$root._batchData?.batch?.model"></span> •
                <span class="font-medium">Data:</span> <span x-text="$root._batchData?.batch?.created_at"></span>
            </div>

            {{-- Per-question items table --}}
            <div class="max-h-[60vh] overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2">Questão</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Antes → Depois</th>
                            <th class="px-4 py-2 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="item in ($root._batchItems || [])" :key="item.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900" x-text="'#' + item.question_id"></div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs" x-text="item.statement"></div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                          :class="{
                                              'bg-green-50 text-green-700': item.status === 'processed',
                                              'bg-red-50 text-red-700': item.status === 'failed',
                                              'bg-amber-50 text-amber-700': item.status === 'reverted',
                                              'bg-gray-50 text-gray-600': item.status === 'pending'
                                          }"
                                          x-text="item.status"></span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <template x-if="item.before && item.after">
                                        <div class="space-y-1">
                                            <div class="flex gap-2">
                                                <span class="text-red-500 line-through" x-text="item.before?.difficulty || '—'"></span>
                                                <span>→</span>
                                                <span class="text-green-600 font-medium" x-text="item.after?.difficulty || '—'"></span>
                                            </div>
                                            <div class="text-gray-500">
                                                <span x-text="(item.before?.explanation ? 'Com' : 'Sem') + ' explicação → ' + (item.after?.explanation ? 'Com' : 'Sem') + ' explicação'"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!item.before || !item.after">
                                        <span class="text-gray-400">—</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <template x-if="item.status === 'processed'">
                                        <button @click="undoItem(item.id)" 
                                                class="text-amber-600 hover:text-amber-800 text-xs font-medium">
                                            ↩ Desfazer
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="isOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- Error Log Modal --}}
<div x-data="{ isOpen: false, errors: [] }" 
     x-on:show-batch-errors.window="isOpen = true; errors = $event.detail.errors"
     x-show="isOpen" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" @click="isOpen = false"></div>
        
        <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6">
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
                <template x-if="errors.length === 0">
                    <p class="text-center text-gray-500 py-4">Nenhum detalhe de erro disponível.</p>
                </template>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button @click="isOpen = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- Alpine.js controller for batch history interactions --}}
<script>
function batchHistory() {
    return {
        _batchData: null,
        _batchItems: [],

        /**
         * Load and display batch details in the modal.
         * Fetches per-question items with before/after snapshots from the API.
         */
        async loadDetails(batchId) {
            try {
                const res = await fetch(`/admin/questions-batch/details/${batchId}`);
                const data = await res.json();
                if (data.success) {
                    this._batchData = data;
                    this._batchItems = data.items || [];
                    this.$dispatch('open-batch-details');
                } else {
                    alert('Erro ao carregar detalhes do lote.');
                }
            } catch (e) {
                console.error('loadDetails error:', e);
                alert('Erro de conexão ao carregar detalhes.');
            }
        },

        /**
         * Undo a single question's AI changes.
         * Restores the question to its pre-processing state using the stored snapshot.
         */
        async undoItem(itemId) {
            if (!confirm('Reverter esta questão ao estado anterior?')) return;
            try {
                const res = await fetch(`/admin/questions-batch/undo-item/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    // Update the item status in the current view
                    const item = this._batchItems.find(i => i.id === itemId);
                    if (item) item.status = 'reverted';
                    alert(data.message);
                } else {
                    alert(data.message || 'Erro ao reverter.');
                }
            } catch (e) {
                console.error('undoItem error:', e);
                alert('Erro de conexão ao reverter.');
            }
        },

        /**
         * Undo ALL processed questions in a batch.
         * Restores every question to its pre-processing state.
         */
        async undoBatch(batchId) {
            if (!confirm('Reverter TODAS as questões deste lote ao estado anterior? Essa ação não pode ser desfeita.')) return;
            try {
                const res = await fetch(`/admin/questions-batch/undo-batch/${batchId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                alert(data.message || 'Operação concluída.');
                if (data.success) location.reload();
            } catch (e) {
                console.error('undoBatch error:', e);
                alert('Erro de conexão.');
            }
        },

        /**
         * Retry failed items from a batch.
         * Creates a new batch with only the failed questions.
         */
        async retryBatch(batchId) {
            if (!confirm('Reprocessar as questões que falharam neste lote?')) return;
            try {
                const res = await fetch(`/admin/questions-batch/retry/${batchId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                alert(data.message || 'Operação concluída.');
                if (data.success) location.reload();
            } catch (e) {
                console.error('retryBatch error:', e);
                alert('Erro de conexão.');
            }
        }
    }
}
</script>
@endsection
