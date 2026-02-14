@extends('layouts.app')

@section('page-title', 'Minhas Redações')

@section('content')
    <style>
        .essays-header {
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
        }

        .btn-new:hover {
            background: #1d4ed8;
        }

        .essays-list {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .essay-card {
            padding: 20px;
            border: 2px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .essay-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .essay-title h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .essay-theme {
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

        .essay-preview {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .essay-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
        }

        .essay-date {
            font-size: 13px;
            color: #94a3b8;
        }

        .score-display {
            font-size: 24px;
            font-weight: 700;
            color: #2563EB;
        }
    </style>

    <div class="essays-header">
        <h1 style="font-size: 28px; font-weight: 700;">Minhas Redações</h1>
        <a href="{{ route('essays.create') }}" class="btn-new">+ Nova Redação</a>
    </div>

    <div class="essays-list">
        @forelse($essays as $essay)
            <div class="essay-card">
                <div class="essay-header">
                    <div class="essay-title">
                        <h3>{{ $essay->title }}</h3>
                        <p class="essay-theme">Tema: {{ $essay->theme }}</p>
                    </div>
                    <span class="status-badge status-{{ $essay->status }}">
                        {{ [
                'draft' => 'Rascunho',
                'pending' => 'Em correção',
                'corrected' => 'Corrigida'
            ][$essay->status] ?? 'Desconhecido' }}
                    </span>
                </div>

                <p class="essay-preview">
                    {{ Str::limit($essay->content, 150) }}
                </p>

                <div class="essay-footer">
                    <span class="essay-date">
                        {{ $essay->created_at->format('d/m/Y H:i') }}
                    </span>

                    <div style="display: flex; gap: 12px; align-items: center;">
                        @if($essay->score)
                            <span class="score-display">{{ $essay->score }}/1000</span>
                        @endif
                        <a href="{{ route('essays.show', $essay) }}"
                            style="padding: 8px 16px; background: #f1f5f9; border-radius: 6px; text-decoration: none; color: #334155; font-size: 14px; font-weight: 500;">
                            Ver detalhes
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    style="width: 64px; height: 64px; margin: 0 auto 16px; opacity: 0.3;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <p>Você ainda não escreveu nenhuma redação.</p>
                <a href="{{ route('essays.create') }}" class="btn-new" style="margin-top: 16px; display: inline-block;">Escrever
                    Primeira Redação</a>
            </div>
        @endforelse
    </div>
@endsection