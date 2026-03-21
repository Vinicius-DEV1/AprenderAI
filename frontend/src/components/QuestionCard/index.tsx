import { useState, useRef, useEffect, memo, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { toast } from 'sonner';
import { useQueryClient } from '@tanstack/react-query';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import api from '../../api/axios';
import { renderMd } from '../../utils/markdown';

// Modals
import DialogReportQuestion from './modals/DialogReportQuestion';
import DialogNotebookManager from './modals/DialogNotebookManager';
import DialogQuestionStats from './modals/DialogQuestionStats';
import AdminRevertToTriageModal from './modals/AdminRevertToTriageModal';
import { useNavigate } from 'react-router-dom';

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
    institution?: string;
    role?: string;
    arquivo_origem?: string;
    source: string;
    subjects: { id: number; name: string }[];
    topics: { id: number; name: string }[];
    difficulty: 'easy' | 'medium' | 'hard';
    statement_html: string;
    statement: string;
    image_path?: string;
    tipo_questao?: 'Objetiva' | 'Discursiva' | 'Redação';
    discursive_answer?: any;
    explanation?: string;
    alternatives: Alternative[];
    already_answered?: boolean;
    was_correct?: boolean;
    is_favorite?: boolean;
    has_notes?: boolean;
    notebook_ids?: number[];

    // Admin only
    difficulty_reasoning?: string;
    triage_logs?: any[];
    reports?: any[];
}

interface QuestionCardProps {
    question: Question;
    mode?: 'bank' | 'result' | 'view';
    userAnswer?: string | null;
    isCorrect?: boolean | null;
    simulationId?: number | null;
}

