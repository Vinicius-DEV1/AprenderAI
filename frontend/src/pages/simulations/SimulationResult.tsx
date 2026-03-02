import { useState, useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { marked } from 'marked';
import { useConfigStore } from '../../stores/configStore';
import '../../styles/question-bank.css';

const getSimulationResult = async (id: string) => {
    const { data } = await api.get(`/api/v1/simulations/${id}`, { params: { include_answers: 1 } });
    return data.data;
};

export default function SimulationResult() {
    const { id } = useParams<{ id: string }>();
    const { aiName } = useConfigStore();
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
                .answer-item { padding: 16px; border: 2px solid #f1f5f9; border-radius: 8px; margin-bottom: 12px; }
                .dark .answer-item { border-color: rgba(255,255,255,0.08); }
                .answer-item.correct { border-color: #10b981; background: #f0fdf4; }
                .dark .answer-item.correct { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); }
                .answer-item.incorrect { border-color: #ef4444; background: #fef2f2; }
                .dark .answer-item.incorrect { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
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
                    filteredAnswers.map((ans: any) => (
                        <AnswerCard key={ans.question_id} answer={ans} index={answers.indexOf(ans)} aiName={aiName} simulationId={simulation.id} />
                    ))
                )}
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

function AnswerCard({ answer, index, aiName, simulationId }: { answer: any, index: number, aiName: string, simulationId: number }) {
    const q = answer.question;
    const [showChat, setShowChat] = useState(false);

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
                    {q.organization && <span className="qb-badge" style={{ background: '#E2E8F0', color: '#475569' }}>{q.organization}</span>}
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
                {q.alternatives?.sort((a: any, b: any) => a.label.localeCompare(b.label)).map((alt: any) => (
                    <div key={alt.label} className={`flex items-start gap-3 p-3 rounded-lg border ${answer.user_answer === alt.label ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20' : 'bg-slate-50 border-slate-100 dark:bg-slate-800/40 dark:border-slate-700'} ${alt.is_correct ? 'ring-2 ring-green-500 ring-offset-1' : ''}`}>
                        <span className={`flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold ${answer.user_answer === alt.label ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'}`}>
                            {alt.label}
                        </span>
                        <div className="flex flex-col gap-2 flex-grow overflow-hidden">
                            {alt.content && <div className="text-sm dark:text-slate-300 word-break-all">{alt.content}</div>}
                            {alt.image_path && <img src={`/storage/${alt.image_path}`} alt={`Alternativa ${alt.label}`} className="max-w-full h-auto rounded object-contain mt-2" />}
                        </div>
                        {alt.is_correct && <span className="ml-auto text-green-600 font-bold">✓</span>}
                    </div>
                ))}
            </div>

            <div className="bg-slate-50 dark:bg-slate-800/60 p-4 rounded-lg border border-slate-200 dark:border-slate-700 mt-4">
                <h4 className="text-sm font-bold text-gray-700 dark:text-slate-300 mb-2 flex items-center gap-2">
                    <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.364-6.364l-.707-.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M12 11a3 3 0 110-6 3 3 0 010 6z" />
                    </svg>
                    Resolução Comentada
                </h4>
                <div className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" dangerouslySetInnerHTML={renderMd(q.explanation || 'Resolução sendo processada...')} />
            </div>

            <div className="mt-2 border-t border-gray-100 dark:border-slate-700 pt-2">
                <button onClick={() => setShowChat(!showChat)} className="text-xs text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-1.5 transition-colors">
                    <span>{showChat ? 'Ocultar Chat' : `💬 Tirar Dúvida com ${aiName}`}</span>
                </button>

                {showChat && (
                    <div className="mt-3 animate-xavier-pop">
                        <ChatInterface questionId={q.id} simulationId={simulationId} aiName={aiName} />
                    </div>
                )}
            </div>
        </div>
    );
}

function ChatInterface({ questionId, simulationId, aiName }: { questionId: number, simulationId: number, aiName: string }) {
    const [messages, setMessages] = useState<any[]>([]);
    const [input, setInput] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    const renderMd = (text: string) => {
        if (!text) return { __html: '' };
        return { __html: marked.parse(text) as string };
    };

    const loadHistory = async () => {
        try {
            const res = await api.get(`/api/v1/questions/${questionId}/chat`);
            if (res.data.length === 0) {
                setMessages([{ role: 'assistant', message: `Olá! Sou o ${aiName}. Qual sua dúvida sobre esta questão?` }]);
            } else {
                setMessages(res.data);
            }
        } catch (e) { }
    };

    useEffect(() => { loadHistory(); }, []);
    useEffect(() => { scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' }); }, [messages, isTyping]);

    const sendMessage = async () => {
        if (!input.trim() || isTyping) return;

        const userMsg = input;
        setInput('');

        const newMessages = [...messages, { role: 'user', message: userMsg }];
        setMessages(newMessages);
        setIsTyping(true);

        const currentMsgIndex = newMessages.length;
        setMessages(prev => [...prev, { role: 'assistant', message: '' }]);

        try {
            const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token') || localStorage.getItem('token');
            const baseUrl = api.defaults.baseURL || '';

            const response = await fetch(`${baseUrl}/api/v1/simulations/${simulationId}/questions/${questionId}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'include',
                body: JSON.stringify({ message: userMsg, simulation_id: simulationId })
            });

            if (!response.ok) {
                const errorText = await response.text();
                let errorMessage = 'Desculpe, ocorreu um erro ao processar sua dúvida.';

                try {
                    const errorData = JSON.parse(errorText);
                    if (errorData.status === 'quota_exceeded') {
                        setMessages(prev => {
                            const updated = [...prev];
                            updated[currentMsgIndex] = { role: 'system', message: errorData.message, upgrade_url: errorData.upgrade_url };
                            return updated;
                        });
                        setIsTyping(false);
                        return;
                    }
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    errorMessage = `Erro ${response.status}: Não foi possível falar com a IA agora.`;
                }

                setMessages(prev => {
                    const updated = [...prev];
                    updated[currentMsgIndex] = { role: 'assistant', message: errorMessage };
                    return updated;
                });
                setIsTyping(false);
                return;
            }

            const reader = response.body?.getReader();
            const decoder = new TextDecoder();
            let done = false;

            if (reader) {
                let buffer = '';
                let firstChunkReceived = false;

                while (!done) {
                    const { done: doneReading, value } = await reader.read();
                    done = doneReading;
                    if (value) {
                        buffer += decoder.decode(value, { stream: true });

                        const parts = buffer.split('\n\n');
                        buffer = parts.pop() || '';

                        for (const part of parts) {
                            const lines = part.split('\n');
                            for (const line of lines) {
                                if (line.startsWith('data: ')) {
                                    const content = line.substring(6);
                                    if (content) {
                                        if (!firstChunkReceived) {
                                            setIsTyping(false);
                                            firstChunkReceived = true;
                                        }

                                        setMessages(prev => {
                                            const updated = [...prev];
                                            updated[currentMsgIndex] = {
                                                role: 'assistant',
                                                message: (updated[currentMsgIndex]?.message || '') + content
                                            };
                                            return updated;
                                        });
                                    }
                                }
                            }
                        }
                    }
                }

                // Final flush
                if (buffer.startsWith('data: ')) {
                    const finalContent = buffer.substring(6);
                    if (finalContent) {
                        if (!firstChunkReceived) setIsTyping(false);
                        setMessages(prev => {
                            const updated = [...prev];
                            updated[currentMsgIndex] = {
                                role: 'assistant',
                                message: (updated[currentMsgIndex]?.message || '') + finalContent
                            };
                            return updated;
                        });
                    }
                }
            }

        } catch (e) {
            setMessages(prev => {
                const updated = [...prev];
                updated[currentMsgIndex] = { role: 'assistant', message: 'Desculpe, ocorreu um erro ao processar sua dúvida.' };
                return updated;
            });
        } finally {
            setIsTyping(false);
        }
    };

    return (
        <div className="qb-chat-container bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
            <div className="qb-chat-history space-y-3 mb-3 max-h-[400px] overflow-y-auto p-1" ref={scrollRef}>
                {messages.map((m, i) => (
                    <div key={i} className={`flex flex-col ${m.role === 'user' ? 'items-end' : (m.role === 'system' ? 'items-center' : 'items-start')}`}>
                        {m.role === 'system' ? (
                            <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-center w-full my-2 shadow-sm">
                                <p className="text-xs text-red-800 dark:text-red-300 font-bold mb-2">{m.message}</p>
                                <Link to={m.upgrade_url || '/plans'} className="inline-block bg-red-600 text-white text-[10px] font-bold py-1.5 px-4 rounded-full hover:bg-red-700 transition-colors uppercase">
                                    🚀 Turbinar Plano
                                </Link>
                            </div>
                        ) : (
                            <>
                                <div className={`px-3 py-2 rounded-lg text-[11px] max-w-[85%] shadow-sm ${m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 border dark:border-slate-700 text-slate-800 dark:text-slate-200'}`}>
                                    {m.role === 'assistant' ? <div className="markdown-body text-[11px]" dangerouslySetInnerHTML={renderMd(m.message)} /> : m.message}
                                </div>
                                <span className="text-[9px] text-slate-400 mt-1 uppercase tracking-tighter">{m.role === 'user' ? 'Você' : aiName}</span>
                            </>
                        )}
                    </div>
                ))}
                {isTyping && !messages[messages.length - 1]?.message && (
                    <div className="flex items-center gap-2 px-3 py-2 bg-slate-100 dark:bg-slate-800 rounded-lg w-fit border dark:border-slate-700 shadow-sm animate-pulse">
                        <span className="text-[10px] text-slate-500 font-medium">{aiName} está digitando</span>
                        <div className="flex gap-0.5">
                            <span className="w-1 h-1 bg-slate-400 rounded-full"></span>
                            <span className="w-1 h-1 bg-slate-400 rounded-full"></span>
                            <span className="w-1 h-1 bg-slate-400 rounded-full"></span>
                        </div>
                    </div>
                )}
            </div>
            <div className="flex gap-2 p-2 border-t dark:border-slate-700">
                <input
                    type="text"
                    value={input}
                    onChange={e => setInput(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && sendMessage()}
                    placeholder={`Dúvida com ${aiName}...`}
                    className="flex-1 bg-transparent text-xs outline-none dark:text-slate-200"
                    disabled={isTyping}
                />
                <button onClick={sendMessage} className="text-indigo-600 font-bold text-xs disabled:opacity-50" disabled={isTyping || !input.trim()}>Enviar</button>
            </div>
        </div>
    );
}
