import React from 'react';
import { 
    Zap, 
    AlertCircle, 
    Search, 
    RefreshCw, 
    ArrowRight, 
    Microscope, 
    Filter, 
    Target, 
    Layers, 
    Clock 
} from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats } from './Types';
import PipelineStep from './PipelineStep';
import SearchResultItem from './SearchResultItem';

interface MaestroTesterProps {
    stats: DashboardStats | null;
    searchPrompt: string;
    setSearchPrompt: (prompt: string) => void;
    handleTestSearch: (e: React.FormEvent) => Promise<void>;
    testLoading: boolean;
    searchResults: any;
    parsePipelineSteps: (logs: string[]) => any;
    TARGET_PIPELINE_VERSION: string;
}

const MaestroTester: React.FC<MaestroTesterProps> = ({
    stats,
    searchPrompt,
    setSearchPrompt,
    handleTestSearch,
    testLoading,
    searchResults,
    parsePipelineSteps,
    TARGET_PIPELINE_VERSION
}) => {
    return (
        <div className="space-y-6">
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm flex flex-col h-full">
                <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                    <Zap className="w-5 h-5 text-amber-500" />
                    Painel do Maestro (Xavier 2.0)
                </h2>

                {/* Warning: Qdrant empty */}
                {stats && stats.qdrant.questions_points === 0 && (
                    <div className="mb-4 flex items-start gap-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-4">
                        <AlertCircle className="w-5 h-5 text-rose-500 mt-0.5 shrink-0" />
                        <div>
                            <p className="text-sm font-bold text-rose-700 dark:text-rose-400">Qdrant vazio — buscas retornarão zero resultados!</p>
                            <p className="text-xs text-rose-600 dark:text-rose-500 mt-1">O banco vetorial não possui questões indexadas. Use o botão <strong>"Indexar Questões"</strong> acima para iniciar a indexação.</p>
                        </div>
                    </div>
                )}

                <form onSubmit={handleTestSearch} className="mb-6 flex gap-3 items-stretch w-full">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                        <input
                            id="maestro-search-input"
                            type="text"
                            value={searchPrompt}
                            onChange={(e) => setSearchPrompt(e.target.value)}
                            placeholder="Simule uma busca... ex: física menos mecânica"
                            className="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 backdrop-blur-sm outline-none focus:ring-2 focus:ring-indigo-500 shadow-inner transition-all text-slate-800 dark:text-white"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={testLoading || !searchPrompt}
                        className="bg-gradient-to-br from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 disabled:from-slate-400 disabled:to-slate-500 text-white min-w-[60px] flex items-center justify-center rounded-2xl shadow-lg shadow-indigo-500/25 transition-all active:scale-95"
                    >
                        {testLoading ? <RefreshCw className="w-6 h-6 animate-spin" /> : <ArrowRight className="w-6 h-6" />}
                    </button>
                </form>

                {searchResults ? (
                    <div className="flex-1 flex flex-col lg:flex-row gap-6 overflow-hidden">
                        {/* LEFT: MAESTRO X-RAY */}
                        <div className="w-full lg:w-96 shrink-0 space-y-4 overflow-y-auto pr-2 custom-scrollbar border-r border-slate-100 dark:border-slate-700/50">
                            <div className="flex items-center gap-2 mb-4">
                                <Microscope className="w-4 h-4 text-indigo-500" />
                                <h3 className="text-xs font-black uppercase tracking-widest text-slate-500">Pipeline Maestro</h3>
                            </div>

                            {/* Step 1: Lexical */}
                            <PipelineStep
                                icon={Filter}
                                title="Análise Léxica"
                                isActive={true}
                                description="O Xavier separa o que você QUER (+) do que você NÃO QUER (-) na busca."
                                helpText="Primeira camada: a IA limpa o seu texto e identifica palavras de negação (ex: 'menos', 'não') para criar filtros rígidos."
                            >
                                <div className="flex flex-wrap gap-1.5">
                                    {parsePipelineSteps(searchResults.logs).lexical.positive.map((t: string) => (
                                        <span key={t} className="text-[10px] font-bold bg-blue-50 dark:bg-blue-900/30 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100 dark:border-blue-800">
                                            +{t}
                                        </span>
                                    ))}
                                    {parsePipelineSteps(searchResults.logs).lexical.negative.map((t: string) => (
                                        <span key={t} className="text-[10px] font-bold bg-rose-50 dark:bg-rose-900/30 text-rose-600 px-2 py-0.5 rounded-full border border-rose-100 dark:border-rose-800">
                                            -{t}
                                        </span>
                                    ))}
                                    {parsePipelineSteps(searchResults.logs).difficulty && (
                                        <span className="text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 px-2 py-0.5 rounded-full border border-amber-100 dark:border-amber-800">
                                            Dificuldade: {parsePipelineSteps(searchResults.logs).difficulty}
                                        </span>
                                    )}
                                    {parsePipelineSteps(searchResults.logs).temporal && (
                                        <span className="text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 px-2 py-0.5 rounded-full border border-emerald-100 dark:border-emerald-800">
                                            Ano: {parsePipelineSteps(searchResults.logs).temporal?.operator} {parsePipelineSteps(searchResults.logs).temporal?.year}
                                        </span>
                                    )}
                                    {parsePipelineSteps(searchResults.logs).organizations.map((org: string, idx: number) => (
                                        <span key={idx} className="text-[10px] font-bold bg-purple-50 dark:bg-purple-900/30 text-purple-600 px-2 py-0.5 rounded-full border border-purple-100 dark:border-purple-800">
                                            Banca: {org}
                                        </span>
                                    ))}
                                    {parsePipelineSteps(searchResults.logs).institutions.map((inst: string, idx: number) => (
                                        <span key={idx} className="text-[10px] font-bold bg-blue-50 dark:bg-blue-900/30 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100 dark:border-blue-800">
                                            Instituição: {inst}
                                        </span>
                                    ))}
                                    {parsePipelineSteps(searchResults.logs).lexical.positive.length === 0 &&
                                        parsePipelineSteps(searchResults.logs).lexical.negative.length === 0 &&
                                        !parsePipelineSteps(searchResults.logs).difficulty &&
                                        !parsePipelineSteps(searchResults.logs).temporal && (
                                            <span className="text-[10px] text-slate-400">Nenhum termo processado.</span>
                                        )}
                                </div>
                            </PipelineStep>

                            {/* Step 2: Intent */}
                            <PipelineStep
                                icon={Target}
                                title="Detecção de Intenção"
                                isActive={true}
                                description="A IA tenta adivinhar a matéria ou assunto técnico."
                                helpText="Segunda camada: cruzamos seu texto com nossa base de Disciplinas e Tópicos. Se houver match forte (>0.45), a busca é filtrada automaticamente."
                            >
                                <div className="space-y-1">
                                    {parsePipelineSteps(searchResults.logs).intent.map((i: any, idx: number) => (
                                        <div key={idx} className={clsx(
                                            "flex items-center justify-between text-[10px] p-1.5 rounded-lg border",
                                            i.type.includes('EXCLUIR')
                                                ? "bg-rose-50/50 border-rose-100 dark:bg-rose-900/20 dark:border-rose-800 text-rose-700 dark:text-rose-400"
                                                : "bg-indigo-50/50 border-indigo-100 dark:bg-indigo-900/20 dark:border-indigo-800 text-indigo-700 dark:text-indigo-400"
                                        )}>
                                            <span className="font-bold shrink-0">{i.type}:</span>
                                            <span className="truncate ml-2 text-right">{i.value}</span>
                                        </div>
                                    ))}
                                    {parsePipelineSteps(searchResults.logs).intent.length === 0 && (
                                        <p className="text-[10px] text-slate-400 italic">Busca puramente semântica.</p>
                                    )}
                                </div>
                            </PipelineStep>

                            <PipelineStep
                                icon={Layers}
                                title="Busca Vetorial (5 Vetores)"
                                isActive={true}
                                status={`${searchResults.results?.length || 0} candidatos`}
                                description="Captura paralela de contexto nos 5 facets semânticos da questão."
                                helpText="Terceira camada: o Qdrant busca em paralelo nos 5 vetores (statement, concept, explanation, alternatives, skills) e funde com Reciprocal Rank Fusion."
                            >
                                <div className="grid grid-cols-5 gap-1">
                                    {[
                                        { label: 'Stmt', color: 'emerald' },
                                        { label: 'Cncpt', color: 'emerald' },
                                        { label: 'Expl', color: 'emerald' },
                                        { label: 'Alts', color: 'blue' },
                                        { label: 'Skills', color: 'violet' },
                                    ].map(slot => (
                                        <div key={slot.label} className={clsx(
                                            "flex flex-col items-center p-1.5 rounded-lg border",
                                            slot.color === 'blue' ? "bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800" :
                                            slot.color === 'violet' ? "bg-violet-50 dark:bg-violet-900/20 border-violet-100 dark:border-violet-800" :
                                            "bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800"
                                        )}>
                                            <div className={clsx("w-1.5 h-1.5 rounded-full mb-1",
                                                slot.color === 'blue' ? "bg-blue-500" : slot.color === 'violet' ? "bg-violet-500" : "bg-emerald-500"
                                            )} />
                                            <span className={clsx("text-[8px] uppercase font-bold",
                                                slot.color === 'blue' ? "text-blue-700 dark:text-blue-400" : slot.color === 'violet' ? "text-violet-700 dark:text-violet-400" : "text-emerald-700 dark:text-emerald-400"
                                            )}>{slot.label}</span>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-[9px] text-slate-400 mt-2">🔵 V2: Alts + Skills são novos no v8_qdrant_native</p>
                            </PipelineStep>

                            <div className="p-4 bg-slate-900 rounded-2xl border border-slate-800 shadow-inner">
                                <h4 className="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                                    <Clock className="w-3 h-3" /> Latência API
                                </h4>
                                <div className="flex justify-between items-end">
                                    <span className="text-2xl font-black text-white">{searchResults.latency_ms}ms</span>
                                    <span className="text-[10px] text-slate-500 mb-1">Qdrant+Logic</span>
                                </div>
                            </div>
                        </div>

                        {/* RIGHT: RESULTS */}
                        <div className="flex-1 flex flex-col gap-4 overflow-hidden border-l border-slate-200 dark:border-slate-700 pl-0 lg:pl-6">
                            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2">
                                <h4 className="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">
                                    Resultados Ranqueados
                                </h4>
                                <span className="text-xs font-mono text-slate-400">{searchResults.results?.length} itens</span>
                            </div>

                            <div className="overflow-y-auto pr-2 custom-scrollbar space-y-4 pb-20">
                                {searchResults.results?.map((res: any) => (
                                    <SearchResultItem 
                                        key={res.question_id} 
                                        res={res} 
                                        targetPipelineVersion={TARGET_PIPELINE_VERSION} 
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="flex-1 flex flex-col items-center justify-center text-slate-400 py-12">
                        <Search className="w-12 h-12 mb-3 text-slate-300 dark:text-slate-600" />
                        <p>Execute uma busca para inspecionar os logs do pipeline e os scores.</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MaestroTester;
