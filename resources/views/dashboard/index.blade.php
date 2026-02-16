@extends('layouts.app')

@section('page-title', 'Dashboard')

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

        .wrap::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background:
                radial-gradient(circle at 26% 32%, rgba(255, 255, 255, .58), rgba(255, 255, 255, 0) 58%),
                radial-gradient(circle at 72% 28%, rgba(255, 255, 255, .46), rgba(255, 255, 255, 0) 62%);
            opacity: .55;
        }

        /* Admin CTA (de volta) */
        .admin-cta {
            margin: 6px 0 12px;
        }

        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 950;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, var(--indigo) 0%, var(--violet) 60%, #9333ea 100%);
            box-shadow: 0 16px 28px rgba(79, 70, 229, .18);
            transition: transform .18s var(--ease), box-shadow .18s var(--ease), filter .18s var(--ease);
        }

        .admin-link:hover {
            transform: translateY(-2px);
            filter: brightness(.99);
            box-shadow: 0 18px 34px rgba(79, 70, 229, .22);
        }

        .admin-link:focus {
            outline: none;
            box-shadow: 0 18px 34px rgba(79, 70, 229, .22), var(--ring);
        }

        .admin-link svg {
            width: 20px;
            height: 20px;
            opacity: .95;
        }

        /* Hero (compacto) */
        .hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin: 8px 0 14px;
            padding: 16px 16px;
            border-radius: var(--r2);
            background: linear-gradient(135deg, rgba(255, 255, 255, .84), rgba(255, 255, 255, .95));
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }

        .hero h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 950;
            color: var(--text);
            letter-spacing: .2px;
        }

        .hero p {
            margin: 6px 0 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            line-height: 1.45;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, .08);
            border: 1px solid rgba(37, 99, 235, .16);
            color: #1e40af;
            font-size: 12px;
            font-weight: 950;
            white-space: nowrap;
        }

        .chip::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: linear-gradient(180deg, var(--blue), var(--violet));
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        /* Stats (compacto) */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .stat {
            position: relative;
            overflow: hidden;
            padding: 16px 16px;
            border-radius: var(--r2);
            background: linear-gradient(135deg, rgba(255, 255, 255, .86), rgba(255, 255, 255, .95));
            border: 1px solid rgba(15, 23, 42, .06);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            transition: transform .18s var(--ease), box-shadow .18s var(--ease), border-color .18s var(--ease);
        }

        .stat::before {
            content: "";
            position: absolute;
            inset: -70px -90px auto auto;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle,
                    rgba(37, 99, 235, .10),
                    rgba(124, 58, 237, .07) 42%,
                    rgba(37, 99, 235, 0) 72%);
            pointer-events: none;
        }

        .stat:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow2);
            border-color: rgba(37, 99, 235, .16);
        }

        .stat .k {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 900;
            color: var(--muted);
            letter-spacing: .25px;
            text-transform: uppercase;
        }

        .stat .v {
            margin: 0;
            font-size: 28px;
            font-weight: 950;
            color: var(--text);
            line-height: 1.1;
        }

        .stat .l {
            margin: 6px 0 0;
            font-size: 12px;
            font-weight: 800;
            color: var(--muted2);
        }

        /* Cards */
        .card {
            border-radius: var(--r2);
            background: linear-gradient(135deg, rgba(255, 255, 255, .86), rgba(255, 255, 255, .96));
            border: 1px solid rgba(15, 23, 42, .06);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            padding: 16px;
            margin-bottom: 14px;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: -110px -130px auto auto;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle,
                    rgba(37, 99, 235, .10),
                    rgba(124, 58, 237, .07) 45%,
                    rgba(37, 99, 235, 0) 74%);
            pointer-events: none;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .card h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 950;
            color: var(--text);
            letter-spacing: .2px;
        }

        .sub {
            margin: 0;
            font-size: 12px;
            font-weight: 850;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .25px;
        }

        /* Charts: tamanho FIXO */
        .charts {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .chart-box {
            height: 220px;
            width: 100%;
            position: relative;
        }

        .chart-box canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }

        /* Plan badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(37, 99, 235, .08);
            border: 1px solid rgba(37, 99, 235, .18);
            color: #1e40af;
            font-size: 12px;
            font-weight: 950;
            white-space: nowrap;
        }

        .badge::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: linear-gradient(180deg, var(--blue), var(--violet));
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        /* List */
        .list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 12px;
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, .06);
            background: linear-gradient(180deg, rgba(248, 250, 252, .55), rgba(255, 255, 255, 1));
            transition: transform .18s var(--ease), box-shadow .18s var(--ease), border-color .18s var(--ease);
            margin-bottom: 10px;
        }

        .item:hover {
            transform: translateY(-1px);
            border-color: rgba(37, 99, 235, .16);
            box-shadow: 0 10px 22px rgba(15, 23, 42, .07);
        }

        .info {
            min-width: 0;
        }

        .info h4 {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 950;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info p {
            margin: 0;
            font-size: 12px;
            font-weight: 800;
            color: var(--muted);
        }

        .score {
            font-size: 18px;
            font-weight: 950;
            color: var(--blue);
            white-space: nowrap;
        }

        .pending {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .22);
            color: #9a3412;
            font-weight: 950;
            font-size: 12px;
            white-space: nowrap;
        }

        .pending::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: linear-gradient(180deg, #f59e0b, #f97316);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            text-decoration: none;
            font-weight: 950;
            font-size: 14px;
            background: linear-gradient(135deg, var(--blue) 0%, var(--indigo) 45%, var(--violet) 100%);
            box-shadow: 0 14px 22px rgba(37, 99, 235, .18);
            transition: transform .18s var(--ease), box-shadow .18s var(--ease);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(37, 99, 235, .22);
        }

        .btn:focus {
            outline: none;
            box-shadow: 0 18px 30px rgba(37, 99, 235, .22), var(--ring);
        }

        @media (max-width: 980px) {
            .charts {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <div class="wrap">
        @if(auth()->user()->isAdmin())
            <div class="admin-cta">
                <a href="{{ route('admin.dashboard') }}" class="admin-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Acessar Painel Admin
                </a>
            </div>
        @endif

        <div class="hero">
            <div>
                <h1>Dashboard premium — visão clara do seu progresso</h1>
                <p>Gráficos rápidos, métricas essenciais e histórico recente. Visual que convence.</p>
            </div>
            <div class="chip">Online</div>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="k">Provas realizadas</div>
                <div class="v">{{ $stats->total_simulations }}</div>
                <div class="l">no total</div>
            </div>
            <div class="stat">
                <div class="k">Média em Matemática</div>
                <div class="v">{{ number_format($stats->average_math_score, 1) }}%</div>
                <div class="l">de acertos</div>
            </div>
            <div class="stat">
                <div class="k">Média em Português</div>
                <div class="v">{{ number_format($stats->average_portuguese_score, 1) }}%</div>
                <div class="l">de acertos</div>
            </div>
            <div class="stat">
                <div class="k">Redações enviadas</div>
                <div class="v">{{ $stats->total_essays }}</div>
                <div class="l">no total</div>
            </div>
        </div>

        <div class="charts">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h3>Progresso nas Provas</h3>
                        <p class="sub">Evolução (últimas provas)</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h3>Desempenho por Matéria</h3>
                        <p class="sub">Distribuição de acertos</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Seu Plano <span class="badge">{{ $user->plan->name }}</span></h3>
            </div>

            <p style="margin:0; font-size:13px; font-weight:800; color:var(--muted); line-height:1.55;">
                @if($simulationLimit['can_create'])
                    Você pode criar mais <strong>{{ $simulationLimit['remaining'] }}</strong>
                    {{ is_numeric($simulationLimit['remaining']) && $simulationLimit['remaining'] == 1 ? 'prova' : 'provas' }}
                    este mês.
                @else
                    {{ $simulationLimit['message'] }}
                @endif
            </p>

            @if(!$simulationLimit['can_create'])
                <a href="{{ route('plans.index') }}" class="btn" style="margin-top:12px;">Fazer Upgrade</a>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Provas Recentes</h3>
            </div>

            @if($recentSimulations->count() > 0)
                <ul class="list">
                    @foreach($recentSimulations as $simulation)
                        <li class="item">
                            <div class="info">
                                <h4>{{ ucfirst($simulation->type) }} - {{ $simulation->configuration['questions'] ?? 'N/A' }}
                                    questões</h4>
                                <p>{{ $simulation->created_at->format('d/m/Y H:i') }}</p>
                            </div>

                            @if(in_array($simulation->status, ['finished', 'corrected']))
                                <div class="score">{{ number_format((float) $simulation->score, 1) }}%</div>
                            @else
                                <span class="pending">Pendente</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div style="text-align:center; padding:18px 10px; color:var(--muted2); font-weight:900;">
                    Você ainda não realizou nenhuma prova.
                    <div>
                        <a href="{{ route('simulations.create') }}" class="btn" style="margin-top:12px;">Criar Primeira
                            Prova</a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        // ===== Data =====
        const recent = @json(
            $recentSimulations->map(fn($s) => [
                'label' => $s->created_at->format('d/m'),
                'score' => is_numeric($s->score) ? (float) $s->score : null,
            ])->values()
        );

        const labels = recent.map(x => x.label);
        const lineData = recent.map(x => (x.score === null ? null : Number(x.score)));

        const math = Number(@json((float) $stats->average_math_score));
        const pt = Number(@json((float) $stats->average_portuguese_score));

        const commonFont = {
            family: 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial',
            size: 12,
            weight: '800'
        };

        const safeDestroy = (id) => {
            const existing = Chart.getChart(id);
            if (existing) existing.destroy();
        };

        // ===== Line chart =====
        if (document.getElementById('progressChart') && labels.length > 0) {
            safeDestroy('progressChart');
            new Chart(document.getElementById('progressChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: lineData,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.14)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,.92)',
                            titleColor: '#fff',
                            bodyColor: '#e5e7eb',
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                label: (ctx) => `Score: ${Number(ctx.raw).toFixed(1)}%`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(15,23,42,.06)' },
                            ticks: { color: 'rgba(15,23,42,.55)', font: commonFont }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: 100,
                            grid: { color: 'rgba(15,23,42,.06)' },
                            ticks: { color: 'rgba(15,23,42,.55)', font: commonFont, callback: v => v + '%' }
                        }
                    }
                }
            });
        }

        // ===== Doughnut chart =====
        if (document.getElementById('subjectChart')) {
            safeDestroy('subjectChart');
            new Chart(document.getElementById('subjectChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Matemática', 'Português'],
                    datasets: [{
                        data: [isFinite(math) ? math : 0, isFinite(pt) ? pt : 0],
                        backgroundColor: ['#2563eb', '#7c3aed'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'rgba(15,23,42,.68)',
                                font: commonFont,
                                boxWidth: 10,
                                boxHeight: 10
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,.92)',
                            titleColor: '#fff',
                            bodyColor: '#e5e7eb',
                            padding: 10,
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ${Number(ctx.raw).toFixed(1)}%`
                            }
                        }
                    }
                }
            });
        }
    </script>
@endsection