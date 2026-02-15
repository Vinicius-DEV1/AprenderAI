@extends('layouts.app')

@section('page-title', 'Resultado da Prova')

@section('content')
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
    </style>

    <div class="result-header">
        <h1>{{ number_format($percentageScore, 1) }}%</h1>
        <p>
            Você acertou {{ $correctAnswers }} de {{ $totalQuestions }} questões
        </p>
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
    </div>

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

                <p class="answer-text">
                    <strong>Sua resposta:</strong> {{ $answer->user_answer ?? 'Não respondida' }}
                </p>

                @if(!$answer->is_correct)
                    <p class="answer-text">
                        <strong>Resposta correta:</strong> {{ $answer->question->correct_answer }}
                    </p>
                @endif

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
            </div>
        @endforeach
    </div>

    <div style="text-align: center; margin-top: 32px;">
        <a href="{{ route('dashboard') }}" class="btn-back">Voltar ao Dashboard</a>
        <a href="{{ route('simulations.create') }}" class="btn-back" style="background: #10b981; margin-left: 12px;">Nova
            Prova</a>
    </div>
@endsection