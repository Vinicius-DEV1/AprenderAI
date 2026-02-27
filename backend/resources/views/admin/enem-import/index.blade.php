@extends('layouts.admin')

@section('content')
<div class="container mx-auto" x-data="enemImport()">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Importação ENEM Dev API</h1>
            <p class="text-gray-600 text-sm mt-1">Ferramenta para reabastecimento automático via API Pública.</p>
        </div>
    </div>

    <!-- Seção do Progresso Ativo -->
    @if($activeBatch)
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Lote em Andamento</h2>
            <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" :style="`width: ${progress}%`"></div>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span x-text="`Progresso: ${progress}%`">Progresso: {{ $activeBatch->progress() }}%</span>
                <span x-text="`Sub-tarefas (Anos): ${processed} de ${total}`">Sub-tarefas (Anos): {{ $activeBatch->processedJobs() }} de {{ $activeBatch->totalJobs }}</span>
            </div>
            
            <template x-if="isFinished">
                <div class="mt-4 p-3 bg-green-50 border-l-4 border-green-500 text-green-700">
                    O processamento em lote foi concluído! Recarregando sistema...
                </div>
            </template>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Disparar Importação -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Novo Acionamento</h2>
                
                <form action="{{ route('admin.enem-import.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ano Opcional</label>
                        <input type="number" name="year" min="2009" max="{{ date('Y') }}" placeholder="Ex: 2022"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <p class="text-xs text-gray-500 mt-1">Deixe em branco para importar TODOS os anos disponíveis (Atenção: muito demorado!).</p>
                    </div>

                    <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors {{ $activeBatch && !$activeBatch->finished() ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ $activeBatch && !$activeBatch->finished() ? 'disabled' : '' }}>
                        INICIAR INTEGRAÇÃO
                    </button>
                    
                    <div class="mt-4 p-3 bg-blue-50 border border-blue-100 text-blue-700 text-sm rounded-lg">
                        <strong>Transacional e Idempotente</strong>
                        <p class="mt-1 text-xs">A importação atualizará apenas registros que não foram importados ainda no banco de dados. Múltiplos acionamentos são seguros.</p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Histórico e Logs -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Histórico de Importações</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Iniciado Em</th>
                                <th class="px-6 py-3">Alvo</th>
                                <th class="px-6 py-3">Inseridas</th>
                                <th class="px-6 py-3">Ignoradas</th>
                                <th class="px-6 py-3">Erros</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr class="border-b bg-white hover:bg-gray-50">
                                    <td class="px-6 py-4">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 font-medium">{{ $log->year == 0 ? 'COMPLETO (TUDO)' : 'Ano ' . $log->year }}</td>
                                    <td class="px-6 py-4 text-green-600">{{ $log->inserted_count }}</td>
                                    <td class="px-6 py-4 text-gray-500">
                                        {{ $log->ignored_count }}
                                        @if($log->ignored_count > 0 && !empty($log->ignored_details))
                                            <button @click='openIgnoredModal(@json($log->ignored_details))' class="ml-2 text-xs text-blue-500 hover:underline" title="Ver Detalhes">🔍</button>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-red-500">
                                        {{ $log->error_count }}
                                        @if($log->error_count > 0 && !empty($log->errors))
                                            <button @click='openErrorModal(@json($log->errors))' class="ml-2 text-xs text-blue-500 hover:underline">Ver Falhas</button>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($log->status === 'completed')
                                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Concluído</span>
                                        @elseif($log->status === 'failed')
                                            <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">Falhou</span>
                                        @else
                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Em Progresso</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Nenhum evento de integração registrado no histórico.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($logs->hasPages())
                    <div class="p-4 border-t border-gray-100">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de Erros -->
    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity z-40" aria-hidden="true" @click="showModal = false">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Relatório de Diagnóstico</h3>
                    <div class="mt-2 max-h-64 overflow-y-auto rounded bg-gray-100 p-2 text-sm font-mono text-red-600">
                        <template x-for="err in currentErrors">
                            <div class="mb-2 p-1 border-b border-gray-200" x-text="err"></div>
                        </template>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="showModal = false">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Itens Ignorados -->
    <div x-show="showIgnoredModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity z-40" aria-hidden="true" @click="showIgnoredModal = false">
                <div class="absolute inset-0 bg-gray-900 opacity-60 backdrop-blur-sm"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative z-50 border border-gray-100">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>🕵️‍♂️</span> Itens Ignorados na Importação
                    </h3>
                </div>
                <div class="bg-white px-6 py-6 font-sans">
                    <div class="overflow-y-auto max-h-[75vh] pr-2 custom-scrollbar">
                        <div class="space-y-6">
                            <template x-for="item in currentIgnored">
                                <div class="bg-white border-2 border-gray-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-500 group">
                                    <!-- Header: ID + Motivo -->
                                    <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b border-gray-100">
                                        <div class="flex items-center gap-4">
                                            <div class="bg-blue-600 text-white text-[11px] font-black px-4 py-1.5 rounded-xl uppercase tracking-widest shadow-lg shadow-blue-200">
                                                ID #<span x-text="item.index"></span>
                                            </div>
                                            <div class="hidden sm:flex items-center gap-2 px-3 py-1 bg-white rounded-lg border border-gray-200 shadow-sm">
                                                <span class="text-gray-400 text-[10px] font-bold uppercase">Prova:</span>
                                                <span class="text-gray-800 text-[10px] font-black uppercase" x-text="item.full_data?.year"></span>
                                            </div>
                                        </div>
                                        
                                        <template x-if="item.reason === 'duplicate'">
                                            <span class="px-4 py-1.5 rounded-full bg-blue-50 text-blue-700 font-black text-[10px] uppercase border-2 border-blue-100">DUPLICATA</span>
                                        </template>
                                        <template x-if="item.reason === 'invalid_data'">
                                            <span class="px-4 py-1.5 rounded-full bg-orange-50 text-orange-700 font-black text-[10px] uppercase border-2 border-orange-100">DADOS INCOMPLETOS</span>
                                        </template>
                                    </div>

                                    <div class="p-6">
                                        <!-- Atributos -->
                                        <div class="flex flex-wrap gap-4 mb-6 pb-6 border-b border-gray-50">
                                            <div class="flex flex-col">
                                                <span class="text-[9px] font-bold text-blue-500 uppercase tracking-widest">Disciplina</span>
                                                <span class="text-xs font-black text-gray-800" x-text="item.full_data?.discipline"></span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest">Assunto</span>
                                                <span class="text-xs font-black text-gray-800" x-text="item.full_data?.topic || 'Geral'"></span>
                                            </div>
                                            <div x-show="item.full_data?.language" class="flex flex-col">
                                                <span class="text-[9px] font-bold text-purple-500 uppercase tracking-widest">Língua</span>
                                                <span class="text-xs font-black text-gray-800" x-text="item.full_data?.language"></span>
                                            </div>
                                        </div>

                                        <!-- Enunciado e Imagens -->
                                        <div class="prose prose-sm max-w-none text-gray-800">
                                            <!-- Imagens do Enunciado -->
                                            <div class="flex flex-wrap gap-4 mb-4">
                                                <template x-for="img in extractImages(item.full_data?.context)">
                                                    <div class="relative group/img">
                                                        <img :src="img" class="max-h-64 rounded-xl border-2 border-gray-100 shadow-sm hover:scale-105 transition-transform duration-300">
                                                        <div class="absolute top-2 right-2 bg-black/50 text-white text-[8px] font-bold px-2 py-1 rounded-md opacity-0 group-hover/img:opacity-100 transition-opacity">ENUNCIADO</div>
                                                    </div>
                                                </template>
                                            </div>

                                            <div class="font-bold text-lg leading-relaxed mb-4" x-text="item.full_data?.context.replace(/!\[.*?\]\(.*?\)/g, '')"></div>
                                            
                                            <div x-show="item.full_data?.alternativesIntroduction" 
                                                 class="text-sm font-medium text-gray-600 bg-gray-50 p-3 rounded-xl border-l-4 border-gray-200 mb-6"
                                                 x-text="item.full_data?.alternativesIntroduction">
                                            </div>
                                        </div>

                                        <!-- Alternativas -->
                                        <div class="mt-8 space-y-3">
                                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Alternativas</span>
                                            <template x-for="alt in item.full_data?.alternatives">
                                                <div class="flex items-start gap-4 p-4 rounded-2xl border-2 transition-all duration-200"
                                                     :class="alt.isCorrect ? 'bg-green-50/50 border-green-200 shadow-sm' : 'bg-white border-gray-50 hover:border-gray-100'">
                                                    
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center font-black text-sm transition-colors"
                                                         :class="alt.isCorrect ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'bg-gray-100 text-gray-500'">
                                                        <span x-text="alt.letter"></span>
                                                    </div>

                                                    <div class="flex-1">
                                                        <div class="flex flex-col gap-3">
                                                            <p class="text-sm font-bold text-gray-800 pt-1" x-text="alt.text"></p>
                                                            
                                                            <!-- Imagem da Alternativa -->
                                                            <template x-if="alt.file">
                                                                <img :src="alt.file" class="max-h-48 w-fit rounded-lg border border-gray-100 shadow-sm mt-2">
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <template x-if="alt.isCorrect">
                                                        <div class="flex-shrink-0 text-green-600">
                                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20 shadow-sm"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-8 flex justify-center border-t border-gray-100">
                    <button type="button" class="group flex items-center gap-4 px-12 py-4 bg-gray-900 border-2 border-gray-900 rounded-3xl shadow-xl shadow-gray-200 text-sm font-black text-white hover:bg-black hover:scale-105 transition-all duration-300 focus:outline-none" @click="showIgnoredModal = false">
                        <span>FECHAR DOCUMENTO</span>
                        <kbd class="hidden sm:inline-block px-2 py-0.5 text-[10px] font-bold bg-gray-700 text-gray-300 border border-gray-600 rounded-lg">ESC</kbd>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('enemImport', () => ({
            showModal: false,
            showIgnoredModal: false,
            currentErrors: [],
            currentIgnored: [],
            
            // Polling Data
            progress: {{ $activeBatch ? $activeBatch->progress() : 0 }},
            processed: {{ $activeBatch ? $activeBatch->processedJobs() : 0 }},
            total: {{ $activeBatch ? $activeBatch->totalJobs : 0 }},
            isFinished: {{ $activeBatch && ($activeBatch->finished() || $activeBatch->cancelled()) ? 'true' : 'false' }},
            batchId: '{{ $activeBatch ? $activeBatch->id : "" }}',
            pollingInterval: null,

            init() {
                if (this.batchId && !this.isFinished) {
                    this.startPolling();
                } else if (this.isFinished && this.batchId) {
                    // Já terminou, dá o último reset de limpeza.
                    setTimeout(() => window.location.reload(), 2000);
                }
            },

            startPolling() {
                this.pollingInterval = setInterval(async () => {
                    try {
                        const response = await fetch(`/admin/enem-import/status?batch_id=${this.batchId}`);
                        const data = await response.json();
                        
                        this.progress = data.progress;
                        this.processed = data.processed;
                        this.total = data.total;
                        
                        // Assim que bater 100% ou Cancelado
                        if (data.finished) {
                            this.isFinished = true;
                            clearInterval(this.pollingInterval); // Quebra o Polling
                            setTimeout(() => window.location.reload(), 2000); // Reload final pra limpar BD/Session
                        }
                    } catch (err) {
                        console.error('Polling error:', err);
                    }
                }, 2000);
            },

            openErrorModal(errors) {
                this.currentErrors = errors;
                this.showModal = true;
            },

            openIgnoredModal(ignored) {
                this.currentIgnored = ignored;
                this.showIgnoredModal = true;
            },

            extractImages(text) {
                if (!text) return [];
                const regex = /!\[.*?\]\((.*?)\)/g;
                const matches = [];
                let match;
                while ((match = regex.exec(text)) !== null) {
                    matches.push(match[1]);
                }
                return matches;
            }
        }))
    })
</script>
@endsection
