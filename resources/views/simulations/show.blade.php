@extends('layouts.app')

@section('page-title', 'Realizando Prova')

@section('content')
<div class="simulation-page">
    @if($simulation->status === 'generating')
        <div class="flex flex-col items-center justify-center min-h-screen bg-slate-50 p-6" x-data="{ 
            messages: [
                'Analisando seu desempenho histórico...',
                'Selecionando questões inéditas...',
                'Equilibrando níveis de dificuldade...',
                'Construindo seu DNA pedagógico...',
                'Finalizando a estrutura da prova...',
                'Quase lá! Preparando seu ambiente...'
            ],
            currentMessage: 0,
            init() {
                setInterval(() => {
                    this.currentMessage = (this.currentMessage + 1) % this.messages.length;
                }, 3000);
            }
        }">
            <style>
                @keyframes pulse-glow {
                    0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(79, 70, 229, 0.4); }
                    50% { transform: scale(1.05); box-shadow: 0 0 40px rgba(79, 70, 229, 0.6); }
                }

                @keyframes rotate-ring {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }

                @keyframes shimmer {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }

                .ai-orb-container {
                    position: relative;
                    width: 120px;
                    height: 120px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 2rem;
                }

                .ai-orb {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 800;
                    letter-spacing: 1px;
                    z-index: 10;
                    animation: pulse-glow 3s ease-in-out infinite;
                    position: relative;
                }

                .ai-ring {
                    position: absolute;
                    width: 110px;
                    height: 110px;
                    border: 2px solid transparent;
                    border-top-color: #4f46e5;
                    border-right-color: rgba(79, 70, 229, 0.2);
                    border-bottom-color: #7c3aed;
                    border-left-color: rgba(124, 58, 237, 0.2);
                    border-radius: 50%;
                    animation: rotate-ring 4s linear infinite;
                }

                .ai-ring-outer {
                    position: absolute;
                    width: 120px;
                    height: 120px;
                    border: 1px dashed rgba(79, 70, 229, 0.3);
                    border-radius: 50%;
                    animation: rotate-ring 12s linear infinite reverse;
                }

                .progress-bar-container {
                    width: 100%;
                    max-width: 350px;
                    height: 8px;
                    background: #e2e8f0;
                    border-radius: 10px;
                    overflow: hidden;
                    position: relative;
                }

                .progress-bar-fill {
                    height: 100%;
                    width: 100%;
                    background: linear-gradient(90deg, #4f46e5, #7c3aed, #4f46e5);
                    background-size: 200% 100%;
                    animation: bg-move 3s linear infinite;
                    border-radius: 10px;
                    position: relative;
                }

                @keyframes bg-move {
                    0% { background-position: 0% 0%; }
                    100% { background-position: -200% 0%; }
                }

                .shimmer-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(
                        90deg,
                        transparent,
                        rgba(255, 255, 255, 0.4),
                        transparent
                    );
                    animation: shimmer 1.5s infinite;
                }
            </style>

            <div class="text-center p-10 bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-100">
                <div class="ai-orb-container mx-auto">
                    <div class="ai-ring-outer"></div>
                    <div class="ai-ring"></div>
                    <div class="ai-orb">
                        <span class="text-xl">AI</span>
                    </div>
                </div>
                
                <h2 class="text-2xl font-extrabold text-slate-800 mb-2">Construindo seu Simulado</h2>
                
                <div class="h-12 flex items-center justify-center mb-6 relative">
                    <template x-for="(msg, index) in messages" :key="index">
                        <p x-show="currentMessage === index" 
                           x-transition:enter="transition ease-out duration-500"
                           x-transition:enter-start="opacity-0 transform translate-y-2"
                           x-transition:enter-end="opacity-100 transform translate-y-0"
                           x-transition:leave="transition ease-in duration-300"
                           x-transition:leave-start="opacity-100 transform translate-y-0"
                           x-transition:leave-end="opacity-0 transform -translate-y-2"
                           class="text-indigo-600 font-medium text-lg absolute"
                           x-text="msg">
                        </p>
                    </template>
                </div>

                <div class="progress-bar-container mx-auto mb-4">
                    <div class="progress-bar-fill">
                        <div class="shimmer-overlay"></div>
                    </div>
                </div>

                <p class="text-slate-400 text-sm">
                    Isso geralmente leva menos de 10 segundos.
                </p>

                <div id="status-debug" class="text-xs text-gray-300 font-mono mt-4 hidden">Polling active...</div>
            </div>

            <script>
                const simulationId = {{ $simulation->id }};
                
                function checkStatus() {
                    fetch(`/simulations/${simulationId}/status`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.simulation_status === 'error') {
                                alert('Ocorreu um erro ao gerar o simulado. Por favor, tente novamente.');
                                window.location.href = '/simulations/create';
                            } else if (data.simulation_status !== 'generating') {
                                window.location.reload();
                            }
                        })
                        .catch(err => console.error('Polling error:', err));
                }

                // Poll every 2 seconds
                setInterval(checkStatus, 2000);
            </script>
        </div>
    @else
    <style>
        /* Scoped styles for simulation page ONLY */
        .simulation-page .simulation-full-width {
            max-width: 100% !important;
            padding: 0 20px !important;
        }
        
        .simulation-page .simulation-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 24px;
            height: calc(100vh - 120px);
        }
        
        @media (max-width: 768px) {
            .simulation-page .simulation-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .simulation-page .question-nav {
                display: none;
            }
        }
        
        .simulation-page .question-nav {
            background: white;
            border-radius: 12px;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }
        
        .simulation-page .timer {
            background: #1e293b;
            color: white;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .simulation-page .timer-label {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 4px;
        }
        
        .simulation-page .timer-value {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .simulation-page .nav-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 20px;
            flex: 1;
            overflow-y: auto;
        }
        
        .simulation-page .nav-btn {
            width: 100%;
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .simulation-page .nav-btn:hover {
            border-color: #cbd5e1;
        }
        
        .simulation-page .nav-btn.answered {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .simulation-page .nav-btn.marked {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #78350f;
        }
        
        .simulation-page .nav-btn.active {
            background: #2563EB;
            border-color: #2563EB;
            color: white;
        }
        
        .simulation-page .legend {
            font-size: 12px;
            margin-top: 16px;
        }
        
        .simulation-page .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .simulation-page .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }
        
        .simulation-page .question-area {
            background: white;
            border-radius: 12px;
            padding: 32px;
            overflow-y: auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        
        .simulation-page .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap; /* Safe wrapping */
            gap: 10px;
        }
        
        .simulation-page .question-number {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }
        
        .simulation-page .question-statement {
            font-size: 16px;
            line-height: 1.7;
            color: #1e293b;
            margin-bottom: 32px;
            max-width: 900px; /* Readability limit */
            white-space: pre-wrap;
        }
        
        .simulation-page .alternatives {
            list-style: none;
            max-width: 900px;
        }
        
        .simulation-page .alternative {
            margin-bottom: 16px;
        }
        
        .simulation-page .alternative label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .simulation-page .alternative label:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        
        .simulation-page .alternative input[type="radio"]:checked + label {
            border-color: #2563EB;
            background: #eff6ff;
        }
        
        .simulation-page .alternative input[type="radio"] {
            margin-top: 2px;
        }
        
        .simulation-page .alternative-letter {
            font-weight: 700;
            color: #2563EB;
            min-width: 20px; /* Prevent shrinking */
        }
        
        .simulation-page .question-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #f1f5f9;
        }
        
        .simulation-page .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .simulation-page .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }
        
        .simulation-page .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .simulation-page .btn-primary {
            background: #2563EB;
            color: white;
        }
        
        .simulation-page .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .simulation-page .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .simulation-page .btn-danger:hover {
            background: #dc2626;
        }
        
        .simulation-page .checkbox-mark {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Toggle Button Style */
        .simulation-page #sidebarToggleBtn {
            margin-right: 15px; 
            padding: 8px 16px; 
            background: #475569; 
            color: white; 
            border-radius: 6px; 
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .simulation-page #sidebarToggleBtn:hover {
            background: #334155;
        }

        /* DARK MODE */
        :root.dark .simulation-page .question-nav,
        :root.dark .simulation-page .question-area {
            background: #1e293b;
            color: #f1f5f9;
        }
        :root.dark .simulation-page h2 {
            color: #f1f5f9;
        }
        :root.dark .simulation-page .question-statement {
            color: #e2e8f0;
        }
        :root.dark .simulation-page .question-number {
             color: #94a3b8;
        }
        :root.dark .simulation-page .question-header {
             border-bottom-color: rgba(255,255,255,0.1);
        }
        :root.dark .simulation-page .question-actions {
             border-top-color: rgba(255,255,255,0.1);
        }
        :root.dark .simulation-page .alternative label {
             border-color: rgba(255,255,255,0.1);
             color: #cbd5e1;
        }
        :root.dark .simulation-page .alternative label:hover {
             background: rgba(255,255,255,0.05);
             border-color: rgba(255,255,255,0.2);
        }
        :root.dark .simulation-page .alternative input[type="radio"]:checked + label {
             background: rgba(37,99,235,0.2);
             border-color: #3b82f6;
             color: #e2e8f0;
        }
        :root.dark .simulation-page .nav-btn {
             background: #0f172a;
             border-color: rgba(255,255,255,0.1);
             color: #cbd5e1;
        }
        :root.dark .simulation-page .nav-btn:hover {
             border-color: rgba(255,255,255,0.3);
        }
        :root.dark .simulation-page .nav-btn.active {
             background: #2563EB;
             color: white;
             border-color: #2563EB;
        }
        :root.dark .simulation-page .nav-btn.answered {
             background: rgba(6, 95, 70, 0.4);
             border-color: #059669;
             color: #a7f3d0;
        }
        :root.dark .simulation-page .nav-btn.marked {
             background: rgba(120, 53, 15, 0.4);
             border-color: #d97706;
             color: #fde68a;
        }
        :root.dark .simulation-page .legend-item span {
             color: #94a3b8;
        }
        :root.dark .simulation-page .btn-secondary {
             background: #334155;
             color: #e2e8f0;
        }
        :root.dark .simulation-page .btn-secondary:hover {
             background: #475569;
        }
    </style>

    <div style="margin-bottom: 20px; display: flex; align-items: center;">
        <button type="button" id="sidebarToggleBtn" onclick="toggleAppSidebar()">
            ☰ Menu Painel
        </button>
        <h2 class="text-xl font-bold text-gray-800">Simulado em Progresso</h2>
    </div>

    <div class="simulation-container">
        <!-- Navigation Sidebar (Prova) -->
        <aside class="question-nav">
            <div class="timer" id="timer">
                <div class="timer-label">Tempo restante</div>
                <div class="timer-value" id="timerDisplay">00:00:00</div>
            </div>
            
            <div class="nav-grid" id="questionNavGrid">
                @foreach($simulation->answers as $index => $answer)
                    <button 
                        type="button" 
                        class="nav-btn {{ $index === 0 ? 'active' : '' }} {{ $answer->user_answer ? 'answered' : '' }} {{ $answer->marked_for_review ? 'marked' : '' }}"
                        data-question="{{ $index }}"
                        onclick="goToQuestion({{ $index }})">
                        {{ $index + 1 }}
                    </button>
                @endforeach
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #d1fae5; border-color: #10b981;"></div>
                    <span>Respondida</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #fef3c7; border-color: #f59e0b;"></div>
                    <span>Marcada</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: white; border-color: #e2e8f0;"></div>
                    <span>Não respondida</span>
                </div>
            </div>
            
            <button type="button" class="btn btn-danger" style="width: 100%; margin-top: 20px;" onclick="finishSimulation()">
                Finalizar Prova
            </button>
        </aside>
        
        <!-- Question Area -->
        <main class="question-area" id="questionArea">
            @foreach($simulation->answers as $index => $answer)
                <div class="question-content" data-question="{{ $index }}" style="display: {{ $index === 0 ? 'block' : 'none' }};">
                    <div class="question-header">
                        <span class="question-number">Questão {{ $index + 1 }} de {{ $simulation->answers->count() }}</span>
                        <span style="font-size: 14px; color: #64748b; background: #f1f5f9; padding: 4px 12px; rounded: 12px;">
                            {{ ucfirst($answer->question->subject) }}
                        </span>
                    </div>
                    
                    <div class="question-statement">
                        {!! $answer->question->statement_html !!}
                    </div>
                    
                    <ul class="alternatives">
                        @php
                            $alternatives = is_array($answer->question->alternatives) 
                                ? $answer->question->alternatives 
                                : json_decode($answer->question->alternatives, true);
                        @endphp
                        
                        @foreach($alternatives as $letter => $text)
                            <li class="alternative">
                                <input 
                                    type="radio" 
                                    name="question_{{ $answer->question->id }}" 
                                    id="q{{ $answer->question->id }}_{{ $letter }}"
                                    value="{{$letter}}"
                                    {{ $answer->user_answer === $letter ? 'checked' : '' }}
                                    onchange="saveAnswer({{ $answer->question->id }}, '{{ $letter }}', {{ $index }})">
                                <label for="q{{ $answer->question->id }}_{{ $letter }}">
                                    <span class="alternative-letter">{{ $letter }})</span>
                                    <span>{{ $text }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    
                    <div class="question-actions">
                        <div class="checkbox-mark">
                            <input 
                                type="checkbox" 
                                id="mark_{{ $index }}"
                                {{ $answer->marked_for_review ? 'checked' : '' }}
                                onchange="toggleMark({{ $answer->question->id }}, {{ $index }})">
                            <label for="mark_{{ $index }}">Marcar para revisão</label>
                        </div>
                        
                        <div style="display: flex; gap: 12px;">
                            @if($index > 0)
                                <button type="button" class="btn btn-secondary" onclick="previousQuestion()">← Anterior</button>
                            @endif
                            @if($index < $simulation->answers->count() - 1)
                                <button type="button" class="btn btn-primary" onclick="nextQuestion()">Próxima →</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </main>
    </div>
    @endif
</div>

<script>
let currentQuestion = 0;
const totalQuestions = {{ $simulation->answers->count() }};
const simulationId = {{ $simulation->id }};
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let appSidebar = null;

// Timer
let timeLimit = {{ $simulation->configuration['time_limit'] ?? 10800 }};
let timeRemaining = timeLimit;

function updateTimer() {
    const hours = Math.floor(timeRemaining / 3600);
    const minutes = Math.floor((timeRemaining % 3600) / 60);
    const seconds = timeRemaining % 60;
    
    document.getElementById('timerDisplay').textContent = 
        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    if (timeRemaining > 0) {
        timeRemaining--;
    } else {
        finishSimulation();
    }
}

setInterval(updateTimer, 1000);
updateTimer();

function goToQuestion(index) {
    document.querySelectorAll('.question-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
    
    document.querySelector(`.question-content[data-question="${index}"]`).style.display = 'block';
    document.querySelectorAll('.nav-btn')[index].classList.add('active');
    
    currentQuestion = index;
}

function nextQuestion() {
    if (currentQuestion < totalQuestions - 1) {
        goToQuestion(currentQuestion + 1);
        window.scrollTo(0, 0); // Scroll to top
    }
}

function previousQuestion() {
    if (currentQuestion > 0) {
        goToQuestion(currentQuestion - 1);
        window.scrollTo(0, 0); // Scroll to top
    }
}

async function saveAnswer(questionId, answer, index) {
    try {
        await fetch(`/simulations/${simulationId}/answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                question_id: questionId,
                answer: answer,
                time_spent: timeLimit - timeRemaining
            })
        });
        document.querySelectorAll('.nav-btn')[index].classList.add('answered');
    } catch (error) {
        console.error('Erro ao salvar resposta:', error);
    }
}

async function toggleMark(questionId, index) {
    const marked = document.getElementById(`mark_${index}`).checked;
    try {
        await fetch(`/simulations/${simulationId}/answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                question_id: questionId,
                marked_for_review: marked
            })
        });
        
        if (marked) {
            document.querySelectorAll('.nav-btn')[index].classList.add('marked');
        } else {
            document.querySelectorAll('.nav-btn')[index].classList.remove('marked');
        }
    } catch (error) {
        console.error('Erro ao marcar questão:', error);
    }
}

