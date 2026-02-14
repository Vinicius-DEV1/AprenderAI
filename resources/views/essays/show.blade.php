@extends('layouts.app')

@section('page-title', 'Redação')

@section('content')
    <style>
        .essay-container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .essay-header {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f1f5f9;
        }

        .essay-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .essay-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #64748b;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-corrected {
            background: #d1fae5;
            color: #065f46;
        }

        .essay-content {
            font-size: 16px;
            line-height: 1.8;
            color: #1e293b;
            margin-bottom: 32px;
            white-space: pre-wrap;
        }

        .score-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 32px;
            color: white;
            text-align: center;
            margin-bottom: 32px;
        }

        .score-section h2 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .competencies-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .competency {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .competency h4 {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .competency .score {
            font-size: 32px;
            font-weight: 700;
            color: #2563EB;
        }

        .feedback-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .feedback-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1e293b;
        }

        .feedback-text {
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
        }

        .actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563EB;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }
    </style>

    <div class="essay-container">
        <div class="essay-header">
            <h1>{{ $essay->title }}</h1>
            <div class="essay-meta">
                <span>Tema: {{ $essay->theme }}</span>
                <span>•</span>
                <span>{{ $essay->created_at->format('d/m/Y H:i') }}</span>
                <span>•</span>
                <span class="status-badge status-{{ $essay->status }}">
                    {{ [
        'draft' => 'Rascunho',
        'pending' => 'Em correção',
        'corrected' => 'Corrigida'
    ][$essay->status] ?? 'Desconhecido' }}
                </span>
            </div>
        </div>

        <div class="essay-content">{{ $essay->content }}</div>

        @if($essay->isCorrected())
            <div class="score-section">
                <h2>{{ $essay->score }}/1000</h2>
                <p style="opacity: 0.9;">Sua nota final</p>
            </div>

            <div class="competencies-grid">
                <div class="competency">
                    <h4>Comp. 1</h4>
                    <div class="score">{{ $essay->competency_1 ?? 0 }}/200</div>
                </div>
                <div class="competency">
                    <h4>Comp. 2</h4>
                    <div class="score">{{ $essay->competency_2 ?? 0 }}/200</div>
                </div>
                <div class="competency">
                    <h4>Comp. 3</h4>
                    <div class="score">{{ $essay->competency_3 ?? 0 }}/200</div>
                </div>
                <div class="competency">
                    <h4>Comp. 4</h4>
                    <div class="score">{{ $essay->competency_4 ?? 0 }}/200</div>
                </div>
                <div class="competency">
                    <h4>Comp. 5</h4>
                    <div class="score">{{ $essay->competency_5 ?? 0 }}/200</div>
                </div>
            </div>

            @if($essay->feedback)
                <div class="feedback-section">
                    <h3>Feedback</h3>
                    <div class="feedback-text">{{ $essay->feedback }}</div>
                </div>
            @endif

            @if($essay->ai_suggestions)
                <div class="feedback-section" style="background: #eff6ff;">
                    <h3>💡 Sugestões de Melhoria (Plano {{ $essay->user->plan->name }})</h3>
                    <div class="feedback-text">{{ $essay->ai_suggestions }}</div>
                </div>
            @endif
        @endif

        <div class="actions">
            @if($essay->status === 'draft')
                <form method="POST" action="{{ route('essays.submit', $essay) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Enviar para Correção</button>
                </form>
            @endif
            <a href="{{ route('essays.index') }}" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
@endsection