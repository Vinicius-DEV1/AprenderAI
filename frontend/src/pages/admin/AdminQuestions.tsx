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
        <div className="p-6 max-w-[1600px] mx-auto space-y-8 animate-in fade-in duration-500 bg-gray-50/30 min-h-screen">
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

            {/* AI TRIAGE SECTION */}
            {counts.pending_total > 0 && (
                <div className="bg-gradient-to-br from-indigo-50/50 to-purple-50/50 border border-indigo-100 rounded-3xl p-8 shadow-sm">
                    <div className="flex flex-col lg:flex-row justify-between items-start gap-8">
                        <div className="w-full lg:w-1/3 space-y-6">
                            <div className="flex items-center gap-3">
                                <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-indigo-200">🤖</div>
                                <div>
                                    <h2 className="text-2xl font-black text-gray-900 leading-tight">Triagem de IA</h2>
                                    <p className="text-indigo-600 text-sm font-bold uppercase tracking-wider">{counts.pending_total} Questões Pendentes</p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3 mb-4">
                                <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                    🟠 {counts.missing_difficulty} sem dificuldade
                                </span>
                                <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    🔵 {counts.missing_explanation} sem explicação
                                </span>
                                <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                    🏷️ {counts.missing_classification} sem taxonomia
                                </span>
                                <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    🔴 {counts.both_missing} incompletas (múltiplos)
                                </span>
                            </div>

                            <button
                                onClick={() => setIsBatchModalOpen(true)}
                                className="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-indigo-700 transition shadow-xl shadow-indigo-200"
                            >
                                🧨 Processamento em Lote
                            </button>
                        </div>

                        <div className="w-full lg:w-2/3 bg-white rounded-3xl border border-white shadow-xl flex flex-col overflow-hidden">
                            {/* Triage Filters */}
                            <div className="p-4 border-b border-gray-50 flex flex-wrap gap-2 bg-gray-50/30">
                                <input
                                    placeholder="Buscar na triagem..."
                                    className="flex-grow px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500"
                                    value={triageFilters.triage_search}
                                    onChange={e => setTriageFilters(prev => ({ ...prev, triage_search: e.target.value }))}
                                />
                                <select
                                    className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500"
                                    value={triageFilters.triage_status}
                                    onChange={e => setTriageFilters(prev => ({ ...prev, triage_status: e.target.value }))}
                                >
                                    <option value="">Status da Triagem</option>
                                    <option value="missing_difficulty">Sem Dificuldade</option>
                                    <option value="missing_explanation">Sem Explicação</option>
                                    <option value="missing_classification">Sem Classificação</option>
                                    <option value="both_missing">Crítico (Ambos)</option>
                                </select>
                                <select
                                    className="px-4 py-2 bg-white rounded-xl text-sm font-bold border border-gray-100 focus:ring-2 focus:ring-indigo-500"
                                    value={triageFilters.triage_subject}
                                    onChange={e => setTriageFilters(prev => ({ ...prev, triage_subject: e.target.value }))}
                                >
                                    <option value="">Matéria</option>
                                    {availableSubjects.map((s: string) => <option key={s} value={s}>{s}</option>)}
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left">
                                    <thead className="bg-gray-50/50">
                                        <tr>
                                            <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">Questão</th>
                                            <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">Contexto</th>
                                            <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">Status</th>
                                            <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase text-right">Ações IA</th>
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
                                                        className={`hover:bg-gray-50/50 transition-colors ${removingIds.includes(q.id) ? 'pointer-events-none' : ''}`}
                                                    >
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col">
                                                                <span className="text-xs font-black text-gray-300 font-mono">#{q.id}</span>
                                                                <div className="text-sm font-bold text-gray-700 line-clamp-3 w-64" dangerouslySetInnerHTML={{ __html: q.statement }}></div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col gap-1">
                                                                <span className="text-[10px] font-black bg-blue-50 text-blue-600 px-2 py-0.5 rounded uppercase w-max">{q.subjects?.[0]?.name || 'N/A'}</span>
                                                                <span className="text-[10px] font-black bg-gray-50 text-gray-500 px-2 py-0.5 rounded uppercase w-max w-24 truncate" title={q.organization}>{q.organization || 'Inédita'}</span>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col items-start gap-1">
                                                                {missingDiff && missingExpl && missingClass ? (
                                                                    <span className="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] rounded-full font-medium border border-red-200">🔴 Incompleta</span>
                                                                ) : (
                                                                    <>
                                                                        {missingDiff && <span className="px-2 py-0.5 bg-orange-100 text-orange-700 text-[10px] rounded-full font-medium border border-orange-200">Dificuldade</span>}
                                                                        {missingExpl && <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] rounded-full font-medium border border-blue-200">Explicação</span>}
                                                                        {missingClass && <span className="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] rounded-full font-medium border border-yellow-200">Taxonomia</span>}
                                                                    </>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex justify-end gap-1.5 flex-wrap w-48 ml-auto">
                                                                {missingDiff && (
                                                                    <TriageAction
                                                                        icon="⚡ Dificul"
                                                                        onClick={() => adminActions.mutate({ id: q.id, action: 'evaluate-difficulty' })}
                                                                        pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'evaluate-difficulty'}
                                                                        variant="blade-orange"
                                                                    />
                                                                )}
                                                                {missingExpl && (
                                                                    <TriageAction
                                                                        icon="📝 Explic"
                                                                        onClick={() => adminActions.mutate({ id: q.id, action: 'generate-explanation' })}
                                                                        pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'generate-explanation'}
                                                                        variant="blade-blue"
                                                                    />
                                                                )}
                                                                {missingClass && (
                                                                    <TriageAction
                                                                        icon="🏷️ Classif"
                                                                        onClick={() => adminActions.mutate({ id: q.id, action: 'classify' })}
                                                                        pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'classify'}
                                                                        variant="blade-yellow"
                                                                    />
                                                                )}
                                                                <TriageAction
                                                                    icon="👁️ Ver"
                                                                    onClick={() => { }}
                                                                    variant="blade-indigo"
                                                                />
                                                                <TriageAction
                                                                    icon="🚀 Full"
                                                                    onClick={() => adminActions.mutate({ id: q.id, action: 'complete' })}
                                                                    pending={adminActions.isPending && adminActions.variables?.id === q.id && adminActions.variables?.action === 'complete'}
                                                                    variant="blade-green"
                                                                />
                                                            </div>
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
                </div>
            )}

            {/* MAIN QUESTION BANK */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div className="p-6 border-b border-gray-50 bg-gray-50/30 flex flex-wrap gap-4 items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-lg">🏦</div>
                        <h2 className="text-xl font-black text-gray-900">Banco Completo</h2>
                    </div>

                    <div className="flex flex-wrap gap-2">
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
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="bg-gray-50/80">
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase w-20">ID</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">Enunciado / Disciplina</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase w-32">Dificuldade</th>
                                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {questions.data.map((q: any) => (
                                <tr key={q.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4 text-sm font-black text-gray-300 font-mono">#{q.id}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-col">
                                            <div dangerouslySetInnerHTML={{ __html: q.statement }} className="text-sm font-bold text-gray-700 line-clamp-1 w-[400px]" />
                                            <div className="flex gap-2 mt-1 items-center">
                                                <span className="text-[10px] font-black text-indigo-400 uppercase">{q.subjects?.[0]?.name || 'Sem Matéria'}</span>
                                                <span className="text-[10px] font-black text-gray-300 uppercase">•</span>
                                                <span className="text-[10px] font-black text-gray-400 uppercase">{q.organization || 'AprenderAI'} {q.year && `/ ${q.year}`}</span>
                                                <span className="text-[10px] font-black text-gray-300 uppercase">•</span>
                                                {q.format === 'true_false' ? (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-700 uppercase tracking-tighter">⚖️ Certo/Errado</span>
                                                ) : (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-600 uppercase tracking-tighter">📝 Múltipla Escolha</span>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <DifficultyBadge level={q.difficulty} />
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex justify-end gap-2 items-center">
                                            <button className="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] rounded hover:bg-indigo-200 font-medium flex items-center gap-1" title="Ver">👁️ Ver</button>
                                            <Link to={`/admin/questions/${q.id}/edit`} className="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] rounded hover:bg-blue-200 font-medium flex items-center gap-1">✏️ Editar</Link>
                                            <button onClick={() => adminActions.mutate({ id: q.id, action: 'evaluate-difficulty' })} className="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] rounded hover:bg-purple-200 font-medium flex items-center gap-1" title="Reavaliar IA">⚡ IA</button>
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
        </div>
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
            className={`px-2 py-0.5 text-[10px] rounded font-medium transition shadow-sm flex items-center gap-1 ${pending ? 'opacity-50 cursor-wait bg-gray-100 border-none' : ''} ${variants[variant]}`}
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
