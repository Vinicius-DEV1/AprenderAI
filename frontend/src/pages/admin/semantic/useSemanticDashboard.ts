import React, { useState, useEffect, useRef } from 'react';
import api from '../../api/axios';
import { toast } from 'sonner';
import { DashboardStats, ConfigState } from './Types';

export const useSemanticDashboard = () => {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [configLoading, setConfigLoading] = useState(false);
    const [testLoading, setTestLoading] = useState(false);
    const [reindexing, setReindexing] = useState(false);
    const [reindexingIntents, setReindexingIntents] = useState(false);
    const [clearingCache, setClearingCache] = useState(false);
    const [clearingQueue, setClearingQueue] = useState(false);
    const [resetting, setResetting] = useState(false);
    const [isResetModalOpen, setIsResetModalOpen] = useState(false);
    const [resetConfirmText, setResetConfirmText] = useState('');
    const [wakingUp, setWakingUp] = useState(false);
    const [actionsMenuOpen, setActionsMenuOpen] = useState(false);
    const actionsMenuRef = useRef<HTMLDivElement>(null);

    // Config form
    const [configState, setConfigState] = useState<ConfigState>({
        vector_search_enabled: true,
        concept_detection_threshold: 0.45,
        rerank_weights: {
            vector: 0.60,
            popularity: 0.15,
            quality: 0.15,
            recency: 0.10
        },
        search_cache_enabled: true,
    });

    // Index Modal (Questions)
    const [isIndexModalOpen, setIsIndexModalOpen] = useState(false);
    const [indexBatchLimit, setIndexBatchLimit] = useState(500);
    const [indexForce, setIndexForce] = useState(false);

    // Intent Index Modal (Subjects/Topics)
    const [isIntentModalOpen, setIsIntentModalOpen] = useState(false);
    const [intentBatchLimit, setIntentBatchLimit] = useState(1000);
    const [intentForce, setIntentForce] = useState(false);

    // Test search
    const [searchPrompt, setSearchPrompt] = useState('');
    const [searchResults, setSearchResults] = useState<any>(null);

    const parsePipelineSteps = (logs: string[]) => {
        const steps = {
            lexical: { positive: [] as string[], negative: [] as string[] },
            intent: [] as { type: string, value: string }[],
            expansion: [] as string[],
            temporal: null as { operator: string, year: number } | null,
            difficulty: null as string | null,
            organizations: [] as string[],
            institutions: [] as string[],
            vector: { statement: false, concept: false, explanation: false },
            rerank: [] as string[]
        };

        if (!logs) return steps;

        logs.forEach(log => {
            if (log.includes(" - Positive:")) {
                const match = log.match(/- Positive: '(.*)'/);
                if (match) steps.lexical.positive = match[1].split(' ').filter(t => t);
            }
            if (log.includes(" - Negative:")) {
                const match = log.match(/- Negative: '(.*)'/);
                if (match) steps.lexical.negative = match[1].split(' ').filter(t => t);
            }
            if (log.includes("INTENT DETECTED:")) {
                const parts = log.split("INTENT DETECTED: ")[1].split(" ");
                steps.intent.push({ type: parts[0], value: parts.slice(1).join(" ") });
            }
            if (log.includes("EXCLUSION DETECTED:")) {
                const parts = log.split("EXCLUSION DETECTED: ")[1].split(" ");
                steps.intent.push({ type: 'EXCLUIR ' + parts[0], value: parts.slice(1).join(" ") });
            }
            if (log.includes("DIFFICULTY DETECTED:")) {
                steps.difficulty = log.split("DIFFICULTY DETECTED: ")[1];
            }
            if (log.includes("TEMPORAL OPERATOR:")) {
                const parts = log.split("TEMPORAL OPERATOR: ")[1].split(" ");
                steps.temporal = { operator: parts[0], year: parseInt(parts[1]) };
            }
            if (log.includes("ORG DETECTED:")) {
                steps.organizations.push(log.split("ORG DETECTED: ")[1]);
            }
            if (log.includes("INST DETECTED:")) {
                steps.institutions.push(log.split("INST DETECTED: ")[1]);
            }
        });

        return steps;
    };

    const handleReplay = (prompt: string) => {
        setSearchPrompt(prompt);
        const element = document.getElementById('maestro-search-input');
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
            element.focus();
        }
        toast.info(`Prompt "${prompt}" carregado no Maestro.`);
    };

    const loadStats = async () => {
        try {
            isBusyRef.current = true;
            setLoading(true);
            const res = await api.get('/api/v1/admin/semantic');
            setStats(res.data);
            setConfigState({
                vector_search_enabled: res.data.config.vector_search_enabled == 1 || res.data.config.vector_search_enabled == true,
                concept_detection_threshold: parseFloat(res.data.config.concept_detection_threshold) || 0.45,
                rerank_weights: {
                    vector: res.data.config.rerank_weights?.vector || 0.60,
                    popularity: res.data.config.rerank_weights?.popularity || 0.15,
                    quality: res.data.config.rerank_weights?.quality || 0.15,
                    recency: res.data.config.rerank_weights?.recency || 0.10
                },
                search_cache_enabled: res.data.config.search_cache_enabled || false
            });
        } catch (error) {
            toast.error('Erro ao carregar os dados do dashboard.');
        } finally {
            setLoading(false);
            isBusyRef.current = false;
        }
    };

    const isBusyRef = useRef(false);

    useEffect(() => {
        loadStats();

        const interval = setInterval(() => {
            if (!isBusyRef.current) {
                loadStats();
            }
        }, 10000);

        const handleClickOutside = (e: MouseEvent) => {
            if (actionsMenuRef.current && !actionsMenuRef.current.contains(e.target as Node)) {
                setActionsMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () => { clearInterval(interval); document.removeEventListener('mousedown', handleClickOutside); };
    }, []);

    const handleSaveConfig = async () => {
        try {
            setConfigLoading(true);
            await api.post('/api/v1/admin/semantic/config', configState);
            toast.success('Configuração atualizada. Pode levar alguns segundos para refletir globalmente.');
            await loadStats();
        } catch (error) {
            toast.error('Erro ao salvar as configurações.');
        } finally {
            setConfigLoading(false);
        }
    };

    const handleTestSearch = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!searchPrompt) return;

        try {
            setTestLoading(true);
            const res = await api.post('/api/v1/admin/semantic/test-search', { prompt: searchPrompt });
            setSearchResults(res.data);
            toast.success('Busca concluída!');
        } catch (error) {
            toast.error('Erro ao executar a busca de teste.');
            setSearchResults(null);
        } finally {
            setTestLoading(false);
        }
    };

    const handleReindex = async () => {
        try {
            isBusyRef.current = true;
            setReindexing(true);
            const res = await api.post('/api/v1/admin/semantic/reindex', {
                limit: indexBatchLimit,
                force: indexForce
            });
            toast.success(res.data.message || 'Indexação de questões iniciada!');
            setIsIndexModalOpen(false);
            loadStats();
        } catch (error) {
            toast.error('Erro ao disparar indexação de questões.');
        } finally {
            setReindexing(false);
            isBusyRef.current = false;
        }
    };

    const handleReindexIntents = async () => {
        try {
            isBusyRef.current = true;
            setReindexingIntents(true);
            const res = await api.post('/api/v1/admin/semantic/reindex-concepts', {
                limit: intentBatchLimit,
                force: intentForce
            });
            toast.success(res.data.message || 'Indexação de intenções iniciada!');
            setIsIntentModalOpen(false);
            loadStats();
        } catch (error) {
            toast.error('Erro ao disparar indexação de intenções.');
        } finally {
            setReindexingIntents(false);
            isBusyRef.current = false;
        }
    };

    const handleClearCache = async () => {
        if (!confirm('Isso apagará todo o histórico de buscas otimizadas (L1/L2 Cache). Próximas buscas podem demorar mais para processar. Deseja continuar?')) {
            return;
        }

        try {
            setClearingCache(true);
            const res = await api.post('/api/v1/admin/semantic/clear-cache');
            toast.success(res.data.message);
            loadStats();
        } catch (error) {
            toast.error('Erro ao limpar o cache.');
        } finally {
            setClearingCache(false);
        }
    };

    const handleClearQueue = async () => {
        if (!confirm('Isso removerá TODOS os jobs pendentes da fila de embeddings. Use se a fila estiver travada ou com muitos erros. Deseja continuar?')) {
            return;
        }

        try {
            setClearingQueue(true);
            const res = await api.post('/api/v1/admin/semantic/clear-queue');
            toast.success(res.data.message);
            loadStats();
        } catch (error) {
            toast.error('Erro ao limpar a fila de jobs.');
        } finally {
            setClearingQueue(false);
        }
    };

    const handleResetEmbeddings = async () => {
        if (resetConfirmText !== 'RESET') return;
        try {
            setResetting(true);
            const res = await api.post('/api/v1/admin/semantic/reset-embeddings', { confirm: 'RESET' });
            toast.success(res.data.message);
            setIsResetModalOpen(false);
            setResetConfirmText('');
            loadStats();
        } catch (error) {
            toast.error('Erro ao executar reset completo.');
        } finally {
            setResetting(false);
        }
    };

    const handleClearCongestion = async () => {
        setWakingUp(true);
        try {
            await api.post('/api/v1/admin/semantic/clear-congestion');
            toast.success('Jobs acordados! O sistema vai retentar imediatamente.');
            loadStats();
        } catch { 
            toast.error('Falha ao acordar os jobs.'); 
        } finally { 
            setWakingUp(false); 
        }
    };

    return {
        stats,
        loading,
        configLoading,
        testLoading,
        reindexing,
        reindexingIntents,
        clearingCache,
        clearingQueue,
        resetting,
        isResetModalOpen,
        setIsResetModalOpen,
        resetConfirmText,
        setResetConfirmText,
        wakingUp,
        actionsMenuOpen,
        setActionsMenuOpen,
        actionsMenuRef,
        configState,
        setConfigState,
        isIndexModalOpen,
        setIsIndexModalOpen,
        indexBatchLimit,
        setIndexBatchLimit,
        indexForce,
        setIndexForce,
        isIntentModalOpen,
        setIsIntentModalOpen,
        intentBatchLimit,
        setIntentBatchLimit,
        intentForce,
        setIntentForce,
        searchPrompt,
        setSearchPrompt,
        searchResults,
        setSearchResults,
        parsePipelineSteps,
        handleReplay,
        loadStats,
        handleSaveConfig,
        handleTestSearch,
        handleReindex,
        handleReindexIntents,
        handleClearCache,
        handleClearQueue,
        handleResetEmbeddings,
        handleClearCongestion
    };
};
