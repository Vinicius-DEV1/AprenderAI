<x-layouts.admin>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📦 Importação de Questões
            </h2>
            <a href="{{ route('admin.import.review.index') }}"
               class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 text-sm font-medium flex items-center gap-2">
                🔍 Painel de Revisão
                @php $pendingCount = \App\Models\Question::where('review_status', 'review')->count(); @endphp
                @if($pendingCount > 0)
                    <span class="bg-white text-yellow-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingCount }}</span>
                @endif
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ALERTAS DE SESSÃO --}}
            @if(session('success'))
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <p class="text-green-800 font-medium">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    <p class="text-red-800 font-medium">{{ session('error') }}</p>
                </div>
            @endif

            {{-- CARD DE UPLOAD --}}
            {{-- 
                O formulário usa enctype="multipart/form-data" para envio binário do .zip.
                O Alpine.js gerencia o estado de feedback visual durante o upload.
                O atributo x-data define o estado reativo local deste componente.
            --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg"
                 x-data="{
                     uploading: false,
                     fileName: '',
                     fileSizeMB: '',
                     
                     progressMode: false,
                     showBanner: false, // Controla o mini-banner persistente
                     progressPercent: 0,
                     totalQuestions: 0,
                     processedQuestions: 0,
                     statusText: 'Iniciando Processo...',
                     importId: null,
                     pollInterval: null,
                     
                     init() {
                         // Executa ao carregar a página: verifica se tem job em background persistente
                         this.checkActiveJob();
                     },

                     async checkActiveJob() {
                         try {
                             let response = await fetch('{{ route('admin.import.active-job') }}');
                             let data = await response.json();
                             
                             if (data.active) {
                                 this.importId = data.import_id;
                                 this.progressMode = false; // Não trava a tela se já estava em background
                                 this.showBanner = true;    // Mas mostra o banner superior
                                 this.totalQuestions = data.total;
                                 this.processedQuestions = data.processed;
                                 this.startPolling();
                             }
                         } catch (e) {
                             console.warn('Silent fail check active job');
                         }
                     },

                     selectFile(event) {
                         const file = event.target.files[0];
                         if (file) {
                             this.fileName = file.name;
                             this.fileSizeMB = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                         }
                     },
                     
                     async submit() {
                        if (!this.fileName || this.uploading || this.pollInterval) {
                            alert('Já existe uma operação em andamento paralela. Aguarde!');
                            return;
                        }
                        
                        this.uploading = true;
                        this.progressMode = true; // Modal subindo
                        this.showBanner = false;
                        this.statusText = 'Fazendo upload do arquivo .zip (Demorará conforme a internet)...';
                        
                        let formData = new FormData();
                        formData.append('zip_file', document.getElementById('zip_file').files[0]);
                        formData.append('_token', '{{ csrf_token() }}');

                        try {
                            let response = await fetch('{{ route('admin.import.store') }}', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            let result = await response.json();

                            if (response.ok && result.success) {
                                this.importId = result.import_id;
                                this.statusText = 'Criando lote e enviando para o Worker extrair...';
                                this.startPolling();
                                // Após disparar pro Worker, soltamos o form e ele desminiza se quiser
                                this.uploading = false; 
                                document.getElementById('zip_file').value = '';
                                this.fileName = '';
                            } else {
                                alert(result.error || 'Falha ao processar arquivo no servidor.');
                                this.resetUpload();
                            }
                        } catch (e) {
                            alert('Erro crítico ao comunicar com o servidor.');
                            this.resetUpload();
                        }
                     },

                     moveToBackground() {
                         // Fechamos o modal Dark, e ativamos o banner persistente do painel (mantendo poller ligado)
                         this.progressMode = false;
                         this.showBanner = true;
                     },

                     startPolling() {
                        if (this.pollInterval) clearInterval(this.pollInterval);

                        this.pollInterval = setInterval(async () => {
                            try {
                                let response = await fetch(`/admin/import/${this.importId}/progress`, {
                                    headers: { 'Accept': 'application/json' }
                                });
                                let data = await response.json();

                                if (data.status === 'processing' || data.status === 'completed' || data.status === 'pending') {
                                    this.totalQuestions = data.total;
                                    this.processedQuestions = data.processed;
                                    
                                    if (data.total > 0) {
                                        this.progressPercent = Math.round((data.processed / data.total) * 100);
                                        this.statusText = `Inserindo via Job: ${data.processed} de ${data.total} questões (${this.progressPercent}%).`;
                                    } else {
                                        this.statusText = 'Extraindo o ZIP e aguardando processamento inicial...';
                                    }

                                    if (data.status === 'completed') {
                                        this.progressPercent = 100;
                                        this.statusText = '✅ Importação finalizada com sucesso!';
                                        this.showBanner = false;
                                        clearInterval(this.pollInterval);
                                        
                                        // Não recarrega abruptamente para não atrapalhar, apenas dá um aviso.
                                        alert('Lote importado e 100% processado! Clique no Painel de Revisão para gerenciar as novas imagens.');
                                        
                                        setTimeout(() => {
                                            this.progressMode = false;
                                            window.location.reload();
                                        }, 3000);
                                    }
                                } else if (data.status === 'failed') {
                                    clearInterval(this.pollInterval);
                                    alert('A importação falhou no Job em Background: ' + (data.error || 'Erro Desconhecido'));
                                    this.resetUpload();
                                }

                            } catch (e) {
                                console.error('Erro de polling ignorado', e);
                            }
                        }, 2500);
                     },

                     resetUpload() {
                        this.uploading = false;
                        this.progressMode = false;
                        this.showBanner = false;
                        this.fileName = '';
                        this.fileSizeMB = '';
                        this.progressPercent = 0;
                        document.getElementById('zip_file').value = '';
                        if (this.pollInterval) clearInterval(this.pollInterval);
                     }
                 }">
                {{-- BANNER PERSISTENTE DE FUNDO (Aparece invés do Modal se recarregar a tela ou encolher) --}}
                <div x-show="showBanner" style="display: none;" class="bg-indigo-600 text-white rounded-t-lg rounded-b-none px-6 py-4 flex items-center justify-between shadow-md mb-0">
                    <div class="flex items-center gap-4 w-full">
                        <svg class="animate-spin w-6 h-6 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <div class="flex-1">
                            <h4 class="font-bold text-sm uppercase tracking-wide">Importação em Background (Lote)</h4>
                            <p class="text-xs text-indigo-200 mt-0.5" x-text="statusText"></p>
                            
                            {{-- Mini progressBar --}}
                            <div class="w-full bg-indigo-800 rounded-full h-1.5 mt-2">
                              <div class="bg-blue-300 h-1.5 rounded-full transition-all duration-300" :style="`width: ${progressPercent}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6" :class="{ 'rounded-t-none border-t border-indigo-400': showBanner }">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                        <div class="p-3 rounded-full bg-indigo-50">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Novo Upload de Questões</h3>
                            <p class="text-sm text-gray-500">Envie o arquivo <code class="bg-gray-100 px-1 rounded">.zip</code> gerado pelo <code class="bg-gray-100 px-1 rounded">scraper.py</code></p>
                        </div>
                    </div>

                    <form method="POST" 
                          action="{{ route('admin.import.store') }}"
                          enctype="multipart/form-data"
                          @submit.prevent="submit">
                        @csrf

                        {{-- Zona de drag-and-drop para o arquivo .zip --}}
                        <label for="zip_file" 
                               class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors"
                               :class="{ 'border-indigo-400 bg-indigo-50': fileName }">
                            
                            {{-- Estado: Nenhum arquivo selecionado --}}
                            <div x-show="!fileName" class="flex flex-col items-center gap-2 text-gray-400">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-sm font-medium">Clique para selecionar o arquivo <strong>.zip</strong></p>
                                <p class="text-xs">Exportação do scraper.py (máx. 200MB)</p>
                            </div>

                            {{-- Estado: Arquivo selecionado --}}
                            <div x-show="fileName" class="flex flex-col items-center gap-2 text-indigo-600">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm font-medium" x-text="fileName"></p>
                                <p class="text-xs text-indigo-400" x-text="fileSizeMB"></p>
                            </div>

                            <input id="zip_file" name="zip_file" type="file" accept=".zip" class="hidden"
                                   @change="selectFile($event)">
                        </label>

                        @error('zip_file')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Instruções --}}
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700 space-y-1">
                            <p class="font-medium">📋 Estrutura esperada do .zip:</p>
                            <ul class="list-disc list-inside space-y-0.5 text-blue-600">
                                <li>Um arquivo <code class="bg-blue-100 px-1 rounded">banco_[banca].db</code> na raiz</li>
                                <li>Uma pasta <code class="bg-blue-100 px-1 rounded">imagens/</code> com os arquivos .jpg das questões</li>
                            </ul>
                            <p class="text-xs text-blue-500 mt-1">Questões com imagens ou alternativas visuais ficarão como <strong>Pendentes</strong> até a revisão manual.</p>
                        </div>

                        {{-- Botão de envio com estado de carregamento --}}
                        <div class="mt-6 flex justify-end">
                            <button type="submit"
                                    :disabled="!fileName || uploading"
                                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                
                                {{-- Ícone de spinner durante o upload --}}
                                <svg x-show="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                
                                <span x-text="uploading ? 'Processando... Aguarde' : 'Enviar e Importar'"></span>
                            </button>
                        </div>
                    </form>
                </div>
                
                {{-- Modal de Progresso Bloqueador --}}
                <div x-show="progressMode" 
                     style="display: none;" 
                     class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm">
                    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 transform transition-all text-center">
                        <div class="mb-6 flex justify-center">
                            <div class="p-3 bg-indigo-50 rounded-full">
                                <svg class="animate-spin w-12 h-12 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Processando Importação</h3>
                        
                        <p class="text-sm text-gray-500 mb-6 font-medium bg-gray-50 py-2 px-3 rounded-lg border border-gray-100" x-text="statusText"></p>
                        
                        {{-- Barra Visual HTML5 Styled --}}
                        <div class="relative w-full h-4 bg-gray-200 rounded-full overflow-hidden shadow-inner mb-2">
                            <div class="absolute top-0 left-0 h-full bg-indigo-600 transition-all duration-500 ease-out"
                                 :style="`width: ${progressPercent}%`">
                                 <div class="w-full h-full opacity-20 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwYXRoIGQ9Ik0tMSsxbDgtOFptMi0yTDggNW0tMiAyTDggNyIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjZmZmIiBzdHJva2Utd2lkdGg9IjEuNSIvPjwvc3ZnPg==')] bg-repeat" style="background-size: 16px;"></div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between text-xs text-gray-500 font-bold uppercase tracking-wider mt-3">
                            <span>0%</span>
                            <span x-text="`${progressPercent}%`" class="text-indigo-600"></span>
                            <span>100%</span>
                        </div>
                        
                        
                        <p class="mt-5 text-xs text-gray-400 mb-4">Você também pode enviar este processo para background caso queira sair ou navegar em outras abas.</p>
                        
                        <button type="button" 
                                @click="moveToBackground()"
                                class="w-full inline-flex justify-center flex-row items-center gap-2 py-2.5 px-4 text-sm font-medium border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 hover:bg-gray-50 focus:outline-none transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            Ocultar Modal e Rodar em Solitário
                        </button>
                    </div>
                </div>

            </div>

            {{-- HISTÓRICO DE LOTES DE IMPORTAÇÃO --}}
            @if($imports->isNotEmpty())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Histórico de Importações</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lote</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pendentes</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aprovadas</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($imports as $import)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $import->batch_name }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $import->original_filename ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $import->uploader->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ $import->total_questions }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($import->pending_count > 0)
                                        <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">{{ $import->pending_count }}</span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">{{ $import->approved_count }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusMap = [
                                            'processing' => ['bg-blue-100 text-blue-700', '⏳ Processando'],
                                            'completed'  => ['bg-green-100 text-green-700', '✅ Concluído'],
                                            'failed'     => ['bg-red-100 text-red-700', '❌ Falhou'],
                                        ];
                                        [$statusClass, $statusLabel] = $statusMap[$import->status] ?? ['bg-gray-100 text-gray-600', $import->status];
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    @if($import->pending_count > 0)
                                    <a href="{{ route('admin.import.review.index', ['import_id' => $import->id]) }}"
                                       class="px-3 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600 font-medium">
                                        Revisar
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @if($import->status === 'failed' && $import->error_message)
                            <tr>
                                <td colspan="9" class="px-4 py-2 bg-red-50 text-xs text-red-600">
                                    <strong>Erro:</strong> {{ $import->error_message }}
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-layouts.admin>
