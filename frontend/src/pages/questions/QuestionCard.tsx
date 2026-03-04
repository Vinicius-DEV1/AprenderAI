import { toast } from 'sonner';
import { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import api from '../../api/axios';
import { marked } from 'marked';

// Modals
import DialogReportQuestion from './modals/DialogReportQuestion';
import DrawerQuestionNotes from './modals/DrawerQuestionNotes';
import DialogNotebookManager from './modals/DialogNotebookManager';
import DialogQuestionStats from './modals/DialogQuestionStats';

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
    is_favorite?: boolean;
    has_notes?: boolean;
    notebook_ids?: number[];
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
    const [submitting, setSubmitting] = useState(false);

    // Feature states
    const [isFavorite, setIsFavorite] = useState(q.is_favorite || false);
    const [favLoading, setFavLoading] = useState(false);

    // Notes state tracking to show highlighted icon
    const [hasNotes, setHasNotes] = useState(q.has_notes || false);
    const [notebookIds, setNotebookIds] = useState<number[]>(q.notebook_ids || []);

    const [showReportModal, setShowReportModal] = useState(false);
    const [showNotesDrawer, setShowNotesDrawer] = useState(false);
    const [showNotebookModal, setShowNotebookModal] = useState(false);
    const [showStatsModal, setShowStatsModal] = useState(false);

    // NEW FOR ANALYTICS
    const viewingLoggedRef = useRef(false);
    const startTimeRef = useRef<number>(Date.now());

    useEffect(() => {
        if (!viewingLoggedRef.current) {
            viewingLoggedRef.current = true;
            // Send view event
            api.post(`/api/v1/questions/${q.id}/view`).catch(() => { });
        }
    }, [q.id]);

    const [activeTab, setActiveTab] = useState<'gabarito' | 'chat' | 'history' | null>(null);

    const [chatMessages, setChatMessages] = useState<any[]>([]);
    const [chatInput, setChatInput] = useState('');
    const [chatTyping, setChatTyping] = useState(false);
    const [chatLoaded, setChatLoaded] = useState(false);
    const chatHistoryRef = useRef<HTMLDivElement>(null);

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

    const apiUrl = import.meta.env.VITE_API_BASE_URL || (import.meta.env.PROD ? '' : 'http://localhost:8000');

    const renderMd = (text: string) => {
        if (!text) return { __html: '' };

        let processedText = text;
        if (processedText.includes('](/storage/')) {
            processedText = processedText.replace(/\]\(\/storage\//g, `](${apiUrl}/storage/`);
        }
        if (processedText.includes('src="/storage/')) {
            processedText = processedText.replace(/src="\/storage\//g, `src="${apiUrl}/storage/`);
        }

        try {
            return { __html: marked.parse(processedText) as string };
        } catch (e) {
            return { __html: processedText };
        }
    };

    const handleToggleFavorite = async () => {
        if (favLoading) return;
        setFavLoading(true);
        // Optimistic UI
        setIsFavorite(!isFavorite);
        try {
            const res = await api.post(`/api/v1/questions/${q.id}/favorite`);
            setIsFavorite(res.data.is_favorite);
            if (res.data.is_favorite) {
                toast.success('Questão adicionada aos favoritos.');
            } else {
                toast.success('Questão removida dos favoritos.');
            }
        } catch (e) {
            setIsFavorite(!isFavorite);
            toast.error('Erro ao favoritar questão.');
        } finally {
            setFavLoading(false);
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

            // Calc time spent
            const timeSpent = Math.floor((Date.now() - startTimeRef.current) / 1000);
            payload.time_spent_seconds = timeSpent;

            const res = await api.post(`/api/v1/questions/${q.id}/answer`, payload);
            const data = res.data;
            setAnswered(true);
            setIsCorrect(data.correct ?? null);
            setCorrectAnswer(data.correct_answer ?? null);
            setExplanation(data.explanation || '');
            setActiveTab('gabarito');
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
        setActiveTab(null);
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
        const nextState = activeTab !== 'chat';
        setActiveTab(nextState ? 'chat' : null);
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
            const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token') || localStorage.getItem('token');
            const baseUrl = api.defaults.baseURL || '';

            const res = await fetch(`${baseUrl}/api/v1/questions/${q.id}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'include',
                body: JSON.stringify({ message: msg })
            });

            if (!res.ok) {
                const errorText = await res.text();
                let errorMessage = 'Erro ao conectar à IA.';
                try {
                    const errorJson = JSON.parse(errorText);
                    if (errorJson.status === 'quota_exceeded') {
                        setChatMessages(prev => [...prev, { role: 'system', message: errorJson.message, upgrade_url: errorJson.upgrade_url, id: Date.now() }]);
                        setChatTyping(false);
                        return;
                    }
                    errorMessage = errorJson.message || errorMessage;
                } catch (e) {
                    errorMessage = `Erro ${res.status}: Não foi possível processar sua solicitação agora.`;
                }

                setChatMessages(prev => [...prev, { role: 'assistant', message: errorMessage, id: Date.now() }]);
                setChatTyping(false);
                return;
            }

            // Prepare a placeholder for the streamed reply
            const replyId = Date.now();
            setChatMessages(prev => [...prev, { role: 'assistant', message: '', id: replyId }]);

            const reader = res.body?.getReader();
            const decoder = new TextDecoder('utf-8');
            let done = false;
            let buffer = '';

            let firstChunkReceived = false;

            while (reader && !done) {
                const { value, done: doneReading } = await reader.read();
                done = doneReading;
                if (value) {
                    buffer += decoder.decode(value, { stream: true });

                    // SSE format: data: <content>\n\n
                    // Some providers might send multiple data blocks in one chunk
                    const parts = buffer.split('\n\n');
                    buffer = parts.pop() || '';

                    for (const part of parts) {
                        const lines = part.split('\n');
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const content = line.substring(6);
                                if (content) {
                                    if (!firstChunkReceived) {
                                        setChatTyping(false);
                                        firstChunkReceived = true;
                                    }

                                    setChatMessages(prev =>
                                        prev.map(m => m.id === replyId ? { ...m, message: m.message + content } : m)
                                    );
                                    scrollToBottom();
                                }
                            }
                        }
                    }
                }
            }

            // Flush remaining buffer if any
            if (buffer.startsWith('data: ')) {
                const finalContent = buffer.substring(6);
                if (finalContent) {
                    if (!firstChunkReceived) setChatTyping(false);
                    setChatMessages(prev =>
                        prev.map(m => m.id === replyId ? { ...m, message: m.message + finalContent } : m)
                    );
                    scrollToBottom();
                }
            }

            setChatTyping(false);
        } catch (e) {
            setChatTyping(false);
            setChatMessages(prev => [...prev, { role: 'assistant', message: 'Erro de comunicação ao conectar à IA.', id: Date.now() }]);
        }
    };

    const toggleHistory = async () => {
        const nextState = activeTab !== 'history';
        setActiveTab(nextState ? 'history' : null);
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

    const toggleGabarito = () => {
        setActiveTab(activeTab === 'gabarito' ? null : 'gabarito');
    };

    return (
        <div className="qb-card relative">
            <div className="flex justify-between items-start mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">
                <div className="qb-card-meta !mb-0 flex-1">
                    {q.source === 'ai_generated' && <span className="qb-badge qb-badge-ai">✨ INÉDITA</span>}
                    {q.year && <span className="qb-badge qb-badge-origin">{q.year}</span>}
                    {q.organization && <span className="qb-badge qb-badge-origin">{q.organization}</span>}
                    <span className="qb-badge qb-badge-origin">{q.subjects.map(s => s.name).join(', ')}</span>
                    <span className={`qb-badge ${dc.class}`}>{dc.label}</span>
                </div>

                <div className="flex gap-1.5 ml-2 sticky top-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm p-1 rounded-lg border border-gray-100 dark:border-slate-800 shadow-sm z-10">
                    <button
                        onClick={handleToggleFavorite}
                        title="Favoritar"
                        disabled={favLoading}
                        className={`p-1.5 rounded-md transition-colors ${isFavorite ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-600 dark:hover:text-gray-300'}`}
                    >
                        {isFavorite ? '⭐' : '☆'}
                    </button>
                    <button
                        onClick={() => setShowNotebookModal(true)}
                        title="Salvar em Cadernos"
                        className={`p-1.5 rounded-md transition-colors ${notebookIds && notebookIds.length > 0 ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-600 dark:hover:text-gray-300'}`}
                    >
                        📁
                    </button>
                    <button
                        onClick={() => setShowNotesDrawer(true)}
                        title="Minhas Anotações"
                        className={`p-1.5 rounded-md transition-colors ${hasNotes ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-600 dark:hover:text-gray-300'}`}
                    >
                        📝
                    </button>
                    <button
                        onClick={() => setShowReportModal(true)}
                        title="Reportar Erro"
                        className="p-1.5 rounded-md text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                    >
                        🚩
                    </button>
                    <button
                        onClick={() => setShowStatsModal(true)}
                        title="Estatísticas (Somente Admin)"
                        className="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-gray-300 transition-colors"
                    >
                        📊
                    </button>
                </div>
            </div>

            <div className="qb-card-meta !mt-0">
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
                    {[...(q.alternatives || [])].sort((a, b) => a.label.localeCompare(b.label)).map(alt => (
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
                    {[...(q.alternatives || [])].sort((a, b) => a.label.localeCompare(b.label)).map(alt => (
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
                                    <div className="qb-alt-text prose prose-sm max-w-none text-slate-700 dark:text-slate-300" style={{ wordBreak: 'break-word', fontSize: '15px' }} dangerouslySetInnerHTML={renderMd(alt.content)} />
                                )}
                                {alt.image_path && (
                                    <img src={alt.image_path.startsWith('http') ? alt.image_path : `${apiUrl}/storage/${alt.image_path}`} alt={`Alternativa ${alt.label}`} style={{ maxWidth: '100%', height: 'auto', borderRadius: '4px', objectFit: 'contain' }} />
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
                    <div className="flex overflow-x-auto whitespace-nowrap gap-2 pb-2 items-center w-full max-w-full" style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}>
                        <button
                            className={`qb-action-btn transition-colors duration-200 ${activeTab === 'gabarito' ? '!bg-indigo-600 !text-white !border-indigo-600 shadow-sm' : ''}`}
                            onClick={toggleGabarito}
                            style={{ flexShrink: 0 }}
                        >
                            📖 Gabarito Comentado
                        </button>
                        <button
                            className={`qb-action-btn transition-colors duration-200 ${activeTab === 'chat' ? '!bg-indigo-600 !text-white !border-indigo-600 shadow-sm' : ''}`}
                            onClick={toggleChat}
                            style={{ flexShrink: 0 }}
                        >
                            {activeTab === 'chat' ? '▲ Ocultar Chat' : '✨ Tirar Dúvida'}
                        </button>
                        <button
                            className={`qb-action-btn transition-colors duration-200 ${activeTab === 'history' ? '!bg-indigo-600 !text-white !border-indigo-600 shadow-sm' : ''}`}
                            onClick={toggleHistory}
                            style={{ flexShrink: 0 }}
                        >
                            📜 Meu Histórico
                        </button>
                        <button className="qb-action-btn retry" onClick={resetCard} style={{ flexShrink: 0 }}>
                            <span>🔄 Tentar Novamente</span>
                        </button>
                    </div>
                )}
            </div>

            <AnimatePresence mode="wait">
                {/* Independent Correctness Indicator */}
                {answered && !isDiscursive && (
                    <motion.div
                        initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}
                        className={`qb-feedback rounded-2xl shadow-sm mb-4 ${isCorrect ? 'correct' : 'incorrect'}`}
                    >
                        <div className="qb-feedback-title !mb-0">
                            <span>{isCorrect ? '✅ Resposta Correta!' : '❌ Resposta Incorreta'}</span>
                            {!isCorrect && correctAnswer && (
                                <span style={{ fontWeight: 400, fontSize: '12px', color: '#64748b' }}>
                                    {" "}Correta: <strong style={{ color: '#065f46' }}>{correctAnswer}</strong>
                                </span>
                            )}
                        </div>
                    </motion.div>
                )}

                {answered && isDiscursive && activeTab === 'gabarito' && (
                    <motion.div
                        key="feedback-discursive"
                        initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} transition={{ duration: 0.3 }}
                        className="qb-feedback border-l-4 border-indigo-500 bg-indigo-50 p-4 mb-4 rounded-r-lg shadow-sm"
                    >
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
                    </motion.div>
                )}

                {answered && !isDiscursive && activeTab === 'gabarito' && explanation && (
                    <motion.div
                        key="feedback-objective"
                        initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} transition={{ duration: 0.3 }}
                        className="qb-explanation mt-0 mb-4"
                    >
                        <h4>📖 Resolução Comentada</h4>
                        <div className="qb-explanation-text" dangerouslySetInnerHTML={renderMd(explanation)} />
                    </motion.div>
                )}

                {answered && activeTab === 'history' && (
                    <motion.div
                        key="history-panel"
                        initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} transition={{ duration: 0.3 }}
                        className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4 overflow-hidden w-full"
                    >
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
                    </motion.div>
                )}

                {answered && activeTab === 'chat' && (
                    <motion.div
                        key="chat-panel"
                        initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} transition={{ duration: 0.3 }}
                        className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4 overflow-hidden w-full"
                    >
                        <div className="qb-chat-history space-y-2 p-1 overflow-y-auto max-h-64" ref={chatHistoryRef}>
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
                        <div className="flex gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                            <input
                                type="text"
                                value={chatInput}
                                onChange={e => setChatInput(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && sendChat()}
                                placeholder={`Qual sua dúvida, ${user?.name?.split(' ')[0] || 'estudante'}?`}
                                disabled={chatTyping}
                                className="flex-1 rounded-md border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-100 shadow-sm text-xs px-3 py-2 focus:ring-1 focus:ring-indigo-500"
                            />
                            <button onClick={sendChat} disabled={chatTyping || !chatInput.trim()} className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-xs font-medium disabled:opacity-50 transition-colors">Enviar</button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <DialogReportQuestion
                isOpen={showReportModal}
                onClose={() => setShowReportModal(false)}
                questionId={q.id}
            />

            <DrawerQuestionNotes
                isOpen={showNotesDrawer}
                onClose={() => setShowNotesDrawer(false)}
                questionId={q.id}
                onNoteSaved={() => setHasNotes(true)}
            />

            <DialogNotebookManager
                isOpen={showNotebookModal}
                onClose={() => setShowNotebookModal(false)}
                questionId={q.id}
                initialNotebookIds={notebookIds}
                onSaved={(ids) => setNotebookIds(ids)}
            />

            <DialogQuestionStats
                isOpen={showStatsModal}
                onClose={() => setShowStatsModal(false)}
                questionId={q.id}
            />
        </div >
    );
}
