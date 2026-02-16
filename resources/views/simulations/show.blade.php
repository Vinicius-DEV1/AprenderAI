@extends('layouts.app')

@section('page-title', 'Realizando Prova')

@section('content')
<div class="simulation-page">
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
