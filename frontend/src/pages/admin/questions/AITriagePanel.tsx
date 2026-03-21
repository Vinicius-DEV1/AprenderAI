import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';
import { TriageAction } from './components/Common';

interface AITriagePanelProps {
    counts: any;
    triageFilters: any;
    setTriageFilters: (filters: any) => void;
    availableSubjects: string[];
    pendingQuestions: any;
    removingIds: number[];
    activeMenu: number | null;
    setActiveMenu: (id: number | null) => void;
    adminActions: any;
    setDeleteModal: (modal: any) => void;
    ui: any;
    triagePage: number;
    setTriagePage: (page: number) => void;
    SmartPagination: any;
}

export default function AITriagePanel({
    counts,
    triageFilters,
    setTriageFilters,
    availableSubjects,
    pendingQuestions,
    removingIds,
    activeMenu,
    setActiveMenu,
    adminActions,
    setDeleteModal,
    ui,
    triagePage,
    setTriagePage,
    SmartPagination
}: AITriagePanelProps) {
    if (counts.pending_total === 0) return null;

    return (
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
                        onClick={() => ui.openBatchModal(counts.pending_total)}
                        className="px-6 py-3 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2"
                    >
                        🧨 Processamento em Lote
                    </button>
                    <p className="text-[9px] text-gray-400 font-bold uppercase tracking-tighter text-right">Ação em massa para agilizar curadoria</p>
                </div>
            </div>

            <div className="w-full bg-white rounded-3xl border border-white shadow-xl flex flex-col overflow-hidden">
                {/* Triage Filters */}
                <div className="p-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:flex xl:flex-nowrap gap-3 bg-gray-50/50 items-center">
                    <div className="relative flex-grow">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">🔍</span>
                        <input
                            placeholder="Buscar na triagem..."
                            className="w-full pl-9 pr-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all"
                            value={triageFilters.triage_search}
                            onChange={e => setTriageFilters({ ...triageFilters, triage_search: e.target.value })}
                        />
                    </div>
                    <select
                        className="w-full xl:w-[160px] px-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all"
                        value={triageFilters.triage_status}
                        onChange={e => setTriageFilters({ ...triageFilters, triage_status: e.target.value })}
                    >
                        <option value="">Todos os Status</option>
                        <option value="missing_difficulty">Sem Dificuldade</option>
                        <option value="missing_explanation">Sem Explicação</option>
                        <option value="missing_classification">Sem Classificação</option>
                        <option value="both_missing">Crítico (Ambos)</option>
                    </select>
                    <select
                        className="w-full xl:w-[140px] px-4 py-2.5 bg-white rounded-xl text-xs font-bold border border-gray-200 focus:ring-2 focus:ring-indigo-500 transition-all"
                        value={triageFilters.triage_subject}
                        onChange={e => setTriageFilters({ ...triageFilters, triage_subject: e.target.value })}
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
                                                            {q.latest_triage_log?.issues_detected?.includes('ERRO: IMAGEM NÃO CARREGADA/ALUCINAÇÃO DE TEXTO') && (
                                                                <span className="px-1.5 py-0.5 bg-red-600 text-white text-[8px] rounded font-black border border-red-700 uppercase tracking-tighter shadow-sm animate-pulse mb-1 block">
                                                                    🚫 Mídia Alucinada
                                                                </span>
                                                            )}
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

                                                                <div className="h-0.5 bg-gray-50 my-1"></div>

                                                                <button
                                                                    onClick={() => {
                                                                        setDeleteModal({ isOpen: true, id: q.id });
                                                                        setActiveMenu(null);
                                                                    }}
                                                                    className="px-3 py-2 text-[10px] rounded-xl font-black transition flex items-center gap-2 border border-transparent uppercase tracking-wider bg-red-50 text-red-600 hover:bg-red-100"
                                                                >
                                                                    🗑️ EXCLUIR QUESTÃO
                                                                </button>
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
                <div className="p-4 bg-gray-50/50 border-t border-gray-50">
                    <SmartPagination
                        currentPage={triagePage}
                        lastPage={pendingQuestions.last_page || 1}
                        onPageChange={setTriagePage}
                    />
                </div>
            </div>
        </div>
    );
}
