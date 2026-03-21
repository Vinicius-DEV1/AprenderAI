import { useState, useEffect, useRef, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocation, useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { useConfigStore } from '../../stores/configStore';
import { useAuthStore } from '../../stores/authStore';
import QuestionCard from '../../components/QuestionCard';
import StatsSlideOver from './StatsSlideOver';
import SearchableSelect from '../../components/SearchableSelect';
import GoalSettingsModal from './components/GoalSettingsModal';
import { motion, AnimatePresence } from 'framer-motion';
// @ts-ignore
import html2pdf from 'html2pdf.js';
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
    notebook_id?: string;
    id?: string;
    favorites_only?: boolean;
    question_ids?: number[];
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
const ENABLE_DISCURSIVAS_FILTER = false;

export default function QuestionBank() {
    const location = useLocation();
    const [searchParams, setSearchParams] = useSearchParams();
    const initialNotebookId = searchParams.get('notebook_id') || '';
    const initialQuestionId = searchParams.get('id') || searchParams.get('question_id') || '';

    const { aiName } = useConfigStore();
    const { user } = useAuthStore();
    
    const page = parseInt(searchParams.get('page') || '1', 10);
    const setPage = (newPage: number) => {
        setSearchParams((prev) => {
            const params = new URLSearchParams(prev);
            if (newPage > 1) {
                params.set('page', newPage.toString());
            } else {
                params.delete('page');
            }
            return params;
        }, { replace: true });
    };

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
        include_discursive: false,
        notebook_id: initialNotebookId,
        id: initialQuestionId,
        favorites_only: false,
        question_ids: []
    });
    const [moreFilters, setMoreFilters] = useState(!!initialNotebookId || !!initialQuestionId);
    const [statsOpen, setStatsOpen] = useState(false);
    const [goalModalOpen, setGoalModalOpen] = useState(false);

    // --- Engagement State ---
    const { data: engagementData, refetch: refetchEngagement } = useQuery({
        queryKey: ['engagement', user?.id],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions/engagement');
            return res.data;
        },
        enabled: !!user?.id
    });

    // Provide refetch to context or pass it down? 
    // Actually, TanStack Query handles this if we use queryClient.invalidateQueries({ queryKey: ['engagement'] }) inside QuestionCard.

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
    const [triggerScroll, setTriggerScroll] = useState(false);
    const [placeholderText, setPlaceholderText] = useState(STATIC_PREFIX);
    const [aiScoreDetails, setAiScoreDetails] = useState<Record<string, any>>({});

    // --- Data Fetching (Moved up to avoid "used before declaration" in effects) ---
    const { data: questionsData, isLoading: questionsLoading } = useQuery({
        queryKey: ['questions', user?.id, page, filters],
        queryFn: async () => {
            const res = await api.get('/api/v1/questions', { params: { ...filters, page, per_page: 20 } });
            return res.data;
        },
        enabled: !!user?.id
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

    const { data: notebooksData } = useQuery({
        queryKey: ['notebooks'],
        queryFn: async () => {
            const res = await api.get('/api/v1/notebooks');
            return res.data;
        }
    });

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

    // ── Auto Scroll When Search/Filters Finish Loading ──
    useEffect(() => {
        if (triggerScroll && !questionsLoading) {
            setTriggerScroll(false);
            setTimeout(() => {
                const container = document.getElementById('questions-container');
                if (container) {
                    const topPos = container.getBoundingClientRect().top + window.scrollY - 100; // Offset para o header
                    window.scrollTo({ top: topPos, behavior: 'smooth' });
                }
            }, 100);
        }
    }, [questionsLoading, triggerScroll]);

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
            ...(name === 'subject' ? { topic: '' } : {}),
            question_ids: [] // Clear AI results if manual filters change
        }));
        setPage(1);
    };

    const onFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        updateFilter(name, value);
    };

    const clearFilters = () => {
        setFilters({
            type: '', subject: '', topic: '', keyword: '', year: '', id: '',
            difficulty: '', status: '', organization: '', institution: '', role: '', include_discursive: false,
            notebook_id: '', favorites_only: false, question_ids: []
        });
        setMoreFilters(false);
        setPage(1);
    };

    const applyXavierSuggestion = (newFilters: Partial<FilterOptions>) => {
        // RADICAL RESET: Substituímos TUDO pelo que a IA sugeriu, 
        // evitando que filtros anteriores (ex: Ano 2024) persistam se não estiverem na sugestão.
        const baseFilters = {
            type: '', subject: '', topic: '', keyword: '', year: '', id: '',
            difficulty: '', status: '', organization: '', institution: '', role: '', include_discursive: false,
            notebook_id: '', favorites_only: false
        };
        const finalFilters = { ...baseFilters, ...newFilters };
        setFilters(finalFilters as FilterOptions);

        // Auto-expand advanced filters if suggestion sets them
        if (newFilters.year || newFilters.difficulty || newFilters.organization || newFilters.institution || newFilters.role) {
            setMoreFilters(true);
        }
        setAiMessage(null);
        setAiSuggestions([]);
        setAiSuggestion(null);
        setPage(1);
        setTriggerScroll(true);
        setToastMessage('✅ Busca atualizada conforme sugestão.');
        setShowToast(true);
        setTimeout(() => setShowToast(false), 4000);
    };

    const applySearchResults = (data: any) => {
        setAiLoading(false);
        if (data.suggestion_tip) setAiSuggestion(data.suggestion_tip);
        if (data.suggestions) setAiSuggestions(data.suggestions);
        if (data.score_details) setAiScoreDetails(data.score_details);

        const baseFilters = {
            type: '', subject: '', topic: '', keyword: '', year: '', id: '',
            difficulty: '', status: '', organization: '', institution: '', role: '', include_discursive: false,
            notebook_id: '', favorites_only: false, question_ids: data.question_ids || []
        };
        const filtersToApply = data.filters || {};
        const finalFilters = { ...baseFilters, ...filtersToApply };
        setFilters(finalFilters as FilterOptions);

        if (finalFilters.year || finalFilters.difficulty || finalFilters.organization || finalFilters.institution || finalFilters.role) {
            setMoreFilters(true);
        }
        setPage(1);
        if (!data.suggestions || data.suggestions.length === 0) {
            setTriggerScroll(true);
        }
        
        const isVector = data.search_mode === 'vector' || data.search_path === 'vector_only';
        setToastMessage(isVector 
            ? `🧠 Xavier Semantic: ${data.total} questões encontradas por significado.`
            : `⚡ Inteligência Instantânea: Busca recuperada do Cache.`);
        setShowToast(true);
        setTimeout(() => setShowToast(false), 4000);
    };

    const pollSearchStatus = async (requestId: number, retryCount = 0) => {
        if (retryCount >= 16) { // 8 segundos de timeout (16 x 500ms)
            setAiLoading(false);
            setAiMessage(`O ${aiName} demorou um pouco mais que o normal para gerar os embeddings. Tente novamente em alguns segundos.`);
            return;
        }

        try {
            const res = await api.get(`/api/v1/questions/ai-search/${requestId}/status`);
            
            if (res.data.status === 'completed') {
                applySearchResults(res.data);
            } else if (res.data.status === 'generating') {
                // Atualiza a mensagem a cada ~2 segundos (4 retries)
                if ((retryCount + 1) % 4 === 0) {
                    const nextMsg = FUN_MESSAGES[Math.floor(Math.random() * FUN_MESSAGES.length)];
                    setAiStatusText(nextMsg);
                }
                
                // Continua no polling leve
                setTimeout(() => pollSearchStatus(requestId, retryCount + 1), 500);
            } else {
                setAiLoading(false);
                setAiMessage(res.data.error || `Houve um erro no processamento do ${aiName}.`);
            }
        } catch (err) {
            console.error("Erro no polling da busca:", err);
            setAiLoading(false);
            setIsAiError(true);
            setAiMessage("Erro de comunicação com o servidor ao verificar status da busca.");
        }
    };

    const submitSearch = async () => {
        if (!prompt.trim() || aiLoading) return;
        setAiLoading(true);
        setAiMessage(null);
        setAiSuggestion(null);
        setAiSuggestions([]);
        setIsQuotaExceeded(false);
        setIsAiError(false);
        setTriggerScroll(false);

        try {
            const res = await api.post('/api/v1/questions/ai-search', { prompt });

            if (res.data.status === 'completed') {
                // HIT INSTANTÂNEO (Model L1/L2 Cache)
                applySearchResults(res.data);
            } else if (res.data.status === 'generating') {
                // PARALELIZAÇÃO ASSÍNCRONA: Inicia polling leve (embeddings sendo gerados em jobs)
                pollSearchStatus(res.data.request_id);
            } else {
                setAiLoading(false);
                setAiMessage(res.data.message || `O ${aiName} não conseguiu interpretar essa busca.`);
                if (res.data.code === 'quota_exceeded') setIsQuotaExceeded(true);
            }
        } catch (err) {
            console.error("Erro ao iniciar busca AI:", err);
            setAiLoading(false);
            setIsAiError(true);
            const randomFail = FAILURE_MESSAGES[Math.floor(Math.random() * FAILURE_MESSAGES.length)];
            setAiMessage(randomFail);
        }
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

    const handleExportPdf = () => {
        if (!questions || questions.length === 0) {
            setToastMessage("Não há questões visíveis para exportar.");
            setShowToast(true);
            setTimeout(() => setShowToast(false), 3000);
            return;
        }

        const container = document.createElement('div');
        container.style.fontFamily = 'Arial, sans-serif';
        container.style.padding = '20px';
        container.style.color = '#333';

        let html = `
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 24px; color: #1e293b; margin: 0;">Caderno de Questões</h2>
                <p style="color: #64748b; margin-top: 5px;">AprenderAI | Gerado em ${new Date().toLocaleDateString('pt-BR')}</p>
            </div>
        `;

        // Section 1: Questions
        questions.forEach((q: any, idx: number) => {
            html += `
                <div style="margin-bottom: 40px; page-break-inside: avoid;">
                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 12px; color: #4f46e5; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px;">
                        Questão ${idx + 1} (Ref: ${q.id})
                    </div>
                    <div style="font-size: 14px; line-height: 1.6; color: #1e293b; margin-bottom: 16px;">
                        ${q.statement}
                    </div>
            `;
            
            if (q.alternatives && q.alternatives.length > 0) {
                html += `<div style="margin-left: 20px;">`;
                q.alternatives.forEach((alt: any) => {
                    html += `
                        <div style="margin-bottom: 10px; font-size: 14px; color: #334155; display: flex;">
                            <strong style="margin-right: 8px;">${alt.label})</strong> 
                            <div>${alt.content}</div>
                        </div>
                    `;
                });
                html += `</div>`;
            }
            html += `</div>`;
        });

        // Section 2: Answer Key (Gabarito)
        html += `
            <div style="page-break-before: always; padding-top: 20px;">
                <h3 style="font-size: 20px; color: #1e293b; margin-bottom: 20px; text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                    Gabarito e Explicações
                </h3>
        `;

        questions.forEach((q: any, idx: number) => {
            const correctAlt = q.alternatives?.find((a: any) => a.is_correct);
            const explanation = correctAlt?.explanation || q.explanation || 'Nenhuma explicação detalhada disponível.';
            const answerText = correctAlt ? `Letra ${correctAlt.label}` : 'Discursiva';

            html += `
                <div style="margin-bottom: 24px; page-break-inside: avoid; background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981;">
                    <div style="font-weight: bold; font-size: 15px; margin-bottom: 8px; color: #1e293b;">
                        Questão ${idx + 1} <span style="color: #10b981; margin-left: 10px;">✅ Resposta: ${answerText}</span>
                    </div>
                    <div style="font-size: 13px; color: #475569; line-height: 1.5;">
                        <strong style="color: #334155;">Explicação:</strong><br/>
                        <div style="margin-top: 5px;">${explanation}</div>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
        
        // Append to DOM (hidden) to ensure html2canvas has context
        container.style.position = 'fixed';
        container.style.left = '-9999px';
        container.style.top = '0';
        container.style.width = '190mm'; // Specify width for better rendering
        document.body.appendChild(container);

        const opt = {
            margin:       10,
            filename:     `questoes-aprenderai-${new Date().toISOString().split('T')[0]}.pdf`,
            image:        { type: 'jpeg', quality: 0.95 },
            html2canvas:  { 
                scale: 1, // Safer scale to avoid canvas limits
                useCORS: true, 
                logging: false,
                letterRendering: true,
                allowTaint: false
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
            pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
        };

        setToastMessage("⏳ Gerando PDF... Aguarde um instante.");
        setShowToast(true);

        html2pdf().set(opt).from(container).save().then(() => {
            document.body.removeChild(container);
            setToastMessage("✅ PDF gerado com sucesso!");
            setTimeout(() => setShowToast(false), 3000);
        }).catch((err: any) => {
            console.error("PDF generation failed", err);
            if (document.body.contains(container)) {
                document.body.removeChild(container);
            }
            setToastMessage("❌ Erro ao gerar o PDF.");
            setTimeout(() => setShowToast(false), 3000);
        });
    };

    // ── Determine if we should show concurso-specific filters ──
    const showConcursoFilters = filters.type !== 'enem';

    return (
        <div className="qb-main-container w-full">
            <div className="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">

                {/* Original Header Restored + New Metrics */}
                <div className="qb-header w-full flex-col md:flex-row items-start md:items-center gap-4 md:gap-8">
                    <div className="qb-header-left shrink-0">
                        <h1>📘 Banco de Questões</h1>
                        <p>Resolva questões, veja explicações e tire dúvidas com {aiName}</p>
                    </div>
                    <div className="qb-header-right flex flex-col items-start md:items-end gap-3 flex-1 w-full">
                        <button className="qb-btn-desempenho whitespace-nowrap" onClick={() => setStatsOpen(true)}>
                            📊 Ver Meu Desempenho
                        </button>

                        {/* Status bar */}
                        <div className="qb-header-stats flex flex-wrap gap-2 justify-start md:justify-end w-full">
                            <div className="qb-stat">
                                <div className="val">{engagementData?.total_answered || 0}</div>
                                <div className="lbl">Respondidas</div>
                            </div>
                            <div className="qb-stat">
                                <div className="val">{engagementData?.accuracy_rate || 0}%</div>
                                <div className="lbl">Acerto</div>
                            </div>
                            <div className="qb-stat">
                                <div className="val">{engagementData?.today_count || 0}</div>
                                <div className="lbl">Hoje</div>
                            </div>
                            <div className="qb-stat">
                                <div className="val" style={{ color: '#fb923c' }}>🔥 {engagementData?.streak_days || 0}</div>
                                <div className="lbl">Streak</div>
                            </div>
                            <div className="qb-stat" title="Limite de questões para hoje">
                                <div className="val" style={{ color: '#fff' }}>
                                    {engagementData?.daily_quota?.used || 0} / {engagementData?.daily_quota?.limit === 9999 ? '∞' : (engagementData?.daily_quota?.limit || 0)}
                                </div>
                                <div className="lbl">Limite Diário</div>
                            </div>
                            <div className="qb-stat" style={{ minWidth: '100px', cursor: 'pointer' }} onClick={() => setGoalModalOpen(true)} title="Definir Meta">
                                <div className="val" style={{ color: (engagementData?.today_count || 0) >= (engagementData?.daily_goal || 10) ? '#4ade80' : 'inherit' }}>
                                    {engagementData?.today_count || 0} / {engagementData?.daily_goal || 10}
                                </div>
                                <div className="lbl" style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>Meta Diária ⚙️</div>
                            </div>
                        </div>

                        {/* Discrete Sparkline within the header limits */}
                        {engagementData?.sparkline && engagementData.sparkline.length > 0 && (
                            <div className="w-full max-w-[200px] h-6 flex items-end gap-[2px] opacity-80 mt-2 md:mt-0" title="Produtividade dos últimos 14 dias">
                                {engagementData.sparkline.map((day: any, idx: number) => {
                                    const maxVal = Math.max(...engagementData.sparkline.map((d: any) => d.value), 1);
                                    const height = (day.value / maxVal) * 100;
                                    return (
                                        <div key={idx} style={{ flex: 1, height: '100%', position: 'relative' }} className="group">
                                            <div
                                                style={{
                                                    position: 'absolute', bottom: 0, width: '100%',
                                                    height: `${Math.max(10, height)}%`,
                                                    backgroundColor: day.value >= (engagementData?.daily_goal || 10) ? '#4ade80' : 'rgba(255,255,255,0.4)',
                                                    borderRadius: '2px 2px 0 0',
                                                    transition: 'all 0.2s'
                                                }}
                                                className="group-hover:bg-white"
                                            />
                                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 bg-slate-900 text-white text-[9px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-20">
                                                {day.value}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        <AnimatePresence>
                            {(engagementData?.today_count || 0) >= (engagementData?.daily_goal || 10) && (
                                <motion.div
                                    initial={{ opacity: 0, y: 5 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    className="text-[10px] font-bold text-green-400 flex items-center gap-1"
                                >
                                    🎉 Meta batida hoje!
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </div>
                </div>

                <GoalSettingsModal
                    isOpen={goalModalOpen}
                    onClose={() => setGoalModalOpen(false)}
                    currentGoal={engagementData?.daily_goal || 10}
                    onSave={async (newGoal) => {
                        await api.post('/api/v1/questions/goal', { daily_goal: newGoal });
                        refetchEngagement();
                    }}
                />

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
                                        <button key={idx} className="xavier-sug-btn w-full sm:w-auto" onClick={() => applyXavierSuggestion(sug.filters)}>
                                            <span className="text-[14px]">🔍</span>
                                            {sug.label}
                                        </button>
                                    ))}
                                    {isQuotaExceeded && (
                                        <a href="/planos" className="xavier-action-btn" style={{ background: '#fbbf24', color: '#78350f' }}>
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
                        <div className="flex items-center pl-4">
                            <span className="text-xl">✨</span>
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
                            label={filters.type === 'enem' ? 'Eixo Temático' : (filters.type === 'concurso' ? 'Assunto' : 'Assunto / Eixo Temático')}
                            name="topic"
                            value={filters.topic}
                            options={topicsData || []}
                            loading={loadingTopics}
                            placeholder={loadingTopics ? 'Carregando...' : (!filters.subject ? 'Selecione uma matéria...' : 'Todos')}
                            onChange={updateFilter}
                        />
                        <button type="button" className="qb-filter-toggle" onClick={() => setMoreFilters(!moreFilters)}>
                            <svg className={`w-4 h-4 transition-transform ${moreFilters ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <span>{moreFilters ? 'Menos filtros' : 'Mais filtros'}</span>
                        </button>
                    </div>

                    <div className="qb-filter-row">
                        <div className="qb-filter-item flex-[2_1_250px]">
                            <label>Busca</label>
                            <input type="text" name="keyword" value={filters.keyword} onChange={onFilterChange} placeholder="Palavras-chave..." />
                        </div>
                        <div className={`qb-filter-checkbox ${!ENABLE_DISCURSIVAS_FILTER ? 'opacity-50 pointer-events-none' : ''}`}>
                            <label className="flex items-center gap-2 cursor-pointer whitespace-nowrap" title={!ENABLE_DISCURSIVAS_FILTER ? "Filtro temporariamente indisponível" : ""}>
                                <input
                                    type="checkbox"
                                    name="include_discursive"
                                    checked={filters.include_discursive}
                                    onChange={(e) => {
                                        setFilters(prev => ({ ...prev, include_discursive: e.target.checked }));
                                        setPage(1);
                                    }}
                                    disabled={!ENABLE_DISCURSIVAS_FILTER}
                                />
                                <span className="text-sm font-bold text-slate-600 select-none">Mostrar Discursivas</span>
                            </label>
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
                            <div className="qb-filter-item">
                                <label>Caderno</label>
                                <select name="notebook_id" value={filters.notebook_id || ''} onChange={onFilterChange}>
                                    <option value="">Todos</option>
                                    {notebooksData?.map((nb: any) => (
                                        <option key={nb.id} value={nb.id}>{nb.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="qb-filter-checkbox">
                                <label className="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        name="favorites_only"
                                        checked={filters.favorites_only}
                                        onChange={(e) => {
                                            setFilters(prev => ({ ...prev, favorites_only: e.target.checked }));
                                            setPage(1);
                                        }}
                                    />
                                    <span className="text-sm font-bold text-slate-600 dark:text-slate-300 select-none">Apenas Favoritas ⭐</span>
                                </label>
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
                        <button className="qb-btn qb-btn-ghost" onClick={handleExportPdf} disabled={!questions || questions.length === 0}>
                            📄 Exportar PDF
                        </button>
                        <span className="qb-result-count">
                            {meta.total || 0} questões encontradas
                        </span>
                    </div>
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
                        <div key={q.id} className="relative">
                            {user?.role === 'admin' && aiScoreDetails[q.id] && (
                                <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 mb-2 rounded-lg text-xs">
                                    <div className="flex justify-between font-bold mb-2">
                                        <span className="text-slate-500">Apuramento Xavier AI</span>
                                        <span className="text-emerald-600">FINAL: {aiScoreDetails[q.id].composite_score?.toFixed(4) || 'N/A'}</span>
                                    </div>
                                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2">
                                        <div className="bg-white dark:bg-slate-800 p-2 rounded border border-slate-100 dark:border-slate-700">
                                            <span className="text-[9px] text-slate-500 block uppercase font-bold tracking-widest">SEMÂNTICA VEC</span>
                                            <span className="font-mono text-indigo-600 dark:text-indigo-400 font-bold">+{aiScoreDetails[q.id].details?.vector?.weighted?.toFixed(4)}</span>
                                        </div>
                                        <div className="bg-white dark:bg-slate-800 p-2 rounded border border-slate-100 dark:border-slate-700">
                                            <span className="text-[9px] text-slate-500 block uppercase font-bold tracking-widest">POPULARIDADE</span>
                                            <span className="font-mono text-sky-600 dark:text-sky-400 font-bold">+{aiScoreDetails[q.id].details?.popularity?.weighted?.toFixed(4)}</span>
                                        </div>
                                        <div className="bg-white dark:bg-slate-800 p-2 rounded border border-slate-100 dark:border-slate-700">
                                            <span className="text-[9px] text-slate-500 block uppercase font-bold tracking-widest">QUALIDADE PED.</span>
                                            <span className="font-mono text-amber-600 dark:text-amber-400 font-bold">+{aiScoreDetails[q.id].details?.quality?.weighted?.toFixed(4)}</span>
                                        </div>
                                        <div className="bg-white dark:bg-slate-800 p-2 rounded border border-slate-100 dark:border-slate-700">
                                            <span className="text-[9px] text-slate-500 block uppercase font-bold tracking-widest">RECÊNCIA ANO</span>
                                            <span className="font-mono text-emerald-600 dark:text-emerald-400 font-bold">+{aiScoreDetails[q.id].details?.recency?.weighted?.toFixed(4)}</span>
                                        </div>
                                        <div className="bg-indigo-50 dark:bg-indigo-900/20 p-2 rounded border border-indigo-100 dark:border-indigo-800">
                                            <span className="text-[9px] text-indigo-500 dark:text-indigo-400 block uppercase font-bold tracking-widest">INTENT BOOSTER</span>
                                            <span className="font-mono text-purple-600 dark:text-purple-400 font-bold">+{aiScoreDetails[q.id].details?.intent?.weighted?.toFixed(4)}</span>
                                        </div>
                                    </div>
                                </div>
                            )}
                            <QuestionCard question={q} />
                        </div>
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
                        <span className="text-xs text-slate-500 dark:text-slate-400 mt-1 w-full text-center sm:w-auto sm:ml-4 sm:mt-0">
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
