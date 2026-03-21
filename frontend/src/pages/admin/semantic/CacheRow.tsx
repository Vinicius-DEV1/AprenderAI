import React, { useState } from 'react';
import { 
    Database, 
    Clock, 
    ChevronUp, 
    ChevronDown, 
    Box, 
    Layers, 
    Eye 
} from 'lucide-react';
import { clsx } from 'clsx';

interface CacheRowProps {
    item: any;
}

const CacheRow = ({ item }: CacheRowProps) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const [showVector, setShowVector] = useState(false);

    return (
        <>
            <tr className={clsx(
                "hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors cursor-pointer group",
                isExpanded && "bg-slate-50/80 dark:bg-slate-700/40"
            )} onClick={() => setIsExpanded(!isExpanded)}>
                <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                         <div className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500">
                            <Database className="w-3.5 h-3.5" />
                        </div>
                        <span className="font-mono text-[10px] text-slate-400">{item.prompt_hash.substring(0, 8)}...</span>
                    </div>
                </td>
                <td className="px-4 py-3 font-medium text-slate-800 dark:text-white max-w-sm truncate">
                    “{item.prompt_text}”
                </td>
                <td className="px-4 py-3 text-xs text-slate-500">
                    <div className="flex flex-col">
                        <span className="flex items-center gap-1">
                            <Clock className="w-3 h-3" />
                            {item.last_used_at || 'Nunca usado'}
                        </span>
                        <span className="text-[10px] opacity-60">Criado em: {item.created_at}</span>
                    </div>
                </td>
                <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                        <span className="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700">
                            {item.filters_result?.question_ids?.length || 0} IDs
                        </span>
                        {isExpanded ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
                    </div>
                </td>
            </tr>
            {isExpanded && (
                <tr className="bg-slate-50/50 dark:bg-slate-700/20">
                    <td colSpan={4} className="px-4 py-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-1 duration-200">
                            <div className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h5 className="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center gap-1.5">
                                    <Box className="w-3 h-3 text-amber-500" />
                                    Conteúdo Cacheado
                                </h5>
                                <div className="space-y-3">
                                    <div>
                                        <span className="text-[10px] text-slate-400 block mb-1">IDs de Conceito Detectados:</span>
                                        <div className="flex flex-wrap gap-1">
                                            {item.concept_ids?.length > 0 ? item.concept_ids.map((id: number) => (
                                                <span key={id} className="text-[10px] bg-amber-50 dark:bg-amber-900/30 text-amber-600 px-1.5 py-0.5 rounded border border-amber-100 dark:border-amber-800/50">#{id}</span>
                                            )) : <span className="text-[10px] text-slate-400 italic">Nenhum</span>}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-[10px] text-slate-400 block mb-1">Questões Resultantes ({item.filters_result?.question_ids?.length || 0}):</span>
                                        <div className="font-mono text-[9px] bg-slate-100 dark:bg-slate-900 p-1.5 rounded max-h-20 overflow-y-auto text-slate-600 dark:text-slate-400 break-all">
                                            {item.filters_result?.question_ids?.join(', ') || 'Nenhum ID'}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h5 className="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2 flex items-center justify-between">
                                    <div className="flex items-center gap-1.5">
                                        <Layers className="w-3 h-3 text-indigo-500" />
                                        Metadados do Vetor
                                    </div>
                                    <button 
                                        onClick={() => setShowVector(!showVector)}
                                        className="text-indigo-500 hover:text-indigo-600 flex items-center gap-1"
                                    >
                                        <Eye className="w-3 h-3" />
                                        {showVector ? 'Esconder' : 'Ver Debug'}
                                    </button>
                                </h5>
                                <div className="space-y-2">
                                    <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-500">Prompt Hash (MD5):</span>
                                        <span className="font-mono text-slate-400">{item.prompt_hash}</span>
                                    </div>
                                    {showVector && (
                                        <div className="animate-in zoom-in-95 duration-200">
                                            <span className="text-[10px] text-slate-400 block mb-1">Vetor (Primeiras 5 dimensões):</span>
                                            <div className="grid grid-cols-5 gap-1">
                                                {item.vector_preview.map((val: number, idx: number) => (
                                                    <div key={idx} className="bg-indigo-50 dark:bg-indigo-900/30 text-[9px] font-mono p-1 rounded text-center text-indigo-600">
                                                        {val.toFixed(4)}
                                                    </div>
                                                ))}
                                            </div>
                                            <p className="mt-2 text-[9px] text-slate-400 leading-tight">
                                                O vetor completo possui 1536 dimensões (OpenAI text-embedding-3-small).
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
};

export default CacheRow;
