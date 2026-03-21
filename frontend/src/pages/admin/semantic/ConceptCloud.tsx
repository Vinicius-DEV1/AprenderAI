import React from 'react';
import { Box, Database, Hash } from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats } from './Types';

interface ConceptCloudProps {
    stats: DashboardStats;
}

const ConceptCloud: React.FC<ConceptCloudProps> = ({ stats }) => {
    if (!stats.top_concepts) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-2">
                    <Box className="w-5 h-5 text-indigo-500" />
                    <h2 className="text-lg font-bold text-slate-800 dark:text-white">Nuvem de Matérias & Assuntos (Qdrant)</h2>
                </div>
                <div className="flex gap-4 text-xs font-medium">
                    <span className="flex items-center gap-1.5 text-purple-600"><div className="w-2.5 h-2.5 rounded-full bg-purple-100 dark:bg-purple-900/40 border border-purple-200 dark:border-purple-700"></div> Matérias</span>
                    <span className="flex items-center gap-1.5 text-blue-600"><div className="w-2.5 h-2.5 rounded-full bg-blue-100 dark:bg-blue-900/40 border border-blue-200 dark:border-blue-700"></div> Assuntos</span>
                </div>
            </div>

            <div className="flex flex-wrap justify-center items-center gap-x-4 gap-y-6 p-4 bg-slate-50/50 dark:bg-slate-900/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
                {stats.top_concepts?.map((concept, i) => {
                    const maxCount = Math.max(...(stats.top_concepts?.map(c => c.count) || []), 1);
                    const ratio = concept.count / maxCount;

                    let sizeClass = "text-xs";
                    if (ratio > 0.8) sizeClass = "text-2xl font-black";
                    else if (ratio > 0.5) sizeClass = "text-xl font-extrabold";
                    else if (ratio > 0.2) sizeClass = "text-lg font-bold";
                    else if (ratio > 0.05) sizeClass = "text-sm font-semibold";

                    return (
                        <div
                            key={i}
                            className={clsx(
                                "px-4 py-2 rounded-2xl flex items-center gap-2 transition-all hover:scale-110 hover:shadow-lg cursor-default border group",
                                sizeClass,
                                concept.type === 'subject'
                                    ? "bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-700 shadow-purple-100/50"
                                    : concept.type === 'topic'
                                        ? "bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700 shadow-blue-100/50"
                                        : "bg-white text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 shadow-sm"
                            )}
                            title={`${concept.type === 'subject' ? 'Disciplina' : concept.type === 'topic' ? 'Tópico' : 'Conceito'}: ${concept.count} questões`}
                        >
                            {concept.type === 'subject' && <Database className="w-3 h-3 group-hover:animate-bounce" />}
                            {concept.type === 'topic' && <Hash className="w-3 h-3 group-hover:animate-rotate" />}
                            {concept.name}
                            <span className={clsx(
                                "text-[10px] px-1.5 py-0.5 rounded-full font-black ml-1",
                                concept.count > 100
                                    ? "bg-indigo-600 text-white"
                                    : "bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400"
                            )}>
                                {concept.count > 1000 ? `${(concept.count / 1000).toFixed(1)}k` : concept.count}
                            </span>
                        </div>
                    );
                })}
            </div>
            <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-900/40 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="space-y-1">
                    <p className="text-slate-500 text-[10px] uppercase font-bold flex items-center gap-1.5">
                        <Database className="w-3.5 h-3.5 text-purple-500" />
                        <span className="text-purple-600 dark:text-purple-400">Roxo: Matérias/Disciplinas (Hard Filter)</span>
                    </p>
                    <p className="text-[10px] text-slate-400 leading-tight">A IA detectou uma matéria específica. A busca é travada APENAS nessas matérias.</p>
                </div>
                <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-4">
                    <p className="text-slate-500 text-[10px] uppercase font-bold flex items-center gap-1.5">
                        <Hash className="w-3.5 h-3.5 text-blue-500" />
                        <span className="text-blue-600 dark:text-blue-400">Azul: Assuntos (Aumento Precisão)</span>
                    </p>
                    <p className="text-[10px] text-slate-400 leading-tight">Expansão semântica para tópicos relacionados. Melhora a nota de questões que batem com esses temas.</p>
                </div>
            </div>
        </div>
    );
};

export default ConceptCloud;
