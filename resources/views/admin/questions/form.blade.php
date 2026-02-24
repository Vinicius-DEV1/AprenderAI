<x-layouts.admin>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ isset($question) ? 'Editar Questão #' . $question->id : 'Nova Questão' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900" x-data="questionFormHandler(@js($question->format ?? 'multiple_choice'), @js($question->type ?? 'enem'), @js(old('statement', $question->statement ?? '')))">
                    <form method="POST" action="{{ isset($question) ? route('admin.questions.update', $question) : route('admin.questions.store') }}" class="space-y-6">
                        @csrf
                        @if(isset($question))
                            @method('PUT')
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Metadados -->
                            <div>
                                <x-input-label for="subject" value="Matéria" />
                                <select id="subject" name="subject" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    @php
                                        $currentSubjectName = isset($question) && $question->subjects->isNotEmpty() 
                                                                ? strtolower($question->subjects->first()->name) 
                                                                : '';
                                    @endphp
                                    @foreach($subjects as $subj)
                                        <option value="{{ strtolower($subj->name) }}" {{ old('subject', $currentSubjectName) == strtolower($subj->name) ? 'selected' : '' }}>
                                            {{ $subj->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="type" value="Tipo de Prova" />
                                <select id="type" name="type" x-model="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="enem" {{ old('type', $question->type ?? '') == 'enem' ? 'selected' : '' }}>ENEM</option>
                                    <option value="concurso" {{ old('type', $question->type ?? '') == 'concurso' ? 'selected' : '' }}>Concurso</option>
                                </select>
                            </div>

                            <div>
                                <x-input-label for="format" value="Formato da Questão" />
                                <select id="format" name="format" x-model="format" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="multiple_choice" {{ old('format', $question->format ?? '') == 'multiple_choice' ? 'selected' : '' }}>Múltipla Escolha (A, B, C, D, E)</option>
                                    <option value="true_false" {{ old('format', $question->format ?? '') == 'true_false' ? 'selected' : '' }}>Certo ou Errado (Somente C/E)</option>
                                </select>
                            </div>

                            <div>
                                <x-input-label for="source" value="Origem" />
                                <select id="source" name="source" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="manual" {{ old('source', $question->source ?? '') == 'manual' ? 'selected' : '' }}>Manual (ENEM)</option>
                                    <option value="ai_generated" {{ old('source', $question->source ?? '') == 'ai_generated' ? 'selected' : '' }}>IA Gerada</option>
                                </select>
                            </div>

                            <div>
                                <x-input-label for="organization" value="Banca / Organização (Ex: ENEM, CESPE)" />
                                <input type="text" id="organization" name="organization" value="{{ old('organization', $question->organization ?? '') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            </div>

                            <div x-show="type === 'concurso'">
                                <x-input-label for="topic" value="Assunto / Tópico (Opcional)" />
                                <select id="topic" name="topic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Selecione um tópico...</option>
                                    @php
                                        $currentTopicName = isset($question) && $question->topics->isNotEmpty() 
                                                                ? strtolower($question->topics->first()->name) 
                                                                : '';
                                    @endphp
                                    @foreach($topics ?? [] as $topic)
                                        <option value="{{ strtolower($topic->name) }}" {{ old('topic', $currentTopicName) == strtolower($topic->name) ? 'selected' : '' }}>
                                            {{ $topic->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="difficulty" value="Dificuldade" />
                                <select id="difficulty" name="difficulty" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="easy" {{ old('difficulty', $question->difficulty ?? '') == 'easy' ? 'selected' : '' }}>Fácil</option>
                                    <option value="medium" {{ old('difficulty', $question->difficulty ?? 'medium') == 'medium' ? 'selected' : '' }}>Média</option>
                                    <option value="hard" {{ old('difficulty', $question->difficulty ?? '') == 'hard' ? 'selected' : '' }}>Difícil</option>
                                </select>
                            </div>

                            <div class="md:col-span-2 bg-blue-50 p-4 rounded-lg">
                                <x-input-label for="difficulty_reasoning" value="Justificativa da IA (Dificuldade)" />
                                <textarea id="difficulty_reasoning" name="difficulty_reasoning" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm placeholder-gray-500" placeholder="Explicação da IA sobre a dificuldade...">{{ old('difficulty_reasoning', $question->difficulty_reasoning ?? '') }}</textarea>
                                <p class="text-sm text-gray-500 mt-1">Este texto é gerado automaticamente pela IA, mas pode ser editado para refinar a explicação.</p>
                            </div>
                        </div>

                        <!-- Enunciado -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <x-input-label for="statement" value="Enunciado (Markdown/Texto)" />
                                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded">Live Preview Ativo</span>
                            </div>

                            {{-- Preview Area --}}
                            <div x-show="statement" class="p-6 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl mb-4">
                                <h4 class="text-[10px] uppercase font-bold text-gray-400 mb-3 tracking-widest">Prévia do Aluno</h4>
                                <div class="prose prose-indigo max-w-none text-gray-800" x-html="statementHtml"></div>
                            </div>

                            <textarea id="statement" name="statement" x-model="statement" rows="5" 
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                                      placeholder="Ex: ![Imagem](url) ...">{{ old('statement', $question->statement ?? '') }}</textarea>
                        </div>

                        <div class="border-t pt-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="format === 'multiple_choice' ? 'Alternativas (Múltipla Escolha)' : 'Alternativas (Certo ou Errado)'"></h3>
                            <div class="space-y-4">
                                {{-- Múltipla Escolha --}}
                                <template x-if="format === 'multiple_choice'">
                                    <div class="space-y-4">
                                        @foreach(['A', 'B', 'C', 'D', 'E'] as $letter)
                                            <div>
                                                <x-input-label for="alt_{{ $letter }}" value="Alternativa {{ $letter }}" />
                                                <div class="flex items-center gap-2">
                                                    <input type="radio" name="correct_answer" value="{{ $letter }}" {{ old('correct_answer', $question->correct_answer ?? '') == $letter ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500" :required="format === 'multiple_choice'">
                                                    @php
                                                        $alternative = (isset($question) && $question->alternatives)
                                                            ? $question->alternatives->firstWhere('label', $letter)
                                                            : null;
                                                        $altContent = old('alternatives.' . $letter, $alternative->content ?? '');
                                                    @endphp
                                                    <div class="flex-1">
                                                        <input type="text" id="alt_{{ $letter }}" name="alternatives[{{ $letter }}]" 
                                                               value="{{ $altContent }}" 
                                                               class="block w-full border-gray-300 rounded-md shadow-sm" :required="format === 'multiple_choice' && '{{ $letter }}' <= 'E'">
                                                        
                                                        @if($alternative && $alternative->image_path)
                                                            <div class="mt-2 text-xs text-gray-500">📎 Possui imagem anexada</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </template>

                                {{-- Certo ou Errado --}}
                                <template x-if="format === 'true_false'">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach(['C' => 'Certo', 'E' => 'Errado'] as $letter => $label)
                                            <div class="p-4 border rounded-lg hover:bg-gray-50 flex items-center gap-4">
                                                <input type="radio" name="correct_answer" value="{{ $letter }}" 
                                                       {{ old('correct_answer', $question->correct_answer ?? '') == $letter ? 'checked' : '' }} 
                                                       class="w-6 h-6 text-indigo-600 focus:ring-indigo-500" :required="format === 'true_false'">
                                                <div class="flex-1">
                                                    <span class="block font-bold text-gray-700">{{ $label }}</span>
                                                    <input type="hidden" name="alternatives[{{ $letter }}]" value="{{ $label }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Explicação -->
                        <div class="border-t pt-4 bg-yellow-50 p-4 rounded-lg">
                            <x-input-label for="explanation" value="Explicação da Resposta (Crucial para o modo Offline)" />
                            <textarea id="explanation" name="explanation" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm placeholder-gray-500" placeholder="Explique por que a alternativa correta é a correta...">{{ old('explanation', $question->explanation ?? '') }}</textarea>
                            <p class="text-sm text-gray-500 mt-1">Se deixado em branco, o aluno será forçado a solicitar ajuda ao tutor (gerando custo de API).</p>
                        </div>

                        <div class="flex items-center justify-end mt-8 border-t pt-4">
                            <a href="{{ route('admin.questions.index') }}" class="text-gray-600 underline mr-4">Cancelar</a>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-semibold shadow-sm">
                                Salvar Questão
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
        function questionFormHandler(initialFormat, initialType, initialStatement) {
            return {
                format: initialFormat,
                type: initialType,
                statement: initialStatement,
                get statementHtml() {
                    if (!this.statement) return '';
                    // Sanitização básica e conversão de markdown imagem -> img
                    let html = this.statement
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" />')
                        .replace(/\n/g, '<br>');
                    return html;
                }
            }
        }
    </script>
    @endpush
</x-layouts.admin>
