<!DOCTYPE html>
<html>

<head>
    <title>Redação Corrigida</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
        }

        .score {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
            margin: 20px 0;
        }

        .btn {
            background-color: #3b82f6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Olá, {{ $essay->user->name }}!</h2>
        <p>Sua redação com o tema <strong>"{{ $essay->title }}"</strong> foi corrigida.</p>

        @if($essay->score)
            <div class="score">Sua Nota: {{ $essay->score }}</div>
        @endif

        <p>Veja os comentários detalhados e sugestões de melhoria na plataforma.</p>

        <a href="{{ route('essays.show', $essay->id) }}" class="btn">Ver Correção Completa</a>

        <p><small>Equipe AprovaAI</small></p>
    </div>
</body>

</html>