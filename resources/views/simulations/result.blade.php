@extends('layouts.app')

@section('page-title', 'Resultado da Prova')

@section('content')
    <!-- Debug: Vanilla JS Check -->
    <script>
        console.log('JS básico carregado no result.blade.php');
        window.onload = () => { console.log('DOM pronto (window.onload)'); };
    </script>
    
    <!-- Alpine.js CDN (Fallback Check) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

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
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .answers-section h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .answer-item {
            padding: 20px;
            border: 2px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 16px;
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
            font-weight: 600;
            color: #1e293b;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
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
            padding: 16px;
            border-radius: 6px;
            margin-top: 12px;
            font-size: 14px;
            line-height: 1.6;
        }

        .explanation h4 {
            font-size: 13px;
            font-weight: 600;
            color: #2563EB;
            margin-bottom: 8px;
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

                <!-- Alternatives List -->
                <div class="space-y-2 mb-6">
                    @foreach($answer->question->alternatives as $letter => $text)
                        @php
                            $isUserSelected = strtoupper($letter) === strtoupper($answer->user_answer ?? '');
                            $isCorrect = strtoupper($letter) === strtoupper($answer->question->correct_answer);
                            
                            $containerClass = 'border-gray-200 bg-white hover:bg-gray-50';
                            $textClass = 'text-gray-700';
                            
                            if ($isUserSelected && $isCorrect) {
                                // User got it right
                                $containerClass = 'border-green-500 bg-green-50';
                                $textClass = 'text-green-900 font-medium';
                            } elseif ($isUserSelected) {
                                // User selected this but it's wrong
                                $containerClass = 'border-blue-500 bg-blue-50';
                                $textClass = 'text-blue-900 font-medium';
                            } elseif ($isCorrect) {
                                // This is the correct answer (user missed it)
                                $containerClass = 'border-green-400 bg-white ring-1 ring-green-400';
                                $textClass = 'text-green-800 font-medium';
                            }
                        @endphp
                        
                        <div class="p-3 border rounded-md flex gap-3 items-start transition-colors {{ $containerClass }}">
                            <span class="font-bold min-w-[24px] uppercase {{ $textClass }}">{{ $letter }})</span>
                            <div class="flex-1 {{ $textClass }}">{{ $text }}</div>
                            
                            @if($isCorrect)
                                <span class="text-green-600 font-bold" title="Resposta Correta">✓</span>
                            @elseif($isUserSelected)
                                <span class="text-blue-600 font-bold" title="Sua Escolha">●</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @php
                    $aiExplanation = null;
                    if($simulation->correction) {
                        $aiExplanation = $simulation->correction->getExplanationForQuestion($answer->question_id);
                    }
                @endphp

                <div class="explanation" data-question-id="{{ $answer->question_id }}" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <h4 style="display: flex; align-items: center; gap: 8px;">
                        @if($simulation->correction)
                            ✨ Análise da IA
                        @else
                            🤖 Processando Correção...
                        @endif
                    </h4>

                    @if($simulation->correction)
                        @if($aiExplanation)
                            <div class="markdown-content" style="white-space: pre-wrap;">{{ $aiExplanation }}</div>
                        @else
                            <p class="text-muted" style="font-style: italic; color: #64748b;">Aguardando análise detalhada da IA para esta questão...</p>
                        @endif
                    @else
                        <div class="ai-loading" style="color: #64748b;">
                            <p class="blink" style="margin: 0;">[...] Gerando explicação personalizada com IA...</p>
                            <small>Aguarde alguns segundos e atualize a página.</small>
                        </div>
                    @endif
                </div>

                <!-- Chat Contextual -->
                <div class="mt-4 border-t border-gray-100 pt-4" 
                     x-data="chatComponent({{ $simulation->id }}, {{ $answer->question_id }})">
                    
                    <button @click="toggleChat()" 
                            class="text-sm text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-2 transition-colors">
                        <span x-text="showChat ? 'Ocultar Chat' : '💬 Tirar Dúvidas com IA'">💬 Tirar Dúvidas com IA</span>
                    </button>

                    <div x-show="showChat" 
                         x-transition.opacity.duration.300ms
                         x-cloak
                         class="mt-4 bg-gray-50 rounded-lg p-4 border border-gray-200">
                        
                        <!-- History -->
                        <div class="chat-history space-y-3 mb-4 max-h-60 overflow-y-auto" x-ref="history">
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                                    <div 
                                        :class="msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-200'" 
                                        :style="msg.role === 'user' ? 'background-color: #4f46e5 !important; color: white !important;' : 'background-color: white !important; color: #1f2937 !important; border: 1px solid #e5e7eb;'"
                                        class="rounded-lg px-4 py-2 max-w-[85%] text-sm shadow-sm">
                                        <span x-text="msg.message" class="break-words"></span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 mt-1" x-text="msg.role === 'user' ? 'Você' : 'IA'"></span>
                                </div>
                            </template>
                            
                            <!-- Typing Indicator -->
                            <div x-show="isTyping" class="flex items-start">
                                <div class="bg-gray-200 rounded-lg px-4 py-2 text-sm text-gray-500 animate-pulse flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                                    <span class="ml-1 text-xs">IA está digitando...</span>
                                </div>
                            </div>
                            
                            <!-- Loading History -->
                            <div x-show="isLoadingHistory" class="flex justify-center py-2">
                                <span class="text-xs text-gray-400 animate-pulse">Carregando histórico...</span>
                            </div>

                            <!-- Error Message -->
                            <div x-show="errorMessage" class="text-xs text-red-600 text-center mt-2 bg-red-50 p-1 rounded" x-text="errorMessage"></div>
                        </div>

                        <!-- Input Area -->
                        <div class="flex gap-2">
                            <input type="text" 
                                x-model="newMessage" 
                                @keydown.enter.prevent="sendMessage()"
                                placeholder="Digite sua dúvida aqui..." 
                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-gray-900"
                                :disabled="isTyping || isLoadingHistory">
                                
                            <button @click="sendMessage()" 
                                type="button"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm"
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

    <!-- Chat Component Definition -->
    <script>
        window.chatComponent = function(simulationId, questionId) {
            return {
                showChat: false,
                isLoadingHistory: false,
                isTyping: false,
                newMessage: '',
                messages: [],
                errorMessage: null,
                
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
                    
                    this.newMessage = '';
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
@endsection