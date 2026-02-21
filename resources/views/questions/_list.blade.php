@forelse($questions as $question)
    <div class="qb-card" x-data="questionCard({{ $question->id }}, {{ json_encode(isset($answeredMap[$question->id])) }}, {{ json_encode($answeredMap[$question->id] ?? null) }})">
        <div class="qb-card-meta">
            <span class="qb-card-id">#{{ $question->external_id ?? $question->id }}</span>
            @if($question->source === 'ai_generated')
                <span class="qb-badge qb-badge-ai">✨ INÉDITA</span>
            @endif
            @if($question->year)<span class="qb-badge qb-badge-origin">{{ $question->year }}</span>@endif
            @if($question->organization)<span class="qb-badge qb-badge-origin">{{ $question->organization }}</span>@endif
            <span class="qb-badge qb-badge-origin">{{ $question->subjects->pluck('name')->join(', ') }}</span>
            @php
                $dc = match($question->difficulty) {
                    'easy' => ['class' => 'qb-badge-easy', 'label' => 'Fácil'],
                    'medium' => ['class' => 'qb-badge-medium', 'label' => 'Média'],
                    'hard' => ['class' => 'qb-badge-hard', 'label' => 'Difícil'],
                    default => null,
                };
            @endphp
            @if($dc)<span class="qb-badge {{ $dc['class'] }}">{{ $dc['label'] }}</span>@endif
            <template x-if="alreadyAnswered && !answered">
                <span class="qb-badge" :class="wasCorrect ? 'qb-badge-correct' : 'qb-badge-incorrect'" x-text="wasCorrect ? '✓ Já Resolvida' : '✗ Já Resolvida'"></span>
            </template>
        </div>

        {{-- Renderiza o enunciado processando Markdown de imagens e quebras de linha de forma segura --}}
        <div class="qb-statement">{!! $question->statement_html !!}</div>

        <div class="qb-alternatives-list">
            @foreach($question->alternatives->sortBy('label') as $alt)
                <div class="qb-alt"
                     :class="{
                        'selected': selectedAnswer === '{{ $alt->label }}' && !answered,
                        'correct-reveal': answered && '{{ $alt->label }}' === correctAnswer,
                        'incorrect-reveal': answered && selectedAnswer === '{{ $alt->label }}' && '{{ $alt->label }}' !== correctAnswer,
                        'disabled': answered
                     }"
                     @click="!answered ? selectAnswer('{{ $alt->label }}') : null">
                    <div class="qb-alt-letter">{{ $alt->label }}</div>
                    <div style="display: flex; flex-direction: column; gap: 8px; flex-grow: 1; overflow: hidden;">
                        @if($alt->content)
                            <div class="qb-alt-text" style="word-break: break-word;">{{ $alt->content }}</div>
                        @endif
                        @if($alt->image_path)
                            <img src="{{ Storage::url($alt->image_path) }}" alt="Alternativa {{ $alt->label }}" style="max-width: 100%; height: auto; border-radius: 4px; object-fit: contain;">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="qb-card-actions">
            <button class="qb-action-btn primary" x-show="!answered" @click="submitAnswer()" :disabled="!selectedAnswer || submitting">
                <span x-show="!submitting">📝 Responder</span>
                <span x-show="submitting">⏳ Enviando...</span>
            </button>
            <button class="qb-action-btn" x-show="answered" @click="toggleChat()">
                <span x-text="showChat ? '▲ Ocultar Chat' : '💬 Tirar Dúvida'"></span>
            </button>
            <button class="qb-action-btn" x-show="answered" @click="toggleHistory()">📜 Meu Histórico</button>
            <button class="qb-action-btn retry" x-show="answered" @click="resetCard()">
                <span>🔄 Tentar Novamente</span>
            </button>
        </div>

        <template x-if="answered">
            <div class="qb-feedback" :class="isCorrect ? 'correct' : 'incorrect'">
                <div class="qb-feedback-title">
                    <span x-text="isCorrect ? '✅ Resposta Correta!' : '❌ Resposta Incorreta'"></span>
                    <span style="font-weight:400; font-size:12px; color:#64748b" x-show="!isCorrect">
                        Correta: <strong x-text="correctAnswer" style="color:#065f46"></strong>
                    </span>
                </div>
                <div class="qb-explanation" x-show="explanation">
                    <h4>📖 Resolução Comentada</h4>
                    <div class="qb-explanation-text" x-html="renderMd(explanation)"></div>
                </div>
                <div class="qb-difficulty-box" x-show="difficultyReasoning">
                    <h5>🎯 Por que essa dificuldade?</h5>
                    <p x-text="difficultyReasoning"></p>
                </div>
            </div>
        </template>

        <div x-show="showHistory" x-transition x-cloak class="qb-history-popover">
            <h4 style="font-size:13px; font-weight:700; color:#6366f1; margin-bottom:8px">📜 Seu Histórico nesta Questão</h4>
            <template x-if="historyLoading"><p style="font-size:12px; color:#94a3b8">Carregando...</p></template>
            <template x-if="!historyLoading && historyData.length === 0"><p style="font-size:12px; color:#94a3b8">Nenhum registro encontrado.</p></template>
            <template x-for="h in historyData" :key="h.answered_at">
                <div class="qb-history-row">
                    <span style="color:#64748b" x-text="formatDate(h.answered_at)"></span>
                    <span>Resposta: <strong x-text="h.selected_answer"></strong></span>
                    <span class="qb-badge" :class="h.is_correct ? 'qb-badge-correct' : 'qb-badge-incorrect'" x-text="h.is_correct ? 'Acerto' : 'Erro'"></span>
                </div>
            </template>
        </div>

        <div x-show="showChat" x-transition x-cloak class="qb-chat-container">
            <div class="qb-chat-history space-y-2 p-1" x-ref="chatHistory">
                <template x-for="msg in chatMessages" :key="msg.id || msg.created_at">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : (msg.role === 'system' ? 'flex justify-center' : 'flex justify-start')">
                        <template x-if="msg.role === 'user'">
                            <div class="rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" style="background:#4f46e5;color:white" x-text="msg.message"></div>
                        </template>
                        <template x-if="msg.role === 'assistant'">
                            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" x-html="renderMd(msg.message)"></div>
                        </template>
                        <template x-if="msg.role === 'system'">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center w-[90%]">
                                <p class="text-xs text-red-800 font-bold" x-text="msg.message"></p>
                                <a :href="msg.upgrade_url || '/'" class="mt-2 inline-block bg-gradient-to-r from-red-500 to-orange-500 text-white text-xs font-bold py-1.5 px-4 rounded-full">🚀 Turbinar Plano</a>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="chatTyping" class="flex items-start">
                    <div class="bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 border border-gray-200">
                        <span class="font-medium">Xavier digitando</span>
                        <span class="flex gap-1">
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.1s"></span>
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.2s"></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-2">
                <input type="text" x-model="chatInput" x-ref="chatInput" @keydown.enter.prevent="sendChat()" placeholder="Qual sua dúvida, @auth {{ auth()->user()->first_name }}? @else estudante? @endauth" :disabled="chatTyping" class="flex-1 rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-100 shadow-sm text-xs px-3 py-2">
                <button @click="sendChat()" :disabled="chatTyping || !chatInput.trim()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-xs font-medium disabled:opacity-50">Enviar</button>
            </div>
        </div>
    </div>
@empty
    <div class="qb-card qb-no-results" style="text-align:center; padding:48px">
        <p style="font-size:18px; color:#94a3b8">🔍 Nenhuma questão encontrada</p>
    </div>
@endforelse

<div style="display:flex; justify-content:center; margin-top:20px">{{ $questions->links() }}</div>
