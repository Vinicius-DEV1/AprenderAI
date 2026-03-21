import React from 'react';
import { BarChart3, HelpCircle } from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats, ConfigState } from './Types';

interface RankingPrioritiesProps {
    stats: DashboardStats;
    configState: ConfigState;
    setConfigState: (state: ConfigState | ((v: ConfigState) => ConfigState)) => void;
}

const RankingPriorities: React.FC<RankingPrioritiesProps> = ({ stats, configState, setConfigState }) => {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
            <h3 className="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center justify-between">
                <span className="flex items-center gap-2">
                    <BarChart3 className="w-4 h-4 text-indigo-500" />
                    Prioridades de Ranking (Regras de Pesos)
                </span>
                <span className="text-[10px] bg-slate-100 dark:bg-slate-700 font-mono px-2 py-0.5 rounded">Soma: {(configState.rerank_weights.vector + configState.rerank_weights.popularity + configState.rerank_weights.quality + configState.rerank_weights.recency).toFixed(2)}</span>
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {[
                    { key: 'vector', label: 'Similaridade Vetorial', sub: 'Contexto/IA (Cérebro)', color: 'accent-indigo-600', help: 'O quanto o significado da questão (vetores) vale na nota final. É o coração da busca semântica.' },
                    { key: 'popularity', label: 'Popularidade', sub: 'Resolvidas/Acessos', color: 'accent-sky-500', help: 'Dá um bônus para questões que outros alunos acessam ou resolvem com frequência.' },
                    { key: 'quality', label: 'Qualidade Pedagógica', sub: 'Banca e complexidade', color: 'accent-amber-500', help: 'Peso para questões de bancas renomadas ou com enunciados classificados como alta qualidade.' },
                    { key: 'recency', label: 'Recência (Ano)', sub: 'Favorece o atual', color: 'accent-emerald-500', help: 'Dá preferência para questões mais novas (ex: 2024 sobre 2010), mantendo a base atualizada.' }
                ].map(w => (
                    <div key={w.key} className="bg-slate-50/50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div className="flex justify-between items-center mb-1">
                            <div>
                                <span className="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                    {w.label}
                                    <div className="cursor-help text-slate-400" title={w.help}>
                                        <HelpCircle className="w-3 h-3" />
                                    </div>
                                </span>
                                <p className="text-[10px] text-slate-500">{w.sub}</p>
                            </div>
                            <span className="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">{(configState.rerank_weights as any)[w.key].toFixed(2)}</span>
                        </div>
                        <input
                            type="range"
                            min="0" max="1" step="0.01"
                            value={(configState.rerank_weights as any)[w.key]}
                            onChange={(e) => setConfigState({
                                ...configState,
                                rerank_weights: { ...configState.rerank_weights, [w.key]: parseFloat(e.target.value) }
                            })}
                            className={clsx("w-full h-1.5 rounded-lg cursor-pointer", w.color)}
                        />
                    </div>
                ))}
            </div>
            <p className="text-[10px] text-slate-400 mt-4 leading-relaxed italic">
                * Se a soma for {'>'} 1.0, o sistema normaliza automaticamente. Recomendamos manter a soma em 1.0.
            </p>

            {/* Fixed boosts - User Profile */}
            <div className="mt-5 border-t border-slate-100 dark:border-slate-700 pt-4">
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-3">Bônus do Perfil do Usuário (Fixos)</p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div className="bg-indigo-50/50 dark:bg-indigo-900/20 p-3 rounded-xl border border-indigo-100 dark:border-indigo-800">
                        <div className="flex justify-between items-center mb-1">
                            <span className="text-xs font-semibold text-indigo-700 dark:text-indigo-400 flex items-center gap-1">
                                🧠 Proficiência (Tema Fraco)
                            </span>
                            <span className="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-300">+0.25</span>
                        </div>
                        <p className="text-[10px] text-slate-500">Questões de temas que o usuário erra com frequência recebem este bônus para forçar prática/crescimento.</p>
                    </div>
                    <div className="bg-emerald-50/50 dark:bg-emerald-900/20 p-3 rounded-xl border border-emerald-100 dark:border-emerald-800">
                        <div className="flex justify-between items-center mb-1">
                            <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                ✅ Proficiência (Tema Forte)
                            </span>
                            <span className="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-300">+0.05</span>
                        </div>
                        <p className="text-[10px] text-slate-500">Temas que o usuário domina recebem um bônus mínimo para manutenção/revisão leve.</p>
                    </div>
                    <div className="bg-amber-50/50 dark:bg-amber-900/20 p-3 rounded-xl border border-amber-100 dark:border-amber-800">
                        <div className="flex justify-between items-center mb-1">
                            <span className="text-xs font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1">
                                🎯 Intenção (Matéria)
                            </span>
                            <span className="text-xs font-mono font-bold text-amber-600 dark:text-amber-300">+0.30</span>
                        </div>
                        <p className="text-[10px] text-slate-500">Quando a busca detecta semanticamente a matéria certa, questões dessa matéria recebem este bônus.</p>
                    </div>
                    <div className="bg-purple-50/50 dark:bg-purple-900/20 p-3 rounded-xl border border-purple-100 dark:border-purple-800">
                        <div className="flex justify-between items-center mb-1">
                            <span className="text-xs font-semibold text-purple-700 dark:text-purple-400 flex items-center gap-1">
                                📌 Intenção (Assunto/Tópico)
                            </span>
                            <span className="text-xs font-mono font-bold text-purple-600 dark:text-purple-300">+0.15</span>
                        </div>
                        <p className="text-[10px] text-slate-500">Bônus para questões cujo tópico específico foi detectado na intenção da busca semântica.</p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default RankingPriorities;
