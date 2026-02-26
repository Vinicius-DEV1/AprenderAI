import { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { Chart, registerables } from 'chart.js';
import { useDashboard } from '../hooks/useDashboard';
import { useAuthStore } from '../stores/authStore';

Chart.register(...registerables);

export default function Dashboard() {
    const { data, isLoading } = useDashboard();
    const { user } = useAuthStore();
    const progressChartRef = useRef<HTMLCanvasElement>(null);
    const subjectChartRef = useRef<HTMLCanvasElement>(null);
    const progressChartInstance = useRef<Chart | null>(null);
    const subjectChartInstance = useRef<Chart | null>(null);

    useEffect(() => {
        if (isLoading || !data) return;

        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.06)';
        const tickColor = isDark ? 'rgba(255,255,255,.45)' : 'rgba(15,23,42,.55)';
        const legendColor = isDark ? 'rgba(255,255,255,.6)' : 'rgba(15,23,42,.68)';
        const commonFont = {
            family: 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial',
            size: 12,
            weight: 600 as any
        };

        // --- Progress Chart ---
        if (progressChartRef.current) {
            if (progressChartInstance.current) {
                progressChartInstance.current.destroy();
            }

            const recent = data.recent_simulations || [];
            const reversedRecent = [...recent].reverse();
            const labels = reversedRecent.map(x => x.formatted_date || x.created_at);
            const lineData = reversedRecent.map(x => Number(x.calculated_score));

            if (labels.length > 0) {
                progressChartInstance.current = new Chart(progressChartRef.current, {
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
                                    label: (ctx: any) => `Acertos: ${Number(ctx.raw).toFixed(1)}%`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: gridColor },
                                ticks: { color: tickColor, font: commonFont }
                            },
                            y: {
                                beginAtZero: true,
                                suggestedMax: 100,
                                grid: { color: gridColor },
                                ticks: { color: tickColor, font: commonFont, callback: (v: any) => v + '%' }
                            }
                        }
                    }
                });
            } else {
                const ctx = progressChartRef.current.getContext('2d');
                if (ctx) {
                    ctx.font = "14px Inter";
                    ctx.fillStyle = isDark ? "#94a3b8" : "#64748b";
                    ctx.textAlign = "center";
                    ctx.fillText("Ainda não há dados suficientes.", ctx.canvas.width / 2, ctx.canvas.height / 2);
                }
            }
        }

        // --- Subject Chart ---
        if (subjectChartRef.current) {
            if (subjectChartInstance.current) {
                subjectChartInstance.current.destroy();
            }

            // Format subject performance (mocked/default to empty if not provided by backend)
            const subjectPerf = data.subjectPerformance || [];
            const subjectLabels = subjectPerf.map((x: any) => x.name);
            const subjectData = subjectPerf.map((x: any) => Number(x.percentage));
            const backgroundColors = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#4f46e5'];
            const subjectColors = subjectLabels.map((_: any, i: number) => backgroundColors[i % backgroundColors.length]);

            if (subjectLabels.length > 0) {
                subjectChartInstance.current = new Chart(subjectChartRef.current, {
                    type: 'doughnut',
                    data: {
                        labels: subjectLabels,
                        datasets: [{
                            data: subjectData,
                            backgroundColor: subjectColors,
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
                                    color: legendColor,
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
                                    label: (ctx: any) => `${ctx.label}: ${Number(ctx.raw).toFixed(1)}%`
                                }
                            }
                        }
                    }
                });
            } else {
                const ctx = subjectChartRef.current.getContext('2d');
                if (ctx) {
                    ctx.font = "14px Inter";
                    ctx.fillStyle = isDark ? "#94a3b8" : "#64748b";
                    ctx.textAlign = "center";
                    ctx.fillText("Sem dados de matérias.", ctx.canvas.width / 2, ctx.canvas.height / 2);
                }
            }
        }

        return () => {
            progressChartInstance.current?.destroy();
            subjectChartInstance.current?.destroy();
        };
    }, [data, isLoading]);

    if (isLoading) {
        return (
            <div className="flex justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    // Safety guard: if data is missing, we still show a partial UI or a friendly error
    // but with Backend robustness, this should only happen on total network failure.
    if (!data || !user) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-slate-500">
                <svg className="w-12 h-12 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p className="font-bold">Não foi possível carregar os dados do painel.</p>
                <button onClick={() => window.location.reload()} className="mt-4 text-blue-600 hover:underline">Tentar novamente</button>
            </div>
        );
    }

    const { stats, simulationLimit, recent_simulations } = data;
    const userPlan = user.plan || { name: 'Grátis' };
    const isAdmin = user?.role === 'admin';

    return (
        <>
            <style>{`
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

        :root.dark .wrap::before {
            background:
                radial-gradient(1000px 480px at 18% -5%, rgba(37, 99, 235, .12), rgba(37, 99, 235, 0) 60%),
                radial-gradient(900px 480px at 86% 5%, rgba(124, 58, 237, .10), rgba(124, 58, 237, 0) 60%),
                linear-gradient(180deg, var(--bg0) 0%, #050d1d 45%, var(--bg1) 100%);
        }

        :root.dark .wrap::after {
            background:
                radial-gradient(circle at 26% 32%, rgba(37, 99, 235, .06), transparent 58%),
                radial-gradient(circle at 72% 28%, rgba(124, 58, 237, .05), transparent 62%);
            opacity: .4;
        }

        :root.dark .hero {
            background: linear-gradient(135deg, rgba(30, 41, 59, .9), rgba(15, 23, 42, .95));
            border-color: rgba(255, 255, 255, 0.06);
        }

        :root.dark .stat {
            background: linear-gradient(135deg, rgba(30, 41, 59, .85), rgba(15, 23, 42, .95));
            border-color: rgba(255, 255, 255, 0.06);
        }

        :root.dark .card {
            background: linear-gradient(135deg, rgba(30, 41, 59, .85), rgba(15, 23, 42, .95));
            border-color: rgba(255, 255, 255, 0.06);
        }

        :root.dark .item {
            border-color: rgba(255, 255, 255, 0.06);
            background: linear-gradient(180deg, rgba(30, 41, 59, .5), rgba(15, 23, 42, .8));
        }

        :root.dark .item:hover {
            border-color: rgba(99, 102, 241, .3);
            box-shadow: 0 10px 22px rgba(0, 0, 0, .3);
        }

        :root.dark .chip {
            background: rgba(99, 102, 241, .15);
            border-color: rgba(99, 102, 241, .3);
            color: #a5b4fc;
        }

        :root.dark .badge {
            background: rgba(99, 102, 241, .15);
            border-color: rgba(99, 102, 241, .3);
            color: #a5b4fc;
        }

        :root.dark .pending {
            background: rgba(245, 158, 11, .15);
            border-color: rgba(245, 158, 11, .25);
            color: #fbbf24;
        }

        :root.dark .stat:hover {
            border-color: rgba(99, 102, 241, .3);
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

        .admin-cta {
            margin: 6px 0 12px;
        }

        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 800;
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
            font-weight: 700;
            color: var(--text);
            letter-spacing: .2px;
        }

        .hero p {
            margin: 6px 0 0;
            font-size: 13px;
            font-weight: 500;
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
            font-weight: 700;
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

        .stat:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow2);
            border-color: rgba(37, 99, 235, .16);
        }

        .stat .k {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: .25px;
            text-transform: uppercase;
        }

        .stat .v {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
        }

        .stat .l {
            margin: 6px 0 0;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted2);
        }

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
            font-weight: 700;
            color: var(--text);
            letter-spacing: .2px;
        }

        .sub {
            margin: 0;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .25px;
        }

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
            font-weight: 700;
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
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info p {
            margin: 0;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
        }

        .score {
            font-size: 18px;
            font-weight: 800;
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
            font-weight: 700;
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
            font-weight: 800;
            font-size: 14px;
            background: linear-gradient(135deg, var(--blue) 0%, var(--indigo) 45%, var(--violet) 100%);
            box-shadow: 0 14px 22px rgba(37, 99, 235, .18);
            transition: transform .18s var(--ease), box-shadow .18s var(--ease);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(37, 99, 235, .22);
        }

        @media (max-width: 980px) {
            .charts {
                grid-template-columns: 1fr;
            }
        }
      `}</style>

            <div className="wrap">
                {isAdmin && (
                    <div className="admin-cta">
                        <Link to="/admin/dashboard" className="admin-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Acessar Painel Admin
                        </Link>
                    </div>
                )}

                <div className="hero">
                    <div>
                        <h1>Visão clara do seu progresso</h1>
                        <p>Gráficos rápidos, métricas essenciais e histórico recente.</p>
                    </div>
                    <div className="chip">Online</div>
                </div>

                <div className="stats">
                    <div className="stat">
                        <div className="k">Provas realizadas</div>
                        <div className="v">{stats.total_simulations}</div>
                        <div className="l">no total</div>
                    </div>
                    <div className="stat">
                        <div className="k">Média em Matemática</div>
                        <div className="v">{stats.average_math_score.toLocaleString('pt-BR', { minimumFractionDigits: 1 })}%</div>
                        <div className="l">de acertos</div>
                    </div>
                    <div className="stat">
                        <div className="k">Média em Português</div>
                        <div className="v">{stats.average_portuguese_score.toLocaleString('pt-BR', { minimumFractionDigits: 1 })}%</div>
                        <div className="l">de acertos</div>
                    </div>
                    <div className="stat">
                        <div className="k">Redações enviadas</div>
                        <div className="v">{stats.total_essays}</div>
                        <div className="l">no total</div>
                    </div>
                </div>

                <div className="charts">
                    <div className="card">
                        <div className="card-head">
                            <div>
                                <h3>Progresso nas Provas</h3>
                                <p className="sub">Evolução (últimas provas)</p>
                            </div>
                        </div>
                        <div className="chart-box">
                            <canvas ref={progressChartRef}></canvas>
                        </div>
                    </div>

                    <div className="card">
                        <div className="card-head">
                            <div>
                                <h3>Desempenho por Matéria</h3>
                                <p className="sub">Distribuição de acertos</p>
                            </div>
                        </div>
                        <div className="chart-box">
                            <canvas ref={subjectChartRef}></canvas>
                        </div>
                    </div>
                </div>

                <div className="card">
                    <div className="card-head">
                        <h3>Seu Plano <span className="badge">{userPlan.name}</span></h3>
                    </div>

                    <p style={{ margin: 0, fontSize: '13px', fontWeight: 500, color: 'var(--muted)', lineHeight: 1.55 }}>
                        {simulationLimit.can_create ? (
                            <>
                                Você pode criar mais <span style={{ fontWeight: 800 }}>{simulationLimit.remaining}</span>{' '}
                                {simulationLimit.remaining === 1 ? 'prova' : 'provas'} este mês.
                            </>
                        ) : (
                            simulationLimit.message
                        )}
                    </p>

                    {!simulationLimit?.can_create && (
                        <Link to="/plans" className="btn" style={{ marginTop: '12px' }}>Fazer Upgrade</Link>
                    )}
                </div>

                <div className="card">
                    <div className="card-head">
                        <h3>Provas Recentes</h3>
                    </div>

                    {recent_simulations && recent_simulations.length > 0 ? (
                        <ul className="list">
                            {recent_simulations.map((simulation: any) => (
                                <li key={simulation.id} className="item">
                                    <div className="info">
                                        <h4>{String(simulation.type).charAt(0).toUpperCase() + String(simulation.type).slice(1)} - {simulation.questions_count || 'N/A'} questões</h4>
                                        <p>{simulation.formatted_date || simulation.created_at}</p>
                                    </div>

                                    {['finished', 'corrected'].includes(simulation.status) ? (
                                        <div className="score">{Number(simulation.calculated_score || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1 })}%</div>
                                    ) : (
                                        <span className="pending">Pendente</span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div style={{ textAlign: 'center', padding: '18px 10px', color: 'var(--muted2)', fontWeight: 600 }}>
                            Você ainda não realizou nenhuma prova.
                            <div>
                                <Link to="/simulations/create" className="btn" style={{ marginTop: '12px' }}>Criar Primeira Prova</Link>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
