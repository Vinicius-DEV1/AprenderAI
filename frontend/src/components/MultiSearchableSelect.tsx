import { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface Option {
    id: string | number;
    name: string;
}

interface MultiSearchableSelectProps {
    label: string;
    name: string;
    values: (string | number)[];
    options: (string | Option)[];
    placeholder?: string;
    loading?: boolean;
    onChange: (name: string, values: (string | number)[]) => void;
}

export default function MultiSearchableSelect({ label, name, values = [], options = [], placeholder = 'Selecionar múltiplos...', loading = false, onChange }: MultiSearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const normalizedOptions: Option[] = (options || []).map(opt =>
        typeof opt === 'string' ? { id: opt, name: opt } : opt
    );

    const filteredOptions = normalizedOptions.filter(opt =>
        opt.name.toLowerCase().includes(search.toLowerCase())
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

    const toggleSelect = (opt: Option) => {
        const currentStringValues = values.map(v => v.toString());
        if (currentStringValues.includes(opt.id.toString())) {
            onChange(name, values.filter(v => v.toString() !== opt.id.toString()));
        } else {
            onChange(name, [...values, opt.id]);
        }
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(name, []);
        setSearch('');
    };

    const removeBadge = (e: React.MouseEvent, idToRemove: string | number) => {
        e.stopPropagation();
        onChange(name, values.filter(v => v.toString() !== idToRemove.toString()));
    };

    return (
        <div className="qb-filter-item relative" ref={containerRef}>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{label}</label>
            <div
                className={`flex flex-wrap gap-1 items-center min-h-[42px] p-2 bg-white dark:bg-slate-900 border rounded-xl cursor-pointer transition-all duration-200 ${isOpen ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'}`}
                onClick={() => setIsOpen(!isOpen)}
            >
                {values.length === 0 && (
                    <span className="text-[13px] px-1 truncate text-slate-400">
                        {placeholder}
                    </span>
                )}

                {values.map(val => {
                    const found = normalizedOptions.find(o => o.id.toString() === val.toString());
                    const labelStr = found ? found.name : val;
                    return (
                        <span key={val} className="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-xs font-medium rounded-md">
                            {labelStr}
                            <button onClick={(e) => removeBadge(e, val)} className="hover:text-indigo-900 dark:hover:text-indigo-200 focus:outline-none">
                                &times;
                            </button>
                        </span>
                    );
                })}

                <div className="flex-1 flex items-center justify-end gap-1 ml-auto">
                    {loading && (
                        <div className="w-3 h-3 border-2 border-slate-300 border-t-indigo-500 rounded-full animate-spin mr-1"></div>
                    )}
                    {values.length > 0 && (
                        <button
                            onClick={handleClear}
                            className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full text-slate-400 hover:text-red-500 transition-colors focus:outline-none"
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
                                filteredOptions.map((opt, idx) => {
                                    const isSelected = values.map(v => v.toString()).includes(opt.id.toString());
                                    return (
                                        <div
                                            key={idx}
                                            onClick={() => toggleSelect(opt)}
                                            className={`px-4 py-2.5 text-xs cursor-pointer transition-colors flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/50 ${isSelected ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : ''}`}
                                        >
                                            <span className={`${isSelected ? 'text-indigo-600 dark:text-indigo-400 font-medium' : 'text-slate-600 dark:text-slate-400'}`}>
                                                {opt.name}
                                            </span>
                                            <div className={`w-4 h-4 rounded border flex items-center justify-center ${isSelected ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300 dark:border-slate-600'}`}>
                                                {isSelected && (
                                                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })
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
