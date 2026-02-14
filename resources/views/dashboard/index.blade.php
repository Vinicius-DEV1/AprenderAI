@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #94a3b8;
        }

        .section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1e293b;
        }

        .simulation-list {
            list-style: none;
        }

        .simulation-item {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .simulation-item:last-child {
            border-bottom: none;
        }

        .simulation-info h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .simulation-info p {
            font-size: 13px;
            color: #64748b;
        }

        .simulation-score {
            font-size: 24px;
            font-weight: 700;
            color: #2563EB;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 16px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .plan-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #eff6ff;
            color: #2563EB;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Provas Realizadas</h3>
            <div class="value">{{ $stats->total_simulations }}</div>
            <div class="label">no total</div>
        </div>

        <div class="stat-card">
            <h3>Média em Matemática</h3>
            <div class="value">{{ number_format($stats->average_math_score, 1) }}%</div>
            <div class="label">de acertos</div>
        </div>

        <div class="stat-card">
            <h3>Média em Português</h3>
            <div class="value">{{ number_format($stats->average_portuguese_score, 1) }}%</div>
            <div class="label">de acertos</div>
        </div>

        <div class="stat-card">
            <h3>Redações Enviadas</h3>
            <div class="value">{{ $stats->total_essays }}</div>
            <div class="label">no total</div>
        </div>
    </div>

    <div class="section">
        <h3>
            Seu Plano
            <span class="plan-badge">{{ $user->plan->name }}</span>
        </h3>

        <p style="color: #64748b; margin-bottom: 16px;">
            @if($simulationLimit['can_create'])
                Você pode criar mais
                <strong>{{ $simulationLimit['remaining'] }}</strong>
                {{ is_numeric($simulationLimit['remaining']) && $simulationLimit['remaining'] == 1 ? 'prova' : 'provas' }}
                este mês.
            @else
                {{ $simulationLimit['message'] }}
            @endif
        </p>

        @if(!$simulationLimit['can_create'])
            <a href="{{ route('plans.index') }}" class="btn-primary">Fazer Upgrade</a>
        @endif
    </div>

    <div class="section">
        <h3>Provas Recentes</h3>

        @if($recentSimulations->count() > 0)
            <ul class="simulation-list">
                @foreach($recentSimulations as $simulation)
                    <li class="simulation-item">
                        <div class="simulation-info">
                            <h4>{{ ucfirst($simulation->type) }} - {{ $simulation->configuration['questions'] ?? 'N/A' }} questões
                            </h4>
                            <p>{{ $simulation->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @if(in_array($simulation->status, ['finished', 'corrected']))
                            <div class="simulation-score">{{ number_format((float) $simulation->score, 1) }}%</div>
                        @else
                            <span style="color: #f59e0b; font-weight: 600;">Pendente</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>Você ainda não realizou nenhuma prova.</p>
                <a href="{{ route('simulations.create') }}" class="btn-primary">Criar Primeira Prova</a>
            </div>
        @endif
    </div>
@endsection