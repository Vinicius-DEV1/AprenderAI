import { useState, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { createSimulation } from '../../api/simulations';
import QuotaLimitModal from '../../components/QuotaLimitModal';

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
        { value: 'portuguese', label: 'Só Português', desc: '90 Questões' }
    ];

    const getEnemDistribution = () => {
        if (selectedEnemMode === 'math') return { 'MATEMÁTICA': 90, 'PORTUGUÊS': 0 };
        if (selectedEnemMode === 'portuguese') return { 'MATEMÁTICA': 0, 'PORTUGUÊS': 90 };
        return { 'MATEMÁTICA': 45, 'PORTUGUÊS': 45 };
    };

    // Concurso State
    const [totalQuestions, setTotalQuestions] = useState(60);
    const [subjects, setSubjects] = useState<{ name: string; qty: number }[]>([
        { name: 'Matemática', qty: 30 },
        { name: 'Português', qty: 30 }
    ]);

    // Filters State
    const [organizations, setOrganizations] = useState<string[]>([]);
    const [institutions, setInstitutions] = useState<string[]>([]);
    const [roles, setRoles] = useState<string[]>([]);
    const [filtersExpanded, setFiltersExpanded] = useState(false);

    const [includeEssay, setIncludeEssay] = useState(false);

    // Computed
    const totalAllocated = subjects.reduce((sum, sub) => sum + (Number(sub.qty) || 0), 0);
    const isValid = type === 'enem' || totalAllocated === totalQuestions;

    // Handlers
    const handleAddSubject = () => {
        setSubjects([...subjects, { name: '', qty: 0 }]);
    };

    const handleRemoveSubject = (index: number) => {
        if (subjects.length > 1) {
            setSubjects(subjects.filter((_, i) => i !== index));
        }
    };

    const handleSubjectChange = (index: number, field: 'name' | 'qty', value: string | number) => {
        const newSubjects = [...subjects];
        newSubjects[index] = { ...newSubjects[index], [field]: value };
        setSubjects(newSubjects);
    };

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

    const availableSubjects = ['Matemática', 'Português', 'Física', 'Química', 'Biologia', 'História', 'Geografia'];

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
                    <div className="mb-10">
                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wider">
                            Tipo de Prova
                        </label>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label
                                className={`relative flex items-center p-4 cursor-pointer rounded-xl border-2 transition-all duration-200 ${type === 'enem'
                                    ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'
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
                                    ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'
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
                            {/* Question Slider */}
                            <div className="mb-10 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div className="flex justify-between items-end mb-6">
                                    <label className="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                        <svg className="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                        Volume de Questões
                                    </label>
                                    <span className="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{totalQuestions} questões</span>
                                </div>
                                <input
                                    type="range"
                                    min="5"
                                    max="120"
                                    step="5"
                                    value={totalQuestions}
                                    onChange={(e) => setTotalQuestions(Number(e.target.value))}
                                    className="mb-2"
                                />
                                <div className="flex justify-between text-xs text-slate-400 font-medium px-1">
                                    <span>5</span>
                                    <span>60</span>
                                    <span>120</span>
                                </div>
                            </div>

                            {/* Dynamic Subject Selector */}
                            <div className="mb-10">
                                <div className="flex justify-between items-center mb-4">
                                    <h3 className="font-semibold text-slate-800 dark:text-white uppercase tracking-wider text-sm">Distribuição de Disciplinas</h3>
                                    <div className={`text-sm font-medium ${totalAllocated === totalQuestions ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'}`}>
                                        <span>{totalAllocated}</span> / <span>{totalQuestions}</span> alocadas
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    {subjects.map((subject, index) => (
                                        <div key={index} className="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm group">
                                            <div className="text-slate-300 dark:text-slate-600 cursor-grab">
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 8h16M4 16h16" />
                                                </svg>
                                            </div>

                                            <div className="flex-1 relative">
                                                <input
                                                    type="text"
                                                    className="w-full border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 bg-transparent font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400 p-0"
                                                    value={subject.name}
                                                    onChange={(e) => handleSubjectChange(index, 'name', e.target.value)}
                                                    placeholder="Digite a disciplina (ex: Direito Administrativo...)"
                                                    list={`sub-${index}`}
                                                    required
                                                />
                                                <datalist id={`sub-${index}`}>
                                                    {availableSubjects.map(sub => (
                                                        <option key={sub} value={sub} />
                                                    ))}
                                                </datalist>
                                            </div>

                                            <div className="w-24">
                                                <input
                                                    type="number"
                                                    className="w-full text-center rounded-md border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    value={subject.qty}
                                                    min="1"
                                                    max={totalQuestions}
                                                    onChange={(e) => handleSubjectChange(index, 'qty', parseInt(e.target.value) || 0)}
                                                />
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => handleRemoveSubject(index)}
                                                className="text-slate-400 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700"
                                            >
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                <button
                                    type="button"
                                    onClick={handleAddSubject}
                                    className="mt-4 flex items-center gap-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 px-4 py-2 rounded-lg transition-colors"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Adicionar Disciplina
                                </button>

                                {totalAllocated !== totalQuestions && (
                                    <div className="mt-2 text-sm text-amber-600 dark:text-amber-500 flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg">
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        A soma das questões ({totalAllocated}) deve ser igual ao volume total ({totalQuestions}). Ajuste as quantidades.
                                    </div>
                                )}
                            </div>

                            {/* Advanced Filters */}
                            <div className="border-t border-slate-200 dark:border-slate-700 pt-6">
                                <button
                                    type="button"
                                    onClick={() => setFiltersExpanded(!filtersExpanded)}
                                    className="flex items-center justify-between w-full text-left group"
                                >
                                    <div className="flex items-center gap-2">
                                        <span className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 group-hover:text-indigo-600 transition-colors">
                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <h4 className="font-semibold text-slate-800 dark:text-white group-hover:text-indigo-600 transition-colors">Filtros do Edital (Opcional)</h4>
                                            <p className="text-xs text-slate-500 dark:text-slate-400">Restringir por Banca, Órgão ou Cargo</p>
                                        </div>
                                    </div>
                                    <svg className={`w-5 h-5 text-slate-400 transform transition-transform duration-200 ${filtersExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {filtersExpanded && (
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 animate-fade-in-up">
                                        {/* Basic implementation for multiple selects without full TomSelect library */}
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Bancas (Separadas por vírgula)</label>
                                            <input
                                                type="text"
                                                value={organizations.join(', ')}
                                                onChange={(e) => setOrganizations(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                                className="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: CESPE, FGV"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Órgãos</label>
                                            <input
                                                type="text"
                                                value={institutions.join(', ')}
                                                onChange={(e) => setInstitutions(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                                className="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: Polícia Federal"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Cargos</label>
                                            <input
                                                type="text"
                                                value={roles.join(', ')}
                                                onChange={(e) => setRoles(e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                                                className="w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: Agente, Escrivão"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Footer */}
                    <div className="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
                        <label className="flex items-center gap-3 cursor-pointer group">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    checked={includeEssay}
                                    onChange={(e) => setIncludeEssay(e.target.checked)}
                                    className="sr-only peer"
                                />
                                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600">
                                </div>
                            </div>
                            <div>
                                <span className="block text-sm font-medium text-slate-700 dark:text-slate-300">Incluir Redação</span>
                                <span className="block text-xs text-slate-500 dark:text-slate-400">Gera um tema dissertativo extra</span>
                            </div>
                        </label>

                        <button
                            type="submit"
                            disabled={!isValid || submitting}
                            className="w-full md:w-auto px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/30 transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none flex items-center justify-center gap-2"
                        >
                            {!submitting ? (
                                <span>Iniciar Simulado</span>
                            ) : (
                                <span className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
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
