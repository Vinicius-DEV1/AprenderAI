import { toast } from 'sonner';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Filler,
    ScriptableContext
} from 'chart.js';
import { Line } from 'react-chartjs-2';
import { useEssays } from '../../hooks/useEssays';
import { useAuthStore } from '../../stores/authStore';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Filler
);

function EvolutionCard({
    title,
    stats,
    series,
    color,
    maxScore
}: {
    title: string,
    stats: any,
    series: any[],
    color: string,
    maxScore: number
}) {
    // ── Defensive: series can be empty even when stats is not null ──────────
    if (!stats) return <EmptyChart title={title} />;

    const hasSeries = series && series.length >= 2;
    const variation = hasSeries ? (stats.last - stats.prev) : 0;
    const isPositive = variation > 0;
    const isNegative = variation < 0;

    const chartData = hasSeries ? {
        labels: series.map(d => d.date),
        datasets: [
            {
                fill: true,
                label: 'Nota',
                data: series.map(d => d.value),
                borderColor: color,
                backgroundColor: (context: ScriptableContext<'line'>) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 100);
                    gradient.addColorStop(0, color + '66');
                    gradient.addColorStop(1, color + '00');
                    return gradient;
                },
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
            },
        ],
    } : null;

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                mode: 'index' as const,
                intersect: false,
                backgroundColor: '#1e293b',
                titleColor: '#f8fafc',
                bodyColor: '#f8fafc',
                padding: 10,
                displayColors: false,
            },
        },
        scales: {
            x: { display: false },
            y: { display: false, min: 0, max: maxScore + (maxScore * 0.1) },
        },
    };

    return (
        <div className="bg-white dark:bg-slate-900 shadow-sm dark:shadow-none sm:rounded-2xl p-6 dark:border dark:border-slate-700 transition-all hover:shadow-md">
            <div className="flex justify-between items-start mb-4">
                <h3 className="font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest text-[11px] mb-1">
                    {title}
                </h3>
            </div>

            <div className="flex items-baseline gap-2 mb-6">
                <div className="text-3xl font-black text-slate-800 dark:text-white tracking-tight">
                    {stats.last}
                    <span className="text-sm text-slate-400 font-normal ml-1">/ {maxScore}</span>
                </div>
                {hasSeries && (
                    <div className={`flex items-center text-sm font-bold ${isPositive ? 'text-emerald-500' : isNegative ? 'text-rose-500' : 'text-slate-400'}`}>
                        {isPositive ? '↑' : isNegative ? '↓' : '—'} {variation > 0 ? `+${variation}` : variation}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-4 gap-2 mb-6">
                <div className="flex flex-col">
                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-bold">Última</span>
                    <span className="text-sm font-bold text-slate-700 dark:text-slate-200">{stats.last}</span>
                </div>
                <div className="flex flex-col">
                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-bold">Média</span>
                    <span className="text-sm font-bold text-slate-700 dark:text-slate-200">{stats.mean}</span>
                </div>
                <div className="flex flex-col">
                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-bold">Melhor</span>
                    <span className="text-sm font-bold text-slate-700 dark:text-slate-200">{stats.best}</span>
                </div>
                <div className="flex flex-col">
                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-bold">Total</span>
                    <span className="text-sm font-bold text-slate-700 dark:text-slate-200">{stats.total}</span>
                </div>
            </div>

            {hasSeries && chartData && (
                <div className="h-16 w-full opacity-80">
                    <Line data={chartData} options={chartOptions} />
                </div>
            )}
        </div>
    );
}

export default function EssayList() {
    const [page, setPage] = useState(1);
    const { data, isLoading } = useEssays(page);
    const { user } = useAuthStore();

    if (isLoading) {
        return (
            <div className="flex justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    const essays = data?.data || [];
    const meta = data?.meta || {};
    const chartData = meta.charts || {};
    const canCreate = meta?.essayLimit?.can_create ?? true;
    // Use new unified keys: total / used / remaining (matches wizard schema)
    const limit = meta?.essayLimit?.total ?? 0;
    const used = meta?.essayLimit?.used ?? 0;
    const planNameFromMeta = meta?.essayLimit?.plan_name ?? null;

    const handlePageChange = (newPage: number) => {
        if (newPage >= 1 && newPage <= (meta.last_page || 1)) {
            setPage(newPage);
        }
    };

    const getStatusInfo = (status: string) => {
        const map: Record<string, { label: string, classes: string }> = {
            'pending': { label: 'Pendente', classes: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300' },
            'in_progress': { label: 'Rascunho', classes: 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300' },
            'evaluating': { label: 'Avaliando', classes: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300' },
            'completed': { label: 'Corrigida', classes: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' },
            'error': { label: 'Erro', classes: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' },
        };
        return map[status] || { label: status, classes: 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-300' };
    };

    const planLabel = planNameFromMeta || user?.plan?.name;
    const isPlus = String(planLabel || '').toLowerCase().includes('plus');
    const isBasic = String(planLabel || '').toLowerCase().includes('básico') || String(planLabel || '').toLowerCase().includes('basico');

    // ── Compute evolution data from essays array (client-side fallback) ──────
    // This ensures the card shows even when backend chartData is stale/empty
    const buildLocalStats = (essayType: string) => {
        // Filter: has a score (means it was corrected) + matches type
        // Do NOT filter by status string — DB status can vary (corrigida/completed/evaluated/etc.)
        const done = essays
            .filter((e: any) =>
                e.score != null &&
                Number(e.score) >= 0 &&
                (e.type === essayType || (essayType === 'enem' && !e.type))
            )
            .slice() // don't mutate the original
            .sort((a: any, b: any) =>
                new Date(a.submitted_at || a.updated_at || a.created_at).getTime() -
                new Date(b.submitted_at || b.updated_at || b.created_at).getTime()
            );
        if (done.length === 0) return null;
        const scores = done.map((e: any) => Number(e.score));
        const last = scores[scores.length - 1];
        const prev = scores.length >= 2 ? scores[scores.length - 2] : last;
        const mean = Math.round(scores.reduce((a: number, b: number) => a + b, 0) / scores.length);
        const best = Math.max(...scores);
        const series = done.map((e: any) => ({
            date: new Date(e.submitted_at || e.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
            value: Number(e.score),
        }));
        return { stats: { last, prev, mean, best, total: done.length }, series };
    };

    // Prefer backend data (covers all pages), fall back to local array
    const enemEvolution = (chartData.hasEnem && chartData.enemStats)
        ? { stats: chartData.enemStats, series: chartData.enemSeries || [] }
        : buildLocalStats('enem');

    const concursoEvolution = (chartData.hasConcursos && chartData.concursoStats)
        ? { stats: chartData.concursoStats, series: chartData.concursosSeries || [] }
        : buildLocalStats('concurso');

    const hasBothCards = enemEvolution && concursoEvolution;

    return (
        <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <h2 className="text-xl font-bold mb-4">
                    Minhas redações
                </h2>

                {/* Evolution Cards — always show at least EmptyChart */}
                <div className={`mb-8 grid grid-cols-1 ${hasBothCards ? 'md:grid-cols-2' : ''} gap-6`}>
                    <EvolutionCard
                        title="📊 Evolução ENEM"
                        stats={enemEvolution?.stats ?? null}
                        series={enemEvolution?.series ?? []}
                        color="#3B82F6"
                        maxScore={1000}
                    />

                    {concursoEvolution && (
                        <EvolutionCard
                            title="📊 Evolução Concurso"
                            stats={concursoEvolution.stats}
                            series={concursoEvolution.series}
                            color="#10B981"
                            maxScore={100}
                        />
                    )}
                </div>

                {/* Limit Card */}
                <div className="mb-6 bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                    <div className="p-6 text-gray-900 dark:text-slate-200 flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <h3 className="text-lg font-medium">Limite Mensal</h3>
                            <p className="text-sm text-gray-500 dark:text-slate-400">
                                Você usou <span className="font-bold">{used}</span> de <span className="font-bold">{limit}</span> redações este mês {planLabel ? `(${planLabel})` : ''}.
                            </p>
                        </div>
                        {canCreate ? (
                            <Link
                                to="/redacoes/criar"
                                className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 transition ease-in-out duration-150"
                            >
                                Nova Redação
                            </Link>
                        ) : (
                            (isPlus || isBasic) ? (
                                <Link
                                    to="/recharge"
                                    className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 transition ease-in-out duration-150 mr-2"
                                >
                                    {isPlus ? 'Recarregar (+15) - R$ 20,00' : 'Recarregar (+2) - R$ 5,00'}
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => toast.info('Funcionalidade em manutenção')}
                                    className="inline-flex items-center px-4 py-2 bg-gray-400 dark:bg-slate-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150"
                                >
                                    Limite Atingido
                                </button>
                            )
                        )}
                    </div>
                    {!canCreate && limit === 0 && (
                        <div className="px-6 pb-6">
                            <p className="text-red-500 dark:text-red-400 text-sm">Faça upgrade para o plano Basic ou Plus para enviar redações!</p>
                            <Link to="/plans" className="text-blue-500 dark:text-blue-400 hover:underline text-sm">Ver Planos</Link>
                        </div>
                    )}
                </div>

                {/* List */}
                <div className="bg-white dark:bg-slate-900 overflow-hidden shadow-sm dark:shadow-none dark:border dark:border-slate-700 sm:rounded-lg">
                    <div className="p-6 text-gray-900 dark:text-slate-200">
                        {essays.length === 0 ? (
                            <div className="text-center py-10">
                                <p className="text-gray-500 dark:text-slate-400">Nenhuma redação encontrada.</p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                        <thead className="bg-gray-50 dark:bg-slate-800">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Data</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Tema</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Tipo</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Nota</th>
                                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                                            {essays.map((essay: any) => {
                                                const statusInfo = getStatusInfo(essay.status);
                                                return (
                                                    <tr key={essay.id} className="hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                            {essay.submitted_at ? new Date(essay.submitted_at).toLocaleDateString() : (essay.created_at ? new Date(essay.created_at).toLocaleDateString() : '-')}
                                                        </td>
                                                        <td className="px-6 py-4 text-sm text-gray-900 dark:text-slate-200">
                                                            {essay.theme ? (String(essay.theme).length > 60 ? String(essay.theme).substring(0, 60) + '...' : essay.theme) : 'Tema não disponível'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                                                {essay.type === 'enem' ? 'ENEM' : (essay.type === 'concurso' ? 'Concurso Público' : 'ENEM')}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusInfo.classes}`}>
                                                                {statusInfo.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-slate-200 font-bold">
                                                            {(essay.score > 0 ? essay.score : (essay.feedback_json?.overall_score ?? essay.overall_score ?? essay.score ?? '-'))}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            {(essay.status === 'in_progress' || essay.status === 'pending') ? (
                                                                <Link to={`/redacoes/${essay.id}/continuar`} className="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                                                    Continuar
                                                                </Link>
                                                            ) : (
                                                                <Link to={`/redacoes/correcao/${essay.id}`} className="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                                                    Abrir
                                                                </Link>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                {meta.last_page > 1 && (
                                    <div className="mt-4 flex justify-between items-center text-sm">
                                        <button
                                            onClick={() => handlePageChange(page - 1)}
                                            disabled={page === 1}
                                            className="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md disabled:opacity-50 text-gray-700 dark:text-gray-300"
                                        >
                                            Anterior
                                        </button>
                                        <span className="text-gray-600 dark:text-gray-400">Página {page} de {meta.last_page}</span>
                                        <button
                                            onClick={() => handlePageChange(page + 1)}
                                            disabled={page === meta.last_page}
                                            className="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md disabled:opacity-50 text-gray-700 dark:text-gray-300"
                                        >
                                            Próxima
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function EmptyChart({ title }: { title: string }) {
    return (
        <div className="bg-white dark:bg-slate-900 shadow-sm dark:shadow-none sm:rounded-2xl p-6 dark:border dark:border-slate-700 h-full min-h-[220px] flex flex-col">
            <h3 className="font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest text-[11px] mb-4">
                {title}
            </h3>
            <div className="flex-grow flex flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-100 dark:border-slate-700 rounded-xl p-6">
                <svg className="w-8 h-8 mb-2 text-gray-200 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p className="text-xs font-medium">Sem dados suficientes ainda.</p>
                <p className="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Conclua redações para ver sua evolução.</p>
            </div>
        </div>
    );
}

