import { toast } from 'sonner';
import { useState, useRef, useEffect } from 'react';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import api from '../../api/axios';
import { marked } from 'marked';

interface Alternative {
    id: number;
    label: string;
    content: string;
    image_path?: string;
    is_correct?: boolean;
}

interface Question {
    id: number;
    year?: number;
    organization?: string;
    source: string;
    subjects: { id: number; name: string }[];
    difficulty: 'easy' | 'medium' | 'hard';
    statement_html: string;
    statement: string;
    tipo_questao?: 'Objetiva' | 'Discursiva' | 'Redação';
    discursive_answer?: any;
    alternatives: Alternative[];
    already_answered?: boolean;
    was_correct?: boolean;
}

export default function QuestionCard({ question: q }: { question: Question }) {
    const { aiName } = useConfigStore();
    const { user } = useAuthStore();

    const isDiscursive = q.tipo_questao === 'Discursiva';

    const [selectedAnswer, setSelectedAnswer] = useState<string | null>(null);
    const [discursiveAnswers, setDiscursiveAnswers] = useState<Record<string, string>>({});

    const [answered, setAnswered] = useState(false);
    const [isCorrect, setIsCorrect] = useState<boolean | null>(q.was_correct ?? null);
    const [correctAnswer, setCorrectAnswer] = useState<string | null>(null);
    const [explanation, setExplanation] = useState<string | null>(null);
    const [difficultyReasoning, setDifficultyReasoning] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const [showChat, setShowChat] = useState(false);
    const [chatMessages, setChatMessages] = useState<any[]>([]);
    const [chatInput, setChatInput] = useState('');
    const [chatTyping, setChatTyping] = useState(false);
    const [chatLoaded, setChatLoaded] = useState(false);
    const chatHistoryRef = useRef<HTMLDivElement>(null);

    const [showHistory, setShowHistory] = useState(false);
    const [historyData, setHistoryData] = useState<any[]>([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [historyLoaded, setHistoryLoaded] = useState(false);

    // Sync marked options
    useEffect(() => {
        marked.setOptions({ breaks: true, gfm: true });
    }, []);

    const scrollToBottom = () => {
        if (chatHistoryRef.current) {
            chatHistoryRef.current.scrollTop = chatHistoryRef.current.scrollHeight;
        }
    };

    const formatDate = (iso: string) => {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const renderMd = (text: string) => {
        if (!text) return { __html: '' };
        try {
            return { __html: marked.parse(text) as string };
        } catch (e) {
            return { __html: text };
        }
    };

    const selectAnswer = (letter: string) => {
        if (!answered && !isDiscursive) setSelectedAnswer(letter);
    };

    const handleDiscursiveChange = (label: string, text: string) => {
        if (!answered) {
            setDiscursiveAnswers(prev => ({ ...prev, [label]: text }));
        }
    };

    const submitAnswer = async () => {
        if (submitting || answered) return;
        if (!isDiscursive && !selectedAnswer) return;
        if (isDiscursive && Object.keys(discursiveAnswers).length === 0) return;

        setSubmitting(true);
        try {
            let payload: any = { selected_answer: selectedAnswer };
            if (isDiscursive) {
                payload = { respostas_discursivas: discursiveAnswers };
            }

            const res = await api.post(`/api/v1/questions/${q.id}/answer`, payload);
            const data = res.data;
            setAnswered(true);
            setIsCorrect(data.correct ?? null);
            setCorrectAnswer(data.correct_answer ?? null);
            setExplanation(data.explanation || '');
            setDifficultyReasoning(data.difficulty_reasoning || '');
        } catch (e) {
            toast.error('Erro ao enviar resposta.');
        } finally {
            setSubmitting(false);
        }
    };

    const resetCard = () => {
        setAnswered(false);
        setSelectedAnswer(null);
        setDiscursiveAnswers({});
        setIsCorrect(null);
        setCorrectAnswer(null);
        setExplanation(null);
        setDifficultyReasoning(null);
        setShowChat(false);
        setShowHistory(false);
    };

    const loadChatHistory = async () => {
        try {
            const res = await api.get(`/api/v1/questions/${q.id}/chat`);
            const history = res.data;
            if (history.length === 0) {
                setChatMessages([{
                    id: Date.now(),
                    role: 'assistant',
                    message: `Olá! Eu sou o ${aiName}. Qual sua dúvida sobre essa questão?`,
                    created_at: new Date().toISOString()
                }]);
            } else {
                setChatMessages(history);
            }
            setChatLoaded(true);
            setTimeout(scrollToBottom, 50);
        } catch (e) {
            console.error('Chat load error', e);
        }
    };

    const toggleChat = async () => {
        const nextState = !showChat;
        setShowChat(nextState);
        if (nextState && !chatLoaded) {
            await loadChatHistory();
        }
        if (nextState) {
            setTimeout(scrollToBottom, 50);
        }
    };

    const sendChat = async () => {
        if (!chatInput.trim() || chatTyping) return;
        const msg = chatInput;
        setChatMessages(prev => [...prev, { role: 'user', message: msg, id: Date.now() }]);
        setChatInput('');
        setChatTyping(true);
        setTimeout(scrollToBottom, 50);

        try {
            const res = await api.post(`/api/v1/questions/${q.id}/chat`, { message: msg });
            const data = res.data;
            if (data.status === 'quota_exceeded') {
                setChatMessages(prev => [...prev, { role: 'system', message: data.message, upgrade_url: data.upgrade_url, id: Date.now() }]);
                setChatTyping(false);
                return;
            }
            // Simple polling for non-streaming
            pollChat();
        } catch (e) {
            setChatTyping(false);
            setChatMessages(prev => [...prev, { role: 'assistant', message: 'Erro ao processar.', id: Date.now() }]);
        }
    };

    const pollChat = () => {
        let attempts = 0;
        const poller = setInterval(async () => {
            attempts++;
            try {
                const res = await api.get(`/api/v1/questions/${q.id}/chat`);
                const history = res.data;
                const last = history[history.length - 1];
                if (last && last.role === 'assistant') {
                    setChatMessages(history);
                    setChatTyping(false);
                    clearInterval(poller);
                    setTimeout(scrollToBottom, 50);
                }
            } catch (e) { }
            if (attempts >= 30) {
                clearInterval(poller);
                setChatTyping(false);
            }
        }, 2000);
    };

    const toggleHistory = async () => {
        const nextState = !showHistory;
        setShowHistory(nextState);
        if (nextState && !historyLoaded) {
            setHistoryLoading(true);
            try {
                const res = await api.get(`/api/v1/questions/${q.id}/history`);
                setHistoryData(res.data);
                setHistoryLoaded(true);
            } catch (e) { } finally {
                setHistoryLoading(false);
            }
        }
    };

    const difficultyMap = {
        easy: { class: 'qb-badge-easy', label: 'Fácil' },
        medium: { class: 'qb-badge-medium', label: 'Média' },
        hard: { class: 'qb-badge-hard', label: 'Difícil' }
    };
    const dc = difficultyMap[q.difficulty] || difficultyMap.medium;

    return (
        <div className="qb-card">
            <div className="qb-card-meta">
                {q.source === 'ai_generated' && <span className="qb-badge qb-badge-ai">✨ INÉDITA</span>}
                {q.year && <span className="qb-badge qb-badge-origin">{q.year}</span>}
                {q.organization && <span className="qb-badge qb-badge-origin">{q.organization}</span>}
                <span className="qb-badge qb-badge-origin">{q.subjects.map(s => s.name).join(', ')}</span>
                <span className={`qb-badge ${dc.class}`}>{dc.label}</span>
                {q.already_answered && !answered && (
                    <div className="flex items-center gap-2 mb-2">
                        <span className={`qb-badge ${q.was_correct ? 'qb-badge-correct' : 'qb-badge-incorrect'}`}>
                            {q.was_correct ? '✓ Você já acertou esta questão' : '✗ Você já tentou esta questão'}
                        </span>
                        <span className="text-[10px] text-gray-400 font-medium">Você pode responder novamente abaixo.</span>
                    </div>
                )}
            </div>

            <div className="qb-statement" dangerouslySetInnerHTML={{ __html: q.statement_html }} />



            {/* Discursiva Rendering */}
            {isDiscursive && (
                <div className="qb-discursive-list space-y-6 mt-4">
                    {q.alternatives.sort((a, b) => a.label.localeCompare(b.label)).map(alt => (
                        <div key={alt.id} className="qb-discursive-item">
                            <div className="font-bold text-slate-800 dark:text-slate-200 mb-2 whitespace-pre-wrap">
                                {alt.label.toLowerCase()}) {alt.content}
                            </div>
                            <textarea
                                className="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-slate-800 dark:text-gray-100 text-sm"
                                rows={4}
                                placeholder="Sua resposta fundamentada..."
                                value={discursiveAnswers[alt.label] || ''}
                                onChange={(e) => handleDiscursiveChange(alt.label, e.target.value)}
                                disabled={answered}
                            />
                        </div>
                    ))}
                </div>
            )}

            {!isDiscursive && (
                /* Objetiva Rendering */
                <div className="qb-alternatives-list">
                    {q.alternatives.sort((a, b) => a.label.localeCompare(b.label)).map(alt => (
                        <div
                            key={alt.id}
                            className={`qb-alt ${selectedAnswer === alt.label && !answered ? 'selected' : ''
                                } ${answered && alt.label === (correctAnswer || (alt.is_correct ? alt.label : null)) ? 'correct-reveal' : ''
                                } ${answered && selectedAnswer === alt.label && alt.label !== (correctAnswer || (alt.is_correct ? alt.label : null)) ? 'incorrect-reveal' : ''
                                } ${answered ? 'disabled' : ''}`}
                            onClick={() => selectAnswer(alt.label)}
                        >
                            <div className="qb-alt-letter">{alt.label}</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', flexGrow: 1, overflow: 'hidden' }}>
                                {alt.content && (
                                    <div className="qb-alt-text" style={{ wordBreak: 'break-word' }}>{alt.content}</div>
                                )}
                                {alt.image_path && (
                                    <img src={`/storage/${alt.image_path}`} alt={`Alternativa ${alt.label}`} style={{ maxWidth: '100%', height: 'auto', borderRadius: '4px', objectFit: 'contain' }} />
                                    // Note: In production use actual storage URL helper logic
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="qb-card-actions mt-6">
                {!answered && (
                    <button className="qb-action-btn primary" onClick={submitAnswer} disabled={(!isDiscursive && !selectedAnswer) || (isDiscursive && Object.keys(discursiveAnswers).length === 0) || submitting}>
                        {!submitting ? '📝 Responder' : '⏳ Enviando...'}
                    </button>
                )}
                {answered && (
                    <>
                        <button className="qb-action-btn" onClick={toggleChat}>
                            {showChat ? '▲ Ocultar Chat' : '💬 Tirar Dúvida'}
                        </button>
                        <button className="qb-action-btn" onClick={toggleHistory}>📜 Meu Histórico</button>
                        <button className="qb-action-btn retry" onClick={resetCard}>
                            <span>🔄 Tentar Novamente</span>
                        </button>
                    </>
                )}
            </div>

            {answered && isDiscursive && (
                <div className="qb-feedback border-l-4 border-indigo-500 bg-indigo-50 p-4 mt-6 rounded-r-lg">
                    <div className="flex justify-between items-center mb-4">
                        <h4 className="font-bold text-indigo-900 flex items-center gap-2">
                            <span className="text-xl">📋</span> Espelho de Correção Oficial
                        </h4>
                        <button className="text-sm bg-white text-indigo-600 px-3 py-1.5 rounded-md border border-indigo-200 hover:bg-indigo-100 font-semibold shadow-sm transition">
                            ✨ Pedir correção para {aiName}
                        </button>
                    </div>

                    <div className="space-y-4">
                        {typeof q.discursive_answer === 'object' && q.discursive_answer !== null ? (
                            Object.entries(q.discursive_answer).map(([key, value]) => (
                                <div key={key} className="bg-white p-3 rounded-md shadow-sm border border-indigo-100">
                                    <strong className="text-indigo-800">Padrão Esperado ({key}):</strong>
                                    <div className="text-slate-700 mt-1 text-sm whitespace-pre-wrap">{String(value)}</div>
                                </div>
                            ))
                        ) : (
                            <div className="bg-white p-3 rounded-md shadow-sm border border-indigo-100 text-slate-700 whitespace-pre-wrap">
                                {q.discursive_answer ? String(q.discursive_answer) : "Espelho não encontrado."}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {answered && !isDiscursive && (
                <div className={`qb-feedback ${isCorrect ? 'correct' : 'incorrect'}`}>
                    <div className="qb-feedback-title">
                        <span>{isCorrect ? '✅ Resposta Correta!' : '❌ Resposta Incorreta'}</span>
                        {!isCorrect && (
                            <span style={{ fontWeight: 400, fontSize: '12px', color: '#64748b' }}>
                                {" "}Correta: <strong style={{ color: '#065f46' }}>{correctAnswer}</strong>
                            </span>
                        )}
                    </div>
                    {explanation && (
                        <div className="qb-explanation">
                            <h4>📖 Resolução Comentada</h4>
                            <div className="qb-explanation-text" dangerouslySetInnerHTML={renderMd(explanation)} />
                        </div>
                    )}
                    {difficultyReasoning && (
                        <div className="qb-difficulty-box" style={{ borderColor: isCorrect ? '#bbf7d0' : '#fecaca', background: isCorrect ? '#f0fdf4' : '#fef2f2' }}>
                            <h5 className="text-sm font-bold uppercase mb-1">
                                [{dc.label}] 🎯 Por que essa dificuldade?
                            </h5>
                            <p style={{ color: '#475569', fontSize: '13px' }}>{difficultyReasoning}</p>
                        </div>
                    )}
                </div>
            )}

            {showHistory && (
                <div className="qb-history-popover animate-fade-in">
                    <h4 style={{ fontSize: '13px', fontWeight: 700, color: '#6366f1', marginBottom: '8px' }}>📜 Seu Histórico nesta Questão</h4>
                    {historyLoading && <p style={{ fontSize: '12px', color: '#94a3b8' }}>Carregando...</p>}
                    {!historyLoading && historyData.length === 0 && <p style={{ fontSize: '12px', color: '#94a3b8' }}>Nenhum registro encontrado.</p>}
                    {historyData.map((h, idx) => (
                        <div key={idx} className="qb-history-row">
                            <span style={{ color: '#64748b' }}>{formatDate(h.answered_at)}</span>
                            <span>Resposta: <strong>{h.selected_answer}</strong></span>
                            <span className={`qb-badge ${h.is_correct ? 'qb-badge-correct' : 'qb-badge-incorrect'}`}>
                                {h.is_correct ? 'Acerto' : 'Erro'}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {showChat && (
                <div className="qb-chat-container">
                    <div className="qb-chat-history space-y-2 p-1" ref={chatHistoryRef}>
                        {chatMessages.map((msg, idx) => (
                            <div key={idx} className={msg.role === 'user' ? 'flex justify-end' : (msg.role === 'system' ? 'flex justify-center' : 'flex justify-start')}>
                                {msg.role === 'user' && (
                                    <div className="rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" style={{ background: '#4f46e5', color: 'white' }}>{msg.message}</div>
                                )}
                                {msg.role === 'assistant' && (
                                    <div className="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-1.5 max-w-[85%] text-xs shadow-sm" dangerouslySetInnerHTML={renderMd(msg.message)} />
                                )}
                                {msg.role === 'system' && (
                                    <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-center w-[90%]">
                                        <p className="text-xs text-red-800 font-bold">{msg.message}</p>
                                        <a href={msg.upgrade_url || '/plans'} className="mt-2 inline-block bg-gradient-to-r from-red-500 to-orange-500 text-white text-xs font-bold py-1.5 px-4 rounded-full">🚀 Turbinar Plano</a>
                                    </div>
                                )}
                            </div>
                        ))}
                        {chatTyping && (
                            <div className="flex items-start">
                                <div className="bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2 text-xs text-gray-500 flex items-center gap-2 border border-gray-200">
                                    <span className="font-medium">{aiName} digitando</span>
                                    <span className="flex gap-1">
                                        <span className="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></span>
                                        <span className="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }}></span>
                                        <span className="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></span>
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="flex gap-2 mt-2">
                        <input
                            type="text"
                            value={chatInput}
                            onChange={e => setChatInput(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && sendChat()}
                            placeholder={`Qual sua dúvida, ${user?.name?.split(' ')[0] || 'estudante'}?`}
                            disabled={chatTyping}
                            className="flex-1 rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-100 shadow-sm text-xs px-3 py-2"
                        />
                        <button onClick={sendChat} disabled={chatTyping || !chatInput.trim()} className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-xs font-medium disabled:opacity-50">Enviar</button>
                    </div>
                </div>
            )}
        </div>
    );
}
