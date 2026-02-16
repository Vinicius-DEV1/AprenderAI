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
            50% { opacity: 0.5; }
        }

        [x-cloak] { display: none !important; }
    </style>

    <div class="result-header">
        <h1>{{ number_format($percentageScore, 1) }}%</h1>
        <p>
            Você acertou {{ $correctAnswers }} de {{ $totalQuestions }} questões
        </p>
        <!-- Alpine Sanity Check -->
        <div x-data="{ alive: true }" class="mt-4 p-2 bg-white/20 rounded inline-block">
             <span x-show="alive" @click="alert('Alpine JS está ativo e respondendo!')" class="cursor-pointer font-bold text-sm">
                ✅ Clique aqui para testar o Alpine.js
             </span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <h3>Acertos</h3>
            <div class="value" style="color: #10b981;">{{ $correctAnswers }}</div>
        </div>

        <div class="stat-box">
            <h3>Erros</h3>
            <div class="value" style="color: #ef4444;">{{ $totalQuestions - $correctAnswers }}</div>
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
                    <span class="question-num">Questão {{ $index + 1 }} - {{ ucfirst($answer->question->subject) }}</span>
                    <span class="badge {{ $answer->is_correct ? 'badge-correct' : 'badge-incorrect' }}">
                        {{ $answer->is_correct ? '✓ Correta' : '✗ Incorreta' }}
                    </span>
                </div>

                <!-- Question Statement -->
                <div class="mb-4 text-gray-900 text-base leading-relaxed whitespace-pre-wrap border-b border-gray-100 pb-4">
                    {!! $answer->question->statement_html !!}
                </div>

                <!-- Question Alternatives -->
                <div class="space-y-2 mb-4">
                    @foreach($answer->question->alternatives as $letter => $text)
                        <div class="flex items-start gap-2 p-2 rounded-lg border {{ $answer->user_answer === $letter ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-100' }} {{ $answer->question->correct_answer === $letter ? 'ring-2 ring-green-500 ring-offset-1' : '' }}">
                            <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold {{ $answer->user_answer === $letter ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                {{ $letter }}
                            </span>
                            <span class="text-sm text-gray-800 leading-snug">{{ $text }}</span>
                            @if($answer->question->correct_answer === $letter)
                                <svg class="w-4 h-4 text-green-600 ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- AI Analysis Section -->
                <div class="bg-blue-50/50 rounded-lg p-4 border border-blue-100">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.364-6.364l-.707-.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M12 11a3 3 0 110-6 3 3 0 010 6z" />
                        </svg>
                        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider">Análise da IA</h4>
                    </div>
                    
                    <div class="text-sm text-slate-700 leading-relaxed space-y-2 mb-4">
                        @php
                            $questionAnalysis = collect($simulation->correction_details['errors_explanation'] ?? [])
                                ->firstWhere('question_id', $answer->question_id);
                        @endphp

                        @if($questionAnalysis)
                            <div>
                                <p class="font-semibold text-red-700 text-xs mb-1">Por que você errou:</p>
                                <p class="text-slate-600">{{ $questionAnalysis['why_wrong'] }}</p>
                            </div>
                            <div class="mt-2 pt-2 border-t border-blue-100">
                                <p class="font-semibold text-green-700 text-xs mb-1">Como resolver:</p>
                                <p class="text-slate-600">{{ $questionAnalysis['correct_approach'] }}</p>
                            </div>
                        @else
                            <p class="text-slate-600 italic">Parabéns! Você acertou esta questão. Caso tenha alguma dúvida sobre o conceito, use o chat abaixo.</p>
                        @endif
                    </div>
                </div>

                @php
                    $aiExplanation = null;
                    if($simulation->correction) {
                        $aiExplanation = $simulation->correction->getExplanationForQuestion($answer->question_id);
                    }
                @endphp

                <div class="explanation" 
                     data-question-id="{{ $answer->question_id }}" 
                     style="background: #f8fafc; border: 1px solid #e2e8f0;"
                     x-data="analysisPolling({{ $simulation->id }}, {{ $answer->question_id }}, '{{ $aiExplanation ? 'completed' : 'pending' }}', `{{ $aiExplanation ? $aiExplanation : '' }}`)"
                     x-show="true">
                    
                    <h4 style="display: flex; align-items: center; gap: 8px;">
                        <template x-if="status === 'completed'">
                            <span>✨ Análise da IA</span>
                        </template>
                        <template x-if="status !== 'completed'">
                            <span class="flex items-center gap-2 text-indigo-600">
                                <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Analisando...
                            </span>
                        </template>
                    </h4>

                    <div x-show="status === 'completed'" class="markdown-body" style="white-space: pre-wrap;" x-html="renderContent(content)"></div>

                    <div x-show="status !== 'completed'" class="ai-loading space-y-3 mt-4">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                            </span>
                            <p class="text-sm text-indigo-700 font-medium m-0">A IA está analisando seu desempenho...</p>
                        </div>
                        
                        <!-- Skeleton Loader -->
                        <div class="animate-pulse space-y-2">
                            <div class="h-2 bg-indigo-100 rounded w-3/4"></div>
                            <div class="h-2 bg-indigo-100 rounded w-full"></div>
                            <div class="h-2 bg-indigo-100 rounded w-5/6"></div>
                        </div>
                    </div>
                </div>

                <!-- Chat Contextual -->
                <div class="mt-2 border-t border-gray-100 pt-2" 
                     x-data="chatComponent({{ $simulation->id }}, {{ $answer->question_id }})"
                     @explanation-available.window="if ($event.detail.questionId === {{ $answer->question_id }}) hasExplanation = true">
                    
                    <button @click="toggleChat()" 
                            class="text-xs text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-1.5 transition-colors">
                        <span x-text="showChat ? 'Ocultar Chat' : (hasExplanation ? 'Ainda com dúvida na explicação? Pergunte ao Tutor' : '💬 Tirar Dúvida')">💬 Tirar Dúvida</span>
                    </button>

                    <div x-show="showChat" 
                         x-transition.opacity.duration.300ms
                         x-cloak
                         class="mt-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
                        
                        <!-- History -->
                        <div class="chat-history space-y-2 mb-3 max-h-48 overflow-y-auto p-1" x-ref="history">
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                                    <div 
                                        :class="msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-200'" 
                                        :style="msg.role === 'user' ? 'background-color: #4f46e5 !important; color: white !important;' : 'background-color: white !important; color: #1f2937 !important; border: 1px solid #e5e7eb;'"
                                        class="rounded-lg px-3 py-1.5 max-w-[85%] text-[11px] shadow-sm group">
                                        
                                        <!-- User Message (Text Only) -->
                                        <template x-if="msg.role === 'user'">
                                            <span x-text="msg.message" class="break-words leading-tight"></span>
                                        </template>

                                        <!-- AI Message (Markdown HTML) -->
                                        <template x-if="msg.role !== 'user'">
                                            <div x-html="renderMarkdown(msg.message)" class="markdown-body break-words leading-relaxed"></div>
                                        </template>
                                    </div>
                                    <span class="text-[9px] text-gray-400 mt-0.5" x-text="msg.role === 'user' ? 'Você' : 'IA'"></span>
                                </div>
                            </template>
                            
                            <!-- Typing Indicator -->
                            <div x-show="isTyping" class="flex items-start">
                                <div class="bg-gray-100 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 shadow-sm border border-gray-200">
                                    <span class="font-medium">IA está digitando</span>
                                    <div class="flex gap-1">
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></span>
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                                        <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Error Message -->
                            <div x-show="errorMessage" class="text-[10px] text-red-600 text-center mt-2 bg-red-50 p-1 rounded" x-text="errorMessage"></div>
                        </div>

                        <!-- Input Area -->
                        <div class="flex gap-2">
                            <input type="text" 
                                x-model="newMessage" 
                                @keydown.enter.prevent="sendMessage()"
                                placeholder="Dúvida rápida..." 
                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-gray-900 h-8"
                                :disabled="isTyping || isLoadingHistory">
                                
                            <button @click="sendMessage()" 
                                type="button"
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

    <script>
        window.analysisPolling = function(simulationId, questionId, initialStatus, initialContent) {
            return {
                status: initialStatus,
                content: initialContent,
                interval: null,
                attempts: 0,
                maxAttempts: 40, // ~2 minutes (3s interval)

                init() {
                    if (this.status !== 'completed') {
                        this.startPolling();
                    }
                },

                startPolling() {
                    this.interval = setInterval(() => {
                        this.checkAnalysis();
                    }, 3000);
                },

                async checkAnalysis() {
                    this.attempts++;
                    
                    if (this.attempts > this.maxAttempts) {
                        console.warn(`[Polling] Question ${questionId} - Timeout reached.`);
                        clearInterval(this.interval);
                        return;
                    }

                    try {
                        const response = await fetch(`/simulations/${simulationId}/status`);
                        if (!response.ok) return;

                        const result = await response.json();
                        
                        // Only stop polling if we explicitly receive content
                        if (result.data && result.data[questionId]) {
                            console.log(`[Polling] Question ${questionId} - Data received!`);
                            this.content = result.data[questionId];
                            this.status = 'completed';
                            
                            // Notify chat component that explanation is available
                            window.dispatchEvent(new CustomEvent('explanation-available', { detail: { questionId: questionId } }));
                            
                            clearInterval(this.interval);
                        } 
                    } catch (error) {
                        console.error('Polling error:', error);
                    }
                },

                renderContent(text) {
                     if (!text) return '';
                     try {
                         return marked.parse(text);
                     } catch (e) {
                         return text;
                     }
                }
            }
        }
        
        window.chatComponent = function(simulationId, questionId) {
            return {
                showChat: false,
                isLoadingHistory: false,
                isTyping: false,
                newMessage: '',
                messages: [],
                errorMessage: null,
                hasExplanation: false, // Reactive state for button text

                init() {
                    // Check if explanation is already loaded on init (server-side rendered)
                    const explanationDiv = document.querySelector(`.explanation[data-question-id="${questionId}"] .markdown-body`);
                    if (explanationDiv && explanationDiv.innerHTML.trim() !== '') {
                        this.hasExplanation = true;
                    }
                },
                
                renderMarkdown(text) {
                    if (!text) return '';
                    try {
                        return marked.parse(text);
                    } catch (e) {
                        console.error('Markdown parse error:', e);
                        return text;
                    }
                },
                
                toggleChat() {
                    console.log('Botão clicado para a questão: ' + questionId);
                    this.showChat = !this.showChat;
                    if (this.showChat && this.messages.length === 0) {
                        this.loadHistory();
                    }
                },


                async loadHistory() {
                    this.isLoadingHistory = true;
                    try {
                        const response = await fetch(`/simulations/${simulationId}/questions/${questionId}/chat`);
                        if (response.ok) {
                            this.messages = await response.json();
                            this.scrollToBottom();
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
                    
                    this.newMessage = ''; // Clear input immediately
                    this.scrollToBottom();
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
                            if (response.status === 429) {
                                throw new Error(data.error || "Muitas requisições. Aguarde um pouco.");
                            }
                            throw new Error(data.error || "Erro ao enviar mensagem.");
                        }

                        this.messages.push({ 
                            role: 'assistant', 
                            message: data.message, 
                            id: Date.now() + 1 
                        });
                        
                        this.scrollToBottom();

                    } catch (error) {
                        this.errorMessage = error.message;
                    } finally {
                        this.isTyping = false;
                    }
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const container = this.$refs.history;
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                }
            }
        }
    </script>
    
    <!-- Script de Polling para Correção IA -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loadingElements = document.querySelectorAll('.ai-loading');
            
            if (loadingElements.length > 0) {
                const simulationId = "{{ $simulation->id }}";
                const pollInterval = 3000; // 3 segundos

                const intervalId = setInterval(checkStatus, pollInterval);

                async function checkStatus() {
                    try {
                        const response = await fetch("{{ route('simulations.status', $simulation) }}");
                        if (!response.ok) return;
                        
                        const result = await response.json();

                        if (result.status === 'completed') {
                            clearInterval(intervalId);
                            updateUI(result.data);
                        }
                    } catch (error) {
                        console.error('Erro ao verificar status da IA:', error);
                    }
                }

                function updateUI(data) {
                    const explanations = document.querySelectorAll('.explanation[data-question-id]');
                    
                    explanations.forEach(container => {
                        const questionId = container.getAttribute('data-question-id');
                        const text = data[questionId];
                        
                        if (text) {
                            // Atualizar Header
                            const header = container.querySelector('h4');
                            if (header) {
                                header.innerHTML = '✨ Análise da IA';
                            }
                            
                            // Remover Loader
                            const loadingDiv = container.querySelector('.ai-loading');
                            if (loadingDiv) {
                                loadingDiv.remove();
                            }
                            
                            // Criar ou atualizar conteúdo
                            let contentDiv = container.querySelector('.markdown-content');
                            if (!contentDiv) {
                                contentDiv = document.createElement('div');
                                contentDiv.className = 'markdown-content';
                                contentDiv.style.whiteSpace = 'pre-wrap';
                                contentDiv.style.opacity = '0';
                                contentDiv.style.transition = 'opacity 1s ease-in';
                                container.appendChild(contentDiv);
                                
                                // Trigger reflow
                                void contentDiv.offsetWidth; 
                            }
                            
                            contentDiv.innerText = text;
                            contentDiv.style.opacity = '1';
                            
                            // Remover texto de "aguardando" se existir
                            const waitingText = container.querySelector('p.text-muted');
                            if (waitingText && waitingText.textContent.includes('Aguardando')) {
                                waitingText.remove();
                            }
                        } else {
                             // Caso raro onde a IA terminou mas não mandou texto para esta questão
                             const loadingDiv = container.querySelector('.ai-loading');
                             if (loadingDiv) {
                                 loadingDiv.innerHTML = '<p class="text-muted" style="font-style: italic; color: #64748b;">Sem análise específica para esta questão.</p>';
                             }
                        }
                    });
                }
            }
        });
    </script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    // Configure marked for security and typical usage
    marked.setOptions({
        breaks: true, // Enable GFM line breaks
        gfm: true,
        headerIds: false,
        mangle: false
    });
</script>
@endsection