<x-layouts.admin>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📦 Importação de Questões
            </h2>
            <a href="{{ route('admin.import.review.index') }}"
               class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 text-sm font-medium flex items-center gap-2">
                🔍 Painel de Revisão
                @php $pendingCount = \App\Models\Question::where('review_status', 'pending')->count(); @endphp
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
                     uploading: false,       /* Controla o estado de carregamento */
                     fileName: '',           /* Nome do arquivo selecionado */
                     fileSizeMB: '',         /* Tamanho formatado para exibição */
                     selectFile(event) {     /* Chamado quando o usuário escolhe o arquivo */
                         const file = event.target.files[0];
                         if (file) {
                             this.fileName = file.name;
                             this.fileSizeMB = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                         }
                     },
                     submit(event) {         /* Chamado ao enviar o formulário */
                         this.uploading = true;
                     }
                 }">
                <div class="p-6">
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
                          @submit.prevent="submit($event); $el.submit()">
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
