<x-layouts.admin>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ isset($question) ? 'Editar Questão #' . $question->id : 'Nova Questão' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
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
                                <x-input-label for="type" value="Tipo" />
                                <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="enem" {{ old('type', $question->type ?? '') == 'enem' ? 'selected' : '' }}>ENEM</option>
                                    <option value="concurso" {{ old('type', $question->type ?? '') == 'concurso' ? 'selected' : '' }}>Concurso</option>
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
                        <div>
                            <x-input-label for="statement" value="Enunciado (Markdown/Texto)" />
                            <textarea id="statement" name="statement" rows="5" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('statement', $question->statement ?? '') }}</textarea>
                        </div>

                        <!-- Alternativas -->
                        <div class="border-t pt-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Alternativas</h3>
                            <div class="space-y-4">
                                @foreach(['A', 'B', 'C', 'D', 'E'] as $letter)
                                    <div>
                                        <x-input-label for="alt_{{ $letter }}" value="Alternativa {{ $letter }}" />
                                        <div class="flex items-center gap-2">
                                            <input type="radio" name="correct_answer" value="{{ $letter }}" {{ old('correct_answer', $question->correct_answer ?? '') == $letter ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500">
                                            @php
                                                $alternative = (isset($question) && $question->alternatives)
                                                    ? $question->alternatives->firstWhere('label', $letter)
                                                    : null;
                                                $altContent = old('alternatives.' . $letter, $alternative->content ?? '');
                                            @endphp
                                            <div class="flex-1">
                                                <input type="text" id="alt_{{ $letter }}" name="alternatives[{{ $letter }}]" 
                                                       value="{{ $altContent }}" 
                                                       class="block w-full border-gray-300 rounded-md shadow-sm" {{ empty($altContent) && ($alternative && $alternative->image_path) ? '' : 'required' }}>
                                                
                                                @if($alternative && $alternative->image_path)
                                                    <div class="mt-2">
                                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($alternative->image_path) }}" class="max-h-32 rounded border shadow-sm">
                                                        <span class="text-xs text-gray-500">Imagem da Alternativa</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
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
</x-layouts.admin>
