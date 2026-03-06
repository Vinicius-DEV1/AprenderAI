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
            background-color: #e53e3e;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }

        .warning-box {
            padding: 15px;
            background-color: #fff5f5;
            border-left: 4px solid #f56565;
            color: #c53030;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>AprenderAI</h1>
    </div>
    <div class="content">
        <h2>Olá, {{ $user->name }}.</h2>

        <div class="warning-box">
            <strong>Atenção:</strong> Seu plano <strong>{{ $plan->name }}</strong> expira hoje.
        </div>

        <p>Não perca o acesso às correções automáticas, simulados inéditos e ao seu plano de estudos personalizado.</p>

        <p>Para continuar estudando sem interrupções, renove sua assinatura agora mesmo.</p>

        <center>
            <a href="{{ config('app.frontend_url', 'http://localhost:5174') }}/billing" class="button">Renovar
                Assinatura</a>
        </center>

        <p>Se você já realizou o pagamento, por favor desconsidere este e-mail.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} AprenderAI. Estamos aqui para ajudar sua aprovação.</p>
    </div>
</body>

</html>