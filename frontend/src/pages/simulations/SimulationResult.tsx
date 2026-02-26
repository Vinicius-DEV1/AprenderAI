import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { marked } from 'marked';
import '../../styles/question-bank.css';

const getSimulationResult = async (id: string) => {
    const { data } = await api.get(`/api/v1/simulations/${id}`, { params: { include_answers: 1 } });
    return data.data;
};

export default function SimulationResult() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

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
                .answer-item { padding: 16px; border: 2px solid #f1f5f9; border-radius: 8px; margin-bottom: 12px; }
                .dark .answer-item { border-color: rgba(255,255,255,0.08); }
                .answer-item.correct { border-color: #10b981; background: #f0fdf4; }
                .dark .answer-item.correct { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); }
                .answer-item.incorrect { border-color: #ef4444; background: #fef2f2; }
                .dark .answer-item.incorrect { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
                .btn-back { display: inline-block; padding: 12px 24px; background: #2563EB; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
                .btn-back:hover { background: #1d4ed8; transform: translateY(-1px); }
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
                        {totalQuestions > 0 ? formatTime(Math.round((simulation.time_spent || 0) / totalQuestions)) : '00:00:00'}
                    </div>
                </div>
            </div>

            <div className="answers-section">
                <h2>Análise Detalhada</h2>
                {answers.map((ans: any, idx: number) => (
                    <AnswerCard key={ans.question_id} answer={ans} index={idx} simulationId={simulation.id} />
                ))}
            </div>

            <div className="text-center mt-8 gap-4 flex flex-wrap justify-center">
                <Link to="/dashboard" className="btn-back">Voltar ao Dashboard</Link>
                <Link to="/simulations/create" className="btn-back" style={{ background: '#10b981' }}>Nova Prova</Link>
                {simulation.essay && (
                    <Link to={`/essays/${simulation.essay.id}`} className="btn-back" style={{ background: '#7c3aed' }}>
                        📝 Redação e nota
                    </Link>
                )}
            </div>
        </div>
    );
}

function AnswerCard({ answer, index, simulationId }: { answer: any, index: number, simulationId: number }) {
    const q = answer.question;
    const [showChat, setShowChat] = useState(false);

    // Simple marked helper
    const renderMd = (text: string) => {
        if (!text) return { __html: '' };
        try { return { __html: marked.parse(text) as string }; }
        catch (e) { return { __html: text }; }
    };

    const getDifficultyInfo = (difficulty: string) => {
        switch (difficulty) {
            case 'easy': return { bg: '#d1fae5', text: '#065f46', label: 'Fácil' };
            case 'medium': return { bg: '#fef3c7', text: '#92400e', label: 'Média' };
            case 'hard': return { bg: '#fee2e2', text: '#991b1b', label: 'Difícil' };
            default: return null;
        }
    };

    const diff = getDifficultyInfo(q.difficulty);

    return (
        <div className={`answer-item ${answer.is_correct ? 'correct' : 'incorrect'}`}>
            <div className="flex justify-between items-center mb-4 flex-wrap gap-2">
                <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    Questão {index + 1} - {q.subjects?.map((s: any) => s.name).join(', ') || 'Geral'}
                </span>
                <div className="flex gap-2 items-center">
                    {q.source === 'ai_generated' && <span className="qb-badge" style={{ background: '#E9D5FF', color: '#6B21A8' }}>✨ INÉDITA</span>}
                    {diff && (
                        <span className="qb-badge group relative" style={{ background: diff.bg, color: diff.text }} title={q.difficulty_reasoning}>
                            {diff.label}
                            {q.difficulty_reasoning && (
                                <span className="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-2 p-2 bg-gray-800 text-white text-[10px] rounded shadow-lg w-48 z-50 text-center leading-tight font-normal">
                                    {q.difficulty_reasoning}
                                    <span className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            )}
                        </span>
                    )}
                    <span className={`qb-badge ${answer.is_correct ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {answer.is_correct ? '✓ Correta' : '✗ Incorreta'}
                    </span>
                </div>
            </div>

            <div className="question-statement mb-4 dark:text-slate-200" dangerouslySetInnerHTML={renderMd(q.statement_html || q.statement)} />

            <div className="space-y-2 mb-4">
                {q.alternatives?.map((alt: any) => (
                    <div key={alt.label} className={`flex items-start gap-3 p-3 rounded-lg border ${answer.user_answer === alt.label ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20' : 'bg-slate-50 border-slate-100 dark:bg-slate-800/40 dark:border-slate-700'} ${alt.is_correct ? 'ring-2 ring-green-500' : ''}`}>
                        <span className={`flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold ${answer.user_answer === alt.label ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'}`}>
                            {alt.label}
                        </span>
                        <div className="text-sm dark:text-slate-300">{alt.content}</div>
                        {alt.is_correct && <span className="ml-auto text-green-600 font-bold">✓</span>}
                    </div>
                ))}
            </div>

            <div className="bg-slate-50 dark:bg-slate-800/60 p-4 rounded-lg border border-slate-200 dark:border-slate-700 mt-4">
                <h4 className="text-xs font-bold text-indigo-600 mb-2 flex items-center gap-2">💡 Resolução Comentada</h4>
                <div className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" dangerouslySetInnerHTML={renderMd(q.explanation || 'Resolução sendo processada...')} />
            </div>

            {/* Contextual Chat Button */}
            <button onClick={() => setShowChat(!showChat)} className="mt-4 text-xs text-indigo-600 font-bold hover:underline flex items-center gap-1">
                {showChat ? 'Ocultar Chat' : '💬 Tirar Dúvida com Xavier'}
            </button>

            {showChat && (
                <div className="mt-4 animate-xavier-pop">
                    <ChatInterface simulationId={simulationId} questionId={q.id} />
                </div>
            )}
        </div>
    );
}

function ChatInterface({ simulationId, questionId }: { simulationId: number, questionId: number }) {
    const [messages, setMessages] = useState<any[]>([]);
    const [input, setInput] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    const loadHistory = async () => {
        try {
            const res = await api.get(`/api/v1/questions/${questionId}/chat`);
            if (res.data.length === 0) {
                setMessages([{ role: 'assistant', message: 'Olá! Sou o Xavier. Qual sua dúvida sobre esta questão?' }]);
            } else {
                setMessages(res.data);
            }
        } catch (e) { }
    };

    useEffect(() => { loadHistory(); }, []);
    useEffect(() => { scrollRef.current?.scrollTo(0, scrollRef.current.scrollHeight); }, [messages]);

    const sendMessage = async () => {
        if (!input.trim()) return;
        const userMsg = input;
        setInput('');
        setMessages(prev => [...prev, { role: 'user', message: userMsg }]);
        setIsTyping(true);

        try {
            await api.post(`/api/v1/questions/${questionId}/chat`, { message: userMsg });
            // Polling for response (simplified for result view)
            pollAnswer();
        } catch (e) {
            setIsTyping(false);
        }
    };

    const pollAnswer = () => {
        let attempts = 0;
        const poller = setInterval(async () => {
            attempts++;
            try {
                const res = await api.get(`/api/v1/questions/${questionId}/chat`);
                const last = res.data[res.data.length - 1];
                if (last && last.role === 'assistant') {
                    setMessages(res.data);
                    setIsTyping(false);
                    clearInterval(poller);
                }
            } catch (e) { }
            if (attempts > 20) { clearInterval(poller); setIsTyping(false); }
        }, 2000);
    };

    return (
        <div className="qb-chat-container">
            <div className="qb-chat-history p-2" ref={scrollRef}>
                {messages.map((m, i) => (
                    <div key={i} className={`flex flex-col mb-3 ${m.role === 'user' ? 'items-end' : 'items-start'}`}>
                        <div className={`px-3 py-2 rounded-lg text-xs max-w-[85%] ${m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-700 border dark:border-slate-600'}`}>
                            {m.message}
                        </div>
                        <span className="text-[10px] text-slate-400 mt-1">{m.role === 'user' ? 'Você' : 'Xavier'}</span>
                    </div>
                ))}
                {isTyping && <div className="text-[10px] text-slate-400 italic">Xavier está digitando...</div>}
            </div>
            <div className="flex gap-2 p-2 border-t dark:border-slate-700">
                <input type="text" value={input} onChange={e => setInput(e.target.value)} onKeyDown={e => e.key === 'Enter' && sendMessage()} placeholder="Sua dúvida..." className="flex-1 bg-transparent text-xs outline-none" />
                <button onClick={sendMessage} className="text-indigo-600 font-bold text-xs">Enviar</button>
            </div>
        </div>
    );
}
