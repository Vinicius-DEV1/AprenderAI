    {{-- BATCH CONFIGURATION & PREVIEW MODAL --}}
    <div x-data="batchConfigurator" 
         @open-batch-modal.window="openModal()" 
         x-show="isOpen" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="isOpen" 
                 x-transition.opacity
                 class="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity" 
                 aria-hidden="true" @click="closeModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="isOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative inline-block align-bottom bg-white rounded-xl text-left shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full z-10 flex flex-col max-h-[90vh]"
                 @click.stop>
                
                {{-- STEP 1: CONFIGURATION --}}
                <div x-show="step === 'config'" class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 rounded-t-xl overflow-y-auto">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-xl leading-6 font-bold text-gray-900">
                                Configurar Lote de IA
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Defina a quantidade e o tipo de triagem a ser executada em massa.</p>

                            <div class="mt-6 grid grid-cols-1 gap-6">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Quantidade Máxima (Disponível: {{ $pendingCount }})</label>
                                        <input type="number" x-model.number="quantity" max="{{ $pendingCount }}" min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
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

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Ações da IA</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500" :class="{'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50': type === 'complete'}">
                                            <input type="radio" x-model="type" value="complete" class="sr-only">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">🚀 Completo (Recomendado)</span>
                                                <span class="block text-xs text-gray-500 mt-1">Dificuldade, explicação e taxonomia</span>
                                            </div>
                                        </label>
                                        
                                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500" :class="{'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50': type === 'difficulty'}">
                                            <input type="radio" x-model="type" value="difficulty" class="sr-only">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">⚡ Apenas Dificuldade</span>
                                                <span class="block text-xs text-gray-500 mt-1">Gera nível e raciocínio</span>
                                            </div>
                                        </label>

                                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500" :class="{'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50': type === 'explanation'}">
                                            <input type="radio" x-model="type" value="explanation" class="sr-only">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">📝 Apenas Explicação</span>
                                                <span class="block text-xs text-gray-500 mt-1">Gera texto explicativo</span>
                                            </div>
                                        </label>

                                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500" :class="{'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50': type === 'classification'}">
                                            <input type="radio" x-model="type" value="classification" class="sr-only">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900">🏷️ Apenas Classificar</span>
                                                <span class="block text-xs text-gray-500 mt-1">Matéria e assunto</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: PREVIEW --}}
                <div x-show="step === 'preview'" class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 rounded-t-xl flex flex-col h-full overflow-hidden" style="display: none;">
                    <div class="flex justify-between items-center mb-4 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <button @click="step = 'config'" class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition-colors" title="Voltar configuration">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            </button>
                            <h3 class="text-xl leading-6 font-bold text-gray-900">
                                Pré-visualização do Lote
                            </h3>
                        </div>
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-bold" x-text="previewQuestions.length + ' questões selecionadas'"></span>
                    </div>

                    <p class="text-sm text-gray-500 mb-4 flex-shrink-0">Revise as questões que serão processadas. Você pode excluir itens se desejar. Apenas as listadas abaixo serão enviadas no lote.</p>
                    
                    <div class="flex-grow overflow-y-auto border border-gray-200 rounded-lg bg-gray-50">
                        <table class="min-w-full text-left text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 hidden sm:table-header-group shadow-sm">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-gray-700 w-20">ID</th>
                                    <th class="px-4 py-3 font-semibold text-gray-700">Enunciado / Status</th>
                                    <th class="px-4 py-3 font-semibold text-gray-700 w-48">Taxonomia Original</th>
                                    <th class="px-4 py-3 font-semibold text-gray-700 text-right w-24">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="(q, index) in previewQuestions" :key="q.id">
                                    <tr class="hover:bg-red-50 hover:bg-opacity-40 transition-colors group">
                                        <td class="px-4 py-3 align-top font-medium text-gray-900" x-text="'#' + q.id"></td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-xs text-gray-800 line-clamp-3 mb-1" x-text="q.statement"></div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600" x-text="q.status"></span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-xs font-semibold text-blue-700" x-text="q.subject"></div>
                                            <div class="text-[10px] text-gray-500 mt-0.5" x-text="q.organization"></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right">
                                            <button @click="removeQuestion(index)" class="text-red-400 hover:text-red-700 p-1.5 rounded text-xs font-medium bg-red-50 hover:bg-red-100 transition-colors inline-flex items-center gap-1" title="Remover questão do lote">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Remover
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="previewQuestions.length === 0">
                                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        Nenhuma questão selecionada no lote.<br><span class="text-xs">Clique em voltar para adicionar mais.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-4 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 rounded-b-xl border-t border-gray-200 flex-shrink-0">
                    <button @click="closeModal()" type="button" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto transition-colors disabled:opacity-50" :disabled="isLoading">
                        Cancelar
                    </button>

                    {{-- Step 1 Button --}}
                    <button x-show="step === 'config'" @click="fetchPreview()" type="button" :disabled="isLoading || pendingCount == 0" class="w-full inline-flex justify-center items-center gap-2 rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto transition-colors disabled:opacity-50">
                        <span x-show="!isLoading">Ver Prévia →</span>
                        <span x-show="isLoading" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Carregando...
                        </span>
                    </button>

                    {{-- Step 2 Buttons --}}
                    <div x-show="step === 'preview'" class="flex flex-col sm:flex-row w-full sm:w-auto gap-3" style="display: none;">
                        <button @click="startBatch()" type="button" :disabled="isLoading || previewQuestions.length === 0" class="w-full inline-flex justify-center items-center gap-2 rounded-lg border border-transparent shadow-sm px-8 py-2.5 bg-green-600 text-sm font-bold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto transition-colors disabled:opacity-50">
                            <span x-show="!isLoading">🚀 Confirmar e Iniciar Lote</span>
                            <span x-show="isLoading" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Iniciando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('batchConfigurator', () => ({
                isOpen: false,
                step: 'config', // 'config' or 'preview'
                isLoading: false,
                quantity: 10,
                type: 'complete',
                model: '{{ $aiModels->first()?->preferred_model ?? "" }}',
                pendingCount: {{ $pendingCount ?? 0 }},
                previewQuestions: [],

                openModal() {
                    this.step = 'config';
                    this.isOpen = true;
                    this.isLoading = false;
                },

                closeModal() {
                    if (this.isLoading) return;
                    this.isOpen = false;
                },

                // Step 1: Fetch Preview
                async fetchPreview() {
                    this.isLoading = true;
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    try {
                        const response = await fetch('{{ route('admin.questions.batch.preview') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                quantity: this.quantity,
                                type: this.type,
                                triage_status: urlParams.get('triage_status'),
                                triage_subject: urlParams.get('triage_subject'),
                                triage_origin: urlParams.get('triage_origin')
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.previewQuestions = data.questions;
                            this.step = 'preview';
                        } else {
                            showToast(data.message || 'Erro ao carregar prévia.', 'error');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro técnico ao consultar questões.', 'error');
                    } finally {
                        this.isLoading = false;
                    }
                },

                removeQuestion(index) {
                    this.previewQuestions.splice(index, 1);
                },

                // Step 2: Confirm and Start
                async startBatch() {
                    if (this.previewQuestions.length === 0) return;
                    
                    this.isLoading = true;
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    try {
                        const response = await fetch('{{ route('admin.questions.batch.start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                quantity: this.previewQuestions.length,
                                type: this.type,
                                model: this.model,
                                question_ids: this.previewQuestions.map(q => q.id),
                                // send fallback filters just in case
                                triage_status: urlParams.get('triage_status'),
                                triage_subject: urlParams.get('triage_subject'),
                                triage_origin: urlParams.get('triage_origin')
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.dispatchEvent(new CustomEvent('batch-started', { 
                                detail: { batchId: data.batch_id } 
                            }));
                            this.closeModal();
                            showToast('Lote iniciado com sucesso! Monitorando log...', 'success');
                        } else {
                            showToast(data.message || 'Erro ao iniciar lote.', 'error');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro técnico ao iniciar lote.', 'error');
                    } finally {
                        this.isLoading = false;
                    }
                }
            }));
        });
    </script>
    @endpush
