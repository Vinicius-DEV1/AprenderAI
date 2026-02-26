import React from 'react';
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
                        <div className="qb-overview-grid">
                            <div className="qb-overview-card"><div className="val">{stats.overview.total}</div><div className="lbl">Respondidas</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#10b981' }}>{stats.overview.accuracy}%</div><div className="lbl">Taxa de Acerto</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#10b981' }}>{stats.overview.correct}</div><div className="lbl">Acertos</div></div>
                            <div className="qb-overview-card"><div className="val" style={{ color: '#ef4444' }}>{stats.overview.incorrect}</div><div className="lbl">Erros</div></div>
                        </div>

                        <div className="qb-chart-section">
                            <h3>Acerto por Matéria</h3>
                            <div className="h-44">
                                <Bar
                                    data={{
                                        labels: stats.bySubject.map(d => d.subject),
                                        datasets: [
                                            { label: 'Acertos', data: stats.bySubject.map(d => d.correct), backgroundColor: '#10b981', borderRadius: 5 },
                                            { label: 'Total', data: stats.bySubject.map(d => d.total), backgroundColor: '#e2e8f0', borderRadius: 5 }
                                        ]
                                    }}
                                    options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }}
                                />
                            </div>
                        </div>

                        <div className="qb-chart-section">
                            <h3>Evolução (30 dias)</h3>
                            <div className="h-40">
                                <Line
                                    data={{
                                        labels: stats.temporal.map(d => d.date),
                                        datasets: [
                                            { label: 'Resolvidas', data: stats.temporal.map(d => d.total), borderColor: '#6366f1', fill: true, tension: 0.3, pointRadius: 3 },
                                            { label: 'Acertos', data: stats.temporal.map(d => d.correct), borderColor: '#10b981', fill: true, tension: 0.3, pointRadius: 3 }
                                        ]
                                    }}
                                    options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }}
                                />
                            </div>
                        </div>

                        <div className="qb-chart-section">
                            <h3>Heatmap de Dificuldade</h3>
                            <div className="h-40 flex justify-center">
                                <Doughnut
                                    data={{
                                        labels: stats.byDifficulty.map(d => d.difficulty === 'easy' ? 'Fácil' : d.difficulty === 'medium' ? 'Média' : 'Difícil'),
                                        datasets: [{
                                            data: stats.byDifficulty.map(d => d.accuracy),
                                            backgroundColor: stats.byDifficulty.map(d => d.difficulty === 'easy' ? '#10b981' : d.difficulty === 'medium' ? '#f59e0b' : '#ef4444')
                                        }]
                                    }}
                                    options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }}
                                />
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
