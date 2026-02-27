<x-layouts.admin>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.import.review.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Questão #{{ $question->id }} — Inspeção Visual
            </h2>
            <span class="px-3 py-1 rounded-full text-sm font-medium
                {{ $question->review_status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                {{ $question->review_status === 'pending' ? '? Pendente' : '? Aprovada' }}
            </span>
        </div>
    </x-slot>

    {{--
        =========================================================
        CARREGA O CROPPER.JS VIA CDN (sem dependência npm adicional)
        Cropper.js é uma biblioteca pura para recorte interativo de imagens.
        Documentação: https://github.com/fengyuanchen/cropperjs
        =========================================================
    --}}
    @push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    @endpush

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- ALERTAS DE SESSÃO --}}
            @if(session('success'))
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-green-800 font-medium">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <p class="text-red-800 font-medium">{{ session('error') }}</p>
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

                {{-- =================================================================== --}}
                {{-- COLUNA ESQUERDA: Enunciado, Metadados, Alternativas e Ações --}}
                {{-- =================================================================== --}}
                <div class="xl:col-span-2 space-y-4">

                    {{-- Card de Metadados --}}
                    <div class="bg-white rounded-lg shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Metadados</h3>
                        <dl class="space-y-2 text-sm">
                            @if($question->organization)
                            <div class="flex gap-2"><dt class="text-gray-500 w-20 flex-shrink-0">Banca</dt><dd class="font-medium text-gray-800">{{ $question->organization }}</dd></div>
                            @endif
                            @if($question->institution)
                            <div class="flex gap-2"><dt class="text-gray-500 w-20 flex-shrink-0">Órgão</dt><dd class="text-gray-700">{{ $question->institution }}</dd></div>
                            @endif
                            @if($question->role)
                            <div class="flex gap-2"><dt class="text-gray-500 w-20 flex-shrink-0">Cargo</dt><dd class="text-gray-700">{{ $question->role }}</dd></div>
                            @endif
                            @if($question->year)
                            <div class="flex gap-2"><dt class="text-gray-500 w-20 flex-shrink-0">Ano</dt><dd class="text-gray-700">{{ $question->year }}</dd></div>
                            @endif
                            @if($question->subjects->isNotEmpty())
                            <div class="flex gap-2 flex-wrap"><dt class="text-gray-500 w-20 flex-shrink-0">Matérias</dt>
                                <dd class="flex flex-wrap gap-1">
                                    @foreach($question->subjects as $s)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">{{ $s->name }}</span>
                                    @endforeach
                                </dd>
                            </div>
                            @endif
                            @if($importItem)
                            <div class="flex gap-2"><dt class="text-gray-500 w-20 flex-shrink-0">Lote</dt><dd class="text-gray-600 text-xs">{{ $importItem->import->batch_name ?? '—' }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    {{-- Card do Enunciado --}}
                    <div class="bg-white rounded-lg shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Enunciado</h3>
                        {{-- Renderização segura do enunciado com suporte a Markdown de imagens --}}
                        <div class="text-gray-800 text-sm leading-relaxed">{!! $question->statement_html !!}</div>
                        
                        {{-- Preview das imagens associadas ao enunciado (se houver) --}}
                        @if($question->images->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">??? Imagens do Enunciado</p>
                            <div class="space-y-3">
                                @foreach($question->images as $img)
                                <img id="statement-img-preview-{{ $img->id }}" 
                                     src="{{ Storage::url($img->path) }}?t={{ time() }}" 
                                     alt="Imagem do Enunciado"
                                     class="max-w-full h-auto rounded border border-gray-200">
                                @endforeach
                            </div>
                        </div>
                        @else
                            {{-- Fallback para questões antigas que ainda possam ter image_path direto --}}
                            @if($question->image_path)
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">??? Imagem do Enunciado</p>
                                <img id="statement-img-preview-legacy" 
                                     src="{{ Storage::url($question->image_path) }}?t={{ time() }}" 
                                     alt="Imagem do Enunciado"
                                     class="max-w-full h-auto rounded border border-gray-200">
                            </div>
                            @endif
                        @endif
                    </div>

                    {{-- Card das Alternativas --}}
                    <div class="bg-white rounded-lg shadow-sm p-5" id="alternatives-card">
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Alternativas</h3>
                        @if($question->alternatives->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($question->alternatives->sortBy('label') as $alt)
                                <div class="flex gap-3 p-3 rounded-lg {{ $alt->is_correct ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}"
                                     id="alt-{{ $alt->label }}">
                                    <span class="font-bold text-sm w-6 flex-shrink-0 {{ $alt->is_correct ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $alt->label }})
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        {{-- Verifica se o conteúdo é uma URL de imagem (recorte já feito) --}}
                                        @if(Str::startsWith($alt->content, '/storage') || Str::startsWith($alt->content, 'http'))
                                            <img src="{{ $alt->content }}" 
                                                 alt="Alternativa {{ $alt->label }}"
                                                 class="max-w-full h-auto rounded border border-gray-200">
                                        @else
                                            <p class="text-sm text-gray-700">{{ $alt->content }}</p>
                                        @endif
                                    </div>
                                    @if($alt->is_correct)
                                    <span class="text-green-600 text-xs font-semibold flex-shrink-0">? Gabarito</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
                                <p class="text-orange-700 text-sm font-medium">?? Nenhuma alternativa textual</p>
                                <p class="text-orange-600 text-xs mt-1">Use o editor de crop ao lado para recortar as alternativas da imagem.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Ações da Questão --}}
                    <div class="bg-white rounded-lg shadow-sm p-5 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Ações</h3>

                        {{-- Botão Aprovar --}}
                        @if($question->review_status === 'pending')
                        <form method="POST" action="{{ route('admin.import.review.approve', $question) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium text-sm flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Aprovar e Publicar Questão
                            </button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('admin.import.review.revert', $question) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full px-4 py-3 bg-orange-500 text-white rounded-md hover:bg-orange-600 font-medium text-sm flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                Retornar para Revisão
                            </button>
                        </form>
                        @endif

                        {{-- Link para editar via editor completo --}}
                        <a href="{{ route('admin.questions.edit', $question) }}"
                           class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 font-medium text-sm flex items-center justify-center gap-2">
                            ?? Abrir no Editor Completo
                        </a>

                        {{-- Navegação entre questões pendentes --}}
                        <div class="flex gap-2 pt-2 border-t border-gray-100">
                            <a href="{{ route('admin.import.review.index') }}" class="flex-1 px-3 py-2 bg-gray-100 text-gray-600 rounded-md text-sm text-center hover:bg-gray-200">
                                ?? Voltar à Lista
                            </a>
                        </div>

                        {{-- Informação do auditoria --}}
                        @if($importItem && $importItem->approver)
                        <div class="pt-2 border-t border-gray-100 text-xs text-gray-500">
                            @if($importItem->reverted_at)
                            <p>?? Revertida {{ $importItem->reverted_at->diffForHumans() }}</p>
                            @endif
                            @if($importItem->approved_at)
                            <p>? Aprovada por <strong>{{ $importItem->approver->name }}</strong> {{ $importItem->approved_at->diffForHumans() }}</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                {{-- =================================================================== --}}
                {{-- COLUNA DIREITA: Editor de Crop (Cropper.js) --}}
                {{-- =================================================================== --}}
                <div class="xl:col-span-3">
                @if($question->images->isNotEmpty())
                    <div class="space-y-6">
                    @foreach($question->images as $image)
                    {{--
                        Container Alpine.js para todo o fluxo de crop de UMA imagem.
                        O backend enviará requests para /review/{image}/crop.
                    --}}
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden"
                         x-data="{
                             cropper: null,
                             target: 'statement',
                             saving: false,
                             savedTarget: null,
                             errorMsg: null,
                             imageId: {{ $image->id }},

                             /* Inicializa o Cropper.js após o DOM estar pronto */
                             init() {
                                 const img = this.$refs.cropperImage;
                                 this.cropper = new Cropper(img, {
                                     viewMode: 1,
                                     dragMode: 'crop',
                                     aspectRatio: NaN,
                                     autoCropArea: 0.8,
                                     restore: false,
                                     guides: true,
                                     center: true,
                                     highlight: true,
                                     cropBoxMovable: true,
                                     cropBoxResizable: true,
                                     toggleDragModeOnDblclick: false,
                                 });
                             },

                             async saveCrop() {
                                 if (!this.cropper || this.saving) return;
                                 this.saving = true;
                                 this.errorMsg = null;

                                 const cropData = this.cropper.getData(true);

                                 if (cropData.width < 1 || cropData.height < 1) {
                                     this.errorMsg = 'Selecione uma área maior antes de salvar.';
                                     this.saving = false;
                                     return;
                                 }

                                 try {
                                     const response = await fetch('{{ url("/admin/import/review") }}/' + this.imageId + '/crop', {
                                         method: 'POST',
                                         headers: {
                                             'Content-Type': 'application/json',
                                             'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                             'Accept': 'application/json',
                                         },
                                         body: JSON.stringify({
                                             target: this.target,
                                             x: cropData.x,
                                             y: cropData.y,
                                             width: cropData.width,
                                             height: cropData.height,
                                         }),
                                     });

                                     const data = await response.json();

                                     if (data.success) {
                                         this.savedTarget = this.target;
                                         const url = data.url + '?t=' + Date.now();

                                         if (this.target === 'statement') {
                                             const previewEl = document.getElementById('statement-img-preview-' + this.imageId);
                                             if (previewEl) previewEl.src = url;

                                             this.cropper.replace(url);
                                         } else {
                                             const altDiv = document.getElementById('alt-' + this.target);
                                             if (altDiv) {
                                                 const contentEl = altDiv.querySelector('p, img');
                                                 if (contentEl) {
                                                     const img = document.createElement('img');
                                                     img.src = url;
                                                     img.className = 'max-w-full h-auto rounded border border-gray-200';
                                                     img.alt = 'Alternativa ' + this.target;
                                                     contentEl.replaceWith(img);
                                                 }
                                             } else {
                                                 window.location.reload();
                                             }

                                             const labels = ['A', 'B', 'C', 'D', 'E'];
                                             const nextIdx = labels.indexOf(this.target) + 1;
                                             if (nextIdx < labels.length) {
                                                 this.target = labels[nextIdx];
                                             }
                                         }

                                         setTimeout(() => this.savedTarget = null, 3000);
                                     } else {
                                         this.errorMsg = data.message || 'Falha ao salvar o recorte.';
                                     }
                                 } catch (err) {
                                     this.errorMsg = 'Erro de rede: ' + err.message;
                                 } finally {
                                     this.saving = false;
                                 }
                             },

                             async deleteImage() {
                                 if (!confirm('Remover esta imagem? Esta ação não pode ser desfeita.')) return;

                                 const response = await fetch('{{ url("/admin/import/review") }}/' + this.imageId + '/image', {
                                     method: 'DELETE',
                                     headers: {
                                         'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                         'Accept': 'application/json',
                                     },
                                 });

                                 const data = await response.json();
                                 if (data.success) {
                                     window.location.reload();
                                 } else {
                                     alert('Erro ao remover: ' + (data.message || 'Falha desconhecida'));
                                 }
                             }
                         }"
                         x-init="init()">

                        {{-- Cabeçalho do editor --}}
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">??? Editor de Recorte (ID: {{ $image->id }})</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Recorte para o <strong>Enunciado</strong> ou para uma <strong>Alternativa</strong>.</p>
                            </div>
                            <button @click="deleteImage()"
                                    class="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-md hover:bg-red-200 font-medium">
                                ??? Remover Imagem
                            </button>
                        </div>

                        {{-- Área do Cropper.js --}}
                        <div class="p-4 bg-gray-900">
                            <div class="max-h-[500px] overflow-hidden">
                                <img x-ref="cropperImage"
                                     src="{{ Storage::url($image->path) }}"
                                     alt="Imagem da questão #{{ $question->id }} - ID {{ $image->id }}"
                                     class="max-w-full"
                                     style="max-height: 500px; display: block; margin: 0 auto;">
                            </div>
                        </div>

                        {{-- ================================================= --}}
                        {{-- CONTROLES — dois grupos distintos de ação           --}}
                        {{-- ================================================= --}}
                        <div class="p-5 border-t border-gray-100 space-y-4">

                            {{-- --- GRUPO 1: ENUNCIADO ----------------------- --}}
                            <div class="rounded-lg border-2 p-3 transition-colors"
                                 :class="target === 'statement'
                                     ? 'border-blue-400 bg-blue-50'
                                     : 'border-gray-200 bg-gray-50'">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                                            ?? Enunciado
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            A imagem recortada substitui a imagem do enunciado.
                                        </p>
                                    </div>
                                    <button
                                        @click="target = 'statement'; saveCrop()"
                                        :disabled="saving"
                                        class="flex-shrink-0 px-4 py-2.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium text-sm flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                                        <svg x-show="saving && target === 'statement'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        ?? Confirmar no Enunciado
                                    </button>
                                </div>
                            </div>

                            {{-- --- GRUPO 2: ALTERNATIVAS --------------------- --}}
                            <div class="rounded-lg border-2 p-3 transition-colors"
                                 :class="target !== 'statement'
                                     ? 'border-indigo-400 bg-indigo-50'
                                     : 'border-gray-200 bg-gray-50'">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 mb-2">
                                    ?? Alternativa Visual
                                </p>
                                <div class="flex flex-wrap items-center gap-3">

                                    {{-- Seletor de letra --}}
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-gray-600 font-medium">Letra:</span>
                                        @foreach(['A', 'B', 'C', 'D', 'E'] as $ltr)
                                        <button @click="target = '{{ $ltr }}'"
                                                :class="target === '{{ $ltr }}'
                                                    ? 'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300'
                                                    : 'bg-white text-gray-600 hover:bg-indigo-100 border border-gray-300'"
                                                class="w-9 h-9 rounded-md font-bold text-sm transition-all">
                                            {{ $ltr }}
                                        </button>
                                        @endforeach
                                    </div>

                                    {{-- Botão de salvar alternativa --}}
                                    <button
                                        @click="if(target === 'statement') target = 'A'; saveCrop()"
                                        :disabled="saving || target === 'statement'"
                                        class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                                        <svg x-show="saving && target !== 'statement'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span x-text="saving && target !== 'statement'
                                            ? 'Salvando...'
                                            : 'Salvar como Alt. ' + (target === 'statement' ? 'A' : target)"></span>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-2">
                                    ?? Selecione a letra, ajuste o recorte e salve. O sistema avança para a próxima letra automaticamente.
                                </p>
                            </div>

                            {{-- --- FEEDBACK ---------------------------------- --}}
                            <div x-show="savedTarget" x-cloak
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="p-3 bg-green-50 border border-green-200 rounded-md text-sm text-green-700 flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                <span x-text="savedTarget === 'statement'
                                    ? '? Enunciado atualizado com o recorte!'
                                    : '? Alternativa ' + savedTarget + ' salva!'"></span>
                            </div>

                            <div x-show="errorMsg" x-cloak
                                 class="p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
                                ? <span x-text="errorMsg"></span>
                            </div>

                        </div>{{-- /controles --}}
                    </div>{{-- /Alpine wrapper individual --}}
                    @endforeach
                    </div>{{-- /space-y-6 wrapper geral das imagens --}}
                @else
                {{-- Quando a questão não tem imagem --}}
                <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <div class="text-5xl mb-4">??</div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Questão sem imagem</h3>
                    <p class="text-sm text-gray-500 mb-4">Revise o enunciado e as alternativas, e aprove se estiver correta.</p>
                    <form method="POST" action="{{ route('admin.import.review.approve', $question) }}">
                        @csrf
                        <button type="submit" class="px-6 py-2.5 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            ? Aprovar e Publicar
                        </button>
                    </form>
                </div>
                @endif
            </div>{{-- /coluna direita --}}

        </div>{{-- /grid --}}
    </div>

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    @endpush

</x-layouts.admin>
