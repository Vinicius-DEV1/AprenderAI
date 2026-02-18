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
<style>
    /* ===== BASE ===== */
    .qb-header { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); border-radius: 16px; padding: 28px 32px; color: white; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .qb-header-left h1 { font-size: 26px; font-weight: 700; margin-bottom: 2px; }
    .qb-header-left p  { font-size: 13px; opacity: 0.85; }
    .qb-header-stats   { display: flex; gap: 10px; flex-wrap: wrap; }
    .qb-stat { background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border-radius: 10px; padding: 10px 18px; text-align: center; min-width: 90px; }
    .qb-stat .val { font-size: 22px; font-weight: 700; }
    .qb-stat .lbl { font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }
    .qb-btn-desempenho { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: white; border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .qb-btn-desempenho:hover { background: rgba(255,255,255,0.3); }

    /* ===== FILTERS ===== */
    .qb-filters { background: white; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .qb-filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .qb-filter-row + .qb-filter-row { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0; }
    .qb-filter-item { flex: 1; min-width: 140px; }
    .qb-filter-item label { display: block; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; }
    .qb-filter-item select,
    .qb-filter-item input  { width: 100%; padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; background: #f8fafc; transition: border-color 0.2s, box-shadow 0.2s; }
    .qb-filter-item select:focus,
    .qb-filter-item input:focus  { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
    .qb-filter-actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; }
    .qb-btn { padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
    .qb-btn-primary { background: #6366f1; color: white; }
    .qb-btn-primary:hover { background: #4f46e5; transform: translateY(-1px); }
    .qb-btn-ghost { background: transparent; color: #64748b; border: 1.5px solid #e2e8f0; }
    .qb-btn-ghost:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .qb-filter-toggle { font-size: 12px; color: #6366f1; font-weight: 600; cursor: pointer; background: none; border: none; padding: 0; display: flex; align-items: center; gap: 4px; }
    .qb-filter-toggle:hover { text-decoration: underline; }
    .qb-result-count { font-size: 12px; color: #94a3b8; margin-left: auto; }

    /* ===== SLIDE-OVER (Stats) ===== */
    .qb-slideover-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 100; backdrop-filter: blur(2px); }
    .qb-slideover { position: fixed; top: 0; right: 0; height: 100vh; width: min(520px, 95vw); background: white; z-index: 101; box-shadow: -8px 0 32px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; }
    .qb-slideover-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: white; }
    .qb-slideover-header h2 { font-size: 18px; font-weight: 700; }
    .qb-slideover-close { background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .qb-slideover-close:hover { background: rgba(255,255,255,0.35); }
    .qb-slideover-body { flex: 1; overflow-y: auto; padding: 20px 24px; }
    .qb-overview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
    .qb-overview-card { background: #f8fafc; border-radius: 10px; padding: 14px 16px; border: 1px solid #e2e8f0; text-align: center; }
    .qb-overview-card .val { font-size: 28px; font-weight: 700; color: #6366f1; }
    .qb-overview-card .lbl { font-size: 11px; color: #64748b; margin-top: 2px; }
    .qb-chart-section { margin-bottom: 20px; }
    .qb-chart-section h3 { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 10px; }

    /* ===== QUESTION CARDS ===== */
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
    .qb-chat-container { margin-top: 10px; background: #f8fafc; border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; }
    .qb-chat-history { max-height: 200px; overflow-y: auto; margin-bottom: 8px; }

    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* ===== DARK MODE ===== */
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
    :root.dark .qb-slideover-header { border-bottom-color: rgba(255,255,255,0.08); }
    :root.dark .qb-chat-container { background: #0f172a; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-difficulty-box { background: rgba(254,243,199,0.08); border-color: rgba(253,230,138,0.2); }
    :root.dark .qb-difficulty-box h5 { color: #fcd34d; }
    :root.dark .qb-difficulty-box p { color: #fde68a; }
    [x-cloak] { display: none !important; }
</style>

{{-- ===== SLIDE-OVER: Desempenho ===== --}}
<div x-data="statsSlideOver()" x-cloak>
    {{-- Backdrop --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="qb-slideover-backdrop" @click="close()" style="display:none"></div>

    {{-- Panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="qb-slideover" style="display:none">
        <div class="qb-slideover-header">
            <h2>📊 Meu Desempenho</h2>
            <button class="qb-slideover-close" @click="close()">✕</button>
        </div>
        <div class="qb-slideover-body">
            {{-- Overview --}}
            <div class="qb-overview-grid">
                <div class="qb-overview-card">
                    <div class="val">{{ $overview['total'] }}</div>
                    <div class="lbl">Questões Respondidas</div>
                </div>
                <div class="qb-overview-card">
                    <div class="val" style="color:#10b981">{{ $overview['accuracy'] }}%</div>
                    <div class="lbl">Taxa de Acerto</div>
                </div>
                <div class="qb-overview-card">
                    <div class="val" style="color:#10b981">{{ $overview['correct'] }}</div>
                    <div class="lbl">Acertos</div>
                </div>
                <div class="qb-overview-card">
                    <div class="val" style="color:#ef4444">{{ $overview['incorrect'] }}</div>
                    <div class="lbl">Erros</div>
                </div>
            </div>

            {{-- Charts --}}
            <div class="qb-chart-section">
                <h3>Acerto por Matéria</h3>
                <canvas id="chartSubject" height="180"></canvas>
            </div>
            <div class="qb-chart-section">
                <h3>Evolução (últimos 30 dias)</h3>
                <canvas id="chartTemporal" height="160"></canvas>
            </div>
            <div class="qb-chart-section">
                <h3>Heatmap de Dificuldade</h3>
                <canvas id="chartDifficulty" height="160"></canvas>
            </div>
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
        <button class="qb-btn-desempenho" @click="$dispatch('open-stats')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Ver Meu Desempenho
        </button>
        <div class="qb-header-stats">
            <div class="qb-stat"><div class="val">{{ $overview['total'] }}</div><div class="lbl">Respondidas</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['accuracy'] }}%</div><div class="lbl">Acerto</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['correct'] }}</div><div class="lbl">✓ Acertos</div></div>
            <div class="qb-stat"><div class="val">{{ $overview['incorrect'] }}</div><div class="lbl">✗ Erros</div></div>
        </div>
    </div>
</div>

{{-- ===== FILTERS ===== --}}
<div class="qb-filters"
     x-data="filterPanel(
        {{ json_encode(request()->all()) }},
        {{ json_encode($filterOptions['subjectsByType']) }}
     )">

    {{-- Row 1: Primary filters (always visible) --}}
    <form method="GET" action="{{ route('questions.index') }}" @submit.prevent="submitForm($el)">
        <div class="qb-filter-row">
            {{-- Tipo (master filter) --}}
            <div class="qb-filter-item" style="max-width:130px">
                <label>Tipo</label>
                <select name="type" x-model="filters.type" @change="onTypeChange()">
                    <option value="">Todos</option>
                    <option value="enem">ENEM</option>
                    <option value="concurso">Concurso</option>
                </select>
            </div>

            {{-- Matéria (filtered by type) --}}
            <div class="qb-filter-item" style="max-width:200px">
                <label>Matéria</label>
                <select name="subject" x-model="filters.subject">
                    <option value="">Todas</option>
                    <template x-for="s in availableSubjects" :key="s">
                        <option :value="s" x-text="s" :selected="filters.subject === s"></option>
                    </template>
                </select>
            </div>

            {{-- Assunto --}}
            <div class="qb-filter-item">
                <label>Assunto</label>
                <select name="topic" x-model="filters.topic">
                    <option value="">Todos</option>
                    @foreach($filterOptions['topics'] as $t)
                        <option value="{{ $t }}" {{ request('topic') == $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Keyword --}}
            <div class="qb-filter-item" style="min-width:200px">
                <label>Busca no enunciado</label>
                <input type="text" name="keyword" x-model="filters.keyword" placeholder="Digite palavras-chave...">
            </div>

            {{-- Toggle Mais Filtros --}}
            <div style="display:flex; align-items:flex-end; padding-bottom:2px">
                <button type="button" class="qb-filter-toggle" @click="moreFilters = !moreFilters">
                    <svg class="w-4 h-4 transition-transform" :class="moreFilters ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span x-text="moreFilters ? 'Menos filtros' : 'Mais filtros'"></span>
                </button>
            </div>
        </div>

        {{-- Row 2: Secondary filters (collapsible) --}}
        <div x-show="moreFilters" x-transition class="qb-filter-row">
            {{-- Ano --}}
            <div class="qb-filter-item" style="max-width:110px">
                <label>Ano</label>
                <select name="year" x-model="filters.year">
                    <option value="">Todos</option>
                    @foreach($filterOptions['years'] as $y)
                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Dificuldade --}}
            <div class="qb-filter-item" style="max-width:130px">
                <label>Dificuldade</label>
                <select name="difficulty" x-model="filters.difficulty">
                    <option value="">Todas</option>
                    <option value="easy" {{ request('difficulty') == 'easy' ? 'selected' : '' }}>Fácil</option>
                    <option value="medium" {{ request('difficulty') == 'medium' ? 'selected' : '' }}>Média</option>
                    <option value="hard" {{ request('difficulty') == 'hard' ? 'selected' : '' }}>Difícil</option>
                </select>
            </div>

            {{-- Status --}}
            <div class="qb-filter-item" style="max-width:160px">
                <label>Status</label>
                <select name="status" x-model="filters.status">
                    <option value="">Todos</option>
                    <option value="unanswered" {{ request('status') == 'unanswered' ? 'selected' : '' }}>Não respondidas</option>
                    <option value="answered" {{ request('status') == 'answered' ? 'selected' : '' }}>Respondidas</option>
                    <option value="correct" {{ request('status') == 'correct' ? 'selected' : '' }}>Acertei</option>
                    <option value="incorrect" {{ request('status') == 'incorrect' ? 'selected' : '' }}>Errei</option>
                </select>
            </div>

            {{-- Concurso-only filters (hidden when type=enem) --}}
            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Banca</label>
                <select name="organization" x-model="filters.organization">
                    <option value="">Todas</option>
                    @foreach($filterOptions['organizations'] as $o)
                        <option value="{{ $o }}" {{ request('organization') == $o ? 'selected' : '' }}>{{ $o }}</option>
                    @endforeach
                </select>
            </div>

            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Órgão</label>
                <select name="institution" x-model="filters.institution">
                    <option value="">Todos</option>
                    @foreach($filterOptions['institutions'] as $i)
                        <option value="{{ $i }}" {{ request('institution') == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endforeach
                </select>
            </div>

            <div class="qb-filter-item" x-show="filters.type !== 'enem'" x-transition>
                <label>Cargo</label>
                <select name="role" x-model="filters.role">
                    <option value="">Todos</option>
                    @foreach($filterOptions['roles'] as $r)
                        <option value="{{ $r }}" {{ request('role') == $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Actions --}}
        <div class="qb-filter-actions">
            <button type="submit" class="qb-btn qb-btn-primary">🔍 Filtrar</button>
            <button type="button" class="qb-btn qb-btn-ghost" @click="clearFilters()">✕ Limpar</button>
            <span class="qb-result-count">{{ $questions->total() }} questões encontradas</span>
        </div>
    </form>
</div>

{{-- ===== QUESTIONS FEED ===== --}}
@forelse($questions as $question)
    <div class="qb-card" x-data="questionCard({{ $question->id }}, {{ json_encode(isset($answeredMap[$question->id])) }}, {{ json_encode($answeredMap[$question->id] ?? null) }})">
        {{-- Meta --}}
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
            @if($question->organization)
                <span class="qb-badge qb-badge-origin">{{ $question->organization }}</span>
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
            <template x-if="alreadyAnswered">
                <span class="qb-badge" :class="wasCorrect ? 'qb-badge-correct' : 'qb-badge-incorrect'" x-text="wasCorrect ? '✓ Acertou' : '✗ Errou'"></span>
            </template>
        </div>

        {{-- Statement --}}
        <div class="qb-statement">{!! nl2br(e($question->statement)) !!}</div>

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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
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
            <button class="qb-action-btn" @click="toggleChat()" x-show="answered">
                <span x-text="showChat ? '▲ Ocultar Chat' : '💬 Tirar Dúvida com IA'"></span>
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

        {{-- Chat --}}
        <div x-show="showChat" x-transition x-cloak class="qb-chat-container">
            <div class="qb-chat-history space-y-2 p-1" x-ref="chatHistory">
                <template x-for="msg in chatMessages" :key="msg.id || msg.created_at">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : (msg.role === 'system' ? 'flex justify-center' : 'flex justify-start')">
                        <template x-if="msg.role === 'user'">
                            <div class="rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" style="background:#4f46e5;color:white">
                                <span x-text="msg.message"></span>
                            </div>
                        </template>
                        <template x-if="msg.role === 'assistant'">
                            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm">
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
                    <div class="bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 border border-gray-200">
                        <span class="font-medium">IA digitando</span>
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
        <p style="font-size:13px; color:#cbd5e1">Tente ajustar seus filtros ou <a href="{{ route('questions.index') }}" style="color:#6366f1">limpar a busca</a>.</p>
    </div>
@endforelse

{{-- Pagination --}}
<div style="display:flex; justify-content:center; margin-top:20px">
    {{ $questions->links() }}
</div>

{{-- ===== SCRIPTS ===== --}}
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
marked.setOptions({ breaks: true, gfm: true });

// ===== STATS SLIDE-OVER =====
function statsSlideOver() {
    return {
        open: false,
        chartsLoaded: false,
        init() {
            window.addEventListener('open-stats', () => {
                this.open = true;
                if (!this.chartsLoaded) {
                    this.$nextTick(() => this.loadStats());
                }
            });
        },
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
            const ctx = document.getElementById('chartSubject');
            if (!ctx || !data.length) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.subject),
                    datasets: [
                        { label: 'Acertos', data: data.map(d => d.correct), backgroundColor: '#10b981', borderRadius: 5 },
                        { label: 'Total', data: data.map(d => d.total), backgroundColor: '#e2e8f0', borderRadius: 5 },
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }, scales: { y: { beginAtZero: true } } }
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
                        { label: 'Resolvidas', data: data.map(d => d.total), borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3, pointRadius: 3 },
                        { label: 'Acertos', data: data.map(d => d.correct), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3, pointRadius: 3 },
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }, scales: { y: { beginAtZero: true } } }
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
                    datasets: [{ data: data.map(d => d.accuracy), backgroundColor: data.map(d => colors[d.difficulty] || '#94a3b8'), borderWidth: 2, borderColor: '#fff' }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } },
                        tooltip: { callbacks: { label: (ctx) => { const item = data[ctx.dataIndex]; return `${ctx.label}: ${item.accuracy}% (${item.correct}/${item.total})`; } } }
                    }
                }
            });
        }
    };
}

// ===== FILTER PANEL =====
function filterPanel(currentFilters, subjectsByType) {
    return {
        filters: {
            type: currentFilters.type || '',
            subject: currentFilters.subject || '',
            topic: currentFilters.topic || '',
            keyword: currentFilters.keyword || '',
            year: currentFilters.year || '',
            difficulty: currentFilters.difficulty || '',
            status: currentFilters.status || '',
            organization: currentFilters.organization || '',
            institution: currentFilters.institution || '',
            role: currentFilters.role || '',
        },
        moreFilters: !!(currentFilters.year || currentFilters.difficulty || currentFilters.status || currentFilters.organization || currentFilters.institution || currentFilters.role),
        allSubjects: @json($filterOptions['subjects']),
        subjectsByType: subjectsByType,

        get availableSubjects() {
            if (this.filters.type === 'enem') return this.subjectsByType.enem || this.allSubjects;
            if (this.filters.type === 'concurso') return this.subjectsByType.concurso || this.allSubjects;
            return this.allSubjects;
        },

        onTypeChange() {
            // Reset subject when type changes (may no longer be valid)
            this.filters.subject = '';
            // Clear concurso-only filters when switching to ENEM
            if (this.filters.type === 'enem') {
                this.filters.organization = '';
                this.filters.institution = '';
                this.filters.role = '';
            }
        },

        clearFilters() {
            Object.keys(this.filters).forEach(k => this.filters[k] = '');
            window.location.href = '{{ route("questions.index") }}';
        },

        submitForm(form) {
            // Remove empty fields before submitting to keep URL clean
            const url = new URL(form.action);
            Object.entries(this.filters).forEach(([k, v]) => {
                if (v) url.searchParams.set(k, v);
            });
            window.location.href = url.toString();
        }
    };
}

// ===== QUESTION CARD =====
function questionCard(questionId, alreadyAnswered, wasCorrect) {
    return {
        questionId, alreadyAnswered, wasCorrect,
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

                selectAnswer(letter) { this.selectedAnswer = letter; },

        async submitAnswer() {
            if (!this.selectedAnswer || this.answered || this.submitting) return;
            this.submitting = true;
            try {
                const res = await fetch(`/questions/${this.questionId}/answer`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
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

                async toggleChat() {
                    this.showChat = !this.showChat;
                    if (this.showChat && !this.chatLoaded) await this.loadChatHistory();
                },

                async loadChatHistory() {
                    try {
                        const res = await fetch(`/questions/${this.questionId}/chat`);
                        if (res.ok) { this.chatMessages = await res.json(); this.chatLoaded = true; }
                    } catch (e) { console.error('Chat load error', e); }
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
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
                if (attempts >= 30) { clearInterval(poller); this.chatTyping = false; }
            }, 2000);
        }
    };
}
</script>
@endsection