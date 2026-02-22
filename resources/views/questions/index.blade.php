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

    /* ── AI Search ── */
    .qb-ai-wrapper { position: relative; margin-bottom: 24px; }
    .qb-ai-search-container { position: relative; background: white; border-radius: 16px; padding: 4px; box-shadow: 0 4px 20px rgba(99, 102, 241, 0.1); border: 1px solid #e0e7ff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; }
    :root.dark .qb-ai-search-container { background: #1e293b; border-color: rgba(99, 102, 241, 0.2); }
    .qb-ai-search-container:focus-within { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15); border-color: #6366f1; }
    .qb-ai-input { flex: 1; border: none !important; background: transparent !important; padding: 12px 16px; font-size: 15px; color: #1e293b; box-shadow: none !important; }
    :root.dark .qb-ai-input { color: #f1f5f9; }
    .qb-ai-input::placeholder { color: #94a3b8; font-style: italic; }
    .qb-ai-button { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border-radius: 12px; padding: 10px 24px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px; margin-right: 4px; }
    .qb-ai-button:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
    .qb-ai-button:disabled { opacity: 0.7; cursor: wait; transform: none; }
    .qb-ai-locked { background: #f1f5f9; color: #94a3b8; cursor: pointer; border-radius: 12px; padding: 10px 24px; font-size: 13px; font-weight: 700; border: none; margin-right: 4px; display: flex; align-items: center; gap: 8px; }
    :root.dark .qb-ai-locked { background: #334155; }
    .qb-ai-glow { position: absolute; inset: -2px; background: linear-gradient(90deg, #6366f1, #a855f7, #6366f1); border-radius: 18px; z-index: -1; opacity: 0; transition: opacity 0.3s; background-size: 200% 100%; animation: qb-gradient-shift 3s infinite linear; }
    .qb-ai-search-container:focus-within .qb-ai-glow { opacity: 0.3; }
    @keyframes qb-gradient-shift { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }

    /* ── Xavier Bubble & Toast ── */
    .xavier-header-badge { margin-bottom: 12px; display: inline-flex; align-items: center; gap: 10px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); padding: 8px 16px; border-radius: 50px; font-size: 13px; color: #4338ca; font-weight: 500; }
    :root.dark .xavier-header-badge { background: rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.3); color: #c7d2fe; }
    .pulse-dot { width: 8px; height: 8px; background: #6366f1; border-radius: 50%; box-shadow: 0 0 0 rgba(99, 102, 241, 0.4); animation: xavier-pulse 2s infinite; }
    @keyframes xavier-pulse { 0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); } 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); } }

    .xavier-bubble { position: absolute; top: calc(100% + 16px); left: 20px; background: white; border: 1px solid #6366f1; border-radius: 20px; padding: 20px 28px; box-shadow: 0 15px 40px rgba(99, 102, 241, 0.2); max-width: 520px; z-index: 50; display: flex; gap: 16px; align-items: flex-start; animation: xavier-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); border-left-width: 6px; }
    :root.dark .xavier-bubble { background: #1e293b; border-color: #6366f1; }
    .xavier-bubble::after { content: ''; position: absolute; bottom: 100%; left: 32px; border: 12px solid transparent; border-bottom-color: white; }
    :root.dark .xavier-bubble::after { border-bottom-color: #1e293b; }
    .xavier-btns-row { display: flex; flex-direction: row; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
    .xavier-bubble .txt { font-size: 14px; color: #1e293b; line-height: 1.7; flex: 1; }
    :root.dark .xavier-bubble .txt { color: #f1f5f9; }
    .xavier-action-btn { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); white-space: nowrap; }
    .xavier-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4); }
    
    .xavier-sug-btn { background: rgba(99, 102, 241, 0.05); color: #4f46e5; border: 1.5px solid rgba(99, 102, 241, 0.2); padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .xavier-sug-btn:hover { background: rgba(99, 102, 241, 0.1); border-color: #6366f1; transform: translateY(-1px); }
    
    .qb-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 14px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); z-index: 1000; animation: toast-in 0.4s ease-out forwards; }
    
    @keyframes xavier-pop { from { opacity: 0; transform: translateY(10px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes toast-in { from { opacity: 0; transform: translate(-50%, 20px); } to { opacity: 1; transform: translate(-50%, 0); } }

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
    .qb-action-btn.retry { color: #6366f1; border-color: #c7d2fe; background: #f5f3ff; }
    .qb-action-btn.retry:hover { background: #eef2ff; border-color: #a5b4fc; }
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
    :root.dark .qb-alt.selected { border-color: #6366f1; background: rgba(99,102,241,0.15); }
    :root.dark .qb-alt.correct-reveal { border-color: #10b981; background: rgba(16, 185, 129, 0.15); }
    :root.dark .qb-alt.incorrect-reveal { border-color: #ef4444; background: rgba(239, 68, 68, 0.15); }
    :root.dark .qb-alt-text { color: #cbd5e1; }
    :root.dark .qb-alt-letter { background: #334155; color: #94a3b8; }
    :root.dark .qb-explanation { background: #0f172a; border-color: rgba(255,255,255,0.08); }
    :root.dark .qb-explanation-text { color: #94a3b8; }
    :root.dark .qb-action-btn { background: #334155; border-color: rgba(255,255,255,0.08); color: #cbd5e1; }
    :root.dark .qb-action-btn.retry { background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.3); color: #818cf8; }
    :root.dark .qb-action-btn.retry:hover { background: rgba(99, 102, 241, 0.2); border-color: rgba(99, 102, 241, 0.4); }
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
    :root.dark .qb-feedback.correct { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); }
    :root.dark .qb-feedback.incorrect { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
    :root.dark .qb-feedback.correct .qb-feedback-title { color: #34d399; }
    :root.dark .qb-feedback.incorrect .qb-feedback-title { color: #f87171; }
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
        <p>Resolva questões, veja explicações e tire dúvidas com {{ $aiName }}</p>
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


<div class="xavier-header-badge">
    <span class="pulse-dot"></span>
    <strong>{{ $aiName }}</strong> — seu Agente de Busca pronto para garimpar o melhor conteúdo para você.
</div>
<div class="qb-ai-wrapper" x-data="aiSearch()" x-init="initTypewriter()">
    {{-- Balão de Fala do Xavier --}}
    <template x-if="suggestion || message || suggestions.length > 0">
        <div class="xavier-bubble" @click.away="closeBubble()" x-show="suggestion || message || suggestions.length > 0" x-transition:enter="xavier-pop 0.3s ease-out">
            <div style="background: #6366f1; border-radius: 12px; padding: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);">
                <span style="font-size: 20px; color: white;">🤖</span>
            </div>
            <div class="txt">
                <strong x-text="message ? '{{ $aiName }}:' : 'Dica do {{ $aiName }}:'"></strong><br>
                <div x-html="message || suggestion"></div>
                <div class="xavier-btns-row" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                    <template x-if="loading">
                        <div class="bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 border border-gray-200">
                            <span class="font-medium">Eu estou cruzando os dados...</span>
                            <span class="flex gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0s;"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0.2s;"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0.4s;"></span>
                            </span>
                        </div>
                    </template>
                    <template x-if="!loading && !isError && !isQuotaExceeded && suggestions.length > 0">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="sug in suggestions" :key="sug.label">
                                <button class="xavier-sug-btn" @click="applyXavierSuggestion(sug.filters)">
                                    <span style="font-size: 14px;">🔍</span>
                                    <span x-text="sug.label"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    <template x-if="isError">
                        <div class="flex items-center gap-2">
                            <button @click="tryAgain()" class="xavier-action-btn" style="background: #4f46e5; color: white; border: none;">
                                🔄 Tentar novamente
                            </button>
                            <button @click="tryLater()" class="xavier-action-btn" style="background: #94a3b8; color: white; border: none; opacity: 0.8;">
                                ⏳ Tentar mais tarde
                            </button>
                        </div>
                    </template>

                    <template x-if="isQuotaExceeded">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('plans.index') }}" class="xavier-action-btn" style="background: #fbbf24; color: #78350f; border: none; font-weight: bold; text-decoration: none;">
                                ⭐ Fazer Upgrade
                            </a>
                            <button @click="closeBubble()" class="xavier-action-btn" style="background: #e2e8f0; color: #475569; border: none;">
                                ✕ Fechar
                            </button>
                        </div>
                    </template>
                </div>
                <div style="margin-top: 16px; font-size: 11px; opacity: 0.6; cursor: pointer; text-decoration: underline;" @click="closeBubble()">
                    [Fechar conversa]
                </div>
            </div>
        </div>
</template>

    <div class="qb-ai-search-container">
        <div class="qb-ai-glow"></div>
        <div style="display: flex; align-items: center; padding-left: 16px;">
            <span style="font-size: 20px;">✨</span>
        </div>
        <input type="text" 
               x-model="prompt" 
               @keydown.enter="submitSearch()"
               :placeholder="placeholderText" 
               class="qb-ai-input"
               :disabled="loading">
        
        @if(auth()->user()->plan && auth()->user()->plan->name !== 'Gratuito')
            <button @click="submitSearch()" class="qb-ai-button" :disabled="loading || !prompt.trim()">
                <span x-show="!loading">🚀 Consultar Agente {{ $aiName }}</span>
                <span x-show="loading" class="animate-pulse" x-text="statusText">🪄 Mapeando informações...</span>
            </button>
        @else
            <button @click="window.location.href='{{ route('plans.index') }}'" class="qb-ai-locked">
                <span>🔒 Liberação Plus</span>
            </button>
        @endif
    </div>

    {{-- Toast de Sucesso --}}
    <template x-if="showToast">
        <div class="qb-toast" x-text="toastMessage"></div>
    </template>
</div>

{{-- ===== FILTROS ADAPTATIVOS ===== --}}
<div class="qb-filters" x-data="filterPanel({{ json_encode(request()->all()) }})" 
     @ai-no-results.window="handleNoResults($event.detail)">
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
                <select name="subject" x-model="filters.subject" :disabled="loadingSubjects">
                    <option value="" x-text="loadingSubjects ? 'Carregando...' : 'Todas'"></option>
                    <template x-for="s in subjects" :key="s">
                        <option :value="s" x-text="s" :selected="filters.subject == s"></option>
                    </template>
                </select>
            </div>
            <div class="qb-filter-item">
                <!-- O Label muda dinamicamente: "Eixo Temático" para ENEM, "Assunto" para Concurso -->
                <label x-text="filters.type === 'enem' ? 'Eixo Temático' : 'Assunto'"></label>
                <select name="topic" x-model="filters.topic" :disabled="!filters.subject || loadingTopics">
                    <option value="" x-text="loadingTopics ? 'Carregando...' : (!filters.subject ? 'Selecione uma matéria primeiro...' : (topics.length === 0 ? 'Sem assuntos disponíveis' : 'Selecione um assunto...'))"></option>
                    <template x-for="t in topics" :key="t.id">
                        <option :value="t.id" x-text="t.name" :selected="filters.topic == t.id"></option>
                    </template>
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

{{-- ===== FEED DE QUESTÕES (ZERO REFRESH) ===== --}}
<div id="questions-container" style="position: relative; min-height: 400px;">
    {{-- Overlay de Carregamento --}}
    <div x-show="globalLoading" x-transition.opacity 
         style="position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 100; display: flex; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
        <div style="font-weight: 700; color: #4338ca; font-size: 14px;" x-text="statusText">Minerando na base de dados...</div>
    </div>

    <div id="questions-content">
        @include('questions._list')
    </div>
</div>

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
            type: currentFilters.type || '',
            subject: currentFilters.subject || '',
            topic: currentFilters.topic || '',
            keyword: currentFilters.keyword || '',
            year: currentFilters.year || '',
            difficulty: currentFilters.difficulty || '',
            status: currentFilters.status || '',
            organization: currentFilters.organization || '',
            institution: currentFilters.institution || '',
            role: currentFilters.role || ''
        },
        topics: [],
        subjects: {!! json_encode($filterOptions['subjects']) !!},
        loadingTopics: false,
        loadingSubjects: false,
        moreFilters: !!(currentFilters.year || currentFilters.difficulty || currentFilters.status || currentFilters.organization || currentFilters.institution || currentFilters.role),
        statusText: '🪄 Refinando a busca...',
        globalLoading: false,
        isApplyingAiFilters: false,
        
        init() {
            window.addEventListener('ai-loading-start', () => { this.globalLoading = true; });
            window.addEventListener('ai-loading-stop', () => { this.globalLoading = false; });
            
            // Garantir as matérias dependendo do Type, e Tópicos dependendo da matéria
            if (this.filters.type) this.loadSubjects();
            if (this.filters.subject) this.loadTopics();
            
            // Watch para trocar os tópicos quando a matéria mudar
            this.$watch('filters.subject', () => {
                if (!this.isApplyingAiFilters) {
                    this.filters.topic = '';
                }
                this.loadTopics();
            });

            this.$watch('filters.type', () => {
                if (!this.isApplyingAiFilters) {
                    this.filters.topic = '';
                }
                this.loadTopics();
            });

            // Interceptar cliques em links de paginação para usar AJAX
            document.addEventListener('click', (e) => {
                const link = e.target.closest('.qb-pagination a, .pagination a');
                if (link) {
                    e.preventDefault();
                    this.fetchQuestions(link.href);
                }
            });

            // Listener para filtros aplicados via IA
            window.addEventListener('ai-filters-applied', (e) => {
                const { filters, shouldScroll, statusText, hardReset } = e.detail;
                if (statusText) this.statusText = statusText;
                this.applyAiFilters(filters, shouldScroll !== false, !!hardReset);
            });
        },

        handleNoResults() {
            // Se não houver resultados secundários ou algo assim, podemos disparar eventos aqui também
        },

        async loadSubjects() {
            this.loadingSubjects = true;
            try {
                const params = new URLSearchParams();
                if (this.filters.type) params.set('type', this.filters.type);
                
                const res = await fetch(`{{ route('questions.subjects') }}?${params.toString()}`);
                this.subjects = await res.json();
                
                // Se a matéria atual (via IA ou manual) não fizer parte desta filtragem, reseta a escolha
                if (this.filters.subject && !this.subjects.includes(this.filters.subject)) {
                    this.filters.subject = '';
                }
            } catch (e) { console.error('Subjects err', e); }
            finally { this.loadingSubjects = false; }
        },

        async loadTopics() {
            if (!this.filters.subject) {
                this.topics = [];
                return;
            }
            this.loadingTopics = true;
            try {
                const params = new URLSearchParams({
                    subject: this.filters.subject,
                    type: this.filters.type // Envia o tipo para buscar na coluna correta (tema ou tópico)
                });
                const res = await fetch(`{{ route('questions.topics') }}?${params.toString()}`);
                this.topics = await res.json();
            } catch (e) { console.error('Tópicos erro', e); }
            finally { this.loadingTopics = false; }
        },

        onTypeChange() { 
            if (this.filters.type === 'enem') { 
                this.filters.organization = ''; 
                this.filters.institution = ''; 
                this.filters.role = ''; 
            }
            // Sequenciar o recarregamento dos dropdowns isolados
            this.loadSubjects().then(() => {
                this.loadTopics();
            });
        },
        
        applyAiFilters(newFilters, shouldScroll = true, hardReset = false) {
            this.isApplyingAiFilters = true;

            if (hardReset) {
                // Hard Reset: limpa tudo antes de aplicar os novos
                Object.keys(this.filters).forEach(key => this.filters[key] = '');
                this.moreFilters = false;
            }

            Object.keys(newFilters).forEach(key => {
                if (this.filters.hasOwnProperty(key)) {
                    this.filters[key] = newFilters[key];
                }
            });
            if (this.filters.year || this.filters.difficulty || this.filters.organization || this.filters.institution || this.filters.role) {
                this.moreFilters = true;
            }
            
            // Usamos nextTick para garantir que as reatividades (como o watch de subject) 
            // ocorram enquanto isApplyingAiFilters ainda é true
            this.$nextTick(() => {
                this.submitForm(shouldScroll);
                this.isApplyingAiFilters = false;
            });
        },

        async submitForm(shouldScroll = true) {
            const url = new URL('{{ route("questions.index") }}');
            Object.entries(this.filters).forEach(([k, v]) => { if (v) url.searchParams.set(k, v); });
            
            // GA4: Monitorar uso de filtros
            if (typeof gtag === 'function') {
                gtag('event', 'filter_used', {
                    'type': this.filters.type || 'all',
                    'subject': this.filters.subject || 'all',
                    'difficulty': this.filters.difficulty || 'all',
                    'has_keyword': !!this.filters.keyword
                });
            }

            await this.fetchQuestions(url.toString(), shouldScroll);
        },

        async fetchQuestions(url, shouldScroll = true) {
            window.dispatchEvent(new CustomEvent('ai-loading-start'));
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await res.text();
                const container = document.getElementById('questions-content');
                container.innerHTML = html;
                
                // GA4: Virtual Pageview para navegação AJAX
                if (typeof gtag === 'function') {
                    gtag('event', 'page_view', {
                        'page_title': document.title,
                        'page_location': url
                    });
                }

                // Detection: Se houver a classe qb-no-results, não rola
                const hasResults = !container.querySelector('.qb-no-results');
                
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('ai-search-finished', { detail: { hasResults } }));
                });

                if (shouldScroll && hasResults) {
                    window.scrollTo({ top: document.getElementById('questions-container').offsetTop - 100, behavior: 'smooth' });
                }
            } catch (e) { alert('Erro ao carregar questões.'); }
            finally { window.dispatchEvent(new CustomEvent('ai-loading-stop')); }
        },

        clearFilters() { 
            Object.keys(this.filters).forEach(k => this.filters[k] = ''); 
            this.submitForm();
        }
    };
}

function aiSearch() {
    return {
        prompt: '',
        loading: false,
        placeholderText: 'Comece agora busque: ex: ',
        statusText: '🪄 Conectando os temas...',
        globalLoading: false,
        suggestion: '',
        suggestions: [],
        message: '',
        pendingSuggestion: '',
        pendingSuggestions: [],
        pendingMessage: '',
        isError: false,
        isQuotaExceeded: false,
        showToast: false,
        toastMessage: '',
        lastSearchHadResults: true,
        failureMessages: [
            'Eu tentei cruzar todos os dados, mas acabei me perdendo entre tantos enunciados. Que tal tentarmos uma nova rota de busca?',
            'As informações se misturaram na minha mesa de análise. Deixe-me organizar tudo e tentamos de novo?',
            'Garimpar essa questão específica foi mais difícil do que eu esperava. Minhas conexões falharam, vamos repetir?',
            'Houve um desencontro no mapeamento dos filtros. Posso tentar reorganizar minha mesa de estudos e começar de novo?',
            'Eu vasculhei cada canto do banco de dados, de 2009 até hoje, mas essa resposta escapou por pouco. Vamos ajustar os termos?',
            'O mapa da minha busca ficou um pouco confuso agora. Deixe-me recalibrar minha rota entre as questões.'
        ],
        staticPrefix: 'Comece agora busque: ex: ',
        placeholders: [
            "Questões de Trigonometria do ENEM 2022...",
            "Quero questões fáceis de Interpretação de Texto...",
            "Questões de Revolução Industrial para Concurso...",
            "Getúlio Vargas e o Estado Novo - Questões ENEM...",
            "Geometria Espacial nível difícil - Questões...",
            "Biologia Celular: Questões sobre Organelas...",
            "Leis de Newton: Questões de dinâmica e força...",
            "Questões de Gramática: Orações subordinadas...",
            "Questões de Química: Tabela periódica e Ligações...",
            "Questões de Matemática: Probabilidade e Análise...",
            "Questões de Sociologia: Cidadania e Ética...",
            "Política Brasileira: Questões sobre a redemocratização...",
            "Questões de Filosofia: Ética e Moral...",
            "Questões de Biologia: Mitose e Meiose...",
            "Questões de Química: Cálculo de estequiometria...",
            "Questões de História: Segunda Guerra Mundial...",
            "Questões de Literatura: Modernismo no Brasil...",
            "Questões de Matemática: Áreas e volumes complexos...",
            "Questões de Biologia: Fotossíntese e respiração...",
            "Questões de lógica e raciocínio matemático...",
            "Apenas questões que caíram no ENEM 2023...",
            "Questões desafiadoras de Eletromagnetismo! ⚡",
            "{{ $aiName }}, filtre questões de Genética Mendeliana! 🧬",
            "Busque uma maratona de questões de Português! 🏃‍♂️",
            "Questões de atualidades sobre Geopolítica Mundial... 🌍",
            "Questões de Ecologia: Cadeia alimentar e ciclos... 🌱"
        ],
        funMessages: [
            "Garimpando informações importantes nos editais... 🌊",
            "Mapeando conexões entre os temas mais cobrados para você... 📚",
            "Filtrando os melhores enunciados para sua jornada de estudos... ✨",
            "Conectando os pontos entre sua busca e nossa base de dados... 💡",
            "Navegando por um mar de questões para encontrar a ideal... 🚀",
            "Organizando minha mesa de análise para te entregar o melhor resultado... ☕",
            "Eu estou vasculhando cada canto do banco de dados, de 2009 até hoje... 🕰️",
            "Distilando o conhecimento acumulado para sua tela... 🌾",
            "Focando totalmente em mapear sua agulha no palheiro... 🧘",
            "Quase lá! Encontrei caminhos sólidos para sua aprovação... ✨",
            "Finalizando a nossa rota de busca. Só mais um instante... ⏳",
            "Eu encontrei conexões de resultados que você vai adorar explorar... 😊"
        ],
        placeholderIndex: 0,
        
        init() {
            this.initTypewriter();
            window.addEventListener('ai-loading-start', () => { this.globalLoading = true; });
            window.addEventListener('ai-loading-stop', () => { this.globalLoading = false; });
            
            window.addEventListener('ai-search-finished', (e) => {
                this.lastSearchHadResults = e.detail.hasResults;

                // Sempre aplica pendências se existirem
                if (this.pendingSuggestion || this.pendingSuggestions.length > 0 || this.pendingMessage) {
                    this.suggestion = this.pendingSuggestion;
                    this.suggestions = this.pendingSuggestions;
                    this.message = this.pendingMessage;
                } else if (!this.lastSearchHadResults) {
                    // Fallback se não deram nada mas não houve resultados
                    this.message = 'Não encontrei questões para essa busca.';
                } else {
                    this.suggestion = '';
                    this.suggestions = [];
                    this.message = '';
                }
                
                this.pendingSuggestion = '';
                this.pendingSuggestions = [];
                this.pendingMessage = '';
            });
        },

        async initTypewriter() {
            let currentSuffix = '';
            let isDeleting = false;
            const type = () => {
                const fullText = this.placeholders[this.placeholderIndex];
                currentSuffix = isDeleting ? fullText.substring(0, currentSuffix.length - 1) : fullText.substring(0, currentSuffix.length + 1);
                this.placeholderText = this.staticPrefix + currentSuffix;
                let speed = isDeleting ? 30 : 50;
                if (!isDeleting && currentSuffix === fullText) { speed = 2500; isDeleting = true; }
                else if (isDeleting && currentSuffix === '') { isDeleting = false; this.placeholderIndex = (this.placeholderIndex + 1) % this.placeholders.length; speed = 500; }
                setTimeout(type, speed);
            };
            type();
        },

        async applyXavierSuggestion(filters) {
            this.isError = false;
            this.isQuotaExceeded = false;
            
            // UX: Fecha o balão e limpa o prompt para indicar nova busca
            this.closeBubble();
            this.prompt = '';

            // Feedback Visual de Sucesso (Toast)
            this.toastMessage = '✅ Busca atualizada conforme sugestão.';
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);

            window.dispatchEvent(new CustomEvent('ai-filters-applied', { 
                detail: { 
                    filters: filters,
                    hardReset: true, // Garante limpeza total
                    statusText: '🪄 Aplicando sugestão do Xavier...' 
                } 
            }));
        },

        async submitSearch() {
            if (!this.prompt.trim() || this.loading) return;
            this.loading = true;
            this.suggestion = '';
            this.suggestions = [];
            this.message = '';
            this.pendingSuggestion = '';
            this.pendingSuggestions = [];
            this.pendingMessage = '';
            this.isError = false;
            this.isQuotaExceeded = false;
            
            let msgIdx = 0;
            const statusInterval = setInterval(() => {
                this.statusText = this.funMessages[msgIdx];
                msgIdx = (msgIdx + 1) % this.funMessages.length;
            }, 1500);

            try {
                const res = await fetch('{{ route("questions.ai-search") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ prompt: this.prompt })
                });
                const data = await res.json();
                
                if (data.status === 'queued') {
                    this.pollSearch(data.request_id, statusInterval);
                } else {
                    clearInterval(statusInterval);
                    this.loading = false;
                    this.statusText = '🪄 Processando...';
                    this.message = data.message || 'O {{ $aiName }} não conseguiu interpretar essa busca.';
                    
                    if (data.code === 'quota_exceeded' || data.code === 'plan_restricted') {
                        this.isQuotaExceeded = true;
                        // GA4: Monitorar quota excedida
                        if (typeof gtag === 'function') {
                            gtag('event', 'ai_quota_exceeded', { 'ai_name': '{{ $aiName }}' });
                        }
                    }
                }
            } catch (e) { 
                clearInterval(statusInterval); 
                this.loading = false;
                this.statusText = '🪄 Processando...';
                this.message = 'Erro na conexão com o {{ $aiName }}.'; 
                // GA4: Erro técnico na busca
                if (typeof gtag === 'function') {
                    gtag('event', 'ai_search_error', { 'error_type': 'connection_failure' });
                }
            }
        },

        async pollSearch(requestId, statusInterval) {
            let attempts = 0;
            const maxAttempts = 30; // 30 * 2s = 60s
            
            const poller = setInterval(async () => {
                attempts++;
                try {
                    const res = await fetch(`/questions/ai-search/${requestId}/status`);
                    const data = await res.json();

                    if (data.status === 'completed') {
                        clearInterval(poller);
                        clearInterval(statusInterval);
                        this.loading = false;
                        this.statusText = '🪄 Processando...';

                        if (data.suggestion_tip) this.pendingSuggestion = data.suggestion_tip;
                        if (data.suggestions) this.pendingSuggestions = data.suggestions;
                        
                        this.toastMessage = '✅ Busca realizada com sucesso! {{ $aiName }} encontrou o que você precisava.';
                        this.showToast = true;
                        setTimeout(() => this.showToast = false, 4000);

                        window.dispatchEvent(new CustomEvent('ai-filters-applied', { 
                            detail: {
                                filters: data.filters,
                                shouldScroll: this.pendingSuggestions.length === 0,
                                statusText: this.statusText
                            }
                        }));
                    } else if (data.status === 'failed') {
                        clearInterval(poller);
                        clearInterval(statusInterval);
                        this.loading = false;
                        this.isError = true;
                        this.statusText = '🪄 Processando...';
                        
                        // GA4: Falha na interpretação da busca
                        if (typeof gtag === 'function') {
                            gtag('event', 'ai_search_error', { 'error_type': 'interpretation_failure', 'error_msg': data.error });
                        }

                        const funny = this.getRandomFailure();
                        this.message = `${funny}<br><br><small style="opacity: 0.8">${data.error || 'Não conseguimos processar sua busca agora.'}</small>`;
                    }
                } catch (e) {
                    // Silently fail and continue polling until timeout
                }

                if (attempts >= maxAttempts) {
                    clearInterval(poller);
                    clearInterval(statusInterval);
                    this.loading = false;
                    this.statusText = '🪄 Processando...';
                    this.message = 'A busca demorou demais. Tente novamente.';
                }
            }, 2000);
        },

        getRandomFailure() {
            return this.failureMessages[Math.floor(Math.random() * this.failureMessages.length)];
        },

        tryAgain() {
            this.message = '';
            this.isError = false;
            this.statusText = '🪄 Processando...';
            this.submitSearch();
        },

        tryLater() {
            this.message = '';
            this.isError = false;
            this.isQuotaExceeded = false;
            this.prompt = '';
            this.statusText = '🪄 Processando...';
        },

        closeBubble() {
            this.suggestion = '';
            this.message = '';
            this.suggestions = [];
            this.isError = false;
            this.isQuotaExceeded = false;
        }
    };
}

function questionCard(questionId, alreadyAnswered, wasCorrect, subject = 'n/a', streamingEnabled = false) {
    return {
        questionId, alreadyAnswered, wasCorrect, subject, streamingEnabled,
        selectedAnswer: null, answered: false, isCorrect: null,
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

                // GA4: Monitorar submissão de resposta
                if (typeof gtag === 'function') {
                    gtag('event', 'answer_submitted', {
                        'question_id': this.questionId,
                        'is_correct': data.correct,
                        'subject': this.subject,
                        'origin': 'question_bank'
                    });
                }
            } catch (e) { alert('Erro ao enviar resposta.'); } finally { this.submitting = false; }
        },
        resetCard() {
            this.answered = false;
            this.selectedAnswer = null;
            this.isCorrect = null;
            this.correctAnswer = null;
            this.explanation = null;
            this.difficultyReasoning = null;
            this.showChat = false;
            this.showHistory = false;
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
                        this.chatMessages.push({
                            id: Date.now(),
                            role: 'assistant',
                            message: 'Olá! Eu sou o {{ $aiName }}. Qual sua dúvida sobre essa questão?',
                            created_at: new Date().toISOString()
                        });
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
            
            if (this.streamingEnabled) {
                await this.sendChatStreaming(msg);
            } else {
                try {
                    const res = await fetch(`/questions/${this.questionId}/chat`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ message: msg }) });
                    const data = await res.json(); if (data.status === 'quota_exceeded') { this.chatMessages.push({ role: 'system', message: data.message, upgrade_url: data.upgrade_url, id: Date.now() }); this.chatTyping = false; this.$nextTick(() => this.scrollToBottom()); return; }
                    this.pollChat();
                } catch (e) { this.chatTyping = false; this.chatMessages.push({ role: 'assistant', message: 'Erro ao processar.', id: Date.now() }); this.$nextTick(() => this.scrollToBottom()); }
            }
        },
        async sendChatStreaming(message) {
            try {
                const response = await fetch(`/questions/${this.questionId}/chat/stream`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'text/event-stream'
                    },
                    body: JSON.stringify({ message })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    if (errorData.status === 'quota_exceeded') {
                        this.chatMessages.push({ role: 'system', message: errorData.message, upgrade_url: errorData.upgrade_url, id: Date.now() });
                    } else {
                        throw new Error('Falha na conexão');
                    }
                    this.chatTyping = false;
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let assistantMsg = { role: 'assistant', message: '', id: Date.now() };
                this.chatMessages.push(assistantMsg);
                this.chatTyping = false;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const data = JSON.parse(line.substring(6));
                                if (data.text) {
                                    assistantMsg.message += data.text;
                                    this.$nextTick(() => this.scrollToBottom());
                                }
                            } catch (e) {
                                console.error('Error parsing SSE:', e, line);
                            }
                        }
                    }
                }
            } catch (e) {
                console.error('Streaming error:', e);
                this.chatTyping = false;
                this.chatMessages.push({ role: 'assistant', message: 'Erro ao processar o streaming.', id: Date.now() });
            } finally {
                this.chatTyping = false;
                this.$nextTick(() => this.scrollToBottom());
            }
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