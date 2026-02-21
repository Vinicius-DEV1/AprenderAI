@extends('layouts.app')

@section('page-title', 'Minhas Provas')

@section('content')
    <style>
        .simulations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .btn-new {
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-new:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .simulations-list {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .simulation-row {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: center;
        }

        .simulation-row:last-child {
            border-bottom: none;
        }

        .simulation-title h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .simulation-title p {
            font-size: 13px;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-finished {
            background: #fde68a;
            color: #78350f;
        }

        .status-corrected {
            background: #d1fae5;
            color: #065f46;
        }

        .score-display {
            font-size: 24px;
            font-weight: 700;
            color: #2563EB;
        }

        .btn-view {
            padding: 8px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        /* DARK MODE */
        :root.dark .simulations-list {
            background: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        :root.dark .simulation-row {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        :root.dark .simulation-title h4,
        :root.dark .simulations-header h1 {
            color: #f1f5f9;
        }

        :root.dark .simulation-title p {
            color: #94a3b8;
        }

        :root.dark .btn-view {
            background: #0f172a;
            border-color: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }

        :root.dark .btn-view:hover {
            background: #334155;
            border-color: rgba(255, 255, 255, 0.2);
        }

        :root.dark .score-display {
            color: #60a5fa;
        }

        :root.dark .empty-state {
            color: #64748b;
        }

        :root.dark .status-badge.status-pending {
            background: rgba(254, 243, 199, 0.1);
            color: #fde68a;
        }

        :root.dark .status-badge.status-in_progress {
            background: rgba(219, 234, 254, 0.1);
            color: #93c5fd;
        }

        :root.dark .status-badge.status-finished {
            background: rgba(253, 230, 138, 0.1);
            color: #fcd34d;
        }

        :root.dark .status-badge.status-corrected {
            background: rgba(209, 250, 229, 0.1);
            color: #6ee7b7;
        }
    </style>

    {{-- ============================================================ --}}
    {{-- QUOTA STATUS BAR                                             --}}
    {{-- Mirrors the same structure from essays/index.blade.php       --}}
    {{-- ============================================================ --}}
    <div class="simulations-header">
        <h1 style="font-size: 28px; font-weight: 700;">Minhas Provas</h1>

        {{-- The button either starts a new simulation or triggers the limit modal --}}
        @if($canCreate)
            <a href="{{ route('simulations.create') }}" class="btn-new">+ Nova Prova</a>
        @else
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'quota-limit-modal' }))"
                    class="btn-new" style="background: #64748b; cursor: not-allowed;">
                Limite Atingido
            </button>
        @endif
    </div>

    {{-- Usage summary card --}}
    <div style="background: white; border-radius: 12px; padding: 16px 24px; margin-bottom: 16px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); display: flex; align-items: center;
                justify-content: space-between;">
        <div>
            <p style="font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 4px;">Uso Mensal de Provas</p>
            <p style="font-size: 13px; color: #64748b;">
                Você criou <strong>{{ $used }}</strong> de
                <strong>{{ $limit === 0 ? 'ilimitadas' : $limit }}</strong> provas disponíveis neste ciclo.
            </p>
        </div>
        @unless($canCreate)
            <a href="{{ route('plans.index') }}"
               style="font-size: 13px; color: #2563eb; font-weight: 600; text-decoration: none;">
               Ver Planos &rarr;
            </a>
        @endunless
    </div>

    <div class="simulations-list">
        @forelse($simulations as $simulation)
            <div class="simulation-row">
                <div class="simulation-title">
                    <h4>{{ ucfirst($simulation->type) }} - {{ $simulation->configuration['questions'] ?? 0 }} questões</h4>
                    <p>Criada em {{ $simulation->created_at->format('d/m/Y H:i') }}</p>
                </div>

                <div>
                    <span class="status-badge status-{{ $simulation->status }}">
                        {{ [
                'pending' => 'Pendente',
                'in_progress' => 'Em Andamento',
                'finished' => 'Finalizada',
                'corrected' => 'Corrigida'
            ][$simulation->status] ?? 'Desconhecido' }}
                    </span>
                </div>

                <div>
                    @if($simulation->time_elapsed)
                        {{ gmdate('H:i:s', $simulation->time_elapsed) }}
                    @else
                        -
                    @endif
                </div>

                <div>
                    @if($simulation->score !== null)
                        <span class="score-display">{{ number_format($simulation->score, 1) }}%</span>
                    @else
                        <span style="color: #94a3b8;">-</span>
                    @endif
                </div>

                <div>
                    @if($simulation->status === 'in_progress')
                        <a href="{{ route('simulations.show', $simulation) }}" class="btn-view">Continuar</a>
                    @elseif($simulation->isCorrected())
                        <a href="{{ route('simulations.result', $simulation) }}" class="btn-view">Ver Resultado</a>
                    @else
                        <a href="{{ route('simulations.show', $simulation) }}" class="btn-view">Ver</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    style="width: 64px; height: 64px; margin: 0 auto 16px; opacity: 0.3;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>Você ainda não criou nenhuma prova.</p>
                @if($canCreate)
                    <a href="{{ route('simulations.create') }}" class="btn-new" style="margin-top: 16px;">Criar Primeira Prova</a>
                @else
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'quota-limit-modal' }))"
                            class="btn-new" style="margin-top: 16px; background: #64748b; cursor: not-allowed;">
                        Ver Planos
                    </button>
                @endif
            </div>
        @endforelse

        @if($simulations->hasPages())
            <div style="margin-top: 24px;">
                {{ $simulations->links() }}
            </div>
        @endif
    </div>

    {{--
    | Quota Limit Modal
    | Triggered by any button dispatching: 'open-modal' event with detail 'quota-limit-modal'.
    --}}
    <x-quota-limit-modal
        resource="Simulados"
        :used="$used"
        :limit="$limit"
        upgradeRoute="plans.index"
    />

@endsection