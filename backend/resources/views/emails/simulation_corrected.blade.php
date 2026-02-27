<!DOCTYPE html>
<html>

<head>
    <title>Sua correção chegou!</title>
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

        .btn {
            background-color: #3b82f6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Olá, {{ $simulation->user->name }}!</h2>
        <p>Seu simulado de <strong>{{ ucfirst($simulation->type) }}</strong> foi corrigido.</p>

        <p>Acesse a plataforma para ver sua nota detalhada e os comentários da Inteligência Artificial sobre seu
            desempenho.</p>

        <a href="{{ route('simulations.result', $simulation->id) }}" class="btn">Ver Resultado Completo</a>

        <p><small>Equipe {{ config('app.name', 'AprenderAI') }}</small></p>
    </div>
</body>

</html>
