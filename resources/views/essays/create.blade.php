@extends('layouts.app')

@section('page-title', 'Nova Redação')

@section('content')
    <style>
        .essay-form {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            min-height: 400px;
            line-height: 1.7;
            resize: vertical;
        }

        .word-count {
            text-align: right;
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
        }

        .actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
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

        .btn-secondary:hover {
            background: #e2e8f0;
        }
    </style>

    <div class="essay-form bg-white">
        <h1 style="font-size: 28px; font-weight: 700; margin-bottom: 24px;" class="text-slate-900">Nova
            Redação</h1>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-600 p-3 rounded-lg mb-5">
                <ul style="list-style: none; margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('essays.store') }}">
            @csrf

            <div class="form-group">
                <label for="title" class="text-slate-900">Título da Redação</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" required class="">
            </div>

            <div class="form-group">
                <label for="theme" class="text-slate-900">Tema</label>
                <input type="text" id="theme" name="theme" value="{{ old('theme') }}" required
                    placeholder="Ex: Desafios da educação no Brasil" class="">
            </div>

            <div class="form-group">
                <label for="content" class="text-slate-900">Texto da Redação</label>
                <textarea id="content" name="content" required oninput="updateWordCount()"
                    class="">{{ old('content') }}</textarea>
                <div class="word-count text-slate-600" id="wordCount">0 palavras</div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar Rascunho</button>
                <a href="{{ route('essays.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        function updateWordCount() {
            const content = document.getElementById('content').value;
            const words = content.trim().split(/\s+/).filter(word => word.length > 0).length;
            document.getElementById('wordCount').textContent = words + ' palavras';
        }

        // Inicializar contador
        updateWordCount();
    </script>
@endsection