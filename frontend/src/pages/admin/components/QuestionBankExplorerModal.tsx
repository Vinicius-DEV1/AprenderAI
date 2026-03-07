import { useState, useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import api from '../../../api/axios';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Topic {
    id: number;
    name: string;
    total: number;
}

interface Subject {
    id: number;
    name: string;
    total: number;
    topics: Topic[];
}

interface ExplorerData {
    organization: string;
    total_questions: number;
    subjects: Subject[];
}

interface Props {
    organization: string | null;
    onClose: () => void;
}

// ─── Subject / Topic icons ─────────────────────────────────────────────────
const subjectIcon = (name: string): string => {
    const n = name.toLowerCase();
    if (n.includes('mat') || n.includes('álgebra') || n.includes('cálculo')) return '📐';
    if (n.includes('port') || n.includes('redação') || n.includes('literatura')) return '📖';
    if (n.includes('bio') || n.includes('ecol') || n.includes('gen')) return '🧬';
    if (n.includes('fís') || n.includes('física')) return '⚡';
    if (n.includes('quím') || n.includes('química')) return '🧪';
    if (n.includes('hist') || n.includes('história')) return '🏛️';
    if (n.includes('geo') || n.includes('geog')) return '🌎';
    if (n.includes('inglês') || n.includes('espanhol') || n.includes('língua')) return '🌐';
    if (n.includes('filo') || n.includes('soci')) return '💡';
    if (n.includes('arte') || n.includes('música')) return '🎨';
    if (n.includes('ed. fís') || n.includes('educação física')) return '🏃';
    return '📚';
};

const orgGradient = (org: string): string => {
    const o = org.toUpperCase();
    if (o === 'ENEM') return 'from-blue-600 via-indigo-700 to-purple-800';
    if (o.includes('VUNESP')) return 'from-emerald-600 via-teal-700 to-cyan-800';
    if (o.includes('FUVEST')) return 'from-orange-500 via-red-600 to-rose-700';
    if (o.includes('USP')) return 'from-yellow-500 via-amber-600 to-orange-700';
    if (o.includes('FGVS') || o.includes('FGV')) return 'from-violet-600 via-purple-700 to-indigo-800';
    if (o.includes('CESPE') || o.includes('CEBRASPE')) return 'from-sky-600 via-blue-700 to-indigo-800';
    return 'from-slate-600 via-gray-700 to-zinc-800';
};

// ─── SubjectCard ──────────────────────────────────────────────────────────────

function SubjectCard({ subject, maxTotal, searchTopic }: { subject: Subject; maxTotal: number; searchTopic: string }) {
    const [expanded, setExpanded] = useState(false);
    const icon = subjectIcon(subject.name);
    const percentage = maxTotal > 0 ? Math.round((subject.total / maxTotal) * 100) : 0;

    const filteredTopics = searchTopic.trim()
        ? subject.topics.filter(t => t.name.toLowerCase().includes(searchTopic.toLowerCase()))
        : subject.topics;

    const hasTopics = subject.topics.length > 0;

    return (
        <motion.div
            layout
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-lg transition-shadow duration-300"
        >
            {/* Subject Header */}
            <button
                onClick={() => hasTopics && setExpanded(v => !v)}
                className={`w-full p-5 flex items-center gap-4 text-left ${hasTopics ? 'cursor-pointer' : 'cursor-default'}`}
            >
                {/* Icon */}
                <div className="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-2xl shrink-0 shadow-sm">
                    {icon}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5">
                        <span className="font-black text-gray-900 text-base leading-tight truncate">{subject.name}</span>
                        {hasTopics && (
                            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 px-2 py-0.5 rounded-lg border border-gray-100 shrink-0">
                                {subject.topics.length} assuntos
                            </span>
                        )}
                    </div>
                    {/* Progress bar */}
                    <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${percentage}%` }}
                            transition={{ duration: 0.8, ease: 'easeOut' }}
                            className="h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full"
                        />
                    </div>
                </div>

                {/* Total */}
                <div className="text-right shrink-0">
                    <div className="text-2xl font-black text-indigo-700">{subject.total.toLocaleString('pt-BR')}</div>
                    <div className="text-[10px] font-bold text-gray-400 uppercase">{percentage}%</div>
                </div>

                {/* Chevron */}
                {hasTopics && (
                    <motion.div
                        animate={{ rotate: expanded ? 180 : 0 }}
                        transition={{ duration: 0.2 }}
                        className="text-gray-400 shrink-0"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M19 9l-7 7-7-7" />
                        </svg>
                    </motion.div>
                )}
            </button>

            {/* Topics Accordion */}
            <AnimatePresence>
                {expanded && filteredTopics.length > 0 && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.25, ease: 'easeInOut' }}
                        className="overflow-hidden"
                    >
                        <div className="px-5 pb-4 pt-0 border-t border-gray-50">
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 pt-3">
                                {filteredTopics.map((topic, idx) => (
                                    <motion.div
                                        key={topic.id}
                                        initial={{ opacity: 0, scale: 0.95 }}
                                        animate={{ opacity: 1, scale: 1 }}
                                        transition={{ delay: idx * 0.03, duration: 0.15 }}
                                        className="flex items-center justify-between bg-gray-50/80 rounded-xl px-3 py-2.5 border border-gray-100 hover:bg-indigo-50/50 hover:border-indigo-100 transition-colors"
                                    >
                                        <span className="text-[11px] font-bold text-gray-700 leading-tight flex-1 mr-2">{topic.name}</span>
                                        <span className="text-[11px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-lg shrink-0 border border-indigo-100">
                                            {topic.total}
                                        </span>
                                    </motion.div>
                                ))}
                            </div>
                            {searchTopic.trim() && filteredTopics.length === 0 && (
                                <p className="text-center text-xs text-gray-400 py-3">Nenhum assunto encontrado.</p>
                            )}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </motion.div>
    );
}

// ─── BarChart View ─────────────────────────────────────────────────────────────

function BarChartView({ subjects }: { subjects: Subject[] }) {
    const max = subjects[0]?.total ?? 1;
    const colors = [
        'from-indigo-500 to-purple-600',
        'from-blue-500 to-indigo-600',
        'from-violet-500 to-indigo-600',
        'from-purple-500 to-pink-600',
        'from-sky-500 to-blue-600',
        'from-teal-500 to-cyan-600',
        'from-emerald-500 to-teal-600',
    ];

    return (
        <div className="space-y-3">
            {subjects.map((s, idx) => {
                const width = max > 0 ? (s.total / max) * 100 : 0;
                const color = colors[idx % colors.length];
                return (
                    <div key={s.id} className="group flex items-center gap-4">
                        <div className="w-7 text-lg text-center">{subjectIcon(s.name)}</div>
                        <div className="w-40 shrink-0 text-[11px] font-black text-gray-700 truncate">{s.name}</div>
                        <div className="flex-1 h-8 bg-gray-100 rounded-xl overflow-hidden relative">
                            <motion.div
                                initial={{ width: 0 }}
                                animate={{ width: `${width}%` }}
                                transition={{ duration: 0.8, ease: 'easeOut', delay: idx * 0.06 }}
                                className={`h-full bg-gradient-to-r ${color} rounded-xl flex items-center justify-end pr-3`}
                            >
                                <span className="text-[10px] font-black text-white drop-shadow">{s.total.toLocaleString('pt-BR')}</span>
                            </motion.div>
                        </div>
                        <div className="w-10 text-right text-[10px] font-bold text-gray-400">
                            {max > 0 ? Math.round((s.total / max) * 100) : 0}%
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ─── Main Modal ────────────────────────────────────────────────────────────────

export default function QuestionBankExplorerModal({ organization, onClose }: Props) {
    const [viewMode, setViewMode] = useState<'hierarchy' | 'chart'>('hierarchy');
    const [searchSubject, setSearchSubject] = useState('');
    const [searchTopic, setSearchTopic] = useState('');
    const [sortBy, setSortBy] = useState<'total' | 'name'>('total');
    const scrollRef = useRef<HTMLDivElement>(null);

    const { data, isLoading, isError } = useQuery<ExplorerData>({
        queryKey: ['exam-explorer', organization],
        queryFn: async () => {
            const res = await api.get(`/api/v1/admin/exams/explorer/${encodeURIComponent(organization!)}`);
            return res.data;
        },
        enabled: !!organization,
        staleTime: 5 * 60 * 1000, // cache 5min
    });

    // Close on Escape
    useEffect(() => {
        const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [onClose]);

    // Prevent body scroll
    useEffect(() => {
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, []);

    if (!organization) return null;

    const gradient = orgGradient(organization);

    // Filter + sort subjects
    let subjects = data?.subjects ?? [];
    if (searchSubject.trim()) {
        subjects = subjects.filter(s => s.name.toLowerCase().includes(searchSubject.toLowerCase()));
    }
    if (sortBy === 'name') {
        subjects = [...subjects].sort((a, b) => a.name.localeCompare(b.name));
    }
    const maxTotal = subjects[0]?.total ?? 1;

    return (
        <AnimatePresence>
            <motion.div
                key="explorer-overlay"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="fixed inset-0 z-[200] flex flex-col bg-gray-50"
            >
                {/* ── Hero Header ── */}
                <div className={`bg-gradient-to-r ${gradient} px-6 md:px-10 py-6 flex items-center gap-6 shrink-0 shadow-xl`}>
                    {/* Left: Org Info */}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-3 mb-1">
                            <span className="text-white/60 text-xs font-black uppercase tracking-widest">Banco de Questões</span>
                            <span className="bg-white/20 text-white/80 text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-widest">Explorer</span>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black text-white tracking-tight leading-none">{organization}</h1>
                        <div className="flex items-center gap-4 mt-2">
                            {isLoading ? (
                                <span className="text-white/60 text-sm font-bold animate-pulse">Carregando dados...</span>
                            ) : (
                                <>
                                    <span className="text-white font-black text-lg">
                                        {(data?.total_questions ?? 0).toLocaleString('pt-BR')}
                                        <span className="text-white/60 text-sm font-bold ml-1">questões no total</span>
                                    </span>
                                    <span className="bg-white/20 text-white text-xs font-black px-3 py-1 rounded-full">
                                        {(data?.subjects?.length ?? 0)} matérias
                                    </span>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Right: Close */}
                    <button
                        onClick={onClose}
                        className="w-12 h-12 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-white transition-all hover:scale-110 shrink-0"
                        title="Fechar (Esc)"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* ── Controls Bar ── */}
                <div className="bg-white border-b border-gray-100 px-6 md:px-10 py-3 flex flex-wrap items-center gap-3 shrink-0 shadow-sm">
                    {/* View toggle */}
                    <div className="flex bg-gray-100 p-1 rounded-xl">
                        <button
                            onClick={() => setViewMode('hierarchy')}
                            className={`px-4 py-1.5 rounded-lg text-xs font-black transition-all ${viewMode === 'hierarchy' ? 'bg-white shadow-sm text-indigo-700' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            🌳 Hierárquico
                        </button>
                        <button
                            onClick={() => setViewMode('chart')}
                            className={`px-4 py-1.5 rounded-lg text-xs font-black transition-all ${viewMode === 'chart' ? 'bg-white shadow-sm text-indigo-700' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            📊 Gráfico
                        </button>
                    </div>

                    {/* Search subject */}
                    <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">🔍</span>
                        <input
                            placeholder="Buscar matéria..."
                            value={searchSubject}
                            onChange={e => setSearchSubject(e.target.value)}
                            className="pl-8 pr-4 py-2 text-xs font-bold bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none w-48"
                        />
                    </div>

                    {/* Search topic (hierarchy only) */}
                    {viewMode === 'hierarchy' && (
                        <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">🏷️</span>
                            <input
                                placeholder="Buscar assunto..."
                                value={searchTopic}
                                onChange={e => setSearchTopic(e.target.value)}
                                className="pl-8 pr-4 py-2 text-xs font-bold bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none w-48"
                            />
                        </div>
                    )}

                    {/* Sort */}
                    <select
                        value={sortBy}
                        onChange={e => setSortBy(e.target.value as 'total' | 'name')}
                        className="px-3 py-2 text-xs font-bold bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    >
                        <option value="total">↓ Mais questões</option>
                        <option value="name">A–Z Nome</option>
                    </select>

                    {/* Count badge */}
                    <span className="ml-auto text-xs font-black text-gray-400 uppercase tracking-widest">
                        {subjects.length} matéria{subjects.length !== 1 ? 's' : ''}
                    </span>
                </div>

                {/* ── Content ── */}
                <div ref={scrollRef} className="flex-1 overflow-y-auto px-6 md:px-10 py-6">
                    {isLoading && (
                        <div className="flex flex-col items-center justify-center h-64 gap-4">
                            <div className="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin" />
                            <p className="text-gray-500 font-bold">Carregando distribuição de questões...</p>
                        </div>
                    )}

                    {isError && (
                        <div className="flex flex-col items-center justify-center h-64 gap-3">
                            <span className="text-4xl">⚠️</span>
                            <p className="text-red-600 font-bold">Erro ao carregar dados do explorer.</p>
                            <p className="text-gray-400 text-sm">Verifique se o servidor está acessível.</p>
                        </div>
                    )}

                    {!isLoading && !isError && subjects.length === 0 && (
                        <div className="flex flex-col items-center justify-center h-64 gap-3">
                            <span className="text-5xl">🔍</span>
                            <p className="text-gray-500 font-bold">Nenhuma matéria encontrada.</p>
                            {searchSubject && (
                                <button onClick={() => setSearchSubject('')} className="text-indigo-600 text-sm font-bold hover:underline">
                                    Limpar filtro
                                </button>
                            )}
                        </div>
                    )}

                    {!isLoading && !isError && subjects.length > 0 && (
                        <>
                            {viewMode === 'hierarchy' && (
                                <div className="space-y-3 max-w-6xl mx-auto">
                                    {subjects.map(subject => (
                                        <SubjectCard
                                            key={subject.id}
                                            subject={subject}
                                            maxTotal={maxTotal}
                                            searchTopic={searchTopic}
                                        />
                                    ))}
                                </div>
                            )}

                            {viewMode === 'chart' && (
                                <div className="max-w-4xl mx-auto">
                                    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                                        <h2 className="text-lg font-black text-gray-800 mb-6 flex items-center gap-2">
                                            📊 Distribuição por Matéria
                                            <span className="text-sm font-bold text-gray-400">— {organization}</span>
                                        </h2>
                                        <BarChartView subjects={subjects} />
                                    </div>

                                    {/* Summary stats */}
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                                        {subjects.slice(0, 4).map((s, i) => (
                                            <div key={s.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                                                <div className="text-2xl mb-2">{subjectIcon(s.name)}</div>
                                                <div className="text-xs font-black text-gray-500 uppercase truncate mb-1">{s.name}</div>
                                                <div className="text-2xl font-black text-indigo-700">{s.total.toLocaleString('pt-BR')}</div>
                                                <div className="text-[10px] font-bold text-gray-400 mt-1">
                                                    #{i + 1} matéria mais cobrada
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* ── Footer ── */}
                <div className="bg-white border-t border-gray-100 px-10 py-3 flex items-center justify-between shrink-0">
                    <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        Dados em cache por 5 min • Clique em uma matéria para expandir assuntos
                    </span>
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-xs font-black text-gray-500 hover:text-gray-700 uppercase tracking-widest"
                    >
                        Fechar (Esc)
                    </button>
                </div>
            </motion.div>
        </AnimatePresence>
    );
}
