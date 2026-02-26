import { useState, useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/authStore';
import { useConfigStore } from '../../stores/configStore';
import QuestionCard from './QuestionCard';
import StatsSlideOver from './StatsSlideOver';
import '../../styles/question-bank.css';

interface FilterOptions {
    type: string;
    subject: string;
    topic: string;
    keyword: string;
    year: string;
    difficulty: string;
    status: string;
    organization: string;
    institution: string;
    role: string;
}

export default function QuestionBank() {
    const { user } = useAuthStore();
    const { aiName } = useConfigStore();
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<FilterOptions>({
        type: '',
        subject: '',
        topic: '',
        keyword: '',
        year: '',
        difficulty: '',
        status: '',
        organization: '',
        institution: '',
        role: ''
    });
    const [moreFilters, setMoreFilters] = useState(false);
    const [statsOpen, setStatsOpen] = useState(false);

    // --- Xavier AI Search State ---
    const [prompt, setPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const statusText = '🪄 Conectando os temas...';
    const [aiMessage, setAiMessage] = useState<string | null>(null);
    const [aiSuggestion, setAiSuggestion] = useState<string | null>(null);
    const [aiSuggestions, setAiSuggestions] = useState<any[]>([]);
    const [isQuotaExceeded, setIsQuotaExceeded] = useState(false);
    const [isAiError, setIsAiError] = useState(false);
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState('');
    const [placeholderText, setPlaceholderText] = useState('Comece agora busque...');

    // Typewriter effect placeholders
    const placeholders = [
        "Questões de Trigonometria do ENEM 2022...",
        "Quero questões fáceis de Interpretação de Texto...",
        "Questões de Revolução Industrial para Concurso...",
        "Getúlio Vargas e o Estado Novo - Questões ENEM..."
    ];
    const phIndex = useRef(0);
    const phCharIndex = useRef(0);
    const phDeleting = useRef(false);

    useEffect(() => {
        const type = () => {
            const fullText = placeholders[phIndex.current];
            if (phDeleting.current) {
                phCharIndex.current--;
            } else {
                phCharIndex.current++;
            }
            setPlaceholderText("Comece agora busque: ex: " + fullText.substring(0, phCharIndex.current));

            let speed = phDeleting.current ? 30 : 50;
            if (!phDeleting.current && phCharIndex.current === fullText.length) {
                speed = 2500;
                phDeleting.current = true;
            } else if (phDeleting.current && phCharIndex.current === 0) {
                phDeleting.current = false;
                phIndex.current = (phIndex.current + 1) % placeholders.length;
                speed = 500;
            }
            setTimeout(type, speed);
        };
        const timer = setTimeout(type, 1000);
        return () => clearTimeout(timer);
    }, []);

    // --- Data Fetching ---
    const { data: questionsData, isLoading: questionsLoading } = useQuery({
        queryKey: ['questions', page, filters],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions', { params: { ...filters, page } });
            return res.data;
        }
    });

    const { data: subjectsData, isLoading: loadingSubjects } = useQuery({
        queryKey: ['subjects', filters.type],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions/subjects', { params: { type: filters.type } });
            return res.data;
        }
    });

    const { data: topicsData, isLoading: loadingTopics } = useQuery({
        queryKey: ['topics', filters.subject, filters.type],
        queryFn: async () => {
            if (!filters.subject) return [];
            const res = await api.get('/api/v1/questions/topics', { params: { subject: filters.subject, type: filters.type } });
            return res.data;
        },
        enabled: !!filters.subject
    });

    const { data: statsData } = useQuery({
        queryKey: ['stats'],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions/stats');
            return res.data;
        },
        enabled: statsOpen
    });

    // --- Handlers ---
    const onFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        setFilters((prev: FilterOptions) => ({
            ...prev,
            [name]: value,
            ...(name === 'type' ? { subject: '', topic: '' } : {}),
            ...(name === 'subject' ? { topic: '' } : {})
        }));
        setPage(1);
    };

    const clearFilters = () => {
        setFilters({
            type: '', subject: '', topic: '', keyword: '', year: '',
            difficulty: '', status: '', organization: '', institution: '', role: ''
        });
        setPage(1);
    };

    const applyXavierSuggestion = (newFilters: Partial<FilterOptions>) => {
        setFilters((prev: FilterOptions) => ({ ...prev, ...newFilters }));
        setAiMessage(null);
        setAiSuggestions([]);
        setAiSuggestion(null);
        setPage(1);
        setToastMessage('✅ Busca atualizada conforme sugestão.');
        setShowToast(true);
        setTimeout(() => setShowToast(false), 4000);
    };

    const submitSearch = async () => {
        if (!prompt.trim() || aiLoading) return;
        setAiLoading(true);
        setAiMessage(null);
        setAiSuggestion(null);
        setAiSuggestions([]);
        setIsQuotaExceeded(false);
        setIsAiError(false);

        try {
            const res = await api.post('/api/v1/questions/ai-search', { prompt });
            if (res.data.status === 'queued') {
                pollSearch(res.data.request_id);
            } else {
                setAiLoading(false);
                setAiMessage(res.data.message || `O ${aiName} não conseguiu interpretar essa busca.`);
                if (res.data.code === 'quota_exceeded') setIsQuotaExceeded(true);
            }
        } catch (e) {
            setAiLoading(false);
            setIsAiError(true);
            setAiMessage(`Ocorreu um erro ao conectar com o ${aiName}.`);
        }
    };

    const pollSearch = (requestId: string) => {
        let attempts = 0;
        const poller = setInterval(async () => {
            attempts++;
            try {
                const res = await api.get(`/api/v1/questions/ai-search/${requestId}/status`);
                if (res.data.status === 'completed') {
                    clearInterval(poller);
                    setAiLoading(false);
                    if (res.data.suggestion_tip) setAiSuggestion(res.data.suggestion_tip);
                    if (res.data.suggestions) setAiSuggestions(res.data.suggestions);

                    setFilters((prev: FilterOptions) => ({ ...prev, ...res.data.filters }));
                    setPage(1);
                    setToastMessage(`✅ Busca realizada com sucesso! ${aiName} encontrou o que você precisava.`);
                    setShowToast(true);
                    setTimeout(() => setShowToast(false), 4000);
                } else if (res.data.status === 'failed') {
                    clearInterval(poller);
                    setAiLoading(false);
                    setIsAiError(true);
                    setAiMessage(res.data.error || 'Não conseguimos processar sua busca agora.');
                }
            } catch (e) { }
            if (attempts >= 30) {
                clearInterval(poller);
                setAiLoading(false);
                setAiMessage('A busca demorou demais. Tente novamente.');
            }
        }, 2000);
    };

    const closeBubble = () => {
        setAiMessage(null);
        setAiSuggestion(null);
        setAiSuggestions([]);
    };

    return (
        <div className="py-2">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {/* Header */}
                <div className="qb-header">
                    <div className="qb-header-left">
                        <h1>📋 Banco de Questões</h1>
                        <p>Resolva questões, veja explicações e tire dúvidas com {aiName}</p>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '12px' }}>
                        <button className="qb-btn-desempenho" onClick={() => setStatsOpen(true)}>
                            📊 Ver Meu Desempenho
                        </button>
                        <div className="qb-header-stats">
                            <div className="qb-stat">
                                <div className="val">{user?.stats?.questions_answered || 0}</div>
                                <div className="lbl">Respondidas</div>
                            </div>
                            <div className="qb-stat">
                                <div className="val">--%</div>
                                <div className="lbl">Acerto</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Xavier AI Search */}
                <div className="xavier-header-badge">
                    <span className="pulse-dot"></span>
                    <strong>{aiName}</strong> — seu Agente de Busca pronto para garimpar o melhor conteúdo para você.
                </div>

                <div className="qb-ai-wrapper relative">
                    {(aiMessage || aiSuggestion || aiSuggestions.length > 0) && (
                        <div className="xavier-bubble animate-xavier-pop">
                            <div style={{ background: '#6366f1', borderRadius: '12px', padding: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 10px rgba(99, 102, 241, 0.3)' }}>
                                <span style={{ fontSize: '20px', color: 'white' }}>🤖</span>
                            </div>
                            <div className="txt">
                                <strong>{aiMessage ? `${aiName}:` : `Dica do ${aiName}:`}</strong><br />
                                <div dangerouslySetInnerHTML={{ __html: aiMessage || aiSuggestion || '' }} />
                                <div className="xavier-btns-row">
                                    {aiSuggestions.map((sug, idx) => (
                                        <button key={idx} className="xavier-sug-btn" onClick={() => applyXavierSuggestion(sug.filters)}>
                                            <span style={{ fontSize: '14px' }}>🔍</span>
                                            {sug.label}
                                        </button>
                                    ))}
                                    {isQuotaExceeded && (
                                        <a href="/plans" className="xavier-action-btn" style={{ background: '#fbbf24', color: '#78350f' }}>
                                            ⭐ Fazer Upgrade
                                        </a>
                                    )}
                                    {isAiError && (
                                        <button className="xavier-action-btn" onClick={submitSearch}>🔄 Tentar Novamente</button>
                                    )}
                                </div>
                                <div style={{ marginTop: '16px', fontSize: '11px', opacity: 0.6, cursor: 'pointer', textDecoration: 'underline' }} onClick={closeBubble}>
                                    [Fechar conversa]
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="qb-ai-search-container">
                        <div className="qb-ai-glow"></div>
                        <div style={{ display: 'flex', alignItems: 'center', paddingLeft: '16px' }}>
                            <span style={{ fontSize: '20px' }}>✨</span>
                        </div>
                        <input
                            type="text"
                            value={prompt}
                            onChange={e => setPrompt(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && submitSearch()}
                            placeholder={placeholderText}
                            className="qb-ai-input"
                            disabled={aiLoading}
                        />
                        <button onClick={submitSearch} className="qb-ai-button" disabled={aiLoading || !prompt.trim()}>
                            {!aiLoading ? `🚀 Consultar Agente ${aiName}` : (
                                <span className="animate-pulse">🪄 {statusText}</span>
                            )}
                        </button>
                    </div>

                    {showToast && (
                        <div className="qb-toast">{toastMessage}</div>
                    )}
                </div>

                {/* Filters */}
                <div className="qb-filters">
                    <div className="qb-filter-row">
                        <div className="qb-filter-item qb-filter-master" style={{ maxWidth: '130px' }}>
                            <label>⭐ Tipo</label>
                            <select name="type" value={filters.type} onChange={onFilterChange}>
                                <option value="">Todos</option>
                                <option value="enem">ENEM</option>
                                <option value="concurso">Concurso</option>
                            </select>
                        </div>
                        <div className="qb-filter-item" style={{ maxWidth: '200px' }}>
                            <label>Matéria</label>
                            <select name="subject" value={filters.subject} onChange={onFilterChange} disabled={loadingSubjects}>
                                <option value="">{loadingSubjects ? 'Carregando...' : 'Todas'}</option>
                                {subjectsData?.map((s: any) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="qb-filter-item">
                            <label>{filters.type === 'enem' ? 'Eixo Temático' : 'Assunto'}</label>
                            <select name="topic" value={filters.topic} onChange={onFilterChange} disabled={!filters.subject || loadingTopics}>
                                <option value="">
                                    {loadingTopics ? 'Carregando...' : (!filters.subject ? 'Selecione uma matéria...' : 'Todos')}
                                </option>
                                {topicsData?.map((t: any) => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="qb-filter-item" style={{ minWidth: '200px' }}>
                            <label>Busca</label>
                            <input type="text" name="keyword" value={filters.keyword} onChange={onFilterChange} placeholder="Palavras-chave..." />
                        </div>
                        <div style={{ display: 'flex', alignItems: 'flex-end', paddingBottom: '2px' }}>
                            <button type="button" className="qb-filter-toggle" onClick={() => setMoreFilters(!moreFilters)}>
                                <svg className={`w-4 h-4 transition-transform ${moreFilters ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" style={{ width: '14px', height: '14px' }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span>{moreFilters ? 'Menos filtros' : 'Mais filtros'}</span>
                            </button>
                        </div>
                    </div>

                    {moreFilters && (
                        <div className="qb-filter-row animate-fade-in">
                            <div className="qb-filter-item" style={{ maxWidth: '110px' }}>
                                <label>Ano</label>
                                <select name="year" value={filters.year} onChange={onFilterChange}>
                                    <option value="">Todos</option>
                                    {[2024, 2023, 2022, 2021, 2020].map(y => <option key={y} value={y}>{y}</option>)}
                                </select>
                            </div>
                            <div className="qb-filter-item" style={{ maxWidth: '130px' }}>
                                <label>Dificuldade</label>
                                <select name="difficulty" value={filters.difficulty} onChange={onFilterChange}>
                                    <option value="">Todas</option>
                                    <option value="easy">Fácil</option>
                                    <option value="medium">Média</option>
                                    <option value="hard">Difícil</option>
                                </select>
                            </div>
                            <div className="qb-filter-item" style={{ maxWidth: '160px' }}>
                                <label>Status</label>
                                <select name="status" value={filters.status} onChange={onFilterChange}>
                                    <option value="">Todos</option>
                                    <option value="unanswered">Não respondidas</option>
                                    <option value="answered">Respondidas</option>
                                </select>
                            </div>
                        </div>
                    )}

                    <div className="qb-filter-actions">
                        <button className="qb-btn qb-btn-primary" onClick={() => setPage(1)}>🔍 Filtrar</button>
                        <button className="qb-btn qb-btn-ghost" onClick={clearFilters}>✕ Limpar</button>
                        <span className="qb-result-count">
                            {questionsData?.meta?.total || 0} questões encontradas
                        </span>
                    </div>
                </div>

                {/* Question List */}
                <div id="questions-container" className="relative" style={{ minHeight: '400px' }}>
                    {questionsLoading && (
                        <div className="absolute inset-0 bg-white/70 dark:bg-slate-900/70 z-10 flex flex-col items-center justify-center backdrop-blur-sm rounded-xl">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                            <div style={{ fontWeight: 700, color: '#4338ca', fontSize: '14px' }}>Minerando na base de dados...</div>
                        </div>
                    )}

                    <div className="space-y-4">
                        {questionsData?.data.map((q: any) => (
                            <QuestionCard key={q.id} question={q} />
                        ))}
                        {questionsData?.data.length === 0 && !questionsLoading && (
                            <div className="qb-card qb-no-results" style={{ textAlign: 'center', padding: '48px' }}>
                                <p style={{ fontSize: '18px', color: '#94a3b8' }}>🔍 Nenhuma questão encontrada</p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {questionsData?.meta?.last_page > 1 && (
                        <div className="flex justify-center mt-8 gap-1">
                            {Array.from({ length: Math.min(5, questionsData.meta.last_page) }, (_, i) => {
                                const p = i + 1;
                                return (
                                    <button
                                        key={p}
                                        onClick={() => setPage(p)}
                                        className={`px-4 py-2 rounded-md text-sm font-bold transition ${page === p ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50'}`}
                                    >
                                        {p}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Stats Slide-over */}
                <StatsSlideOver
                    stats={statsData}
                    open={statsOpen}
                    onClose={() => setStatsOpen(false)}
                />
            </div>
        </div>
    );
}