const QuestionCard = memo(({
    question: q,
    mode = 'bank',
    userAnswer: initialUserAnswer = null,
    isCorrect: initialIsCorrect = null,
    simulationId = null
}: QuestionCardProps) => {
    const { aiName } = useConfigStore();
    const { user } = useAuthStore();
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const isAdmin = user?.role === 'admin';

    const isDiscursive = q.tipo_questao === 'Discursiva';
    const isResultMode = mode === 'result';

    const [selectedAnswer, setSelectedAnswer] = useState<string | null>(initialUserAnswer || null);
    const [discursiveAnswers, setDiscursiveAnswers] = useState<Record<string, string>>({});
    const [struckLabels, setStruckLabels] = useState<string[]>([]);

    const [answered, setAnswered] = useState(isResultMode);
    const [isCorrect, setIsCorrect] = useState<boolean | null>(
        isResultMode
            ? (initialIsCorrect !== null ? initialIsCorrect : (initialUserAnswer ? q.was_correct ?? null : null))
            : (q.was_correct ?? null)
    );
    const [correctAnswer, setCorrectAnswer] = useState<string | null>(isResultMode ? q.alternatives.find(a => a.is_correct)?.label || null : null);
    const [explanation, setExplanation] = useState<string | null>(isResultMode ? q.explanation || null : null);
    const [submitting, setSubmitting] = useState(false);

    // Feature states
    const [isFavorite, setIsFavorite] = useState(q.is_favorite || false);
    const [favLoading, setFavLoading] = useState(false);

    // Notes state tracking to show highlighted icon
    const [hasNotes, setHasNotes] = useState(q.has_notes || false);
    // List of notebook IDs this question belongs to
    const [notebookIds, setNotebookIds] = useState<number[]>((q.notebook_ids || []).map(id => Number(id)));

    // Sync state if question prop changes
    useEffect(() => {
        setNotebookIds((q.notebook_ids || []).map(id => Number(id)));
        setStruckLabels([]);
    }, [q.id, q.notebook_ids]);

    const [showReportModal, setShowReportModal] = useState(false);
    const [showNotebookModal, setShowNotebookModal] = useState(false);
    const [showStatsModal, setShowStatsModal] = useState(false);
    const [showRevertModal, setShowRevertModal] = useState(false);

    // Notes panel state
    const [notes, setNotes] = useState<any[]>([]);
    const [newNote, setNewNote] = useState('');
    const [loadingNotes, setLoadingNotes] = useState(false);
    const [savingNote, setSavingNote] = useState(false);
    const [notesLoaded, setNotesLoaded] = useState(false);
    const [editingNoteId, setEditingNoteId] = useState<number | null>(null);

    // Analytics (only for bank mode)
    const viewingLoggedRef = useRef(false);
    const startTimeRef = useRef<number>(Date.now());

    useEffect(() => {
        if (mode === 'bank' && !viewingLoggedRef.current) {
            viewingLoggedRef.current = true;
            api.post(`/api/v1/questions/${q.id}/view`).catch(() => { });
        }
    }, [q.id, mode]);

    const statementHtml = useMemo(() => renderMd(q.statement), [q.statement]);
    const explanationHtml = useMemo(() => renderMd(explanation || 'Sem comentário disponível.'), [explanation]);

    const renderedAlternatives = useMemo(() => {
        return [...q.alternatives]
            .sort((a, b) => a.label.localeCompare(b.label))
            .map(alt => ({
                ...alt,
                renderedContent: renderMd(alt.content)
            }));
    }, [q.alternatives]);

    const [activeTab, setActiveTab] = useState<'gabarito' | 'chat' | 'history' | 'notes' | 'admin_history' | null>(isResultMode ? 'gabarito' : null);

    // Horizontal Scroll Indicator Logic
    const actionsScrollRef = useRef<HTMLDivElement>(null);
    const [showRightArrow, setShowRightArrow] = useState(false);

    const checkScroll = () => {
        const el = actionsScrollRef.current;
        if (el) {
            const canScrollRight = el.scrollWidth > el.clientWidth && (el.scrollLeft + el.clientWidth) < (el.scrollWidth - 10);
            setShowRightArrow(canScrollRight);
        }
    };

    useEffect(() => {
        if (answered) {
            const timeout = setTimeout(checkScroll, 100); // Wait for render
            return () => clearTimeout(timeout);
        }
    }, [answered, activeTab]);

    useEffect(() => {
        const el = actionsScrollRef.current;
        if (el) {
            el.addEventListener('scroll', checkScroll);
            window.addEventListener('resize', checkScroll);
            checkScroll(); // Initial check
            return () => {
                el.removeEventListener('scroll', checkScroll);
                window.removeEventListener('resize', checkScroll);
            };
        }
    }, [answered]);

    const [chatMessages, setChatMessages] = useState<any[]>([]);
    const [chatInput, setChatInput] = useState('');
    const [chatTyping, setChatTyping] = useState(false);
    const [chatLoaded, setChatLoaded] = useState(false);
    const chatHistoryRef = useRef<HTMLDivElement>(null);

    const [historyData, setHistoryData] = useState<any[]>([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [historyLoaded, setHistoryLoaded] = useState(false);


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


    const sanitizeHTML = (html: string) => {
        if (!html) return '';
        return html
            .replace(/<script\b[^>]*>([\s\S]*?)<\/script>/gim, "")
            .replace(/on\w+=(['"])(.*?)\1/gim, "")
            .replace(/javascript:/gim, "");
    };

    const decodeEntities = (html: string) => {
        const txt = document.createElement("textarea");
        txt.innerHTML = html;
        return txt.value;
    };

    const handleToggleFavorite = async () => {
        if (favLoading) return;
        setFavLoading(true);
        setIsFavorite(!isFavorite);
        try {
            const res = await api.post(`/api/v1/questions/${q.id}/favorite`);
            setIsFavorite(res.data.is_favorite);
            if (res.data.is_favorite) toast.success('Questão adicionada aos favoritos.');
            else toast.success('Questão removida dos favoritos.');
        } catch (e) {
            setIsFavorite(!isFavorite);
            toast.error('Erro ao favoritar questão.');
        } finally {
            setFavLoading(false);
        }
    };

    const selectAnswer = (letter: string) => {
        if (!answered && !isDiscursive && !struckLabels.includes(letter)) setSelectedAnswer(letter);
    };

    const handleRightClickAlt = (e: React.MouseEvent, label: string) => {
        e.preventDefault();
        if (answered) return;
        setStruckLabels(prev =>
            prev.includes(label) ? prev.filter(l => l !== label) : [...prev, label]
        );
    };

    const handleDiscursiveChange = (label: string, text: string) => {
        if (!answered) setDiscursiveAnswers(prev => ({ ...prev, [label]: text }));
    };

    const submitAnswer = async () => {
        if (submitting || answered) return;
        if (!isDiscursive && !selectedAnswer) return;
        if (isDiscursive && Object.keys(discursiveAnswers).length === 0) return;

        setSubmitting(true);
        try {
            let payload: any = { selected_answer: selectedAnswer };
            if (isDiscursive) payload = { respostas_discursivas: discursiveAnswers };
            const timeSpent = Math.floor((Date.now() - startTimeRef.current) / 1000);
            payload.time_spent_seconds = timeSpent;

            const res = await api.post(`/api/v1/questions/${q.id}/answer`, payload);
            const data = res.data;
            setAnswered(true);
            setIsCorrect(data.correct ?? null);
            setCorrectAnswer(data.correct_answer ?? null);
            setExplanation(data.explanation || '');
            setActiveTab('gabarito');
            queryClient.invalidateQueries({ queryKey: ['engagement'] });
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
        } catch (e) { }
    };

    const toggleChat = async () => {
        const nextState = activeTab !== 'chat';
        setActiveTab(nextState ? 'chat' : null);
        if (nextState && !chatLoaded) await loadChatHistory();
        if (nextState) setTimeout(scrollToBottom, 50);
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
            const endpoint = simulationId
                ? `${baseUrl}/api/v1/simulations/${simulationId}/questions/${q.id}/chat`
                : `${baseUrl}/api/v1/questions/${q.id}/chat`;

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'include',
                body: JSON.stringify({ message: msg, simulation_id: simulationId })
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
                    errorMessage = `Erro ${res.status}: Tente novamente mais tarde.`;
                }
                setChatMessages(prev => [...prev, { role: 'assistant', message: errorMessage, id: Date.now() }]);
                setChatTyping(false);
                return;
            }

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
                    const parts = buffer.split('\n\n');
                    buffer = parts.pop() || '';
                    for (const part of parts) {
                        const lines = part.split('\n');
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const content = line.substring(6);
                                // REMOVED check: if (content) {
                                // We want to allow empty content to preserve newlines!
                                if (!firstChunkReceived) {
                                    setChatTyping(false);
                                    firstChunkReceived = true;
                                }
                                setChatMessages(prev => prev.map(m => m.id === replyId ? { ...m, message: m.message + (content || '') + (line === 'data: ' ? '\n' : '') } : m));
                                scrollToBottom();
                            }
                        }
                    }
                }
            }
            setChatTyping(false);
        } catch (e) {
            setChatTyping(false);
            setChatMessages(prev => [...prev, { role: 'assistant', message: 'Erro de comunicação.', id: Date.now() }]);
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
            } catch (e) { } finally { setHistoryLoading(false); }
        }
    };

    const loadNotes = async () => {
        setLoadingNotes(true);
        try {
            const res = await api.get(`/api/v1/questions/${q.id}/notes`);
            setNotes(res.data);
            setNotesLoaded(true);
        } catch (error) { toast.error('Erro ao carregar anotações.'); }
        finally { setLoadingNotes(false); }
    };

    const toggleNotes = async () => {
        const nextState = activeTab !== 'notes';
        setActiveTab(nextState ? 'notes' : null);
        if (nextState && !notesLoaded) await loadNotes();
    };

    const handleSaveNote = async () => {
        const contentToSave = editorRef.current ? editorRef.current.innerHTML : newNote;
        if (!contentToSave.trim() || contentToSave === '<br>') return;
        setSavingNote(true);
        try {
            const sanitizedContent = sanitizeHTML(contentToSave);
            if (editingNoteId) {
                const res = await api.put(`/api/v1/questions/notes/${editingNoteId}`, { content: sanitizedContent });
                setNotes(notes.map(n => n.id === editingNoteId ? res.data.note : n));
                setEditingNoteId(null);
                toast.success('Anotação atualizada!');
            } else {
                const res = await api.post(`/api/v1/questions/${q.id}/notes`, { content: sanitizedContent });
                setNotes([res.data.note, ...notes]);
                setHasNotes(true);
                toast.success('Anotação salva!');
            }
            setNewNote('');
            if (editorRef.current) editorRef.current.innerHTML = '';
        } catch (error) { toast.error('Erro ao salvar anotação.'); }
        finally { setSavingNote(false); }
    };

    const handleDeleteNote = async (id: number) => {
        try {
            await api.delete(`/api/v1/questions/notes/${id}`);
            const updatedNotes = notes.filter(n => n.id !== id);
            setNotes(updatedNotes);
            if (updatedNotes.length === 0) setHasNotes(false);
            toast.success('Anotação excluída.');
        } catch (error) { toast.error('Erro ao excluir anotação.'); }
    };

    const handleEditNote = (note: any) => {
        const content = decodeEntities(note.content);
        setNewNote(content);
        setEditingNoteId(note.id);
        if (editorRef.current) { editorRef.current.innerHTML = content; editorRef.current.focus(); }
    };

    const editorRef = useRef<HTMLDivElement>(null);
    const applyFormatting = (command: string, value: string = '') => {
        document.execCommand(command, false, value);
        if (editorRef.current) setNewNote(editorRef.current.innerHTML);
    };

    const difficultyMap = {
        easy: { class: 'qb-badge-easy', label: 'Fácil' },
        medium: { class: 'qb-badge-medium', label: 'Média' },
        hard: { class: 'qb-badge-hard', label: 'Difícil' }
    };
    const dc = difficultyMap[q.difficulty] || difficultyMap.medium;

    return (
        <div className={`qb-card relative ${isAdmin ? 'border-indigo-200 dark:border-indigo-900/50' : ''}`}>
            {isAdmin && (
                <div className="absolute -top-3 left-4 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm z-20 flex items-center gap-1">
                    <span className="animate-pulse">🛡️</span> ADMIN VIEW
                </div>
            )}
            <div className="qb-card-header flex justify-between items-start mb-4 border-b border-gray-100 dark:border-slate-800 pb-3">
                <div className="qb-card-meta !mb-0 flex-1 flex flex-wrap gap-1.5 items-center">
                    {q.source === 'ai_generated' && <span className="qb-badge qb-badge-ai">✨ INÉDITA</span>}
                    {q.year && <span className="qb-badge qb-badge-origin !bg-slate-100 !text-slate-600 border-slate-200">{q.year}</span>}
                    {q.organization && <span className="qb-badge qb-badge-origin !bg-slate-100 !text-slate-600 border-slate-200">{q.organization}</span>}

                    {/* Subjects - Indigo */}
                    {q.subjects.map(s => (
                        <span key={s.id} className="qb-badge !bg-indigo-50 !text-indigo-600 border-indigo-100 uppercase tracking-tighter">
                            {s.name}
                        </span>
                    ))}

                    {/* Topics - Emerald */}
                    {(q.topics || []).map(t => (
                        <span key={t.id} className="qb-badge !bg-emerald-50 !text-emerald-600 border-emerald-100 uppercase tracking-tighter">
                            {t.name}
                        </span>
                    ))}

                    <span className={`qb-badge ${dc.class}`}>{dc.label}</span>
                    {q.already_answered && !answered && (
                        <span className={`qb-badge ${q.was_correct ? 'qb-badge-correct' : 'qb-badge-incorrect'} ml-1`}>
                            {q.was_correct ? '✓ Resolvida' : '✗ Tentada'}
                        </span>
                    )}
                </div>

                <div className="flex gap-1.5 ml-2 sticky top-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm p-1 rounded-lg border border-gray-100 dark:border-slate-800 shadow-sm z-10">
                    <button onClick={handleToggleFavorite} disabled={favLoading} className={`p-1.5 rounded-md transition-colors ${isFavorite ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800'}`}>{isFavorite ? '⭐' : '☆'}</button>
                    <button onClick={() => setShowNotebookModal(true)} className={`p-1.5 rounded-md transition-colors ${notebookIds.length > 0 ? 'bg-indigo-100 text-indigo-700' : 'text-gray-400 hover:bg-gray-100'}`}>📁</button>
                    <button onClick={() => setShowReportModal(true)} className="p-1.5 rounded-md text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">🚩</button>
                    {user?.role === 'admin' && <button onClick={() => setShowStatsModal(true)} className="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 transition-colors" title="Estatísticas">📊</button>}
                    {isAdmin && (
                        <>
                            <button onClick={() => navigate(`/admin/questions/${q.id}/edit`)} className="p-1.5 rounded-md text-gray-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors" title="Editar Questão">✏️</button>
                            <button onClick={() => setShowRevertModal(true)} className="p-1.5 rounded-md text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition-colors" title="Retornar para Triagem">🔄</button>
                            {(() => {
                                const idParam = q.arquivo_origem ? btoa(q.arquivo_origem) : 'null';
                                const examParams = new URLSearchParams();
                                if (!q.arquivo_origem) {
                                    if (q.year) examParams.append('year', q.year.toString());
                                    if (q.organization) examParams.append('organization', q.organization);
                                    if (q.institution) examParams.append('institution', q.institution);
                                    if (q.role) examParams.append('role', q.role);
                                }
                                const examUrl = `/admin/provas/${idParam}${examParams.toString() ? '?' + examParams.toString() : ''}`;

                                return (
                                    <button
                                        onClick={() => window.open(examUrl, '_blank')}
                                        className="p-1.5 rounded-md text-gray-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                                        title="Ver Prova Completa"
                                    >
                                        📄
                                    </button>
                                );
                            })()}
                        </>
                    )}
                </div>
            </div>

            <div className="qb-statement prose prose-sm max-w-none text-slate-700 dark:text-slate-300" dangerouslySetInnerHTML={statementHtml} />

            {q.image_path && (
                <div className="mb-4">
                    <img
                        src={q.image_path.startsWith('http') ? q.image_path : `${apiUrl}/storage/${q.image_path.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')}
                        alt="Imagem da questão"
                        className="qb-question-image shadow-sm border border-gray-100 dark:border-slate-800"
                    />
                </div>
            )}

            {isDiscursive ? (
                <div className="qb-discursive-list space-y-6 mt-4">
                    {q.alternatives.map(alt => (
                        <div key={alt.id} className="qb-discursive-item">
                            <div className="font-bold text-slate-800 dark:text-slate-200 mb-2">{alt.label.toLowerCase()}) {alt.content}</div>
                            <textarea className="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-indigo-500 outline-none dark:bg-slate-800 text-sm" rows={4} value={discursiveAnswers[alt.label] || ''} onChange={(e) => handleDiscursiveChange(alt.label, e.target.value)} disabled={answered} />
                        </div>
                    ))}
                </div>
            ) : (
                <div className="qb-alternatives-list">
                    {renderedAlternatives.map(alt => (
                        <div
                            key={alt.id}
                            className={`qb-alt ${selectedAnswer === alt.label && !answered ? 'selected' : ''} ${answered && alt.label === (correctAnswer || (alt.is_correct ? alt.label : null)) ? 'correct-reveal' : ''} ${answered && selectedAnswer === alt.label && alt.label !== correctAnswer ? 'incorrect-reveal' : ''} ${answered ? 'disabled' : ''} ${!answered && struckLabels.includes(alt.label) ? 'opacity-40 grayscale' : ''}`}
                            onClick={() => selectAnswer(alt.label)}
                            onContextMenu={(e) => handleRightClickAlt(e, alt.label)}
                        >
                            <div className="qb-alt-letter" style={{ textDecoration: !answered && struckLabels.includes(alt.label) ? 'line-through' : 'none' }}>{alt.label}</div>
                            <div className="flex flex-col gap-2 flex-grow overflow-hidden">
                                {alt.content && <div className="qb-alt-text prose prose-sm max-w-none text-slate-700 dark:text-slate-300" dangerouslySetInnerHTML={alt.renderedContent} />}
                                {alt.image_path && (
                                    <img
                                        src={alt.image_path.startsWith('http') ? alt.image_path : `${apiUrl}/storage/${alt.image_path.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')}
                                        alt={alt.label}
                                        className="qb-alt-image"
                                    />
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="qb-card-actions mt-6 relative">
                {!answered && (
                    <button className="qb-action-btn primary" onClick={submitAnswer} disabled={submitting || (!isDiscursive && !selectedAnswer)}>
                        {!submitting ? '📝 Responder' : '⏳ Enviando...'}
                    </button>
                )}
                {answered && (
                    <>
                        <div
                            ref={actionsScrollRef}
                            className="flex overflow-x-auto whitespace-nowrap gap-2 pb-2 items-center w-full no-scrollbar px-1"
                            style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                        >
                            <button className={`qb-action-btn ${activeTab === 'gabarito' ? '!bg-indigo-600 !text-white' : ''}`} onClick={() => setActiveTab(activeTab === 'gabarito' ? null : 'gabarito')}>📖 Gabarito Comentado</button>
                            <button className={`qb-action-btn ${activeTab === 'chat' ? '!bg-indigo-600 !text-white' : ''}`} onClick={toggleChat}>✨ Tirar Dúvida</button>
                            <button className={`qb-action-btn ${activeTab === 'history' ? '!bg-indigo-600 !text-white' : ''}`} onClick={toggleHistory}>📜 Meu Histórico</button>
                            <button className={`qb-action-btn ${activeTab === 'notes' ? '!bg-indigo-600 !text-white' : ''} ${hasNotes && activeTab !== 'notes' ? '!bg-emerald-50 !text-emerald-700 !border-emerald-200' : ''}`} onClick={toggleNotes}>
                                {hasNotes ? '📝' : '✏️'} Minhas Anotações
                            </button>
                            {isAdmin && (
                                <button className={`qb-action-btn ${activeTab === 'admin_history' ? '!bg-indigo-600 !text-white' : ''} !border-indigo-200 !text-indigo-600 bg-indigo-50/50`} onClick={() => setActiveTab(activeTab === 'admin_history' ? null : 'admin_history')}>
                                    🛡️ Admin: Histórico & IA
                                </button>
                            )}
                            {mode !== 'result' && <button className="qb-action-btn retry" onClick={resetCard}>🔄 Tentar Novamente</button>}
                        </div>

                        {/* Right Scroll Indicator */}
                        <AnimatePresence>
                            {showRightArrow && (
                                <motion.div
                                    initial={{ opacity: 0, x: 10 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    exit={{ opacity: 0, x: 10 }}
                                    className="absolute right-0 top-0 bottom-2 w-12 flex items-center justify-end pr-1 pointer-events-none bg-gradient-to-l from-white via-white/80 to-transparent dark:from-slate-900 dark:via-slate-900/80 z-10"
                                >
                                    <div className="bg-indigo-600/10 text-indigo-600 rounded-full p-1 qb-animate-pulse-horizontal">
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </>
                )}
            </div>

            <AnimatePresence>
                {answered && !isDiscursive && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className={`qb-feedback rounded-2xl shadow-sm mb-4 ${isCorrect ? 'correct' : 'incorrect'}`}>
                        <div className="qb-feedback-title !mb-0">
                            <span>{isCorrect ? '✅ Resposta Correta!' : '❌ Resposta Incorreta'}</span>
                            {!isCorrect && correctAnswer && (
                                <span className="text-[12px] font-normal text-slate-500 ml-2">Correta: <strong className="text-emerald-700">{correctAnswer}</strong></span>
                            )}
                        </div>
                    </motion.div>
                )}

                {answered && activeTab === 'gabarito' && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} className="qb-explanation-container">
                        {isDiscursive ? (
                            <div className="qb-feedback border-l-4 border-indigo-500 bg-indigo-50 p-4 mb-4 rounded-r-lg shadow-sm w-full">
                                <h4 className="font-bold text-indigo-900 mb-4 flex items-center gap-2">📋 Espelho de Correção</h4>
                                <div className="space-y-4">
                                    {typeof q.discursive_answer === 'object' ? Object.entries(q.discursive_answer).map(([k, v]) => (
                                        <div key={k} className="bg-white p-3 rounded-md border border-indigo-100 shadow-sm"><strong className="text-indigo-800">Padrao ({k}):</strong><div className="text-sm mt-1 prose prose-sm max-w-none dark:text-slate-300" dangerouslySetInnerHTML={renderMd(String(v))} /></div>
                                    )) : <div className="bg-white p-3 rounded-md shadow-sm prose prose-sm max-w-none dark:text-slate-300" dangerouslySetInnerHTML={renderMd(q.discursive_answer || "Não disponível.")} />}
                                </div>
                            </div>
                        ) : (
                            <div className="qb-explanation mt-0 mb-4 w-full">
                                <h4>📖 Resolução Comentada</h4>
                                <div className="qb-explanation-text" dangerouslySetInnerHTML={explanationHtml} />
                            </div>
                        )}
                    </motion.div>
                )}

                {/* Histórico, Chat e Anotações seguem o mesmo padrão de Motion Div */}
                {answered && activeTab === 'history' && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border p-4 mb-4 w-full">
                        <h4 className="text-indigo-600 font-bold text-xs mb-3 uppercase tracking-wider">📜 Seu Histórico</h4>
                        {historyLoading ? <p className="text-xs text-gray-400">Carregando...</p> : historyData.length === 0 ? <p className="text-xs text-gray-400">Nenhum registro.</p> : historyData.map((h, i) => (
                            <div key={i} className="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                                <span className="text-[10px] text-gray-400">{formatDate(h.answered_at)}</span>
                                <span className="text-xs font-bold">Ref: {h.selected_answer}</span>
                                <span className={`qb-badge ${h.is_correct ? 'qb-badge-correct' : 'qb-badge-incorrect'}`}>{h.is_correct ? 'Acerto' : 'Erro'}</span>
                            </div>
                        ))}
                    </motion.div>
                )}

                {answered && activeTab === 'chat' && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="bg-slate-50 dark:bg-slate-800 rounded-2xl shadow-sm border p-4 mb-4 w-full">
                        <div className="qb-chat-history space-y-3 max-h-64 overflow-y-auto pr-1 mb-4" ref={chatHistoryRef}>
                            {chatMessages.map((msg, i) => (
                                <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : (msg.role === 'system' ? 'justify-center' : 'justify-start')}`}>
                                    <div className={`rounded-xl px-3 py-2 text-xs shadow-sm max-w-[90%] ${msg.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-700 border markdown-content'}`} dangerouslySetInnerHTML={msg.role === 'assistant' ? renderMd(msg.message) : undefined}>{msg.role !== 'assistant' ? msg.message : undefined}</div>
                                </div>
                            ))}
                            {chatTyping && (
                                <div className="flex justify-start">
                                    <div className="typing-dots">
                                        <div className="typing-dot" />
                                        <div className="typing-dot" />
                                        <div className="typing-dot" />
                                    </div>
                                </div>
                            )}
                        </div>
                        <div className="flex gap-2 border-t pt-3">
                            <input type="text" className="flex-1 rounded-md border text-xs px-3 py-2 outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-slate-900" placeholder="Qual sua dúvida?" value={chatInput} onChange={e => setChatInput(e.target.value)} onKeyDown={e => e.key === 'Enter' && sendChat()} disabled={chatTyping} />
                            <button onClick={sendChat} className="bg-indigo-600 text-white px-4 py-2 rounded-md text-xs font-bold shadow-sm" disabled={chatTyping || !chatInput.trim()}>Enviar</button>
                        </div>
                    </motion.div>
                )}

                {activeTab === 'notes' && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border p-4 mb-4 w-full">
                        <div className="bg-gray-50 dark:bg-slate-900 p-2 rounded-xl mb-4">
                            <div className="flex gap-1 mb-2 border-b pb-2">
                                <button onClick={() => applyFormatting('bold')} className="w-6 h-6 hover:bg-gray-200 rounded font-bold">B</button>
                                <button onClick={() => applyFormatting('italic')} className="w-6 h-6 hover:bg-gray-200 rounded italic">I</button>
                                <button onClick={() => applyFormatting('hiliteColor', '#fef08a')} className="w-6 h-6 bg-yellow-100 rounded text-[10px]">M</button>
                            </div>
                            <div ref={editorRef} contentEditable className="min-h-[80px] p-1 text-sm outline-none qb-question-text" onBlur={() => setNewNote(editorRef.current?.innerHTML || '')}></div>
                            <div className="flex justify-end mt-2">
                                <button onClick={handleSaveNote} className="bg-indigo-600 text-white px-4 py-1.5 rounded-lg text-[10px] font-bold uppercase shadow-sm">{savingNote ? '...' : 'Salvar'}</button>
                            </div>
                        </div>
                        <div className="space-y-3 max-h-48 overflow-y-auto">
                            {loadingNotes ? (
                                <p className="text-center text-[10px] text-gray-400 py-4 italic">Carregando anotações...</p>
                            ) : notes.length === 0 ? (
                                <p className="text-center text-[10px] text-gray-400 py-4 italic">Nenhuma anotação nesta questão.</p>
                            ) : (
                                notes.map(n => (
                                    <div key={n.id} className="p-3 bg-amber-50 rounded-xl border border-amber-100 relative group">
                                        <div className="absolute top-2 right-2 flex gap-1 group-hover:opacity-100 opacity-0 transition">
                                            <button onClick={() => handleEditNote(n)}>✏️</button>
                                            <button onClick={() => handleDeleteNote(n.id)}>🗑️</button>
                                        </div>
                                        <div className="text-xs pr-12" dangerouslySetInnerHTML={{ __html: sanitizeHTML(decodeEntities(n.content)) }} />
                                    </div>
                                )))}
                        </div>
                    </motion.div>
                )}

                {isAdmin && activeTab === 'admin_history' && (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="bg-slate-50 dark:bg-slate-900/50 rounded-2xl shadow-inner border border-indigo-100 dark:border-indigo-900/30 p-5 mb-4 w-full">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* IA Details */}
                            <div>
                                <h4 className="text-indigo-600 font-bold text-[11px] mb-3 uppercase tracking-wider flex items-center gap-2">
                                    ✨ INSIGHTS DA IA
                                </h4>
                                <div className="space-y-4">
                                    {q.difficulty_reasoning && (
                                        <div className="bg-white dark:bg-slate-800 p-3 rounded-xl border border-indigo-50 shadow-sm">
                                            <div className="text-[10px] font-bold text-slate-400 mb-1 uppercase">Justificativa de Dificuldade</div>
                                            <div className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed italic">"{q.difficulty_reasoning}"</div>
                                        </div>
                                    )}
                                    <div className="flex gap-2">
                                        <div className="flex-1 bg-white dark:bg-slate-800 p-3 rounded-xl border border-indigo-50 shadow-sm text-center">
                                            <div className="text-[10px] font-bold text-slate-400 mb-1 uppercase">Quality Score</div>
                                            <div className={`text-xl font-black ${(q.triage_logs?.[0]?.quality_score || 0) > 70 ? 'text-emerald-500' : 'text-amber-500'}`}>
                                                {q.triage_logs?.[0]?.quality_score || 'N/A'}
                                            </div>
                                        </div>
                                        <div className="flex-[2] bg-white dark:bg-slate-800 p-3 rounded-xl border border-indigo-50 shadow-sm">
                                            <div className="text-[10px] font-bold text-slate-400 mb-1 uppercase">Issues Detectadas</div>
                                            <div className="flex flex-wrap gap-1 mt-1">
                                                {q.triage_logs?.[0]?.issues?.length > 0 ? q.triage_logs?.[0]?.issues.map((issue: string) => (
                                                    <span key={issue} className="bg-red-50 text-red-600 text-[9px] px-1.5 py-0.5 rounded font-bold border border-red-100 uppercase">{issue}</span>
                                                )) : <span className="text-[10px] text-slate-400">Nenhuma issue crítica detectada.</span>}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Triage Timeline */}
                            <div>
                                <h4 className="text-indigo-600 font-bold text-[11px] mb-3 uppercase tracking-wider flex items-center gap-2">
                                    📜 LOGS DE TRIAGEM
                                </h4>
                                <div className="space-y-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                                    {q.triage_logs && q.triage_logs.length > 0 ? q.triage_logs.map((log: any) => (
                                        <div key={log.id} className="relative pl-4 border-l-2 border-slate-200 dark:border-slate-700 py-1">
                                            <div className="absolute -left-[5px] top-2 w-2 h-2 rounded-full bg-slate-300"></div>
                                            <div className="flex justify-between items-start">
                                                <div className="text-[10px] font-bold text-slate-800 dark:text-slate-200">
                                                    {log.type === 'ai_batch' ? '🤖 Triagem IA' : '👤 Ação Manual'}
                                                </div>
                                                <div className="text-[9px] text-slate-400">
                                                    {formatDate(log.created_at)}
                                                </div>
                                            </div>
                                            <div className="text-[10px] text-slate-500 mt-1">
                                                {log.status === 'approved' ? '✅ Aprovada' : '⚠️ Revisão manual'} por <span className="font-semibold">{log.processed_by}</span>
                                            </div>
                                            {log.changes?.reversion_reason && (
                                                <div className="mt-2 p-2 bg-amber-50 border border-amber-100 rounded text-[10px] text-amber-800 italic">
                                                    Motivo: {log.changes.reversion_reason}
                                                </div>
                                            )}
                                        </div>
                                    )) : (
                                        <div className="text-center py-8 text-slate-400 text-xs italic">Sem logs de triagem registrados.</div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Recent Reports */}
                        {q.reports && q.reports.length > 0 && (
                            <div className="mt-8 pt-6 border-t border-indigo-100">
                                <h4 className="text-red-500 font-bold text-[11px] mb-4 uppercase tracking-wider flex items-center gap-2">
                                    🚩 DENÚNCIAS RECENTES
                                </h4>
                                <div className="space-y-2">
                                    {q.reports.map((report: any) => (
                                        <div key={report.id} className="bg-red-50/50 p-3 rounded-xl border border-red-100 flex justify-between items-center">
                                            <div>
                                                <div className="text-xs font-bold text-red-900">{report.reason}</div>
                                                <div className="text-[10px] text-red-400">Enviado por {report.user_name} em {formatDate(report.created_at)}</div>
                                            </div>
                                            <div className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${report.status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                                                {report.status === 'resolved' ? 'RESOLVIDO' : 'PENDENTE'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>

            <DialogReportQuestion isOpen={showReportModal} onClose={() => setShowReportModal(false)} questionId={q.id} />
            <DialogNotebookManager isOpen={showNotebookModal} onClose={() => setShowNotebookModal(false)} questionId={q.id} initialNotebookIds={notebookIds} onSaved={setNotebookIds} />
            <DialogQuestionStats isOpen={showStatsModal} onClose={() => setShowStatsModal(false)} questionId={q.id} />
            <AdminRevertToTriageModal
                isOpen={showRevertModal}
                onClose={() => setShowRevertModal(false)}
                questionId={q.id}
                onSuccess={() => queryClient.invalidateQueries({ queryKey: ['questions'] })}
            />
        </div>
    );
});

export default QuestionCard;
