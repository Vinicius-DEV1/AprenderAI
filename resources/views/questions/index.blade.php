@extends('layouts.app')

@section('page-title', 'Banco de Questões')

@section('content')
<style>
    /* === Question Bank Styles === */
    .qb-header { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); border-radius: 16px; padding: 32px; color: white; margin-bottom: 24px; }
    .qb-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
    .qb-header p { font-size: 14px; opacity: 0.85; }

    .qb-stats { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
    .qb-stat { background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border-radius: 10px; padding: 12px 20px; text-align: center; min-width: 100px; }
    .qb-stat .val { font-size: 24px; font-weight: 700; }
    .qb-stat .lbl { font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }

    .qb-filters { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; border: 1px solid #e2e8f0; }
    .qb-filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
    .qb-filter-grid select, .qb-filter-grid input { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; background: #f8fafc; transition: border-color 0.2s; }
    .qb-filter-grid select:focus, .qb-filter-grid input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
    .qb-filter-actions { display: flex; gap: 8px; margin-top: 12px; }
    .qb-btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
    .qb-btn-primary { background: #6366f1; color: white; }
    .qb-btn-primary:hover { background: #4f46e5; transform: translateY(-1px); }
    .qb-btn-ghost { background: transparent; color: #64748b; border: 1px solid #e2e8f0; }
    .qb-btn-ghost:hover { background: #f1f5f9; }

    .qb-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 16px; border: 1px solid #e2e8f0; transition: box-shadow 0.2s; }
    .qb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .qb-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
    .qb-card-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .qb-card-id { font-size: 12px; font-weight: 700; color: #6366f1; }
    .qb-badge { padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; letter-spacing: 0.3px; }
    .qb-badge-easy { background: #d1fae5; color: #065f46; }
    .qb-badge-medium { background: #fef3c7; color: #92400e; }
    .qb-badge-hard { background: #fee2e2; color: #991b1b; }
    .qb-badge-origin { background: #e2e8f0; color: #475569; }
    .qb-badge-ai { background: #e9d5ff; color: #6b21a8; }
    .qb-badge-correct { background: #d1fae5; color: #065f46; }
    .qb-badge-incorrect { background: #fee2e2; color: #991b1b; }

    .qb-statement { font-size: 15px; line-height: 1.7; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; }

    .qb-alt { display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; border-radius: 10px; border: 2px solid #f1f5f9; margin-bottom: 8px; cursor: pointer; transition: all 0.15s ease; }
    .qb-alt:hover { border-color: #c7d2fe; background: #f5f3ff; }
    .qb-alt.selected { border-color: #6366f1; background: #eef2ff; }
    .qb-alt.correct-reveal { border-color: #10b981; background: #f0fdf4; }
    .qb-alt.incorrect-reveal { border-color: #ef4444; background: #fef2f2; }
    .qb-alt.disabled { pointer-events: none; opacity: 0.7; }
    .qb-alt-letter { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; background: #f1f5f9; color: #64748b; transition: all 0.15s; }
    .qb-alt.selected .qb-alt-letter { background: #6366f1; color: white; }
    .qb-alt.correct-reveal .qb-alt-letter { background: #10b981; color: white; }
    .qb-alt.incorrect-reveal .qb-alt-letter { background: #ef4444; color: white; }
    .qb-alt-text { font-size: 14px; color: #334155; line-height: 1.5; }

    .qb-card-actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
    .qb-action-btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
    .qb-action-btn:hover { background: #f1f5f9; border-color: #cbd5e1; transform: translateY(-1px); }
    .qb-action-btn.primary { background: #6366f1; color: white; border-color: #6366f1; }
    .qb-action-btn.primary:hover { background: #4f46e5; }
    .qb-action-btn.primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .qb-feedback { margin-top: 16px; padding: 16px; border-radius: 10px; animation: fadeSlideIn 0.3s ease; }
    .qb-feedback.correct { background: #f0fdf4; border: 1px solid #bbf7d0; }
    .qb-feedback.incorrect { background: #fef2f2; border: 1px solid #fecaca; }
    .qb-feedback-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
    .qb-feedback.correct .qb-feedback-title { color: #065f46; }
    .qb-feedback.incorrect .qb-feedback-title { color: #991b1b; }

    .qb-explanation { background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 12px; border: 1px solid #e2e8f0; }
    .qb-explanation h4 { font-size: 13px; font-weight: 700; color: #6366f1; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
    .qb-explanation-text { font-size: 13px; line-height: 1.7; color: #475569; }

    .qb-difficulty-box { background: #fefce8; border-radius: 8px; padding: 12px 16px; margin-top: 8px; border: 1px solid #fde68a; }
    .qb-difficulty-box h5 { font-size: 12px; font-weight: 700; color: #92400e; margin-bottom: 4px; }
    .qb-difficulty-box p { font-size: 12px; line-height: 1.5; color: #78350f; }

    /* Skeleton */
    .qb-skeleton { background: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; border: 1px solid #e2e8f0; }
    .qb-skel-line { height: 14px; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; border-radius: 6px; margin-bottom: 10px; animation: shimmer 1.5s infinite; }
    .qb-skel-line.w-75 { width: 75%; }
    .qb-skel-line.w-100 { width: 100%; }
    .qb-skel-line.w-60 { width: 60%; }
    .qb-skel-line.w-40 { width: 40%; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* Charts */
    .qb-charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .qb-chart-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
    .qb-chart-card h3 { font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 12px; }

    /* Chat */
    .qb-chat-container { margin-top: 12px; background: #f8fafc; border-radius: 10px; padding: 14px; border: 1px solid #e2e8f0; }
    .qb-chat-history { max-height: 200px; overflow-y: auto; margin-bottom: 10px; }

    /* Pagination */
    .qb-pagination { display: flex; justify-content: center; margin-top: 24px; }
    .qb-pagination nav { display: flex; } 

    /* Dark Mode */
    :root.dark .qb-filters { background: #1e293b; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-filter-grid select, :root.dark .qb-filter-grid input { background: #0f172a; border-color: rgba(255,255,255,0.1); color: #e2e8f0; }
    :root.dark .qb-card { background: #1e293b; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-statement { color: #e2e8f0; border-bottom-color: rgba(255,255,255,0.1); }
    :root.dark .qb-alt { border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-alt:hover { border-color: #6366f1; background: rgba(99,102,241,0.1); }
    :root.dark .qb-alt-text { color: #cbd5e1; }
    :root.dark .qb-alt-letter { background: #334155; color: #94a3b8; }
    :root.dark .qb-explanation { background: #0f172a; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-explanation-text { color: #94a3b8; }
    :root.dark .qb-chart-card { background: #1e293b; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-chart-card h3 { color: #e2e8f0; }
    :root.dark .qb-skeleton { background: #1e293b; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-skel-line { background: linear-gradient(90deg, #334155 25%, #475569 50%, #334155 75%); background-size: 200% 100%; }
    :root.dark .qb-chat-container { background: #0f172a; border-color: rgba(255,255,255,0.1); }
    :root.dark .qb-action-btn { background: #334155; border-color: rgba(255,255,255,0.1); color: #cbd5e1; }
    :root.dark .qb-difficulty-box { background: rgba(254,243,199,0.1); border-color: rgba(253,230,138,0.3); }
    :root.dark .qb-difficulty-box h5 { color: #fcd34d; }
    :root.dark .qb-difficulty-box p { color: #fde68a; }
    [x-cloak] { display: none !important; }
</style>

{{-- ====== HEADER ====== --}}
<div class="qb-header">
    <h1>📋 Banco de Questões</h1>
    <p>Resolva questões, veja explicações e tire dúvidas com IA</p>
    <div class="qb-stats">
        <div class="qb-stat"><div class="val">{{ $overview['total'] }}</div><div class="lbl">Respondidas</div></div>
        <div class="qb-stat"><div class="val">{{ $overview['accuracy'] }}%</div><div class="lbl">Acerto</div></div>
        <div class="qb-stat"><div class="val">{{ $overview['correct'] }}</div><div class="lbl">Acertos</div></div>
        <div class="qb-stat"><div class="val">{{ $overview['incorrect'] }}</div><div class="lbl">Erros</div></div>
        <div class="qb-stat" style="cursor:pointer" x-data @click="document.getElementById('stats-section').scrollIntoView({behavior:'smooth'})">
            <div class="val">📊</div><div class="lbl">Estatísticas</div>
        </div>
    </div>
</div>

{{-- ====== FILTERS ====== --}}
<div class="qb-filters" x-data="{ expanded: true }">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; cursor:pointer" @click="expanded = !expanded">
        <h2 style="font-size:16px; font-weight:600; color:#334155; display:flex; align-items:center; gap:8px">
            🔍 Filtros
            <span style="font-size:11px; color:#94a3b8; font-weight:400">{{ $questions->total() }} questões encontradas</span>
        </h2>
        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </div>
    <form method="GET" action="{{ route('questions.index') }}" x-show="expanded" x-transition>
        <div class="qb-filter-grid">
            <input type="text" name="keyword" placeholder="Buscar no enunciado..." value="{{ request('keyword') }}">
            <select name="subject">
                <option value="">Matéria</option>
                @foreach($filterOptions['subjects'] as $s)
                    <option value="{{ $s }}" {{ request('subject') == $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <select name="topic">
                <option value="">Assunto</option>
                @foreach($filterOptions['topics'] as $t)
                    <option value="{{ $t }}" {{ request('topic') == $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
            <select name="year">
                <option value="">Ano</option>
                @foreach($filterOptions['years'] as $y)
                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <select name="organization">
                <option value="">Banca</option>
                @foreach($filterOptions['organizations'] as $o)
                    <option value="{{ $o }}" {{ request('organization') == $o ? 'selected' : '' }}>{{ $o }}</option>
                @endforeach
            </select>
            <select name="institution">
                <option value="">Órgão</option>
                @foreach($filterOptions['institutions'] as $i)
                    <option value="{{ $i }}" {{ request('institution') == $i ? 'selected' : '' }}>{{ $i }}</option>
                @endforeach
            </select>
            <select name="difficulty">
                <option value="">Dificuldade</option>
                <option value="easy" {{ request('difficulty') == 'easy' ? 'selected' : '' }}>Fácil</option>
                <option value="medium" {{ request('difficulty') == 'medium' ? 'selected' : '' }}>Média</option>
                <option value="hard" {{ request('difficulty') == 'hard' ? 'selected' : '' }}>Difícil</option>
            </select>
            <select name="status">
                <option value="">Status</option>
                <option value="unanswered" {{ request('status') == 'unanswered' ? 'selected' : '' }}>Não respondidas</option>
                <option value="answered" {{ request('status') == 'answered' ? 'selected' : '' }}>Respondidas</option>
                <option value="correct" {{ request('status') == 'correct' ? 'selected' : '' }}>Acertei</option>
                <option value="incorrect" {{ request('status') == 'incorrect' ? 'selected' : '' }}>Errei</option>
            </select>
            <select name="type">
                <option value="">Tipo</option>
                <option value="enem" {{ request('type') == 'enem' ? 'selected' : '' }}>ENEM</option>
                <option value="concurso" {{ request('type') == 'concurso' ? 'selected' : '' }}>Concurso</option>
            </select>
        </div>
        <div class="qb-filter-actions">
            <button type="submit" class="qb-btn qb-btn-primary">Filtrar</button>
            <a href="{{ route('questions.index') }}" class="qb-btn qb-btn-ghost">Limpar</a>
        </div>
    </form>
</div>

{{-- ====== QUESTIONS FEED ====== --}}
@forelse($questions as $question)
    <div class="qb-card" x-data="questionCard({{ $question->id }}, {{ json_encode(isset($answeredMap[$question->id])) }}, {{ json_encode($answeredMap[$question->id] ?? null) }})">
        {{-- Header --}}
        <div class="qb-card-header">
            <div class="qb-card-meta">
                <span class="qb-card-id">#{{ $question->external_id ?? $question->id }}</span>
                @if($question->origin)
                    <span class="qb-badge qb-badge-origin">{{ $question->origin }}</span>
                @elseif($question->source === 'ai_generated')
                    <span class="qb-badge qb-badge-ai">✨ INÉDITA</span>
                @endif
                @if($question->year)
                    <span class="qb-badge qb-badge-origin">{{ $question->year }}</span>
                @endif
                <span class="qb-badge qb-badge-origin">{{ $question->subjects->pluck('name')->join(', ') }}</span>
                @php
                    $dc = match($question->difficulty) {
                        'easy'   => ['class' => 'qb-badge-easy',   'label' => 'Fácil'],
                        'medium' => ['class' => 'qb-badge-medium', 'label' => 'Média'],
                        'hard'   => ['class' => 'qb-badge-hard',   'label' => 'Difícil'],
                        default  => null,
                    };
                @endphp
                @if($dc)
                    <span class="qb-badge {{ $dc['class'] }}" title="{{ $question->difficulty_reasoning ?? '' }}">{{ $dc['label'] }}</span>
                @endif

                {{-- Previously answered badge --}}
                <template x-if="alreadyAnswered">
                    <span class="qb-badge" :class="wasCorrect ? 'qb-badge-correct' : 'qb-badge-incorrect'" x-text="wasCorrect ? '✓ Acertou' : '✗ Errou'"></span>
                </template>
            </div>
        </div>

        {{-- Statement --}}
        <div class="qb-statement">
            {!! $question->statement_html ?? nl2br(e($question->statement)) !!}
        </div>

        {{-- Alternatives --}}
        @foreach($question->alternatives as $letter => $text)
            <div class="qb-alt"
                 :class="{
                    'selected': selectedAnswer === '{{ $letter }}' && !answered,
                    'correct-reveal': answered && '{{ $letter }}' === correctAnswer,
                    'incorrect-reveal': answered && selectedAnswer === '{{ $letter }}' && '{{ $letter }}' !== correctAnswer,
                    'disabled': answered
                 }"
                 @click="!answered ? selectAnswer('{{ $letter }}') : null">
                <div class="qb-alt-letter">{{ $letter }}</div>
                <div class="qb-alt-text">{{ $text }}</div>
                <template x-if="answered && '{{ $letter }}' === correctAnswer">
                    <svg class="w-5 h-5 text-green-600 ml-auto flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </template>
            </div>
        @endforeach

        {{-- Actions --}}
        <div class="qb-card-actions">
            <button class="qb-action-btn primary" @click="submitAnswer()" :disabled="!selectedAnswer || answered || submitting" x-show="!answered">
                <span x-show="!submitting">📝 Responder</span>
                <span x-show="submitting">⏳ Enviando...</span>
            </button>
            <button class="qb-action-btn" @click="showChat = !showChat" x-show="answered">
                <span x-text="showChat ? 'Ocultar Chat' : '💬 Tirar Dúvida'"></span>
            </button>
        </div>

        {{-- Feedback --}}
        <template x-if="answered">
            <div class="qb-feedback" :class="isCorrect ? 'correct' : 'incorrect'">
                <div class="qb-feedback-title">
                    <span x-text="isCorrect ? '✅ Resposta Correta!' : '❌ Resposta Incorreta'"></span>
                    <span style="font-weight:400; font-size:12px; color:#64748b" x-show="!isCorrect">
                        Correta: <strong x-text="correctAnswer" style="color:#065f46"></strong>
                    </span>
                </div>

                {{-- Explanation --}}
                <div class="qb-explanation" x-show="explanation">
                    <h4>📖 Resolução Comentada</h4>
                    <div class="qb-explanation-text markdown-body" x-html="renderMd(explanation)"></div>
                </div>

                {{-- Difficulty Reasoning --}}
                <div class="qb-difficulty-box" x-show="difficultyReasoning">
                    <h5>🎯 Por que esta questão tem essa dificuldade?</h5>
                    <p x-text="difficultyReasoning"></p>
                </div>
            </div>
        </template>

        {{-- Chat (Mentoring) --}}
        <div x-show="showChat" x-transition x-cloak class="qb-chat-container">
            <div class="qb-chat-history space-y-2 p-1" x-ref="chatHistory">
                <template x-for="msg in chatMessages" :key="msg.id || msg.created_at">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : (msg.role === 'system' ? 'flex justify-center' : 'flex justify-start')">
                        <template x-if="msg.role === 'user'">
                            <div class="bg-indigo-600 text-white rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" style="background-color:#4f46e5!important;color:white!important">
                                <span x-text="msg.message"></span>
                            </div>
                        </template>
                        <template x-if="msg.role === 'assistant'">
                            <div class="bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm markdown-body">
                                <div x-html="renderMd(msg.message)"></div>
                            </div>
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
                    <div class="bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 border border-gray-200 dark:border-slate-600">
                        <span class="font-medium">IA está digitando</span>
                        <span class="flex gap-1">
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.1s"></span>
                            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.2s"></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-2">
                <input type="text" x-model="chatInput" @keydown.enter.prevent="sendChat()"
                    placeholder="Tire sua dúvida..." :disabled="chatTyping"
                    class="flex-1 rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-100 shadow-sm text-xs px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                <button @click="sendChat()" :disabled="chatTyping || !chatInput.trim()"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-xs font-medium disabled:opacity-50 transition-all">
                    Enviar
                </button>
            </div>
        </div>
    </div>
@empty
    <div class="qb-card" style="text-align:center; padding:48px">
        <p style="font-size:18px; color:#94a3b8; margin-bottom:8px">🔍 Nenhuma questão encontrada</p>
        <p style="font-size:13px; color:#cbd5e1">Tente ajustar seus filtros ou limpar a busca.</p>
    </div>
@endforelse

{{-- Pagination --}}
<div class="qb-pagination">
    {{ $questions->links() }}
</div>

{{-- ====== STATS SECTION ====== --}}
<div id="stats-section" style="margin-top:40px">
    <h2 style="font-size:20px; font-weight:700; color:#334155; margin-bottom:16px; display:flex; align-items:center; gap:8px" class="dark:text-slate-100">
        📊 Seu Desempenho
    </h2>
    <div class="qb-charts" x-data="statsCharts()" x-init="loadStats()">
        <div class="qb-chart-card">
            <h3>Acerto por Matéria</h3>
            <canvas id="chartSubject" height="200"></canvas>
        </div>
        <div class="qb-chart-card">
            <h3>Evolução (últimos 30 dias)</h3>
            <canvas id="chartTemporal" height="200"></canvas>
        </div>
        <div class="qb-chart-card">
            <h3>Heatmap de Dificuldade</h3>
            <canvas id="chartDifficulty" height="200"></canvas>
        </div>
    </div>
</div>

{{-- ====== SCRIPTS ====== --}}
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    marked.setOptions({ breaks: true, gfm: true, headerIds: false, mangle: false });

    function questionCard(questionId, alreadyAnswered, wasCorrect) {
        return {
            questionId,
            alreadyAnswered,
            wasCorrect,
            selectedAnswer: null,
            answered: alreadyAnswered,
            isCorrect: wasCorrect,
            correctAnswer: null,
            explanation: null,
            difficultyReasoning: null,
            submitting: false,
            showChat: false,
            chatMessages: [],
            chatInput: '',
            chatTyping: false,
            chatLoaded: false,

            selectAnswer(letter) {
                this.selectedAnswer = letter;
            },

            async submitAnswer() {
                if (!this.selectedAnswer || this.answered || this.submitting) return;
                this.submitting = true;

                try {
                    const res = await fetch(`/questions/${this.questionId}/answer`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ selected_answer: this.selectedAnswer })
                    });

                    const data = await res.json();
                    this.answered = true;
                    this.isCorrect = data.correct;
                    this.correctAnswer = data.correct_answer;
                    this.explanation = data.explanation || '';
                    this.difficultyReasoning = data.difficulty_reasoning || '';
                    this.alreadyAnswered = true;
                    this.wasCorrect = data.correct;
                } catch (e) {
                    alert('Erro ao enviar resposta. Tente novamente.');
                } finally {
                    this.submitting = false;
                }
            },

            renderMd(text) {
                if (!text) return '';
                try { return marked.parse(text); } catch (e) { return text; }
            },

            async loadChatHistory() {
                if (this.chatLoaded) return;
                try {
                    const res = await fetch(`/questions/${this.questionId}/chat`);
                    if (res.ok) { this.chatMessages = await res.json(); this.chatLoaded = true; }
                } catch (e) { console.error('Failed to load chat', e); }
            },

            async sendChat() {
                if (!this.chatInput.trim()) return;
                if (!this.chatLoaded) await this.loadChatHistory();

                const msg = this.chatInput;
                this.chatMessages.push({ role: 'user', message: msg, id: Date.now() });
                this.chatInput = '';
                this.chatTyping = true;

                try {
                    const res = await fetch(`/questions/${this.questionId}/chat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ message: msg })
                    });
                    const data = await res.json();

                    if (data.status === 'quota_exceeded') {
                        this.chatMessages.push({ role: 'system', message: data.message, upgrade_url: data.upgrade_url, id: Date.now() });
                        this.chatTyping = false;
                        return;
                    }

                    this.pollChat();
                } catch (e) {
                    this.chatTyping = false;
                    this.chatMessages.push({ role: 'assistant', message: 'Erro ao processar. Tente novamente.', id: Date.now() });
                }
            },

            pollChat() {
                let attempts = 0;
                const poller = setInterval(async () => {
                    attempts++;
                    try {
                        const res = await fetch(`/questions/${this.questionId}/chat`);
                        if (res.ok) {
                            const history = await res.json();
                            const last = history[history.length - 1];
                            if (last && last.role === 'assistant') {
                                this.chatMessages = history;
                                this.chatTyping = false;
                                clearInterval(poller);
                                this.$nextTick(() => {
                                    const el = this.$refs.chatHistory;
                                    if (el) el.scrollTop = el.scrollHeight;
                                });
                            }
                        }
                    } catch (e) { console.error('Poll error', e); }
                    if (attempts >= 30) {
                        clearInterval(poller);
                        this.chatTyping = false;
                    }
                }, 2000);
            }
        };
    }

    function statsCharts() {
        return {
            async loadStats() {
                try {
                    const res = await fetch('/questions/stats');
                    const data = await res.json();
                    this.renderSubjectChart(data.bySubject);
                    this.renderTemporalChart(data.temporal);
                    this.renderDifficultyChart(data.byDifficulty);
                } catch (e) { console.error('Stats load error', e); }
            },

            renderSubjectChart(data) {
                const ctx = document.getElementById('chartSubject');
                if (!ctx || !data.length) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.subject),
                        datasets: [
                            { label: 'Acertos', data: data.map(d => d.correct), backgroundColor: '#10b981', borderRadius: 6 },
                            { label: 'Total', data: data.map(d => d.total), backgroundColor: '#e2e8f0', borderRadius: 6 },
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });
            },

            renderTemporalChart(data) {
                const ctx = document.getElementById('chartTemporal');
                if (!ctx || !data.length) return;
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.date),
                        datasets: [
                            { label: 'Resolvidas', data: data.map(d => d.total), borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3, pointRadius: 4 },
                            { label: 'Acertos', data: data.map(d => d.correct), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3, pointRadius: 4 },
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });
            },

            renderDifficultyChart(data) {
                const ctx = document.getElementById('chartDifficulty');
                if (!ctx || !data.length) return;
                const labels = { easy: 'Fácil', medium: 'Média', hard: 'Difícil' };
                const colors = { easy: '#10b981', medium: '#f59e0b', hard: '#ef4444' };
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(d => labels[d.difficulty] || d.difficulty),
                        datasets: [{
                            label: 'Acerto %',
                            data: data.map(d => d.accuracy),
                            backgroundColor: data.map(d => colors[d.difficulty] || '#94a3b8'),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const item = data[ctx.dataIndex];
                                        return `${ctx.label}: ${item.accuracy}% (${item.correct}/${item.total})`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        };
    }
</script>
@endsection
