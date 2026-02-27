<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Bem-vindo(a) ao AprenderAI!</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #f4f4f5;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #3b82f6;
        }

        .content {
            color: #334155;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #94a3b8;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Bem-vindo(a) ao AprenderAI!</h1>
        </div>
        <div class="content">
            <p>Olá, <strong>{{ $user->name }}</strong>!</p>
            <p>Seu e-mail foi confirmado com sucesso e sua conta está pronta para uso! Estamos muito felizes em ter você
                conosco.</p>
            <p>O AprenderAI é a sua plataforma completa para estudos, com simulados inteligentes, redações corrigidas
                por IA e muito mais para impulsionar a sua aprovação.</p>

            <p style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Acessar Meu Painel</a>
            </p>

            <p>Se você tiver alguma dúvida ou precisar de ajuda, nossa equipe está sempre à disposição.</p>
            <p>Bons estudos!</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} AprenderAI. Todos os direitos reservados.</p>
        </div>
    </div>
</body>

</html>