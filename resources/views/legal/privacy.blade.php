<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de Privacidade - {{ $siteName }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900 bg-slate-50">

    <main class="min-h-screen py-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 sm:p-10">

                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">
                    Política de Privacidade – {{ $siteName }}
                </h1>

                <p class="text-sm text-slate-500 mb-8">
                    Última atualização: {{ date('d/m/Y') }}
                </p>

                <div class="space-y-8 text-slate-700 leading-relaxed text-sm sm:text-base">

                    <section>
                        <h2 class="font-bold text-slate-900 mb-2">1. Introdução</h2>
                        <p>
                            O {{ $siteName }} valoriza sua privacidade. Esta política explica como coletamos,
                            utilizamos e protegemos suas informações ao utilizar nossa plataforma.
                        </p>
                    </section>

                    <section>
                        <h2 class="font-bold text-slate-900 mb-2">2. Dados Coletados</h2>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Nome, e-mail e dados de cadastro.</li>
                            <li>Respostas de simulados e redações enviadas.</li>
                            <li>Informações técnicas como IP e tipo de dispositivo.</li>
                            <li>Dados de pagamento processados por parceiros seguros.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="font-bold text-slate-900 mb-2">3. Uso das Informações</h2>
                        <p>
                            Utilizamos seus dados para fornecer correções por IA, gerar planos de estudo,
                            melhorar a experiência da plataforma e processar assinaturas.
                        </p>
                    </section>

                    <section>
                        <h2 class="font-bold text-slate-900 mb-2">4. Segurança</h2>
                        <p>
                            Implementamos medidas técnicas e organizacionais para proteger suas informações
                            contra acessos não autorizados e uso indevido.
                        </p>
                    </section>

                    <section>
                        <h2 class="font-bold text-slate-900 mb-2">5. Seus Direitos</h2>
                        <p>
                            Você pode solicitar acesso, correção ou exclusão dos seus dados a qualquer momento.
                        </p>
                    </section>

                    <section class="pt-6 border-t border-slate-200">
                        <p class="text-sm">
                            Dúvidas? Entre em contato:
                        </p>
                        <a href="mailto:stackupsoftware@gmail.com" class="text-blue-600 font-medium hover:underline">
                            stackupsoftware@gmail.com
                        </a>
                    </section>

                </div>
            </div>
        </div>
    </main>

</body>

</html>