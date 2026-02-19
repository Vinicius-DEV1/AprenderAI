@extends('layouts.app')

@section('page-title', 'Resultado da Prova')

@section('content')
    <!-- Debug: Vanilla JS Check -->
    <script>
        console.log('JS básico carregado no result.blade.php');
        window.onload = () => { console.log('DOM pronto (window.onload)'); };
    </script>


    <style>
        .result-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 40px;
            color: white;
            text-align: center;
            margin-bottom: 32px;
        }

        .result-header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .result-header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-box {
            background: white;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-box h3 {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .stat-box .value {
            font-size: 36px;
            font-weight: 700;
            color: #2563EB;
        }

        .answers-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .answers-section h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .answer-item {
            padding: 16px;
            border: 2px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .answer-item.correct {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .answer-item.incorrect {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .answer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .question-num {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            tracking: wide;
        }

        .badge {
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-correct {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-incorrect {
            background: #fee2e2;
            color: #991b1b;
        }

        .answer-text {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .explanation {
            background: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
            font-size: 13px;
            line-height: 1.5;
        }

        .explanation h4 {
            font-size: 12px;
            font-weight: 600;
            color: #2563EB;
            margin-bottom: 6px;
        }

        .btn-back {
            display: inline-block;
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .blink {
            animation: blinker 1.5s linear infinite;
        }

        @keyframes blinker {
            50% {
                opacity: 0.5;
            }
        }

        [x-cloak] {
            display: none !important;
        }

        /* Chat configuration */
        .chat-input {
            background: #fff;
            border-color: #e2e8f0;
            color: #1e293b;
        }
    </style>

    <div class="result-header">
        <h1>{{ number_format($percentageScore, 1) }}%</h1>
        <p>
            Você acertou {{ $correctAnswers }} de {{ $totalQuestions }} questões
        </p>
        <!-- Alpine Sanity Check -->
        <div x-data="{ alive: true }" class="mt-4 p-2 bg-white/20 rounded inline-block">
            <span x-show="alive" @click="alert('Alpine JS está ativo e respondendo!')"
                class="cursor-pointer font-bold text-sm">
                ✅ Clique aqui para testar o Alpine.js
            </span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <h3>Acertos</h3>
            <div class="value value-green" style="color: #10b981;">{{ $correctAnswers }}</div>
        </div>

        <div class="stat-box">
            <h3>Erros</h3>
            <div class="value value-red" style="color: #ef4444;">{{ $totalQuestions - $correctAnswers }}</div>
        </div>

        <div class="stat-box">
            <h3>Tempo Total</h3>
            <div class="value" style="font-size: 24px;">{{ gmdate('H:i:s', $simulation->time_elapsed ?? 0) }}</div>
        </div>

        <div class="stat-box">
            <h3>Média por Questão</h3>
            <div class="value" style="font-size: 24px;">
                {{ $totalQuestions > 0 ? gmdate('i:s', ($simulation->time_elapsed ?? 0) / $totalQuestions) : '00:00' }}
            </div>
        </div>
    </div>
    <!-- Ended stats-grid -->

    <div class="answers-section">
        <h2>Análise Detalhada</h2>

        @foreach($simulation->answers as $index => $answer)
            <div class="answer-item {{ $answer->is_correct ? 'correct' : 'incorrect' }}">
                <div class="answer-header">
                    <span class="question-num">Questão {{ $index + 1 }} -
                        {{ $answer->question->subjects->pluck('name')->join(', ') }}</span>

                    @if($answer->question->source === 'ai_generated')
                        <span class="badge" style="background: #E9D5FF; color: #6B21A8; margin-left: 8px;">✨ INÉDITA</span>
                    @elseif(!empty($answer->question->origin))
                        <span class="badge"
                            style="background: #E2E8F0; color: #475569; margin-left: 8px;">{{ $answer->question->origin }}</span>
                    @endif

                    @php
                        $difficultyColor = match ($answer->question->difficulty) {
                            'easy' => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Fácil'],
                            'medium' => ['bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'Média'],
                            'hard' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Difícil'],
                            default => null,
                        };
                    @endphp

                    @if($difficultyColor)
                        <span class="badge cursor-help group relative"
                            style="background: {{ $difficultyColor['bg'] }}; color: {{ $difficultyColor['text'] }}; margin-left: 8px;"
                            title="IA: {{ $answer->question->difficulty_reasoning ?? 'Análise automática processada pela IA.' }}">
                            {{ $difficultyColor['label'] }}
                            @if($answer->question->difficulty_reasoning)
                                <span
                                    class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-2 p-2 bg-gray-800 text-white text-[10px] rounded shadow-lg w-48 z-50 text-center leading-tight">
                                    {{ $answer->question->difficulty_reasoning }}
                                    <span
                                        class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            @endif
                        </span>
                    @endif
                    <span class="badge {{ $answer->is_correct ? 'badge-correct' : 'badge-incorrect' }}">
                        {{ $answer->is_correct ? '✓ Correta' : '✗ Incorreta' }}
                    </span>
                </div>

                <!-- Question Statement -->
                <div
                    class="question-statement mb-4 text-gray-900 text-base leading-relaxed whitespace-pre-wrap border-b border-gray-100 pb-4">
                    {!! $answer->question->statement_html ?? nl2br(e($answer->question->statement)) !!}
                </div>

                <!-- Question Alternatives -->
                <div class="space-y-2 mb-4">
                    @foreach($answer->question->alternatives as $letter => $text)
                        <div
                            class="flex items-start gap-2 p-2 rounded-lg border {{ $answer->user_answer === $letter ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-100 alternative-box' }} {{ $answer->question->correct_answer === $letter ? 'ring-2 ring-green-500 ring-offset-1' : '' }}">
                            <span
                                class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold {{ $answer->user_answer === $letter ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                {{ $letter }}
                            </span>
                            <span class="text-sm text-gray-800 alternative-text leading-snug">{{ $text }}</span>
                            @if($answer->question->correct_answer === $letter)
                                <svg class="w-4 h-4 text-green-600 ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Resolução / Explicação -->
                <div class="explanation mt-4 bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.364-6.364l-.707-.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M12 11a3 3 0 110-6 3 3 0 010 6z">
                            </path>
                        </svg>
                        Resolução Comentada
                    </h4>

                    <div class="explanation-text text-sm text-gray-600 leading-relaxed markdown-body">
                        @if(!empty($answer->question->explanation))
                            {!! \Illuminate\Support\Str::markdown($answer->question->explanation) !!}
                        @else
                            <p class="italic text-gray-500">A resolução comentada para esta questão está sendo processada e estará
                                disponível em breve.</p>
                        @endif
                    </div>
                </div>

                <!-- Chat Contextual -->
                <div class="mt-2 border-t border-gray-100 pt-2"
                    x-data="chatComponent({{ $simulation->id }}, {{ $answer->question_id }})">

                    <button @click="toggleChat()"
                        class="text-xs text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-1.5 transition-colors">
                        <span x-text="showChat ? 'Ocultar Chat' : '💬 Tirar Dúvida com Xavier'">💬 Tirar Dúvida com Xavier</span>
                    </button>

                    <div x-show="showChat" x-transition.opacity.duration.300ms x-cloak
                        class="chat-container mt-3 bg-gray-50 rounded-lg p-3 border border-gray-200">

                        <!-- History -->
                        <div class="chat-history space-y-2 mb-3 max-h-48 overflow-y-auto p-1" x-ref="history">
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="flex flex-col"
                                    :class="msg.role === 'user' ? 'items-end' : (msg.role === 'system' ? 'items-center' : 'items-start')">

                                    <!-- User Message -->
                                    <template x-if="msg.role === 'user'">
                                        <div class="bg-indigo-600 text-white rounded-lg px-3 py-1.5 max-w-[85%] text-[11px] shadow-sm break-words leading-tight"
                                            style="background-color: #4f46e5 !important; color: white !important;">
                                            <span x-text="msg.message"></span>
                                        </div>
                                    </template>

                                    <!-- AI Message -->
                                    <template x-if="msg.role !== 'user' && msg.role !== 'system'">
                                        <div
                                            class="bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-1.5 max-w-[85%] text-[11px] shadow-sm break-words leading-relaxed markdown-body">
                                            <div x-html="renderMarkdown(msg.message)"></div>
                                        </div>
                                    </template>

                                    <!-- System Message (Quota Exceeded) -->
                                    <template x-if="msg.role === 'system'">
                                        <div
                                            class="bg-red-50 border border-red-200 rounded-lg p-4 text-center w-[95%] mx-auto my-2 shadow-sm">
                                            <p class="text-xs text-red-800 font-bold mb-2 break-words" x-text="msg.message"></p>
                                            <p class="text-[10px] text-red-600 mb-3" x-show="msg.reset_date">Renova em: <span
                                                    x-text="msg.reset_date"></span></p>
                                            <a :href="msg.upgrade_url"
                                                class="inline-block bg-gradient-to-r from-red-500 to-orange-500 text-white text-[11px] font-bold py-2 px-4 rounded-full shadow hover:scale-105 transition-transform uppercase tracking-wide">
                                                🚀 Turbinar meu Plano
                                            </a>
                                        </div>
                                    </template>

                                    <!-- Label -->
                                    <span class="text-[9px] text-gray-400 mt-0.5"
                                        x-text="msg.role === 'user' ? 'Você' : (msg.role === 'system' ? 'Sistema' : 'Xavier')"></span>
                                </div>
                            </template>

                            <!-- Typing Indicator -->
                            <div x-show="isTyping" class="flex items-start">
                                <div
                                    class="bg-gray-100 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 shadow-sm border border-gray-200">
                                    <span class="font-medium">Xavier está digitando</span>
                                    <div class="flex gap-1">
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></span>
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"
                                            style="animation-delay: 0.1s"></span>
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"
                                            style="animation-delay: 0.2s"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div x-show="errorMessage" class="text-[10px] text-red-600 text-center mt-2 bg-red-50 p-1 rounded"
                                x-text="errorMessage"></div>
                        </div>

                        <!-- Input Area -->
                        <div class="flex gap-2">
                            <input type="text" x-model="newMessage" x-ref="chatInput" @keydown.enter.prevent="sendMessage()"
                                placeholder="Dúvida rápida com Xavier..."
                                class="chat-input flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-gray-900 h-8"
                                :disabled="isTyping || isLoadingHistory">

                            <button @click="sendMessage()" type="button"
                                class="px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-xs font-medium disabled:opacity-50 transition-all h-8"
                                :disabled="isTyping || isLoadingHistory || !newMessage.trim()">
                                Enviar
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    <div style="text-align: center; margin-top: 32px;">
        <a href="{{ route('dashboard') }}" class="btn-back">Voltar ao Dashboard</a>
        <a href="{{ route('simulations.create') }}" class="btn-back" style="background: #10b981; margin-left: 12px;">Nova
            Prova</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        // Configure marked
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });

        window.chatComponent = function (simulationId, questionId) {
            return {
                showChat: false,
                isLoadingHistory: false,
                isTyping: false,
                newMessage: '',
                messages: [],
                errorMessage: null,

                init() {
                    // No automatic init actions needed for basic chat
                },

                renderMarkdown(text) {
                    if (!text) return '';
                    try {
                        return marked.parse(text);
                    } catch (e) {
                        return text;
                    }
                },

                toggleChat() {
                    this.showChat = !this.showChat;
                    if (this.showChat) {
                        if (this.messages.length === 0) {
                            this.loadHistory();
                        }
                        this.$nextTick(() => {
                            if (this.$refs.chatInput) this.$refs.chatInput.focus();
                            this.scrollToBottom();
                        });
                    }
                },

                async loadHistory() {
                    this.isLoadingHistory = true;
                    try {
                        const response = await fetch(`/simulations/${simulationId}/questions/${questionId}/chat`);
                        if (response.ok) {
                            const history = await response.json();
                            if (history.length === 0) {
                                this.messages = [{
                                    role: 'assistant',
                                    message: 'Olá! Eu sou o Xavier. Qual sua dúvida sobre essa questão?',
                                    id: Date.now()
                                }];
                            } else {
                                this.messages = history;
                            }
                            this.$nextTick(() => this.scrollToBottom());
                        }
                    } catch (error) {
                        console.error('Failed to load history', error);
                    } finally {
                        this.isLoadingHistory = false;
                    }
                },

                async sendMessage() {
                    if (!this.newMessage.trim()) return;

                    const messageToSend = this.newMessage;

                    // Optimistic Update
                    this.messages.push({
                        role: 'user',
                        message: messageToSend,
                        id: Date.now()
                    });

                    this.newMessage = '';
                    this.$nextTick(() => this.scrollToBottom());
                    this.isTyping = true;
                    this.errorMessage = null;

                    try {
                        const response = await fetch(`/simulations/${simulationId}/questions/${questionId}/chat`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ message: messageToSend })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.error || "Erro ao enviar mensagem.");
                        }

                        // Check for Quota Exceeded
                        if (data.status === 'quota_exceeded') {
                            this.messages.push({
                                role: 'system',
                                message: data.message,
                                upgrade_url: data.upgrade_url,
                                reset_date: data.reset_date,
                                id: Date.now()
                            });
                            this.isTyping = false;
                            this.$nextTick(() => this.scrollToBottom());
                            return; // Stop polling
                        }

                        // Start Polling for Answer
                        this.pollForAnswer();

                    } catch (error) {
                        this.errorMessage = error.message;
                        this.isTyping = false;
                    }
                },

                pollForAnswer() {
                    let attempts = 0;
                    const maxAttempts = 30; // 60 seconds (2s interval)

                    const poller = setInterval(async () => {
                        attempts++;
                        try {
                            const response = await fetch(`/simulations/${simulationId}/questions/${questionId}/chat`);
                            if (response.ok) {
                                const history = await response.json();
                                // Check if the last message is from assistant
                                const lastMsg = history[history.length - 1];

                                if (lastMsg && lastMsg.role === 'assistant') {
                                    // Found answer!
                                    // Only append if we haven't already (check ID or length)
                                    // Simplest: just replace messages or append distinct?
                                    // Let's just append the new one since we have local state

                                    // Check if we already have this message locally (to avoid duplicates if re-render)
                                    // Creating a unique ID based on content/time?
                                    // Actually, replacing `messages` with `history` is safer to sync state.
                                    this.messages = history;

                                    this.isTyping = false;
                                    this.$nextTick(() => this.scrollToBottom());
                                    clearInterval(poller);
                                }
                            }
                        } catch (e) {
                            console.error("Polling error", e);
                        }

                        if (attempts >= maxAttempts) {
                            clearInterval(poller);
                            this.isTyping = false;
                            this.errorMessage = "A IA demorou muito para responder. Tente recarregar a página.";
                        }
                    }, 2000);
                },

                scrollToBottom() {
                    const container = this.$refs.history;
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            }
        }
    </script>
@endsection
```