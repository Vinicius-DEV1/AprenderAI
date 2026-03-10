import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { marked } from 'marked';
import { useConfigStore } from '../../stores/configStore';
import EssayReview from '../essays/EssayReview';
import '../../styles/question-bank.css';

import QuestionCard from '../../components/QuestionCard';

const getSimulationResult = async (id: string) => {
    const { data } = await api.get(`/api/v1/simulations/${id}`, { params: { include_answers: 1 } });
    return data.data;
};

export default function SimulationResult() {
    const { id } = useParams<{ id: string }>();
    const { } = useConfigStore();
    const [filter, setFilter] = useState<'all' | 'correct' | 'incorrect'>('all');

    const { data: simulation, isLoading } = useQuery({
        queryKey: ['simulationResult', id],
        queryFn: () => getSimulationResult(id!),
        enabled: !!id
    });

    useEffect(() => {
        marked.setOptions({ breaks: true, gfm: true });
    }, []);

    if (isLoading || !simulation) {
        return <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>;
    }

    const answers = simulation.answers || [];
    const totalQuestions = answers.length;
    const correctAnswers = answers.filter((a: any) => a.is_correct).length;
    const percentageScore = totalQuestions > 0 ? (correctAnswers / totalQuestions) * 100 : 0;

    const formatTime = (seconds: number) => {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const formatAvgTime = (seconds: number) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const filteredAnswers = answers.filter((ans: any) => {
        if (filter === 'correct') return ans.is_correct;
        if (filter === 'incorrect') return !ans.is_correct;
        return true;
    });

    return (
        <div className="simulation-page p-4 lg:p-8 max-w-[1200px] mx-auto">
            <style>{`
                .result-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 40px; color: white; text-align: center; margin-bottom: 32px; }
                .result-header h1 { font-size: 48px; font-weight: 700; margin-bottom: 8px; }
                .result-header p { font-size: 18px; opacity: 0.9; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
                .stat-box { background: white; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); border: 1px solid transparent; }
                .dark .stat-box { background: #1e293b; border-color: rgba(255,255,255,0.08); }
                .stat-box h3 { font-size: 14px; color: #64748b; font-weight: 500; margin-bottom: 12px; }
                .dark .stat-box h3 { color: #94a3b8; }
                .stat-box .value { font-size: 36px; font-weight: 700; color: #2563EB; }
                .answers-section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-bottom: 24px; }
                .dark .answers-section { background: #1e293b; border-color: rgba(255,255,255,0.08); }
                .answers-section h2 { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #1e293b; }
                .dark .answers-section h2 { color: #f1f5f9; }
                .btn-back { display: inline-block; padding: 12px 24px; background: #2563EB; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
                .btn-back:hover { background: #1d4ed8; transform: translateY(-1px); }
                .filter-tab { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; }
                .filter-tab.active { background: #2563EB; color: white; border-color: #2563EB; }
                .dark .filter-tab { border-color: rgba(255,255,255,0.1); color: #94a3b8; }
                .dark .filter-tab.active { background: #4f46e5; color: white; }
            `}</style>

            <div className="result-header">
                <h1>{percentageScore.toFixed(1)}%</h1>
                <p>Você acertou {correctAnswers} de {totalQuestions} questões</p>
            </div>

            <div className="stats-grid">
                <div className="stat-box">
                    <h3>Acertos</h3>
                    <div className="value" style={{ color: '#10b981' }}>{correctAnswers}</div>
                </div>
                <div className="stat-box">
                    <h3>Erros</h3>
                    <div className="value" style={{ color: '#ef4444' }}>{totalQuestions - correctAnswers}</div>
                </div>
                <div className="stat-box">
                    <h3>Tempo Total</h3>
                    <div className="value" style={{ fontSize: '24px' }}>{formatTime(simulation.time_spent || 0)}</div>
                </div>
                <div className="stat-box">
                    <h3>Média por Questão</h3>
                    <div className="value" style={{ fontSize: '24px' }}>
                        {totalQuestions > 0 ? formatAvgTime(Math.round((simulation.time_spent || 0) / totalQuestions)) : '00:00'}
                    </div>
                </div>
            </div>

            <div className="answers-section">
                <div className="flex justify-between items-center mb-6 flex-wrap gap-4">
                    <h2 className="mb-0">Análise Detalhada</h2>
                    <div className="flex gap-2">
                        <button onClick={() => setFilter('all')} className={`filter-tab ${filter === 'all' ? 'active' : ''}`}>Todas</button>
                        <button onClick={() => setFilter('correct')} className={`filter-tab ${filter === 'correct' ? 'active' : ''}`}>✅ Acertos</button>
                        <button onClick={() => setFilter('incorrect')} className={`filter-tab ${filter === 'incorrect' ? 'active' : ''}`}>❌ Erros</button>
                    </div>
                </div>
                {filteredAnswers.length === 0 ? (
                    <div className="text-center py-10 text-slate-500">Nenhuma questão encontrada com este filtro.</div>
                ) : (
                    <div className="space-y-6">
                        {filteredAnswers.map((ans: any) => (
                            <QuestionCard
                                key={ans.question_id}
                                question={ans.question}
                                mode="result"
                                userAnswer={ans.user_answer}
                                isCorrect={ans.is_correct}
                                simulationId={simulation.id}
                            />
                        ))}
                    </div>
                )}

                {/* --- Módulo de Redação Integrado (Essay) --- */}
                {simulation.essay && simulation.essay.status && (
                    <div className="mt-8 pt-8 border-t border-slate-200 dark:border-slate-700">
                        <h2 className="mb-4">Resultado da Redação</h2>
                        <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden p-4">
                            <EssayReview essayId={simulation.essay.id} isEmbedded={true} />
                        </div>
                    </div>
                )}
            </div>

            <div className="text-center mt-8 gap-4 flex flex-wrap justify-center">
                <Link to="/dashboard" className="btn-back">Voltar ao Dashboard</Link>
                <Link to="/simulados/create" className="btn-back" style={{ background: '#10b981' }}>Nova Prova</Link>
                {simulation.essay && (
                    <Link to={`/redacoes/correcao/${simulation.essay.id}`} className="btn-back" style={{ background: '#7c3aed' }}>
                        📝 Redação e nota
                    </Link>
                )}
            </div>
        </div>
    );
}
