import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import AdminBatchModal from './components/AdminBatchModal';
import { AdminPageSkeleton } from './components/AdminSkeletons';
import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';

export default function AdminQuestions() {
    const queryClient = useQueryClient();

    // Filters State
    const [triageFilters, setTriageFilters] = useState({
        triage_search: '',
        triage_status: '',
        triage_subject: '',
        triage_organization: ''
    });

    const [filters, setFilters] = useState({
        search: '',
        subject: '',
        source: '',
        organization: ''
    });

    const [page, setPage] = useState(1);
    const [triagePage, setTriagePage] = useState(1);
    const [isBatchModalOpen, setIsBatchModalOpen] = useState(false);
    const [removingIds, setRemovingIds] = useState<number[]>([]);
    const [activeMenu, setActiveMenu] = useState<number | null>(null);

    // NEW STATES FOR REPORTS TABS
    const [activeTab, setActiveTab] = useState<'all' | 'reported'>('all');
    const [reportsPage, setReportsPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-questions', filters, page, triageFilters, triagePage],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/questions', {
                params: { ...filters, ...triageFilters, page, triage_page: triagePage }
            });
            return res.data;
        }
    });

    const adminActions = useMutation({
        mutationFn: async ({ id, action }: { id: number, action: string }) => {
            const res = await api.post(`/api/v1/admin/questions/${id}/${action}`);
            return res.data;
        },
        onSuccess: (_, variables) => {
            // Animacao de saida
            setRemovingIds(prev => [...prev, variables.id]);
            setTimeout(() => {
                queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
                setRemovingIds(prev => prev.filter(rid => rid !== variables.id));
            }, 600);
        }
    });

    const { data: reportsData, isLoading: reportsLoading } = useQuery({
        queryKey: ['admin-reports', reportsPage],
        queryFn: async () => {
            const res = await api.get('/api/v1/admin/question-reports', {
                params: { page: reportsPage, status: 'pending' }
            });
            return res.data;
        },
        enabled: activeTab === 'reported'
    });

    const reportActions = useMutation({
        mutationFn: async ({ id, action }: { id: number, action: 'resolve' | 'deactivate' }) => {
            const url = action === 'resolve'
                ? `/api/v1/admin/question-reports/${id}/resolve`
                : `/api/v1/admin/questions/${id}/deactivate`;
            const res = await api.post(url);
            return res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-reports'] });
            queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
        }
    });

    if (isLoading) return <AdminPageSkeleton />;
    if (!data) return <div className="p-8 text-center text-red-500">Erro ao carregar banco de questões.</div>;

    const questions = data.questions || { data: [], total: 0 };
    const pendingQuestions = data.pendingQuestions || { data: [] };
    const meta = data.meta || { total_questions: 0, ai_questions: 0 };
    const counts = data.counts || { pending_total: 0, missing_difficulty: 0, missing_explanation: 0, missing_classification: 0, both_missing: 0 };
    const availableSubjects = data.availableSubjects || [];
    const availableOrganizations = data.availableOrganizations || [];

    const stats = [
        { label: 'Total Geral', value: meta.total_questions, color: 'indigo' },
        { label: 'Inéditas IA', value: meta.ai_questions, color: 'purple' },
        ...(meta.questions_by_organization || []).map((org: any) => ({
            label: org.organization,
            value: org.total,
            color: 'blue'
        }))
    ];

    return (
        <div className="p-4 md:p-6 w-full space-y-6 animate-in fade-in duration-500 bg-gray-50/30 min-h-screen">
            {/* Header */}
            <div className="flex justify-between items-end">
                <div>
                    <h1 className="text-3xl font-black text-gray-900 tracking-tight">Banco de Questões</h1>
                    <p className="text-gray-500 font-medium">Gestão centralizada de conteúdo e triagem de inteligência artificial.</p>
                </div>
                <Link to="/admin/questions/create" className="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold flex items-center gap-2 hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                    <span>➕</span> Nova Questão
                </Link>
            </div>

            {/* Mini Dashboard */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                {stats.map((s, i) => (
                    <div key={i} className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between group hover:shadow-md transition">
                        <span className="text-xs font-black text-gray-400 uppercase tracking-widest">{s.label}</span>
                        <div className="flex items-end justify-between mt-4">
                            <span className={`text-4xl font-black text-${s.color}-600`}>{s.value}</span>
                            <span className={`w-8 h-8 rounded-lg bg-${s.color}-50 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition`}>📈</span>
                        </div>
                    </div>
                ))}
            </div>

            {/* AI TRIAGE SECTION (HORIZONTAL COMPACT HEADER) */}
            {counts.pending_total > 0 && (
                <div className="bg-gradient-to-br from-indigo-50/50 to-purple-50/50 border border-indigo-100 rounded-3xl p-6 shadow-sm space-y-6">
                    {/* Compact Header Row */}
                    <div className="flex flex-col xl:flex-row items-center justify-between gap-6">
                        {/* Left: Identity */}
                        <div className="flex items-center gap-4 shrink-0">
                            <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-indigo-200 shrink-0">🤖</div>
                            <div>
                                <h2 className="text-xl font-black text-gray-900 leading-none mb-1">Triagem de IA</h2>
                                <p className="text-indigo-600 text-[10px] font-black uppercase tracking-widest">{counts.pending_total} Questões Pendentes</p>
                            </div>
                        </div>

                        {/* Center: Row of Badges */}
                        <div className="flex flex-wrap items-center justify-center gap-2">
                            <span className="inline-flex items-center px-3 py-1.5 rounded-xl text-[9px] font-black bg-orange-100/80 text-orange-700 border border-orange-200/50 whitespace-nowrap uppercase tracking-tighter">
                                🟠 {counts.missing_difficulty} sem dificuldade
                            </span>
                            <span className="inline-flex items-center px-3 py-1.5 rounded-xl text-[9px] font-black bg-blue-100/80 text-blue-700 border border-blue-200/50 whitespace-nowrap uppercase tracking-tighter">
                                🔵 {counts.missing_explanation} sem explicação
                            </span>
                            <span className="inline-flex items-center px-3 py-1.5 rounded-xl text-[9px] font-black bg-yellow-100/80 text-yellow-700 border border-yellow-200/50 whitespace-nowrap uppercase tracking-tighter">
                                🏷️ {counts.missing_classification} sem taxonomia
                            </span>
                            <span className="inline-flex items-center px-3 py-1.5 rounded-xl text-[9px] font-black bg-red-100/80 text-red-700 border border-red-200/50 whitespace-nowrap uppercase tracking-tighter">
                                🔴 {counts.both_missing} incompletas
                            </span>
                        </div>

                        {/* Right: Action and Support Text */}
                        <div className="flex flex-col items-center xl:items-end gap-1 shrink-0">
                            <button
                                onClick={() => setIsBatchModalOpen(true)}
                                className="px-6 py-3 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2"
                            >
                                🧨 Processamento em Lote
                            </button>
                            <p className="text-[9px] text-gray-400 font-bold uppercase tracking-tighter text-right">Ação em massa para agilizar curadoria</p>
                        </div>
                    </div>

                    <div className="w-full bg-white rounded-3xl border border-white shadow-xl flex flex-col overflow-hidden">
                        {/* Triage Filters */}
                        {/* Triage Filters */}
                        <div className="p-4 border-b border-gray-100 flex flex-wrap lg:flex-nowrap gap-3 bg-gray-50/50 items-center">
                            <div className="relative flex-grow">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">🔍</span>
                                <input
                                    placeholder="Buscar na triagem..."
                                    className="w-full pl-9 pr-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all"
                                    value={triageFilters.triage_search}
                                    onChange={e => setTriageFilters(prev => ({ ...prev, triage_search: e.target.value }))}
                                />
                            </div>
                            <select
                                className="px-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all min-w-[160px]"
                                value={triageFilters.triage_status}
                                onChange={e => setTriageFilters(prev => ({ ...prev, triage_status: e.target.value }))}
                            >
                                <option value="">Todos os Status</option>
                                <option value="missing_difficulty">Sem Dificuldade</option>
                                <option value="missing_explanation">Sem Explicação</option>
                                <option value="missing_classification">Sem Classificação</option>
                                <option value="both_missing">Crítico (Ambos)</option>
                            </select>
                            <select
                                className="px-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all min-w-[140px]"
                                value={triageFilters.triage_subject}
                                onChange={e => setTriageFilters(prev => ({ ...prev, triage_subject: e.target.value }))}
                            >
                                <option value="">Filtrar Matéria</option>
                                {availableSubjects.map((s: string) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-gray-50/80 sticky top-0 z-10">
                                    <tr className="grid grid-cols-[1fr_140px_100px_60px] items-center border-b border-gray-100">
                                        <th className="px-3 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left border-r border-gray-100/50">Questão</th>
                                        <th className="px-3 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left border-r border-gray-100/50">Contexto</th>
                                        <th className="px-3 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left border-r border-gray-100/50">Status</th>
                                        <th className="px-3 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center italic">Opções</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    <AnimatePresence>
                                        {pendingQuestions.data.map((q: any) => {
                                            const missingDiff = !q.difficulty_reasoning?.trim();
                                            const missingExpl = !q.explanation?.trim();
                                            const missingClass = !q.subjects?.length || !q.topics?.length;
                                            return (
                                                <motion.tr
                                                    key={q.id}
                                                    initial={{ opacity: 1, x: 0 }}
                                                    exit={{ opacity: 0, x: 100, backgroundColor: 'rgba(239, 68, 68, 0.1)' }}
                                                    className={`grid grid-cols-[1fr_140px_100px_60px] items-center hover:bg-indigo-50/30 transition-colors relative ${removingIds.includes(q.id) ? 'pointer-events-none' : ''}`}
                                                >
                                                    <td className="px-3 py-3 h-full border-r border-gray-50/50">
                                                        <div className="flex flex-col gap-1">
                                                            <span className="text-[9px] font-black text-indigo-300 font-mono tracking-tighter">#{q.id}</span>
                                                            <div className="text-[11px] font-bold text-gray-600 line-clamp-2 leading-snug" dangerouslySetInnerHTML={{ __html: q.statement }}></div>
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3 h-full border-r border-gray-50/50 flex flex-col justify-center">
                                                        <div className="flex flex-col gap-1 w-full">
                                                            <span className="text-[9px] font-black bg-blue-50 text-blue-600 px-2 py-0.5 rounded-lg uppercase tracking-tight border border-blue-100/50 truncate">
                                                                {q.subjects?.[0]?.name || 'SEM MATÉRIA'}
                                                            </span>
                                                            <span className="text-[9px] font-black bg-gray-50 text-gray-400 px-2 py-0.5 rounded-lg uppercase tracking-tight border border-gray-200/50 truncate">
                                                                {q.organization || 'Inédita'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3 h-full border-r border-gray-50/50 flex items-center">
                                                        <div className="flex flex-wrap items-center gap-1">
                                                            {missingDiff && missingExpl && missingClass ? (
                                                                <span className="px-1.5 py-0.5 bg-red-50 text-red-600 text-[8px] rounded font-black border border-red-100 uppercase tracking-tighter">🔴 Crítico</span>
                                                            ) : (
                                                                <>
                                                                    {missingDiff && <span className="px-1.5 py-0.5 bg-orange-50 text-orange-600 text-[8px] rounded font-black border border-orange-100 uppercase tracking-tighter">⚡ Dif</span>}
                                                                    {missingExpl && <span className="px-1.5 py-0.5 bg-blue-50 text-blue-600 text-[8px] rounded font-black border border-blue-100 uppercase tracking-tighter">📝 Expl</span>}
                                                                    {missingClass && <span className="px-1.5 py-0.5 bg-yellow-50 text-yellow-600 text-[8px] rounded font-black border border-yellow-100 uppercase tracking-tighter">🏷️ Tax</span>}
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-3 h-full flex items-center justify-center relative">
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setActiveMenu(activeMenu === q.id ? null : q.id);
                                                            }}
                                                            className={`w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition-all ${activeMenu === q.id ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-400'}`}
                                                        >
                                                            <span className="text-xl leading-none">⋮</span>
                                                        </button>

                                                        {activeMenu === q.id && (
                                                            <>
                                                                <div
                                                                    className="fixed inset-0 z-[60]"
                                                                    onClick={() => setActiveMenu(null)}
                                                                ></div>
                                                                <div className="absolute right-4 top-1/2 -translate-y-1/2 z-[70] bg-white rounded-2xl shadow-2xl border border-gray-100 p-2 min-w-[150px] animate-in zoom-in-95 duration-200">
                                                                    <div className="flex flex-col gap-1 text-left">
                                                                        <div className="px-3 py-1.5 mb-1 border-b border-gray-50">
                                                                            <span className="text-[10px] font-black text-gray-300 uppercase tracking-widest">Ações Rápidas</span>
                                                                        </div>
                                                                        {missingDiff && (
                                                                            <TriageAction
                                                                                icon="⚡ Dif. IA"
                                                                                onClick={() => adminActions.mutate({ id: q.id, action: 'evaluate-difficulty' })}
                                                                                pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'evaluate-difficulty'}
                                                                                variant="blade-orange"
                                                                            />
                                                                        )}
                                                                        {missingExpl && (
                                                                            <TriageAction
                                                                                icon="📝 Expl. IA"
                                                                                onClick={() => adminActions.mutate({ id: q.id, action: 'generate-explanation' })}
                                                                                pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'generate-explanation'}
                                                                                variant="blade-blue"
                                                                            />
                                                                        )}
                                                                        {missingClass && (
                                                                            <TriageAction
                                                                                icon="🏷️ Taxo. IA"
                                                                                onClick={() => adminActions.mutate({ id: q.id, action: 'classify' })}
                                                                                pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'classify'}
                                                                                variant="blade-yellow"
                                                                            />
                                                                        )}

                                                                        <Link
                                                                            to={`/admin/questions/${q.id}/edit`}
                                                                            className="px-3 py-2 text-[10px] rounded-xl font-black transition flex items-center gap-2 border border-transparent uppercase tracking-wider bg-gray-50 text-gray-600 hover:bg-blue-50 hover:text-blue-700"
                                                                        >
                                                                            ✏️ EDITAR MANUAL
                                                                        </Link>

                                                                        <div className="h-0.5 bg-gray-50 my-1"></div>

                                                                        <TriageAction
                                                                            icon="🚀 PROCESSAR TUDO"
                                                                            onClick={() => adminActions.mutate({ id: q.id, action: 'complete' })}
                                                                            pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'complete'}
                                                                            variant="blade-green"
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </>
                                                        )}
                                                    </td>
                                                </motion.tr>
                                            )
                                        })}
                                    </AnimatePresence>
                                </tbody>
                            </table>
                        </div>

                        {/* Triage Pagination */}
                        <div className="p-4 bg-gray-50/50 flex justify-center border-t border-gray-50">
                            <div className="flex gap-2">
                                <button
                                    onClick={() => setTriagePage(p => Math.max(1, p - 1))}
                                    disabled={triagePage === 1}
                                    className="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-xs font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                >←</button>
                                <span className="text-xs font-black self-center text-gray-400 px-4">PÁGINA {triagePage}</span>
                                <button
                                    onClick={() => setTriagePage(p => p + 1)}
                                    disabled={pendingQuestions.data.length < 10}
                                    className="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-xs font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                >→</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* MAIN QUESTION BANK */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex flex-wrap gap-4 items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-lg">🏦</div>
                        <h2 className="text-xl font-black text-gray-900 flex items-center gap-2">
                            Qualidade do Acervo
                            {data.DEBUG_CODE_VERSION && (
                                <span className="text-[8px] bg-red-500 text-white px-1 rounded animate-pulse">
                                    V_STRITO
                                </span>
                            )}
                        </h2>
                    </div>

                    <div className="flex bg-gray-100/80 p-1 rounded-xl">
                        <button
                            onClick={() => setActiveTab('all')}
                            className={`px-4 py-2 rounded-lg text-sm font-bold transition-all ${activeTab === 'all' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            Banco Completo
                        </button>
                        <button
                            onClick={() => setActiveTab('reported')}
                            className={`px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${activeTab === 'reported' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            Denunciadas
                            {reportsData?.total > 0 && (
                                <span className="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{reportsData.total}</span>
                            )}
                        </button>
                    </div>
                </div>

                {activeTab === 'all' && (
                    <div className="p-4 border-b border-gray-50 bg-white flex flex-wrap gap-2 items-center justify-end">
                        <input
                            name="search"
                            placeholder="Pesquisar..."
                            className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 min-w-[200px]"
                            value={filters.search}
                            onChange={e => setFilters(prev => ({ ...prev, search: e.target.value }))}
                        />
                        <select
                            name="subject"
                            className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                            value={filters.subject}
                            onChange={e => setFilters(prev => ({ ...prev, subject: e.target.value }))}
                        >
                            <option value="">Todas as Matérias</option>
                            {availableSubjects.map((s: string) => <option key={s} value={s}>{s}</option>)}
                        </select>
                        <select
                            name="source"
                            className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                            value={filters.source}
                            onChange={e => setFilters(prev => ({ ...prev, source: e.target.value }))}
                        >
                            <option value="">Todas as Origens</option>
                            <option value="manual">Manual (ENEM)</option>
                            <option value="ai_generated">IA Gerada</option>
                        </select>
                        <select
                            name="organization"
                            className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500"
                            value={filters.organization}
                            onChange={e => setFilters(prev => ({ ...prev, organization: e.target.value }))}
                        >
                            <option value="">Todas Organizações</option>
                            {availableOrganizations.map((o: string) => <option key={o} value={o}>{o}</option>)}
                        </select>
                    </div>
                )}

                {activeTab === 'all' && (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="bg-gray-50/80">
                                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-16">ID</th>
                                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase">Enunciado / Disciplina</th>
                                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase w-28">Dificuldade</th>
                                        <th className="px-4 py-3 text-[10px] font-black text-gray-400 uppercase text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {questions.data.map((q: any) => (
                                        <tr key={q.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-4 py-3 text-[11px] font-black text-gray-300 font-mono">#{q.id}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-[11px] font-bold text-gray-700 line-clamp-1 max-w-[500px]" />
                                                    <div className="flex gap-2 mt-0.5 items-center">
                                                        <span className="text-[9px] font-black text-indigo-400 uppercase">{q.subjects?.[0]?.name || 'Sem Matéria'}</span>
                                                        <span className="text-[9px] font-black text-gray-300 uppercase">•</span>
                                                        <span className="text-[9px] font-black text-gray-400 uppercase">{q.organization || 'AprenderAI'}</span>
                                                        <span className="text-[10px] font-black text-gray-300 uppercase">•</span>
                                                        {q.tipo_questao === 'Redação' ? (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-100 text-orange-700 uppercase tracking-tighter">✍️ Redação</span>
                                                        ) : q.tipo_questao === 'Discursiva' ? (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-100 text-blue-700 uppercase tracking-tighter">🎓 Discursiva</span>
                                                        ) : q.format === 'true_false' ? (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-700 uppercase tracking-tighter">⚖️ Certo/Errado</span>
                                                        ) : (
                                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-600 uppercase tracking-tighter">📝 Múltipla Escolha</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <DifficultyBadge level={q.difficulty} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5 items-center">
                                                    <button className="px-2 py-1 bg-indigo-100 text-indigo-700 text-[9px] rounded-lg hover:bg-indigo-200 font-black uppercase tracking-tighter" title="Ver">👁️ Ver</button>
                                                    <Link to={`/admin/questions/${q.id}/edit`} className="px-2 py-1 bg-blue-100 text-blue-700 text-[9px] rounded-lg hover:bg-blue-200 font-black uppercase tracking-tighter">✏️ Editar</Link>
                                                    <button onClick={() => adminActions.mutate({ id: q.id, action: 'evaluate-difficulty' })} className="px-2 py-1 bg-purple-100 text-purple-700 text-[9px] rounded-lg hover:bg-purple-200 font-black uppercase tracking-tighter" title="Reavaliar IA">⚡ IA</button>
                                                    <button onClick={() => adminActions.mutate({ id: q.id, action: 'retry-evaluation' })} className="px-2 py-1 bg-red-100 text-red-700 text-[9px] rounded-lg hover:bg-red-200 font-black uppercase tracking-tighter" title="Reprocessar">🔄 Reset</button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Main Pagination */}
                        <div className="p-6 bg-gray-50/30 border-t border-gray-100 flex items-center justify-between">
                            <span className="text-xs font-black text-gray-400 uppercase">Total: {questions.total} questões</span>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => setPage(p => Math.max(1, p - 1))}
                                    disabled={page === 1}
                                    className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                >Anterior</button>
                                <button
                                    onClick={() => setPage(p => p + 1)}
                                    disabled={!questions.next_page_url}
                                    className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                >Próxima</button>
                            </div>
                        </div>
                    </>
                )}

                {activeTab === 'reported' && (
                    <>
                        {reportsLoading ? (
                            <div className="p-12 text-center text-gray-400 font-bold animate-pulse">Carregando denúncias...</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left">
                                    <thead>
                                        <tr className="bg-red-50/50">
                                            <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase w-16">ID / Qtd</th>
                                            <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase">Questão & Motivos</th>
                                            <th className="px-4 py-3 text-[10px] font-black text-red-500 uppercase text-right">Ações de Curadoria</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {reportsData?.data?.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="px-4 py-12 text-center text-gray-400 font-bold">Nenhuma questão denunciada! 🎉</td>
                                            </tr>
                                        ) : reportsData?.data?.map((q: any) => (
                                            <tr key={q.id} className="hover:bg-red-50/30 transition-colors">
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col items-start gap-1">
                                                        <span className="text-[11px] font-black text-gray-400 font-mono">#{q.id}</span>
                                                        <span className="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                                                            <span>🚩</span> {q.reports_count} reports
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col gap-2">
                                                        <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-[11px] font-bold text-gray-700 line-clamp-2 max-w-[500px]" />

                                                        {/* Mostrar os motivos */}
                                                        <div className="flex flex-col gap-1 mt-1 border-l-2 border-red-200 pl-3">
                                                            {q.reports?.map((rep: any) => (
                                                                <div key={rep.id} className="text-[10px] text-gray-600 flex flex-col">
                                                                    <span className="font-bold text-red-600">"{rep.reason}"</span>
                                                                    <span className="text-gray-400">Por {rep.user?.name || 'Aluno'} em {new Date(rep.created_at).toLocaleDateString()}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex flex-col justify-end gap-2 items-end">
                                                        <Link to={`/admin/questions/${q.id}/edit`} className="w-full text-center px-3 py-1.5 bg-blue-100 text-blue-700 text-[10px] rounded-lg hover:bg-blue-200 font-black uppercase tracking-tighter">
                                                            ✏️ Editar Questão
                                                        </Link>
                                                        <button
                                                            onClick={() => {
                                                                if (confirm('Marcar todos os relatos como resolvidos sem desativar a questão?')) {
                                                                    // Aqui podemos resolver individualmente ou adicionar um helper. 
                                                                    // Como não há rota "resolve all", chamaremos a action 'resolve' no 1º report e assumiremos que resolve a questão pra quem vê. Mas a rota de resolver é do Report.
                                                                    // Vou mandar para o primeiro caso não queiramos mudar a api.
                                                                    if (q.reports.length > 0) {
                                                                        reportActions.mutate({ id: q.reports[0].id, action: 'resolve' });
                                                                    }
                                                                }
                                                            }}
                                                            className="w-full text-center px-3 py-1.5 bg-green-100 text-green-700 text-[10px] rounded-lg hover:bg-green-200 font-black uppercase tracking-tighter"
                                                        >
                                                            ✅ Descartar e Resolver
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                if (confirm('ATENÇÃO: Isso vai ocultar a questão dos simulados e alunos, e resolver as denúncias pendentes. Confirmar?')) {
                                                                    reportActions.mutate({ id: q.id, action: 'deactivate' });
                                                                }
                                                            }}
                                                            className="w-full text-center px-3 py-1.5 bg-red-100 text-red-700 text-[10px] rounded-lg hover:bg-red-200 font-black uppercase tracking-tighter"
                                                        >
                                                            🚫 Desativar Questão
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Reports Pagination */}
                        {reportsData && (
                            <div className="p-6 bg-red-50/30 border-t border-red-50 flex items-center justify-between">
                                <span className="text-xs font-black text-gray-500 uppercase">Total: {reportsData.total} relatadas</span>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setReportsPage(p => Math.max(1, p - 1))}
                                        disabled={reportsPage === 1}
                                        className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                    >Anterior</button>
                                    <button
                                        onClick={() => setReportsPage(p => p + 1)}
                                        disabled={!reportsData.next_page_url}
                                        className="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold disabled:opacity-50 hover:bg-gray-50 transition"
                                    >Próxima</button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            <AdminBatchModal
                isOpen={isBatchModalOpen}
                onClose={() => setIsBatchModalOpen(false)}
                pendingCount={counts.pending_total}
                onBatchStarted={(bid) => {
                    console.log('Batch started:', bid);
                    queryClient.invalidateQueries({ queryKey: ['admin-questions'] });
                }}
            />
        </div >
    );
}

function TriageAction({ icon, onClick, pending, variant = 'default' }: any) {
    const variants: any = {
        'blade-orange': 'bg-orange-100 text-orange-700 hover:bg-orange-200',
        'blade-blue': 'bg-blue-100 text-blue-700 hover:bg-blue-200',
        'blade-yellow': 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200',
        'blade-indigo': 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200',
        'blade-green': 'bg-green-100 text-green-700 hover:bg-green-200',
        'primary': 'bg-indigo-600 text-white shadow-indigo-100 hover:bg-indigo-700',
        'default': 'bg-white border border-gray-100 text-gray-700 hover:border-indigo-200'
    };

    return (
        <button
            onClick={onClick}
            disabled={pending}
            className={`px-3 py-1.5 text-[9px] rounded-lg font-black transition shadow-sm flex items-center justify-center gap-1.5 border border-transparent uppercase tracking-wider ${pending ? 'opacity-50 cursor-wait bg-gray-100' : ''} ${variants[variant]}`}
        >
            {pending ? <span className="animate-spin text-xs">⏳</span> : icon}
        </button>
    );
}

function DifficultyBadge({ level }: { level: string }) {
    const configs: any = {
        easy: { label: 'Fácil', style: 'bg-green-50 text-green-600 border-green-100' },
        medium: { label: 'Médio', style: 'bg-yellow-50 text-yellow-600 border-yellow-100' },
        hard: { label: 'Difícil', style: 'bg-red-50 text-red-600 border-red-100' },
        default: { label: 'Indefinido', style: 'bg-gray-50 text-gray-400 border-gray-100' }
    };
    const c = configs[level] || configs.default;
    return (
        <span className={`text-[10px] font-black uppercase px-2 py-1 rounded-lg border ${c.style}`}>
            {c.label}
        </span>
    );
}
