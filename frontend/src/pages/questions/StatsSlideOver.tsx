import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';
import { Bar, Line, Doughnut } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

interface Stats {
    overview: {
        total: number;
        accuracy: number;
        correct: number;
        incorrect: number;
    };
    bySubject: { subject: string; total: number; correct: number }[];
    temporal: { date: string; total: number; correct: number }[];
    byDifficulty: { difficulty: string; accuracy: number }[];
}

export default function StatsSlideOver({ stats, open, onClose }: { stats: Stats | null, open: boolean, onClose: () => void }) {
    if (!open) return null;

    // Safe defaults for null safety
    const overview = stats?.overview || { total: 0, accuracy: 0, correct: 0, incorrect: 0 };
    const bySubject = stats?.bySubject || [];
    const temporal = stats?.temporal || [];
    const byDifficulty = stats?.byDifficulty || [];

    return (
        <div x-cloak="true">
            <div className={`qb-slideover-backdrop animate-fade-in`} onClick={onClose}></div>
            <div className={`qb-slideover animate-slide-in-right`}>
                <div className="qb-slideover-header">
                    <h2>📊 Meu Desempenho</h2>
                    <button className="qb-slideover-close" onClick={onClose}>✕</button>
                </div>
                {stats ? (
                    <div className="qb-slideover-body">
                        {/* LINE 1: KPIs */}
                        <div className="qb-overview-grid">
                            <div className="qb-overview-card"><div className="val">{overview.total}</div><div className="lbl">Respondidas</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#10b981' }}>{overview.accuracy}%</div><div className="lbl">Taxa de Acerto</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#10b981' }}>{overview.correct}</div><div className="lbl">Acertos</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#ef4444' }}>{overview.incorrect}</div><div className="lbl">Erros</div></div>
                        </div>

                        {/* LINE 2: Line Chart (Wide) */}
                        <div className="qb-chart-section">
                            <h3>Evolução (30 dias)</h3>
                            {temporal.length > 0 ? (
                                <div className="h-[300px] w-full relative">
                                    <Line
                                        data={{
                                            labels: temporal.map(d => d.date),
                                            datasets: [
                                                { label: 'Resolvidas', data: temporal.map(d => d.total), borderColor: '#6366f1', backgroundColor: 'rgba(99, 102, 241, 0.1)', fill: true, tension: 0.3, pointRadius: 3 },
                                                { label: 'Acertos', data: temporal.map(d => d.correct), borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.3, pointRadius: 3 }
                                            ]
                                        }}
                                        options={{
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            interaction: { mode: 'index', intersect: false },
                                            plugins: { legend: { position: 'bottom' } },
                                            scales: { y: { beginAtZero: true } }
                                        }}
                                    />
                                </div>
                            ) : (
                                <div className="qb-empty-state">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                    </svg>
                                    <p>Ainda não há dados suficientes para este período.</p>
                                </div>
                            )}
                        </div>

                        {/* LINE 3: Secondary Charts */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
                            {/* Bar Chart */}
                            <div className="qb-chart-section mb-0">
                                <h3>Acerto por Matéria</h3>
                                {bySubject.length > 0 ? (
                                    <div className="h-[280px] w-full relative">
                                        <Bar
                                            data={{
                                                labels: bySubject.map(d => d.subject.length > 15 ? d.subject.substring(0, 15) + '...' : d.subject),
                                                datasets: [
                                                    { label: 'Acertos', data: bySubject.map(d => d.correct), backgroundColor: '#10b981', borderRadius: 4 },
                                                    { label: 'Total', data: bySubject.map(d => d.total), backgroundColor: '#e2e8f0', borderRadius: 4 }
                                                ]
                                            }}
                                            options={{
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: { legend: { position: 'bottom' } },
                                                scales: { y: { beginAtZero: true } }
                                            }}
                                        />
                                    </div>
                                ) : (
                                    <div className="qb-empty-state">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <p>Faça questões de diferentes matérias.</p>
                                    </div>
                                )}
                            </div>

                            {/* Doughnut Chart */}
                            <div className="qb-chart-section mb-0">
                                <h3>Taxa de Acerto por Dificuldade</h3>
                                {byDifficulty.length > 0 && byDifficulty.some(d => d.accuracy > 0) ? (
                                    <div className="h-[280px] w-full flex items-center justify-center relative">
                                        <Doughnut
                                            data={{
                                                labels: byDifficulty.map(d => d.difficulty === 'easy' ? 'Fácil' : d.difficulty === 'medium' ? 'Média' : 'Difícil'),
                                                datasets: [{
                                                    data: byDifficulty.map(d => d.accuracy),
                                                    backgroundColor: byDifficulty.map(d => d.difficulty === 'easy' ? '#10b981' : d.difficulty === 'medium' ? '#f59e0b' : '#ef4444')
                                                }]
                                            }}
                                            options={{
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: { legend: { position: 'bottom' } },
                                                cutout: '65%'
                                            }}
                                        />
                                    </div>
                                ) : (
                                    <div className="qb-empty-state">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                        </svg>
                                        <p>Suas taxas de acerto aparecerão aqui.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="flex-1 flex items-center justify-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                )}
            </div>
        </div>
    );
}
