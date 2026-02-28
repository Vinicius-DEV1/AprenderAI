import { useState, useEffect, useRef, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/authStore';
import { useConfigStore } from '../../stores/configStore';
import QuestionCard from './QuestionCard';
import StatsSlideOver from './StatsSlideOver';
import SearchableSelect from '../../components/SearchableSelect';
import '../../styles/question-bank.css';

interface FilterOptions {
    type: string;
    subject: string;
    topic: string;
    keyword: string;
    year: string;
    difficulty: string;
    organization: string;
    institution: string;
    role: string;
    status: string;
    include_discursive: boolean;
}

// ── Typewriter placeholders (mirrored from Blade original) ──
const PLACEHOLDERS = [
    "Questões de Trigonometria do ENEM 2022...",
    "Quero questões fáceis de Interpretação de Texto...",
    "Questões de Revolução Industrial para Concurso...",
    "Getúlio Vargas e o Estado Novo - Questões ENEM...",
    "Geometria Espacial nível difícil - Questões...",
    "Biologia Celular: Questões sobre Organelas...",
    "Leis de Newton: Questões de dinâmica e força...",
    "Questões de Gramática: Orações subordinadas...",
    "Questões de Química: Tabela periódica e Ligações...",
    "Questões de Matemática: Probabilidade e Análise...",
    "Questões de Sociologia: Cidadania e Ética...",
    "Política Brasileira: Questões sobre a redemocratização...",
    "Questões de Filosofia: Ética e Moral...",
    "Questões de Biologia: Mitose e Meiose...",
    "Questões de Química: Cálculo de estequiometria...",
    "Questões de História: Segunda Guerra Mundial...",
    "Questões de Literatura: Modernismo no Brasil...",
    "Questões de Matemática: Áreas e volumes complexos...",
    "Questões de Biologia: Fotossíntese e respiração...",
    "Questões de lógica e raciocínio matemático...",
    "Apenas questões que caíram no ENEM 2023...",
    "Questões desafiadoras de Eletromagnetismo! ⚡",
    "Busque uma maratona de questões de Língua Portuguesa! 🏃‍♂️",
    "Questões de atualidades sobre Geopolítica Mundial... 🌍",
    "Questões de Ecologia: Cadeia alimentar e ciclos... 🌱"
];

// ── Fun loading messages while AI is thinking ──
const FUN_MESSAGES = [
    "Garimpando informações importantes nos editais... 🌊",
    "Mapeando conexões entre os temas mais cobrados para você... 📚",
    "Filtrando os melhores enunciados para sua jornada de estudos... ✨",
    "Conectando os pontos entre sua busca e nossa base de dados... 💡",
    "Navegando por um mar de questões para encontrar a ideal... 🚀",
    "Organizando minha mesa de análise para te entregar o melhor resultado... ☕",
    "Eu estou vasculhando cada canto do banco de dados, de 2009 até hoje... 🕰️",
    "Distilando o conhecimento acumulado para sua tela... 🌾",
    "Focando totalmente em mapear sua agulha no palheiro... 🧘",
    "Quase lá! Encontrei caminhos sólidos para sua aprovação... ✨",
    "Finalizando a nossa rota de busca. Só mais um instante... ⏳",
    "Eu encontrei conexões de resultados que você vai adorar explorar... 😊"
];

const FAILURE_MESSAGES = [
    'Eu tentei cruzar todos os dados, mas acabei me perdendo entre tantos enunciados. Que tal tentarmos uma nova rota de busca?',
    'As informações se misturaram na minha mesa de análise. Deixe-me organizar tudo e tentamos de novo?',
    'Garimpar essa questão específica foi mais difícil do que eu esperava. Minhas conexões falharam, vamos repetir?',
    'Houve um desencontro no mapeamento dos filtros. Posso tentar reorganizar minha mesa de estudos e começar de novo?',
    'Eu vasculhei cada canto do banco de dados, de 2009 até hoje, mas essa resposta escapou por pouco. Vamos ajustar os termos?',
    'O mapa da minha busca ficou um pouco confuso agora. Deixe-me recalibrar minha rota entre as questões.'
];

const STATIC_PREFIX = 'Comece agora busque: ex: ';

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
        role: '',
        include_discursive: false
    });
    const [moreFilters, setMoreFilters] = useState(false);
    const [statsOpen, setStatsOpen] = useState(false);

    // --- Xavier AI Search State ---
    const [prompt, setPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiStatusText, setAiStatusText] = useState('🪄 Conectando os temas...');
    const [aiMessage, setAiMessage] = useState<string | null>(null);
    const [aiSuggestion, setAiSuggestion] = useState<string | null>(null);
    const [aiSuggestions, setAiSuggestions] = useState<any[]>([]);
    const [isQuotaExceeded, setIsQuotaExceeded] = useState(false);
    const [isAiError, setIsAiError] = useState(false);
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState('');
    const [placeholderText, setPlaceholderText] = useState(STATIC_PREFIX);

    // ── Typewriter effect ──
    const phIndex = useRef(0);
    const phCharIndex = useRef(0);
    const phDeleting = useRef(false);

    useEffect(() => {
        let timeoutId: ReturnType<typeof setTimeout>;
        const type = () => {
            const fullText = PLACEHOLDERS[phIndex.current];
            if (phDeleting.current) {
                phCharIndex.current--;
            } else {
                phCharIndex.current++;
            }
            setPlaceholderText(STATIC_PREFIX + fullText.substring(0, phCharIndex.current));

            let speed = phDeleting.current ? 30 : 50;
            if (!phDeleting.current && phCharIndex.current === fullText.length) {
                speed = 2500;
                phDeleting.current = true;
            } else if (phDeleting.current && phCharIndex.current === 0) {
                phDeleting.current = false;
                phIndex.current = (phIndex.current + 1) % PLACEHOLDERS.length;
                speed = 500;
            }
            timeoutId = setTimeout(type, speed);
        };
        timeoutId = setTimeout(type, 1000);
        return () => clearTimeout(timeoutId);
    }, []);

    // ── Fun loading message rotation during AI search ──
    useEffect(() => {
        if (!aiLoading) return;
        let msgIdx = 0;
        setAiStatusText(FUN_MESSAGES[0]);
        const interval = setInterval(() => {
            msgIdx = (msgIdx + 1) % FUN_MESSAGES.length;
            setAiStatusText(FUN_MESSAGES[msgIdx]);
        }, 3500);
        return () => clearInterval(interval);
    }, [aiLoading]);

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

    const { data: filterOptions, isLoading: loadingFilterOptions } = useQuery({
        queryKey: ['filterOptions'],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions/filter-options');
            return res.data;
        }
    });

    const questions = questionsData?.data || [];
    const meta = questionsData?.meta || { total: 0, current_page: 1, last_page: 1 };

    // ── Smart pagination window (shows up to 7 pages centered on current) ──
    const paginationWindow = useMemo(() => {
        const lastPage = meta.last_page;
        const current = meta.current_page;
        if (lastPage <= 7) return Array.from({ length: lastPage }, (_, i) => i + 1);
        const start = Math.max(1, current - 3);
        const end = Math.min(lastPage, start + 6);
        const adjustedStart = Math.max(1, end - 6);
        return Array.from({ length: end - adjustedStart + 1 }, (_, i) => adjustedStart + i);
    }, [meta.last_page, meta.current_page]);

    // --- Handlers ---
    const updateFilter = (name: string, value: any) => {
        setFilters((prev: FilterOptions) => ({
            ...prev,
            [name]: value,
            // Cascade resets
            ...(name === 'type' ? { subject: '', topic: '', ...(value === 'enem' ? { organization: '', institution: '', role: '' } : {}) } : {}),
            ...(name === 'subject' ? { topic: '' } : {})
        }));
        setPage(1);
    };

    const onFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        updateFilter(name, value);
    };

    const clearFilters = () => {
        setFilters({
            type: '', subject: '', topic: '', keyword: '', year: '',
            difficulty: '', status: '', organization: '', institution: '', role: '', include_discursive: false
        });
        setMoreFilters(false);
        setPage(1);
    };

    const applyXavierSuggestion = (newFilters: Partial<FilterOptions>) => {
        setFilters((prev: FilterOptions) => ({ ...prev, ...newFilters }));
        // Auto-expand advanced filters if suggestion sets them
        if (newFilters.year || newFilters.difficulty || newFilters.organization || newFilters.institution || newFilters.role) {
            setMoreFilters(true);
        }
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
        } catch {
            setAiLoading(false);
            setIsAiError(true);
            const randomFail = FAILURE_MESSAGES[Math.floor(Math.random() * FAILURE_MESSAGES.length)];
            setAiMessage(randomFail);
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
                    // Auto-show advanced filters if AI sets them
                    if (res.data.filters?.year || res.data.filters?.difficulty || res.data.filters?.organization) {
                        setMoreFilters(true);
                    }
                    setPage(1);
                    setToastMessage(`✅ Busca realizada com sucesso! ${aiName} encontrou o que você precisava.`);
                    setShowToast(true);
                    setTimeout(() => setShowToast(false), 4000);
                } else if (res.data.status === 'failed') {
                    clearInterval(poller);
                    setAiLoading(false);
                    setIsAiError(true);
                    const randomFail = FAILURE_MESSAGES[Math.floor(Math.random() * FAILURE_MESSAGES.length)];
                    setAiMessage(res.data.error || randomFail);
                }
            } catch { /* polling error, just retry */ }
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

    const handlePageChange = (newPage: number) => {
        setPage(newPage);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // ── Determine if we should show concurso-specific filters ──
    const showConcursoFilters = filters.type !== 'enem';

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
                                <div className="val">{(user as any)?.stats?.questions_answered || 0}</div>
                                <div className="lbl">Respondidas</div>
                            </div>
                            <div className="qb-stat">
                                <div className="val">{(user as any)?.stats?.accuracy_rate ? `${(user as any).stats.accuracy_rate}%` : '--%'}</div>
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
                                <span className="animate-pulse">🪄 {aiStatusText}</span>
                            )}
                        </button>
                    </div>

                    {showToast && (
                        <div className="qb-toast">{toastMessage}</div>
                    )}
                </div>

                {/* ── Filters ── */}
                <div className="qb-filters">
                    {/* Row 1: Core filters (always visible) */}
                    <div className="qb-filter-row">
                        <div className="qb-filter-item qb-filter-master">
                            <label>⭐ Tipo</label>
                            <select name="type" value={filters.type} onChange={onFilterChange}>
                                <option value="">Todos</option>
                                <option value="enem">ENEM</option>
                                <option value="concurso">Concurso</option>
                            </select>
                        </div>
                        <SearchableSelect
                            label="Matéria"
                            name="subject"
                            value={filters.subject}
                            options={subjectsData || []}
                            loading={loadingSubjects}
                            placeholder={loadingSubjects ? 'Carregando...' : 'Todas'}
                            onChange={updateFilter}
                        />
                        <SearchableSelect
                            label={filters.type === 'enem' ? 'Eixo Temático' : 'Assunto'}
                            name="topic"
                            value={filters.topic}
                            options={topicsData || []}
                            loading={loadingTopics}
                            placeholder={loadingTopics ? 'Carregando...' : (!filters.subject ? 'Selecione uma matéria...' : 'Todos')}
                            onChange={updateFilter}
                        />
                        <div className="qb-filter-item flex-[2_1_250px]">
                            <label>Busca</label>
                            <input type="text" name="keyword" value={filters.keyword} onChange={onFilterChange} placeholder="Palavras-chave..." />
                        </div>
                        <div className="qb-filter-checkbox">
                            <label className="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                                <input
                                    type="checkbox"
                                    name="include_discursive"
                                    checked={filters.include_discursive}
                                    onChange={(e) => {
                                        setFilters(prev => ({ ...prev, include_discursive: e.target.checked }));
                                        setPage(1);
                                    }}
                                />
                                <span className="text-sm font-bold text-slate-600 select-none">Mostrar Discursivas</span>
                            </label>
                        </div>
                        <button type="button" className="qb-filter-toggle" onClick={() => setMoreFilters(!moreFilters)}>
                            <svg className={`w-4 h-4 transition-transform ${moreFilters ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <span>{moreFilters ? 'Menos filtros' : 'Mais filtros'}</span>
                        </button>
                    </div>
                </div>

                {/* Row 2: Advanced filters (toggled) */}
                {moreFilters && (
                    <div className="qb-filter-row animate-fade-in">
                        <div className="qb-filter-item">
                            <label>Ano</label>
                            <select name="year" value={filters.year} onChange={onFilterChange}>
                                <option value="">Todos</option>
                                {[2024, 2023, 2022, 2021, 2020, 2019, 2018, 2017, 2016, 2015, 2014, 2013, 2012, 2011, 2010, 2009].map(y => <option key={y} value={y}>{y}</option>)}
                            </select>
                        </div>
                        <div className="qb-filter-item">
                            <label>Dificuldade</label>
                            <select name="difficulty" value={filters.difficulty} onChange={onFilterChange}>
                                <option value="">Todas</option>
                                <option value="easy">Fácil</option>
                                <option value="medium">Média</option>
                                <option value="hard">Difícil</option>
                            </select>
                        </div>
                        <div className="qb-filter-item">
                            <label>Status</label>
                            <select name="status" value={filters.status} onChange={onFilterChange}>
                                <option value="">Todos</option>
                                <option value="unanswered">Não respondidas</option>
                                <option value="answered">Respondidas</option>
                            </select>
                        </div>
                        {/* Concurso-specific filters (hidden when type === 'enem') */}
                        {showConcursoFilters && (
                            <>
                                <SearchableSelect
                                    label="Banca"
                                    name="organization"
                                    value={filters.organization}
                                    options={filterOptions?.organizations || []}
                                    loading={loadingFilterOptions}
                                    placeholder="Ex: CESPE, FCC..."
                                    onChange={updateFilter}
                                />
                                <SearchableSelect
                                    label="Órgão"
                                    name="institution"
                                    value={filters.institution}
                                    options={filterOptions?.institutions || []}
                                    loading={loadingFilterOptions}
                                    placeholder="Ex: TRF, INSS..."
                                    onChange={updateFilter}
                                />
                                <SearchableSelect
                                    label="Cargo"
                                    name="role"
                                    value={filters.role}
                                    options={filterOptions?.roles || []}
                                    loading={loadingFilterOptions}
                                    placeholder="Ex: Analista..."
                                    onChange={updateFilter}
                                />
                            </>
                        )}


                    </div>
                )}

                <div className="qb-filter-actions">
                    <button className="qb-btn qb-btn-primary" onClick={() => setPage(1)}>🔍 Filtrar</button>
                    <button className="qb-btn qb-btn-ghost" onClick={clearFilters}>✕ Limpar</button>
                    <span className="qb-result-count">
                        {meta.total || 0} questões encontradas
                    </span>
                </div>
            </div>

            {/* ── Question List ── */}
            <div id="questions-container" className="relative" style={{ minHeight: '400px' }}>
                {questionsLoading && (
                    <div className="absolute inset-0 bg-white/70 dark:bg-slate-900/70 z-10 flex flex-col items-center justify-center backdrop-blur-sm rounded-xl">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                        <div style={{ fontWeight: 700, color: '#4338ca', fontSize: '14px' }}>Minerando na base de dados...</div>
                    </div>
                )}

                <div className="space-y-4">
                    {questions.map((q: any) => (
                        <QuestionCard key={q.id} question={q} />
                    ))}
                    {questions.length === 0 && !questionsLoading && (
                        <div className="qb-card qb-no-results" style={{ textAlign: 'center', padding: '48px' }}>
                            <p style={{ fontSize: '32px', marginBottom: '8px' }}>🔍</p>
                            <p style={{ fontSize: '18px', color: '#94a3b8', fontWeight: 600 }}>Nenhuma questão encontrada</p>
                            <p style={{ fontSize: '13px', color: '#64748b', marginTop: '8px' }}>Tente ajustar seus filtros ou peça uma busca ao {aiName}.</p>
                        </div>
                    )}
                </div>

                {/* ── Pagination (smart window) ── */}
                {meta.last_page > 1 && (
                    <div className="flex justify-center items-center mt-8 gap-1 flex-wrap">
                        {/* First + Prev */}
                        <button
                            onClick={() => handlePageChange(1)}
                            disabled={page === 1}
                            className="px-3 py-2 rounded-md text-sm font-bold transition bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 disabled:opacity-40"
                        >
                            «
                        </button>
                        <button
                            onClick={() => handlePageChange(Math.max(1, page - 1))}
                            disabled={page === 1}
                            className="px-3 py-2 rounded-md text-sm font-bold transition bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 disabled:opacity-40"
                        >
                            ‹
                        </button>

                        {/* Page numbers */}
                        {paginationWindow.map(p => (
                            <button
                                key={p}
                                onClick={() => handlePageChange(p)}
                                className={`px-4 py-2 rounded-md text-sm font-bold transition ${page === p ? 'bg-indigo-600 text-white shadow-md' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700'}`}
                            >
                                {p}
                            </button>
                        ))}

                        {/* Next + Last */}
                        <button
                            onClick={() => handlePageChange(Math.min(meta.last_page, page + 1))}
                            disabled={page === meta.last_page}
                            className="px-3 py-2 rounded-md text-sm font-bold transition bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 disabled:opacity-40"
                        >
                            ›
                        </button>
                        <button
                            onClick={() => handlePageChange(meta.last_page)}
                            disabled={page === meta.last_page}
                            className="px-3 py-2 rounded-md text-sm font-bold transition bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 disabled:opacity-40"
                        >
                            »
                        </button>

                        {/* Page info */}
                        <span className="ml-4 text-xs text-slate-500 dark:text-slate-400">
                            Página {meta.current_page} de {meta.last_page} ({meta.total} questões)
                        </span>
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
    );
}