function finishSimulation() {
    if (confirm('Tem certeza que deseja finalizar a prova? Esta ação não pode ser desfeita.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/simulations/${simulationId}/finish`;
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Layout Logic: Auto-Collapse Sidebar (Scoped)
document.addEventListener('DOMContentLoaded', () => {
    // Only execute if on simulation page
    if (!document.querySelector('.simulation-page')) return;

    // 1. Identify Dashboard Sidebar (exclude .question-nav)
    appSidebar = document.querySelector('aside:not(.question-nav)');
    
    if (appSidebar) {
        // Collapse by default
        appSidebar.style.display = 'none';
        appSidebar.classList.remove('lg:flex'); // Remove Tailwind responsive flex
    }
    
    // 2. Maximize Container Width (remove max-w-7xl)
    // We look for parent containers with max-w-7xl
    const container = document.querySelector('.max-w-7xl');
    if (container) {
        container.classList.remove('max-w-7xl');
        container.classList.remove('mx-auto');
        container.classList.add('w-full');
        container.style.maxWidth = '100%';
        container.style.padding = '0 20px';
    }
});

// Global Function for Button
window.toggleAppSidebar = function() {
    if (!appSidebar) return;
    
    if (appSidebar.style.display === 'none') {
        appSidebar.style.display = '';
        appSidebar.classList.add('lg:flex'); // Restore
        document.getElementById('sidebarToggleBtn').textContent = '☰ Fechar Menu';
    } else {
        appSidebar.style.display = 'none';
        appSidebar.classList.remove('lg:flex');
        document.getElementById('sidebarToggleBtn').textContent = '☰ Abrir Menu';
    }
}

window.onbeforeunload = function() {
    return "Você tem certeza que deseja sair? Seu progresso foi salvo automaticamente.";
};
</script>
@endsection
