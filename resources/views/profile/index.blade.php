@extends('layouts.app')

@section('page-title', 'Meu Perfil')

@section('content')
    <style>
        :root {
            --text: #0b1220;
            --muted: #5b6b82;
            --muted2: #8a9ab2;
            --bg0: #f7f9fe;
            --bg1: #eef2ff;
            --border: rgba(15, 23, 42, .08);
            --ring: 0 0 0 4px rgba(37, 99, 235, .12);
            --blue: #2563eb;
            --indigo: #4f46e5;
            --violet: #7c3aed;
            --shadow: 0 1px 2px rgba(15, 23, 42, .08), 0 10px 22px rgba(15, 23, 42, .06);
            --shadow2: 0 18px 40px rgba(15, 23, 42, .12), 0 6px 16px rgba(15, 23, 42, .06);
            --r: 16px;
            --r2: 18px;
            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        :root.dark {
            --text: #e2e8f0;
            --muted: #94a3b8;
            --muted2: #64748b;
            --bg0: #020617;
            --bg1: #0f172a;
            --border: rgba(255, 255, 255, 0.08);
            --ring: 0 0 0 4px rgba(99, 102, 241, 0.2);
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 10px 22px rgba(0, 0, 0, 0.2);
            --shadow2: 0 18px 40px rgba(0, 0, 0, 0.3), 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .wrap {
            max-width: 1120px;
            margin: 0 auto;
            position: relative;
        }

        .wrap::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(1000px 480px at 18% -5%, rgba(37, 99, 235, .18), rgba(37, 99, 235, 0) 60%),
                radial-gradient(900px 480px at 86% 5%, rgba(124, 58, 237, .16), rgba(124, 58, 237, 0) 60%),
                linear-gradient(180deg, var(--bg0) 0%, #f4f7ff 45%, var(--bg1) 100%);
        }

        :root.dark .wrap::before {
            background:
                radial-gradient(1000px 480px at 18% -5%, rgba(37, 99, 235, .12), rgba(37, 99, 235, 0) 60%),
                radial-gradient(900px 480px at 86% 5%, rgba(124, 58, 237, .10), rgba(124, 58, 237, 0) 60%),
                linear-gradient(180deg, var(--bg0) 0%, #050d1d 45%, var(--bg1) 100%);
        }

        .card {
            border-radius: var(--r2);
            background: linear-gradient(135deg, rgba(255, 255, 255, .86), rgba(255, 255, 255, .96));
            border: 1px solid rgba(15, 23, 42, .06);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        :root.dark .card {
            background: linear-gradient(135deg, rgba(30, 41, 59, .85), rgba(15, 23, 42, .95));
            border-color: rgba(255, 255, 255, 0.06);
        }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 8px 0 24px;
            padding: 20px;
            border-radius: var(--r2);
            background: linear-gradient(135deg, rgba(255, 255, 255, .84), rgba(255, 255, 255, .95));
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }

        :root.dark .hero {
            background: linear-gradient(135deg, rgba(30, 41, 59, .9), rgba(15, 23, 42, .95));
            border-color: rgba(255, 255, 255, 0.06);
        }

        .hero h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        .input-group {
            margin-bottom: 20px;
        }

        .label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.5);
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s var(--ease);
        }

        :root.dark .input {
            background: rgba(15, 23, 42, 0.5);
        }

        .input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: var(--ring);
            background: rgba(255, 255, 255, 0.8);
        }

        :root.dark .input:focus {
            background: rgba(30, 41, 59, 0.8);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 14px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            background: linear-gradient(135deg, var(--blue) 0%, var(--indigo) 45%, var(--violet) 100%);
            box-shadow: 0 10px 20px rgba(37, 99, 235, .15);
            transition: all 0.2s var(--ease);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(37, 99, 235, .25);
            filter: brightness(1.1);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--blue);
            color: var(--blue);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--blue);
            color: #fff;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, .08);
            border: 1px solid rgba(37, 99, 235, .18);
            color: #1e40af;
            font-size: 12px;
            font-weight: 700;
        }

        :root.dark .badge {
            background: rgba(99, 102, 241, .15);
            border-color: rgba(99, 102, 241, .3);
            color: #a5b4fc;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .info-item h4 {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted2);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-item p {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }
    </style>

    <div class="wrap">
        <div class="hero">
            <div>
                <h1>Configurações de Perfil</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Gerencie suas informações e preferências de conta.</p>
            </div>
            <div class="badge">Ativo</div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Informações Pessoais (Leitura) -->
                <div class="card">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Informações Pessoais
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Nome Completo</h4>
                            <p>{{ $user->name }}</p>
                        </div>
                        <div class="info-item">
                            <h4>E-mail</h4>
                            <p>{{ $user->email }}</p>
                        </div>
                        <div class="info-item">
                            <h4>Telefone</h4>
                            <p>{{ $user->phone ?? 'Não informado' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Edição de Perfil -->
                <div class="card">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar Informações
                    </h3>
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="label">E-mail</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required>
                                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="input-group">
                                <label class="label">Telefone</label>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input" placeholder="(00) 00000-0000">
                                @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn">Salvar Alterações</button>
                        </div>
                    </form>
                </div>

                <!-- Segurança (Alterar Senha) -->
                <div class="card">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Seguranças e Senha
                    </h3>
                    <form action="{{ route('profile.password.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div class="input-group">
                                <label class="label">Senha Atual</label>
                                <input type="password" name="current_password" class="input" required>
                                @error('current_password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="input-group">
                                    <label class="label">Nova Senha</label>
                                    <input type="password" name="password" class="input" required>
                                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="input-group">
                                    <label class="label">Confirmar Nova Senha</label>
                                    <input type="password" name="password_confirmation" class="input" required>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 10px 20px rgba(239, 68, 68, .15);">
                                Atualizar Senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Status do Plano -->
                <div class="card" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%); border-color: rgba(99, 102, 241, 0.2);">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">Seu Plano</h3>
                    <div class="mb-6">
                        <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                            {{ $user->plan->name ?? 'Grátis' }}
                        </span>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                             Status: <span class="text-green-500 font-semibold">Ativo</span>
                        </p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-slate-400">Simulados restantes:</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $simulationLimit['remaining'] ?? '0' }}</span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5">
                            @php
                                $used = $simulationLimit['used'] ?? 0;
                                $limit = $simulationLimit['limit'] ?? 1;
                                $pct = min(100, ($used / ($limit ?: 1)) * 100);
                            @endphp
                            <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ 100 - $pct }}%"></div>
                        </div>
                    </div>

                    <a href="{{ route('plans.index') }}" class="btn w-full">
                        Fazer Upgrade
                    </a>
                </div>

                <!-- Dica -->
                <div class="card bg-slate-50 dark:bg-slate-800/50 border-dashed">
                    <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1a1 1 0 112 0v1a1 1 0 11-2 0zM13.536 15.657a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM16 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z" />
                        </svg>
                        Dica de Segurança
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Use senhas fortes com uma mistura de letras, números e símbolos para manter sua conta segura.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
