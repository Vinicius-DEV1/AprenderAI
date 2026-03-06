<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #edf2f7;
        }

        .content {
            padding: 30px 0;
        }

        .footer {
            text-align: center;
            color: #718096;
            font-size: 0.875rem;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3182ce;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }

        .plan-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #ebf8ff;
            color: #2b6cb0;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
        }

        .benefits-list li {
            margin-bottom: 8px;
            padding-left: 24px;
            position: relative;
        }

        .benefits-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #38a169;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>AprenderAI</h1>
    </div>
    <div class="content">
        <h2>Olá, {{ $user->name }}! 🎉</h2>
        <p>É com muita alegria que confirmamos a sua assinatura premium.</p>

        <p>Agora você tem acesso total ao plano: <span class="plan-badge">{{ $planName }}</span></p>

        @if(!empty($benefits))
            <h3>O que você pode fazer agora:</h3>
            <ul class="benefits-list">
                @foreach($benefits as $benefit)
                    <li>{{ $benefit }}</li>
                @endforeach
            </ul>
        @endif

        <p>Estamos ansiosos para ver sua evolução nos estudos!</p>

        <center>
            <a href="{{ config('app.frontend_url', 'http://localhost:5174') }}/dashboard" class="button">Acessar meu
                Dashboard</a>
        </center>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} AprenderAI. Todos os direitos reservados.</p>
    </div>
</body>

</html>