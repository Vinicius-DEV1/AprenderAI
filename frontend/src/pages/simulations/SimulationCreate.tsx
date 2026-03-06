import { useState, FormEvent, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { createSimulation } from '../../api/simulations';
import QuotaLimitModal from '../../components/QuotaLimitModal';
import api from '../../api/axios';
import { motion, AnimatePresence } from 'framer-motion';

// --- Subject Search Component ---
function SubjectSearch({ availableSubjects, onSelect }: { availableSubjects: string[], onSelect: (sub: string) => void }) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const filteredOptions = availableSubjects.filter(sub => 
        sub.toLowerCase().includes(search.toLowerCase())
    );

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSelect = (sub: string) => {
        onSelect(sub);
        setIsOpen(false);
        setSearch('');
    };

    return (
        <div className="relative" ref={containerRef}>
            <div 
                className={`flex items-center gap-2 px-4 py-3 bg-white dark:bg-slate-900 border rounded-xl cursor-text transition-all duration-200 ${isOpen ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-700'}`}
                onClick={() => setIsOpen(true)}
            >
                <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    type="text" 
                    placeholder="Adicionar disciplina..." 
                    className="flex-1 bg-transparent border-none p-0 focus:ring-0 text-sm font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onFocus={() => setIsOpen(true)}
                />
            </div>

            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, y: -10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        className="absolute z-50 w-full mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto"
                    >
                        {filteredOptions.length > 0 ? (
                            filteredOptions.map((sub, idx) => (
                                <div
                                    key={idx}
                                    onClick={() => handleSelect(sub)}
                                    className="px-4 py-3 text-sm cursor-pointer transition-colors text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 flex items-center gap-2"
                                >
                                    <div className="w-6 h-6 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                                        {sub.charAt(0)}
                                    </div>
                                    {sub}
                                </div>
                            ))
                        ) : (
                            <div className="px-4 py-6 text-center text-sm text-slate-500 italic">
                                Nenhuma disciplina encontrada
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}


export default function SimulationCreate() {
    const navigate = useNavigate();
    const [type, setType] = useState<'enem' | 'concurso'>('enem');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Quota Modal State
    const [isQuotaModalOpen, setIsQuotaModalOpen] = useState(false);
    const [quotaData, setQuotaData] = useState({ limit: 0, used: 0 });

    // ENEM State
    const [selectedEnemMode, setSelectedEnemMode] = useState('mixed');
    const enemModes = [
        { value: 'mixed', label: 'Prova Completa', desc: '45 Mat + 45 Port' },
        { value: 'math', label: 'Só Matemática', desc: '90 Questões' },
        { value: 'portuguese', label: 'Só Língua Portuguesa', desc: '90 Questões' }
    ];

    const getEnemDistribution = () => {
        if (selectedEnemMode === 'math') return { 'MATEMÁTICA': 90, 'LÍNGUA PORTUGUESA': 0 };
        if (selectedEnemMode === 'portuguese') return { 'MATEMÁTICA': 0, 'LÍNGUA PORTUGUESA': 90 };
        return { 'MATEMÁTICA': 45, 'LÍNGUA PORTUGUESA': 45 };
    };

    // Concurso State
    const [totalQuestions, setTotalQuestions] = useState(60);
    const [subjects, setSubjects] = useState<{ name: string; qty: number; isManual: boolean }[]>([
        { name: 'Matemática', qty: 30, isManual: false },
        { name: 'Língua Portuguesa', qty: 30, isManual: false }
    ]);

    // Data from backend for filters
    const [availableSubjects, setAvailableSubjects] = useState<string[]>([]);
    
    useEffect(() => {
        const fetchSubjects = async () => {
            try {
                const res = await api.get('/api/v1/questions/subjects?type=concurso');
                // The API returns an array of objects like { id: "Biologia", name: "Biologia" } or just strings.
                if (res.data && Array.isArray(res.data)) {
                    setAvailableSubjects(res.data.map((item: any) => typeof item === 'string' ? item : item.name));
                }
            } catch (e) {
                // fallback
                setAvailableSubjects(['Matemática', 'Língua Portuguesa', 'Física', 'Química', 'Biologia', 'História', 'Geografia', 'Direito Constitucional', 'Direito Administrativo', 'Informática']);
            }
        };
        fetchSubjects();
    }, []);

    // Filters State
    const [organizations, setOrganizations] = useState<string[]>([]);
    const [institutions, setInstitutions] = useState<string[]>([]);
    const [roles, setRoles] = useState<string[]>([]);

    const [includeEssay, setIncludeEssay] = useState(false);

    // --- Auto-Balancing Logic ---
    const rebalance = (currentSubjects: { name: string; qty: number; isManual: boolean }[], newTotal: number) => {
        if (currentSubjects.length === 0) return currentSubjects;

        let lockedSum = 0;
        let flexibleCount = 0;

        // Count what is locked vs flexible
        currentSubjects.forEach(sub => {
            if (sub.isManual) {
                lockedSum += sub.qty;
            } else {
                flexibleCount++;
            }
        });

        // If manual inputs exceed total OR nothing is flexible, unlock everything to prevent freezing
        if (lockedSum >= newTotal || flexibleCount === 0) {
            currentSubjects = currentSubjects.map(sub => ({ ...sub, isManual: false }));
            lockedSum = 0;
            flexibleCount = currentSubjects.length;
        }

        const remainingQty = Math.max(0, newTotal - lockedSum);
        const baseShare = Math.floor(remainingQty / flexibleCount);
        let remainder = remainingQty % flexibleCount;

        return currentSubjects.map(sub => {
            if (sub.isManual) return sub;
            let qty = baseShare;
            if (remainder > 0) {
                qty++;
                remainder--;
            }
            return { ...sub, qty };
        });
    };

    // When total changes
    const handleTotalChange = (newTotal: number) => {
        setTotalQuestions(newTotal);
        setSubjects(prev => rebalance(prev, newTotal));
    };

    // When adding a new subject
    const handleAddSubject = (name: string) => {
        if (subjects.some(s => s.name.toLowerCase() === name.toLowerCase())) return; // Avoid duplicates
        
        const newSubjects = [...subjects, { name, qty: 0, isManual: false }];
        setSubjects(rebalance(newSubjects, totalQuestions));
    };

    // When removing a subject
    const handleRemoveSubject = (index: number) => {
        const newSubjects = subjects.filter((_, i) => i !== index);
        setSubjects(rebalance(newSubjects, totalQuestions));
    };

    // When manually changing a subject's quantity
    const handleSubjectQtyChange = (index: number, newQty: number) => {
        let safeQty = Math.min(Math.max(1, newQty), totalQuestions);
        
        const updatedSubjects = subjects.map((sub, i) => {
            if (i === index) return { ...sub, qty: safeQty, isManual: true };
            return sub;
        });

        // Enforce total max
        const totalNow = updatedSubjects.reduce((sum, s) => sum + s.qty, 0);
        if (totalNow > totalQuestions) {
            // Need to reduce others to compensate. Let rebalance handle it by unlocking everything except the one just changed if needed
            setSubjects(rebalance(updatedSubjects, totalQuestions));
        } else {
            // Valid manual change, but we have leftovers. Let's auto-fill non-manual ones or unlock something
            setSubjects(rebalance(updatedSubjects, totalQuestions));
        }
    };

    // Computed
    const totalAllocated = subjects.reduce((sum, sub) => sum + (Number(sub.qty) || 0), 0);
    const isValid = type === 'enem' || (totalAllocated === totalQuestions && subjects.length > 0);

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!isValid) return;

        setSubmitting(true);
        setError(null);

        try {
            let payload: any = { type, include_essay: includeEssay };

            if (type === 'enem') {
                payload.total_questions = 90;
                payload.subject_distribution = getEnemDistribution();
            } else {
                payload.total_questions = totalQuestions;
                payload.subject_distribution = subjects.reduce((acc, curr) => {
                    if (curr.name.trim()) acc[curr.name] = Number(curr.qty);
                    return acc;
                }, {} as Record<string, number>);

                if (organizations.length > 0) payload.organization = organizations;
                if (institutions.length > 0) payload.institution = institutions;
                if (roles.length > 0) payload.role = roles;
            }

            const response = await createSimulation(payload);
            if (response && response.data && response.data.id) {
                navigate(`/simulations/${response.data.id}`);
            } else {
                navigate('/simulations');
            }
        } catch (err: any) {
            if (err.response?.status === 403 && err.response?.data?.quota) {
                setQuotaData(err.response.data.quota);
                setIsQuotaModalOpen(true);
                setError(null);
            } else {
                setError(err.response?.data?.message || 'Falha ao criar simulado. Tente novamente.');
            }
            setSubmitting(false);
        }
    };

    // Filter out already selected subjects for the dropdown
    const unselectedSubjects = availableSubjects.filter(sub => !subjects.some(s => s.name === sub));

    return (
        <>
            <style>{`
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.05);
        }

        :root.dark .glass-card {
            background: rgba(15, 23, 42, 0.95);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3);
        }

        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 24px;
            width: 24px;
            border-radius: 50%;
            background: #4f46e5;
            cursor: pointer;
            margin-top: -10px;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.4);
            transition: transform 0.1s;
        }

        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            cursor: pointer;
            background: #e2e8f0;
            border-radius: 2px;
        }

        :root.dark input[type=range]::-webkit-slider-runnable-track {
            background: #334155;
        }
      `}</style>

            <div className="max-w-4xl mx-auto py-8">
                <div className="mb-8 text-center">
                    <h1 className="text-3xl font-bold text-slate-800 dark:text-slate-100 mb-2">Configurar Simulado</h1>
                    <p className="text-slate-500">Personalize sua experiência de treino com foco total.</p>
                </div>

                {error && (
                    <div className="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg shadow-sm">
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="glass-card rounded-2xl p-6 md:p-10">
                    {/* Type Selector */}
                    <div className="mb-8">
                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wider">
                            Tipo de Prova
                        </label>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label
                                className={`relative flex items-center p-4 cursor-pointer rounded-xl border-2 transition-all duration-200 ${type === 'enem'
                                    ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20 shadow-sm'
                                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                                    }`}
                                onClick={() => setType('enem')}
                            >
                                <div className="flex-1">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-bold text-slate-900 dark:text-white">ENEM</span>
                                        {type === 'enem' && (
                                            <span className="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">Selecionado</span>
                                        )}
                                    </div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">Padrão oficial. 90 questões fixas (Matemática e Linguagens).</p>
                                </div>
                            </label>

                            <label
                                className={`relative flex items-center p-4 cursor-pointer rounded-xl border-2 transition-all duration-200 ${type === 'concurso'
                                    ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20 shadow-sm'
                                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'
                                    }`}
                                onClick={() => setType('concurso')}
                            >
                                <div className="flex-1">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-bold text-slate-900 dark:text-white">Concurso / Multidisciplinar</span>
                                        {type === 'concurso' && (
                                            <span className="text-xs font-semibold px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">Selecionado</span>
                                        )}
                                    </div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">Totalmente flexível. Escolha disciplinas, bancas e quantidade.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {/* ENEM Configuration */}
                    {type === 'enem' && (
                        <div className="animate-fade-in-up">
                            <div className="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                                <h3 className="font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                    <svg className="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                    Modo de Aplicação
                                </h3>

                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    {enemModes.map(mode => (
                                        <label key={mode.value} className="cursor-pointer" onClick={() => setSelectedEnemMode(mode.value)}>
                                            <div className={`px-4 py-3 rounded-lg border text-center transition-colors ${selectedEnemMode === mode.value
                                                ? 'bg-white dark:bg-slate-800 border-indigo-500 shadow-sm dark:shadow-none text-indigo-700 dark:text-indigo-300'
                                                : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800'
                                                }`}>
                                                <span className="block font-medium">{mode.label}</span>
                                                <span className="text-xs opacity-75">{mode.desc}</span>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Concurso Configuration */}
                    {type === 'concurso' && (
                        <div className="animate-fade-in-up">
                            {/* NEW: Compact Layout Combining Volume and Subjects */}
                            <div className="bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-6 mb-8">
                                
                                {/* 1. Master Control: Question Volume */}
                                <div className="mb-8">
                                    <div className="flex justify-between items-end mb-4">
                                        <label className="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                            <svg className="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                            Volume Total de Questões
                                        </label>
                                        <div className="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold px-4 py-1.5 rounded-lg text-xl flex items-center gap-2">
                                            {totalQuestions}
                                            <span className="text-sm font-medium opacity-70">qts</span>
                                        </div>
                                    </div>
                                    <input
                                        type="range"
                                        min="5"
                                        max="120"
                                        step="5"
                                        value={totalQuestions}
                                        onChange={(e) => handleTotalChange(Number(e.target.value))}
                                        className="mb-2"
                                    />
                                    <div className="flex justify-between text-xs text-slate-400 font-medium px-1">
                                        <span>Curto (10)</span>
                                        <span>Padrão (60)</span>
                                        <span>Longo (120)</span>
                                    </div>
                                </div>

                                <hr className="border-slate-200 dark:border-slate-700 mb-6" />

                                {/* 2. Subjects Distribution */}
                                <div>
                                    <div className="flex justify-between items-center mb-4">
                                        <h3 className="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                            <svg className="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg>
                                            Distribuição
                                        </h3>
                                        <div className={`text-sm font-medium px-3 py-1 rounded-full ${totalAllocated === totalQuestions ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                                            {totalAllocated} / {totalQuestions}
                                        </div>
                                    </div>

                                    {/* Subject Tags Grid */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                                        <AnimatePresence>
                                            {subjects.map((sub, index) => (
                                                <motion.div 
                                                    key={sub.name}
                                                    initial={{ opacity: 0, scale: 0.95 }}
                                                    animate={{ opacity: 1, scale: 1 }}
                                                    exit={{ opacity: 0, scale: 0.9 }}
                                                    className={`flex items-center justify-between p-3 rounded-xl border ${sub.isManual ? 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-900/20 dark:border-indigo-800/50' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-700'} shadow-sm group`}
                                                >
                                                    <div className="flex-1 min-w-0 mr-3">
                                                        <div className="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate" title={sub.name}>
                                                            {sub.name}
                                                        </div>
                                                        {sub.isManual && <div className="text-[10px] text-indigo-500 font-medium">Ajuste manual</div>}
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            className="w-14 text-center rounded-lg border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-sm font-bold focus:ring-1 focus:ring-indigo-500 py-1 px-1"
                                                            value={sub.qty}
                                                            min="1"
                                                            max={totalQuestions}
                                                            onChange={(e) => handleSubjectQtyChange(index, parseInt(e.target.value) || 0)}
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveSubject(index)}
                                                            className="text-slate-400 hover:text-red-500 transition-colors p-1"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </motion.div>
                                            ))}
                                        </AnimatePresence>
                                    </div>

                                    {/* Subject Search/Add */}
                                    {unselectedSubjects.length > 0 && (
                                        <div className="mt-2 text-sm">
                                            <SubjectSearch availableSubjects={unselectedSubjects} onSelect={handleAddSubject} />
                                        </div>
                                    )}

                                    {totalAllocated !== totalQuestions && (
                                        <div className="mt-4 text-xs font-medium text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 p-2.5 rounded-lg border border-amber-200 dark:border-amber-800/50">
                                            A soma das disciplinas ({totalAllocated}) diverge do volume desejado ({totalQuestions}). Refaça a distribuição ou ajuste as quantidades.
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Advanced Filters (Compact) */}
                            <div>
                                <h3 className="font-semibold text-slate-800 dark:text-white flex items-center gap-2 mb-3 px-1 text-sm uppercase tracking-wider">
                                    <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    Filtros Específicos do Edital
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <input
                                            type="text"
                                            value={organizations.join(', ')}
                                            onChange={(e) => setOrganizations(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                            className="w-full text-sm rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 focus:bg-white focus:border-indigo-500 py-2.5 px-4"
                                            placeholder="Banca (Ex: CESPE)"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            type="text"
                                            value={institutions.join(', ')}
                                            onChange={(e) => setInstitutions(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                            className="w-full text-sm rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 focus:bg-white focus:border-indigo-500 py-2.5 px-4"
                                            placeholder="Órgão (Ex: TRF)"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            type="text"
                                            value={roles.join(', ')}
                                            onChange={(e) => setRoles(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                            className="w-full text-sm rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 focus:bg-white focus:border-indigo-500 py-2.5 px-4"
                                            placeholder="Cargo (Ex: Técnico)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Footer / Actions */}
                    <div className="mt-10 pt-6 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-6">
                        <label className="flex items-center gap-3 cursor-pointer group bg-slate-50 dark:bg-slate-800/50 px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 w-full md:w-auto hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    checked={includeEssay}
                                    onChange={(e) => setIncludeEssay(e.target.checked)}
                                    className="sr-only peer"
                                />
                                <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-indigo-600">
                                </div>
                            </div>
                            <div>
                                <span className="block text-sm font-semibold text-slate-800 dark:text-slate-200">Adicionar Redação Extra</span>
                                <span className="block text-xs text-slate-500">Gera um tema dissertativo extra</span>
                            </div>
                        </label>

                        <button
                            type="submit"
                            disabled={!isValid || submitting}
                            className={`w-full md:w-auto px-10 py-3.5 font-bold rounded-xl text-white transition-all transform flex items-center justify-center gap-2 ${isValid ? 'bg-indigo-600 hover:bg-indigo-700 shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:-translate-y-0.5' : 'bg-slate-300 dark:bg-slate-700 cursor-not-allowed opacity-70'}`}
                        >
                            {!submitting ? (
                                <>
                                    <span>Iniciar Simulado</span>
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </>
                            ) : (
                                <>
                                    <span className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                                    <span>Gerando...</span>
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </div>

            <QuotaLimitModal
                isOpen={isQuotaModalOpen}
                onClose={() => setIsQuotaModalOpen(false)}
                resource="Provas"
                used={quotaData.used}
                limit={quotaData.limit}
                upgradeRoute="/plans"
            />
        </>
    );
}
