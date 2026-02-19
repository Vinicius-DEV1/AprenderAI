{{--
|--------------------------------------------------------------------------
| Banco de Questões — Página Principal (questions/index.blade.php)
|--------------------------------------------------------------------------
|
| VISÃO GERAL:
| Esta view implementa a interface completa do banco de questões estilo
| Qconcursos. Permite que o aluno filtre, resolva questões, veja feedback
| com explicação IA, tire dúvidas via chat, e consulte seu histórico.
|
| COMPONENTES ALPINE.JS:
| - statsSlideOver()  → Painel lateral de desempenho (Chart.js)
| - filterPanel()     → Filtros adaptativos com tipo como mestre
| - questionCard()    → Card interativo com resolução, chat e histórico
|
| DEPENDÊNCIAS:
| - Alpine.js (já carregado no layout)
| - Chart.js (CDN, carregado sob demanda no slide-over)
| - Marked.js (CDN, para renderizar Markdown das respostas IA)
--}}

@extends('layouts.app')
@section('page-title', 'Banco de Questões')

@section('content')
{{-- ===== ESTILOS DO MÓDULO ===== --}}
<style>
    /* ── Header ── */
    .qb-header { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); border-radius: 16px; padding: 28px 32px; color: white; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .qb-header-left h1 { font-size: 26px; font-weight: 700; margin-bottom: 2px; }
    .qb-header-left p { font-size: 13px; opacity: 0.85; }
    .qb-header-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .qb-stat { background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border-radius: 10px; padding: 10px 18px; text-align: center; min-width: 90px; }
    .qb-stat .val { font-size: 22px; font-weight: 700; }
    .qb-stat .lbl { font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }
    .qb-btn-desempenho { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: white; border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .qb-btn-desempenho:hover { background: rgba(255,255,255,0.3); }

    /* ── Filters ── */
    .qb-filters { background: white; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .qb-filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .qb-filter-row + .qb-filter-row { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0; }
    .qb-filter-item { flex: 1; min-width: 140px; }
    .qb-filter-item label { display: block; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; }
    .qb-filter-item select, .qb-filter-item input { width: 100%; padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; background: #f8fafc; transition: border-color 0.2s, box-shadow 0.2s; }
    .qb-filter-item select:focus, .qb-filter-item input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
    /* Tipo é o filtro mestre — destaque visual */
    .qb-filter-master select { border-color: #6366f1; border-width: 2px; font-weight: 600; }
    .qb-filter-actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; }
    .qb-btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
    .qb-btn-primary { background: #6366f1; color: white; }
    .qb-btn-primary:hover { background: #4f46e5; transform: translateY(-1px); }
    .qb-btn-ghost { background: transparent; color: #64748b; border: 1.5px solid #e2e8f0; }
    .qb-btn-ghost:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .qb-filter-toggle { font-size: 12px; color: #6366f1; font-weight: 600; cursor: pointer; background: none; border: none; padding: 0; display: flex; align-items: center; gap: 4px; }
    .qb-filter-toggle:hover { text-decoration: underline; }
    .qb-result-count { font-size: 12px; color: #94a3b8; margin-left: auto; }

    /* ── Slide-over (Stats) ── */
    .qb-slideover-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 100; backdrop-filter: blur(2px); }
    .qb-slideover { position: fixed; top: 0; right: 0; height: 100vh; width: min(520px, 95vw); background: white; z-index: 101; box-shadow: -8px 0 32px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; }
    .qb-slideover-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: white; }
    .qb-slideover-header h2 { font-size: 18px; font-weight: 700; }
    .qb-slideover-close { background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
    .qb-slideover-body { flex: 1; overflow-y: auto; padding: 20px 24px; }
    .qb-overview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
    .qb-overview-card { background: #f8fafc; border-radius: 10px; padding: 14px 16px; border: 1px solid #e2e8f0; text-align: center; }
    .qb-overview-card .val { font-size: 28px; font-weight: 700; color: #6366f1; }
    .qb-overview-card .lbl { font-size: 11px; color: #64748b; margin-top: 2px; }
    .qb-chart-section { margin-bottom: 20px; }
    .qb-chart-section h3 { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 10px; }

    /* ── Question Cards ── */
    .qb-card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 14px; border: 1px solid #e2e8f0; transition: box-shadow 0.2s; }
    .qb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .qb-card-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .qb-card-id { font-size: 12px; font-weight: 700; color: #6366f1; }
    .qb-badge { padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; letter-spacing: 0.3px; }
    .qb-badge-easy { background: #d1fae5; color: #065f46; }
    .qb-badge-medium { background: #fef3c7; color: #92400e; }
    .qb-badge-hard { background: #fee2e2; color: #991b1b; }
    .qb-badge-origin { background: #e2e8f0; color: #475569; }
    .qb-badge-ai { background: #e9d5ff; color: #6b21a8; }
    .qb-badge-correct { background: #d1fae5; color: #065f46; }
    .qb-badge-incorrect { background: #fee2e2; color: #991b1b; }
    .qb-statement { font-size: 15px; line-height: 1.7; color: #1e293b; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; }
    .qb-alt { display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; border-radius: 10px; border: 2px solid #f1f5f9; margin-bottom: 8px; cursor: pointer; transition: all 0.15s ease; }
    .qb-alt:hover { border-color: #c7d2fe; background: #f5f3ff; }
    .qb-alt.selected { border-color: #6366f1; background: #eef2ff; }
    .qb-alt.correct-reveal { border-color: #10b981; background: #f0fdf4; }
    .qb-alt.incorrect-reveal { border-color: #ef4444; background: #fef2f2; }
    .qb-alt.disabled { pointer-events: none; }
    .qb-alt-letter { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; background: #f1f5f9; color: #64748b; transition: all 0.15s; }
    .qb-alt.selected .qb-alt-letter { background: #6366f1; color: white; }
    .qb-alt.correct-reveal .qb-alt-letter { background: #10b981; color: white; }
    .qb-alt.incorrect-reveal .qb-alt-letter { background: #ef4444; color: white; }
    .qb-alt-text { font-size: 14px; color: #334155; line-height: 1.5; }
    .qb-card-actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
    .qb-action-btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
    .qb-action-btn:hover { background: #f1f5f9; transform: translateY(-1px); }
    .qb-action-btn.primary { background: #6366f1; color: white; border-color: #6366f1; }
    .qb-action-btn.primary:hover { background: #4f46e5; }
    .qb-action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .qb-feedback { margin-top: 14px; padding: 14px; border-radius: 10px; animation: fadeSlideIn 0.3s ease; }
    .qb-feedback.correct { background: #f0fdf4; border: 1px solid #bbf7d0; }
    .qb-feedback.incorrect { background: #fef2f2; border: 1px solid #fecaca; }
    .qb-feedback-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
    .qb-feedback.correct .qb-feedback-title { color: #065f46; }
    .qb-feedback.incorrect .qb-feedback-title { color: #991b1b; }
    .qb-explanation { background: #f8fafc; border-radius: 8px; padding: 14px; margin-top: 10px; border: 1px solid #e2e8f0; }
    .qb-explanation h4 { font-size: 12px; font-weight: 700; color: #6366f1; margin-bottom: 6px; }
    .qb-explanation-text { font-size: 13px; line-height: 1.7; color: #475569; }
    .qb-difficulty-box { background: #fefce8; border-radius: 8px; padding: 10px 14px; margin-top: 8px; border: 1px solid #fde68a; }
    .qb-difficulty-box h5 { font-size: 11px; font-weight: 700; color: #92400e; margin-bottom: 3px; }
    .qb-difficulty-box p { font-size: 12px; line-height: 1.5; color: #78350f; }
    /* ── Histórico popover ── */
    .qb-history-popover { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); margin-top: 8px; animation: fadeSlideIn 0.2s ease; }
    .qb-history-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
    .qb-history-row:last-child { border-bottom: none; }
    /* ── Chat ── */
    .qb-chat-container { margin-top: 10px; background: #f8fafc; border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; }
    .qb-chat-history { max-height: 200px; overflow-y: auto; margin-bottom: 8px; }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Dark Mode ── */
    :root.dark .qb-filters { background: #1e293b; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-filter-item select, :root.dark .qb-filter-item input { background: #0f172a; border-color: rgba(255,255,255,0.1); color: #e2e8f0; }
    :root.dark .qb-filter-row + .qb-filter-row { border-top-color: rgba(255,255,255,0.08); }
    :root.dark .qb-card { background: #1e293b; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-statement { color: #e2e8f0; border-bottom-color: rgba(255,255,255,0.08); }
    :root.dark .qb-alt { border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-alt:hover { border-color: #6366f1; background: rgba(99,102,241,0.1); }
    :root.dark .qb-alt-text { color: #cbd5e1; }
    :root.dark .qb-alt-letter { background: #334155; color: #94a3b8; }
    :root.dark .qb-explanation { background: #0f172a; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-explanation-text { color: #94a3b8; }
    :root.dark .qb-action-btn { background: #334155; border-color: rgba(255,255,255,0.08); color: #cbd5e1; }
    :root.dark .qb-slideover { background: #1e293b; }
    :root.dark .qb-overview-card { background: #0f172a; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-overview-card .lbl { color: #94a3b8; }
    :root.dark .qb-chart-section h3 { color: #e2e8f0; }
    :root.dark .qb-slideover-body { background: #1e293b; }
    :root.dark .qb-chat-container { background: #0f172a; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-history-popover { background: #1e293b; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-history-row { border-bottom-color: rgba(255,255,255,0.08); color: #cbd5e1; }
    :root.dark .qb-difficulty-box { background: rgba(254,243,199,0.08); border-color: rgba(253,230,138,0.2); }
    :root.dark .qb-difficulty-box h5 { color: #fcd34d; }
    :root.dark .qb-difficulty-box p { color: #fde68a; }
    [x-cloak] { display: none !important; }
</style>

{{-- ===== SLIDE-OVER: Painel lateral de desempenho ===== --}}
<div x-data="statsSlideOver()" x-cloak>
    <div x-show="open" x-transition.opacity class="qb-slideover-backdrop" @click="close()" style="display:none"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
         class="qb-slideover" style="display:none">
        <div class="qb-slideover-header">
            <h2>📊 Meu Desempenho</h2>
            <button class="qb-slideover-close" @click="close()">✕</button>
        </div>
        <div class="qb-slideover-body">
            <div class="qb-overview-grid">
                <div class="qb-overview-card"><div class="val">{{ $overview['total'] }}</div><div class="lbl">Respondidas</div></div>
                <div class="qb-overview-card"><div class="val" style="color:#10b981">{{ $overview['accuracy'] }}%</div><div class="lbl">Taxa de Acerto</div></div>
                <div class="qb-overview-card"><div class="val" style="color:#10b981">{{ $overview['correct'] }}</div><div class="lbl">Acertos</div></div>
                <div class="qb-overview-card"><div class="val" style="color:#ef4444">{{ $overview['incorrect'] }}</div><div class="lbl">Erros</div></div>
            </div>
            <div class="qb-chart-section"><h3>Acerto por Matéria</h3><canvas id="chartSubject" height="180"></canvas></div>
            <div class="qb-chart-section"><h3>Evolução (30 dias)</h3><canvas id="chartTemporal" height="160"></canvas></div>
            <div class="qb-chart-section"><h3>Heatmap de Dificuldade</h3><canvas id="chartDifficulty" height="160"></canvas></div>
        </div>
    </div>
</div>

{{-- ===== HEADER ===== --}}
<div class="qb-header">
    <div class="qb-header-left">
        <h1>📋 Banco de Questões</h1>
        <p>Resolva questões, veja explicações e tire dúvidas com IA</p>
    </div>
    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:12px">
        <button class="qb-btn-desempenho" @click="$dispatch('open-stats')">📊 Ver Meu Desempenho</button>
        <div class="qb-header-stats">
            <div class="qb-stat"><div class="val">{{ $overview['total'] }}</div><div class="lbl">Respondidas</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['accuracy'] }}%</div><div class="lbl">Acerto</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['correct'] }}</div><div class="lbl">✓ Acertos</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['incorrect'] }}</div><div class="lbl">✗ Erros</div></div>
        </div>
    </div>
</div>

{{-- ===== FILTROS ADAPTATIVOS ===== --}}
<div class="qb-filters" x-data="filterPanel({{ json_encode(request()->all()) }})">
    <form method="GET" action="{{ route('questions.index') }}" @submit.prevent="submitForm($el)">
        <div class="qb-filter-row">
            <div class="qb-filter-item qb-filter-master" style="max-width:130px">
                <label>⭐ Tipo</label>
                <select name="type" x-model="filters.type" @change="onTypeChange()">
                    <option value="">Todos</option>
                    <option value="enem">ENEM</option>
                    <option value="concurso">Concurso</option>
                </select>
            </div>
            <div class="qb-filter-item" style="max-width:200px">
                <label>Matéria</label>
                <select name="subject" x-model="filters.subject">
                    <option value="">Todas</option>
                    @foreach($filterOptions['subjects'] as $s)
                        <option value="{{ $s }}" {{ request('subject') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qb-filter-item">
                <label>Assunto</label>
                <select name="topic" x-model="filters.topic">
                    <option value="">Ex: Trigonometria, Funções...</option>
                    @foreach($filterOptions['topics'] as $t)
                        <option value="{{ $t }}" {{ request('topic') == $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qb-filter-item" style="min-width:200px">
                <label>Busca</label>
                <input type="text" name="keyword" x-model="filters.keyword" placeholder="Palavras-chave no enunciado...">
            </div>
            <div style="display:flex; align-items:flex-end; padding-bottom:2px">
                <button type="button" class="qb-filter-toggle" @click="moreFilters = !moreFilters">
                    <svg class="w-4 h-4" :class="moreFilters ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;transition:transform 0.2s"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <span x-text="moreFilters ? 'Menos filtros' : 'Mais filtros'"></span>
                </button>
            </div>
        </div>

        <div x-show="moreFilters" x-transition class="qb-filter-row">
            <div class="qb-filter-item" style="max-width:110px">
                <label>Ano</label>
                <select name="year" x-model="filters.year">
                    <option value="">Todos</option>
                    @foreach($filterOptions['years'] as $y)
                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qb-filter-item" style="max-width:130px">
                <label>Dificuldade</label>
                <select name="difficulty" x-model="filters.difficulty">
                    <option value="">Todas</option>
                    <option value="easy" {{ request('difficulty')=='easy'?'selected':'' }}>Fácil</option>
                    <option value="medium" {{ request('difficulty')=='medium'?'selected':'' }}>Média</option>
                    <option value="hard" {{ request('difficulty')=='hard'?'selected':'' }}>Difícil</option>
                </select>
            </div>
            <div class="qb-filter-item" style="max-width:160px">
                <label>Status</label>
                <select name="status" x-model="filters.status">
                    <option value="">Todos</option>
                    <option value="unanswered" {{ request('status')=='unanswered'?'selected':'' }}>Não respondidas</option>
                    <option value="answered" {{ request('status')=='answered'?'selected':'' }}>Respondidas</option>
                    <option value="correct" {{ request('status')=='correct'?'selected':'' }}>Acertei</option>
                    <option value="incorrect" {{ request('status')=='incorrect'?'selected':'' }}>Errei</option>
                </select>
            </div>
            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Banca</label>
                <select name="organization" x-model="filters.organization">
                    <option value="">Todas</option>
                    @foreach($filterOptions['organizations'] as $o)
                        <option value="{{ $o }}" {{ request('organization')==$o?'selected':'' }}>{{ $o }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Órgão</label>
                <select name="institution" x-model="filters.institution">
                    <option value="">Todos</option>
                    @foreach($filterOptions['institutions'] as $i)
                        <option value="{{ $i }}" {{ request('institution')==$i?'selected':'' }}>{{ $i }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Cargo</label>
                <select name="role" x-model="filters.role">
                    <option value="">Todos</option>
                    @foreach($filterOptions['roles'] as $r)
                        <option value="{{ $r }}" {{ request('role')==$r?'selected':'' }}>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="qb-filter-actions">
            <button type="submit" class="qb-btn qb-btn-primary">🔍 Filtrar</button>
            <button type="button" class="qb-btn qb-btn-ghost" @click="clearFilters()">✕ Limpar</button>
            <span class="qb-result-count">{{ $questions->total() }} questões encontradas</span>
        </div>
    </form>
</div>

{{-- ===== FEED DE QUESTÕES ===== --}}
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
            <template x-if="alreadyAnswered">
                <span class="qb-badge" :class="wasCorrect ? 'qb-badge-correct' : 'qb-badge-incorrect'" x-text="wasCorrect ? '✓ Acertou' : '✗ Errou'"></span>
            </template>
        </div>

        <div class="qb-statement">{!! nl2br(e($question->statement)) !!}</div>

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
            </div>
        @endforeach

        <div class="qb-card-actions">
            <button class="qb-action-btn primary" x-show="!answered" @click="submitAnswer()" :disabled="!selectedAnswer || submitting">
                <span x-show="!submitting">📝 Responder</span>
                <span x-show="submitting">⏳ Enviando...</span>
            </button>
            <button class="qb-action-btn" x-show="answered" @click="toggleChat()">
                <span x-text="showChat ? '▲ Ocultar Chat' : '💬 Tirar Dúvida'"></span>
            </button>
            <button class="qb-action-btn" x-show="answered" @click="toggleHistory()">📜 Meu Histórico</button>
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
    <div class="qb-card" style="text-align:center; padding:48px">
        <p style="font-size:18px; color:#94a3b8">🔍 Nenhuma questão encontrada</p>
    </div>
@endforelse

<div style="display:flex; justify-content:center; margin-top:20px">{{ $questions->links() }}</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
marked.setOptions({ breaks: true, gfm: true });

function statsSlideOver() {
    return {
        open: false, chartsLoaded: false,
        init() { window.addEventListener('open-stats', () => { this.open = true; if (!this.chartsLoaded) this.$nextTick(() => this.loadStats()); }); },
        close() { this.open = false; },
        async loadStats() {
            try {
                const res = await fetch('/questions/stats');
                const data = await res.json();
                this.renderSubjectChart(data.bySubject);
                this.renderTemporalChart(data.temporal);
                this.renderDifficultyChart(data.byDifficulty);
                this.chartsLoaded = true;
            } catch (e) { console.error('Stats error', e); }
        },
        renderSubjectChart(data) {
            const ctx = document.getElementById('chartSubject'); if (!ctx || !data.length) return;
            new Chart(ctx, { type: 'bar', data: { labels: data.map(d=>d.subject), datasets: [ { label: 'Acertos', data: data.map(d=>d.correct), backgroundColor: '#10b981', borderRadius: 5 }, { label: 'Total', data: data.map(d=>d.total), backgroundColor: '#e2e8f0', borderRadius: 5 } ] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } } });
        },
        renderTemporalChart(data) {
            const ctx = document.getElementById('chartTemporal'); if (!ctx || !data.length) return;
            new Chart(ctx, { type: 'line', data: { labels: data.map(d=>d.date), datasets: [ { label: 'Resolvidas', data: data.map(d=>d.total), borderColor: '#6366f1', fill: true, tension: 0.3, pointRadius: 3 }, { label: 'Acertos', data: data.map(d=>d.correct), borderColor: '#10b981', fill: true, tension: 0.3, pointRadius: 3 } ] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } } });
        },
        renderDifficultyChart(data) {
            const ctx = document.getElementById('chartDifficulty'); if (!ctx || !data.length) return;
            const labels = { easy:'Fácil', medium:'Média', hard:'Difícil' };
            const colors = { easy:'#10b981', medium:'#f59e0b', hard:'#ef4444' };
            new Chart(ctx, { type: 'doughnut', data: { labels: data.map(d=>labels[d.difficulty]||d.difficulty), datasets: [{ data: data.map(d=>d.accuracy), backgroundColor: data.map(d=>colors[d.difficulty]||'#94a3b8') }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.label}: ${data[c.dataIndex].accuracy}%` } } } } });
        }
    };
}

function filterPanel(currentFilters) {
    return {
        filters: {
            type: currentFilters.type || '', subject: currentFilters.subject || '', topic: currentFilters.topic || '',
            keyword: currentFilters.keyword || '', year: currentFilters.year || '', difficulty: currentFilters.difficulty || '',
            status: currentFilters.status || '', organization: currentFilters.organization || '', institution: currentFilters.institution || '', role: currentFilters.role || ''
        },
        moreFilters: !!(currentFilters.year || currentFilters.difficulty || currentFilters.status || currentFilters.organization || currentFilters.institution || currentFilters.role),
        onTypeChange() { if (this.filters.type === 'enem') { this.filters.organization = ''; this.filters.institution = ''; this.filters.role = ''; } },
        clearFilters() { Object.keys(this.filters).forEach(k => this.filters[k] = ''); window.location.href = '{{ route("questions.index") }}'; },
        submitForm(form) { const url = new URL(form.action); Object.entries(this.filters).forEach(([k, v]) => { if (v) url.searchParams.set(k, v); }); window.location.href = url.toString(); }
    };
}

function questionCard(questionId, alreadyAnswered, wasCorrect) {
    return {
        questionId, alreadyAnswered, wasCorrect, selectedAnswer: null, answered: alreadyAnswered, isCorrect: wasCorrect,
        correctAnswer: null, explanation: null, difficultyReasoning: null, submitting: false,
        showChat: false, chatMessages: [], chatInput: '', chatTyping: false, chatLoaded: false,
        showHistory: false, historyData: [], historyLoading: false, historyLoaded: false,
        selectAnswer(letter) { this.selectedAnswer = letter; },
        async submitAnswer() {
            if (!this.selectedAnswer || this.answered || this.submitting) return;
            this.submitting = true;
            try {
                const res = await fetch(`/questions/${this.questionId}/answer`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ selected_answer: this.selectedAnswer }) });
                const data = await res.json();
                this.answered = true; this.isCorrect = data.correct; this.correctAnswer = data.correct_answer;
                this.explanation = data.explanation || ''; this.difficultyReasoning = data.difficulty_reasoning || '';
                this.alreadyAnswered = true; this.wasCorrect = data.correct;
            } catch (e) { alert('Erro ao enviar resposta.'); } finally { this.submitting = false; }
        },
        renderMd(text) { if (!text) return ''; try { return marked.parse(text); } catch (e) { return text; } },
        async toggleChat() { 
            this.showChat = !this.showChat; 
            if (this.showChat) {
                if (!this.chatLoaded) await this.loadChatHistory();
                this.$nextTick(() => {
                    if (this.$refs.chatInput) this.$refs.chatInput.focus();
                    this.scrollToBottom();
                });
            }
        },
        async loadChatHistory() {
            try { 
                const res = await fetch(`/questions/${this.questionId}/chat`); 
                if (res.ok) { 
                    const history = await res.json();
                    if (history.length === 0) {
                        // Injecting welcome message ONLY visuals
                        this.chatMessages = [{
                            role: 'assistant',
                            message: 'Olá! Eu sou o Xavier. Qual sua dúvida sobre essa questão?',
                            created_at: new Date().toISOString()
                        }];
                    } else {
                        this.chatMessages = history;
                    }
                    this.chatLoaded = true;
                    this.$nextTick(() => this.scrollToBottom());
                } 
            } catch (e) { console.error('Chat load error', e); }
        },
        async sendChat() {
            if (!this.chatInput.trim()) return; if (!this.chatLoaded) await this.loadChatHistory();
            const msg = this.chatInput; this.chatMessages.push({ role: 'user', message: msg, id: Date.now() });
            this.chatInput = ''; this.chatTyping = true;
            this.$nextTick(() => this.scrollToBottom());
            try {
                const res = await fetch(`/questions/${this.questionId}/chat`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ message: msg }) });
                const data = await res.json(); if (data.status === 'quota_exceeded') { this.chatMessages.push({ role: 'system', message: data.message, upgrade_url: data.upgrade_url, id: Date.now() }); this.chatTyping = false; this.$nextTick(() => this.scrollToBottom()); return; }
                this.pollChat();
            } catch (e) { this.chatTyping = false; this.chatMessages.push({ role: 'assistant', message: 'Erro ao processar.', id: Date.now() }); this.$nextTick(() => this.scrollToBottom()); }
        },
        pollChat() {
            let attempts = 0; const poller = setInterval(async () => {
                attempts++;
                try {
                    const res = await fetch(`/questions/${this.questionId}/chat`);
                    if (res.ok) {
                        const history = await res.json(); const last = history[history.length - 1];
                        if (last && last.role === 'assistant') { this.chatMessages = history; this.chatTyping = false; this.$nextTick(() => this.scrollToBottom()); clearInterval(poller); }
                    }
                } catch (e) {}
                if (attempts >= 30) { clearInterval(poller); this.chatTyping = false; }
            }, 2000);
        },
        async toggleHistory() {
            this.showHistory = !this.showHistory;
            if (this.showHistory && !this.historyLoaded) {
                this.historyLoading = true;
                try { const res = await fetch(`/questions/${this.questionId}/history`); if (res.ok) this.historyData = await res.json(); this.historyLoaded = true; }
                catch (e) {} finally { this.historyLoading = false; }
            }
        },
        scrollToBottom() {
            const container = this.$refs.chatHistory;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
        formatDate(iso) { if (!iso) return ''; const d = new Date(iso); return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); }
    };
}
</script>
@endsection