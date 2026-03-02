import { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface Option {
    id: string | number;
    name: string;
}

interface SearchableSelectProps {
    label: string;
    name: string;
    value: string | number;
    options: (string | Option)[];
    placeholder?: string;
    loading?: boolean;
    onChange: (name: string, value: string | number) => void;
}

export default function SearchableSelect({ label, name, value, options, placeholder = 'Selecionar...', loading = false, onChange }: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const normalizedOptions: Option[] = (options || []).map(opt =>
        typeof opt === 'string' ? { id: opt, name: opt } : opt
    );

    const filteredOptions = normalizedOptions.filter(opt =>
        opt.name.toLowerCase().includes(search.toLowerCase())
    );

    const selectedOption = normalizedOptions.find(opt => opt.id.toString() === value?.toString());

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSelect = (opt: Option) => {
        onChange(name, opt.id);
        setIsOpen(false);
        setSearch('');
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(name, '');
        setSearch('');
    };

    return (
        <div className="qb-filter-item relative" ref={containerRef}>
            <label>{label}</label>
            <div
                className={`flex items-center justify-between p-2.5 bg-white dark:bg-slate-900 border rounded-xl cursor-pointer transition-all duration-200 ${isOpen ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'}`}
                onClick={() => setIsOpen(!isOpen)}
            >
                <span className={`text-[13px] truncate ${value && value.toString() !== '' ? 'text-slate-900 dark:text-white font-medium' : 'text-slate-400'}`}>
                    {selectedOption ? selectedOption.name : placeholder}
                </span>
                <div className="flex items-center gap-1">
                    {loading && (
                        <div className="w-3 h-3 border-2 border-slate-300 border-t-indigo-500 rounded-full animate-spin mr-1"></div>
                    )}
                    {value && value.toString() !== '' && (
                        <button
                            onClick={handleClear}
                            className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full text-slate-400 hover:text-red-500 transition-colors"
                        >
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    )}
                    <svg className={`w-4 h-4 text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, y: -10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        className="absolute z-50 w-full mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl overflow-hidden"
                        style={{ minWidth: '220px' }}
                    >
                        <div className="p-2 border-b border-slate-100 dark:border-slate-800">
                            <input
                                type="text"
                                autoFocus
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar..."
                                onClick={(e) => e.stopPropagation()}
                                className="w-full p-2 text-xs bg-slate-50 dark:bg-slate-800 border-none rounded-lg focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="max-h-60 overflow-y-auto qb-scrollbar">
                            {filteredOptions.length > 0 ? (
                                filteredOptions.map((opt, idx) => (
                                    <div
                                        key={idx}
                                        onClick={() => handleSelect(opt)}
                                        className={`px-4 py-2.5 text-xs cursor-pointer transition-colors flex items-center justify-between ${value?.toString() === opt.id.toString() ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50'}`}
                                    >
                                        <span>{opt.name}</span>
                                        {value?.toString() === opt.id.toString() && (
                                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                            </svg>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <div className="px-4 py-8 text-center text-xs text-slate-400 italic">
                                    {loading ? 'Carregando opções...' : 'Nenhum resultado encontrado'}
                                </div>
                            )}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
