@extends('layouts.admin')

@section('content')
<div class="p-6" x-data="{}">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Histórico de Triagem IA</h1>
            <p class="text-gray-600">Monitore o progresso e erros de todos os lotes processados.</p>
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
                            @default
                                <span class="text-gray-400 text-sm">{{ $batch->status }}</span>
                        @endswitch
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($batch->status === 'processing')
                            @if($batch->updated_at < now()->subMinutes(15))
                                <span class="text-xs font-semibold text-orange-500 uppercase tracking-widest mr-2">Estagnado</span>
                            @else
                                <button @click="$dispatch('open-batch-monitor', { batchId: '{{ $batch->batch_id }}' })" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                    Monitorar
                                </button>
                            @endif
                        @endif
                        
                        @if($batch->error_count > 0 || $batch->status === 'failed')
                            <button @click="$dispatch('show-batch-errors', { errors: @js($batch->errors_log ?? []) })" 
                                    class="ml-3 text-red-600 hover:text-red-900 font-medium text-sm">
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

{{-- Modal de Erros (Simples para reaproveitamento) --}}
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
@endsection
