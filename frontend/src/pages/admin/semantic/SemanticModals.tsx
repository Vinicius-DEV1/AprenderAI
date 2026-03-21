import React from 'react';
import { 
    Server, 
    RefreshCw, 
    Rocket, 
    PlayCircle, 
    Lightbulb, 
    Trash2, 
    AlertCircle 
} from 'lucide-react';
import { clsx } from 'clsx';

interface IndexModalProps {
    isOpen: boolean;
    onClose: () => void;
    stats: any;
    batchLimit: number;
    setBatchLimit: (val: number) => void;
    force: boolean;
    setForce: (val: boolean) => void;
    reindexing: boolean;
    onStart: () => void;
    targetVersion: string;
}

export const IndexModal = ({ 
    isOpen, onClose, stats, batchLimit, setBatchLimit, force, setForce, reindexing, onStart, targetVersion 
}: IndexModalProps) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div className="p-6">
                    <div className="flex justify-between items-start mb-4">
                        <h3 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <Server className="w-6 h-6 text-indigo-500" />
                            Batch Indexer Xavier
                        </h3>
                        <button onClick={onClose} className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <RefreshCw className="w-5 h-5" style={{ transform: 'rotate(45deg)' }} />
                        </button>
                    </div>

                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                        Dispare jobs de vetorização controlada para economizar nos custos de API.
                        <span className="block mt-1 text-[11px] font-mono text-indigo-500 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-900/20 w-fit px-1.5 py-0.5 rounded">
                            Pipeline Ativo: {stats?.config.pipeline_version || targetVersion}
                        </span>
                    </p>

                    {force && (
                        <div className="mb-4 flex items-start gap-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl p-3">
                            <Rocket className="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" />
                            <div>
                                <p className="text-xs font-bold text-indigo-700 dark:text-indigo-400">Modo Forçado ativado — re-indexação completa (v8)</p>
                                <p className="text-[10px] text-indigo-600/70 dark:text-indigo-400/70 mt-0.5">
                                    Cada questão gerará <strong>5 vetores</strong> (statement, concept, explanation, <span className="text-blue-600 dark:text-blue-400">alternatives</span>, <span className="text-violet-600 dark:text-violet-400">skills</span>) + payload rico com subject_ids[], topic_ids[], has_explanation, word_count.
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl p-4 mb-6">
                        <div className="flex justify-between items-center mb-1">
                            <span className="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wider">
                                {force ? 'Total de Questões para Re-indexar' : 'Pendentes de Indexação'}
                            </span>
                            <span className={clsx("text-lg font-bold", force ? "text-indigo-600 dark:text-indigo-400" : "text-amber-800 dark:text-amber-200")}>
                                {stats ? (
                                    force
                                        ? stats.overview.mysql_published_questions
                                        : stats.overview.mysql_published_questions - stats.overview.mysql_indexed_questions
                                ) : '...'}
                            </span>
                        </div>
                        <p className="text-[10px] text-amber-600 dark:text-amber-500">
                            {force
                                ? "Modo FORÇAR ativado: Todas as questões publicadas no banco serão processadas novamente."
                                : "Questões publicadas (exceto redações) que ainda não possuem vetores sincronizados."}
                        </p>
                    </div>

                    <div className="space-y-2 mb-8">
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Quantas questões deseja indexar nesta leva?
                        </label>
                        <input
                            type="number"
                            min="1" max="100000"
                            value={batchLimit}
                            onChange={(e) => setBatchLimit(parseInt(e.target.value) || 0)}
                            className="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-indigo-500 outline-none text-slate-800 dark:text-white"
                        />
                        <div className="flex justify-between items-center">
                            <p className="text-[10px] text-slate-400">
                                Custo estimado: aprox. ${(batchLimit * 0.0001).toFixed(4)} USD (estimativa baseada em texto médio).
                            </p>
                            <button
                                type="button"
                                onClick={() => {
                                    const pending = stats ? (force ? stats.overview.mysql_published_questions : stats.overview.mysql_published_questions - stats.overview.mysql_indexed_questions) : 0;
                                    setBatchLimit(pending);
                                }}
                                className="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 underline"
                            >
                                Indexar Tudo Pendente
                            </button>
                        </div>
                    </div>

                    <div className="mb-6">
                        <label className="flex items-center gap-3 cursor-pointer group">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    className="sr-only"
                                    checked={force}
                                    onChange={(e) => setForce(e.target.checked)}
                                />
                                <div className={clsx("block w-10 h-6 rounded-full transition-colors", force ? "bg-amber-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                <div className={clsx("dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform", force && "transform translate-x-4")}></div>
                            </div>
                            <div>
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Forçar Re-indexação</span>
                                <p className="text-[10px] text-slate-500">Ignora se a questão já possui vetores e atualiza com a nova lógica.</p>
                            </div>
                        </label>
                    </div>

                    <div className="flex gap-3">
                        <button
                            onClick={onClose}
                            className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={onStart}
                            disabled={reindexing || batchLimit <= 0}
                            className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-indigo-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                        >
                            {reindexing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <PlayCircle className="w-4 h-4" />}
                            Iniciar Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

interface IntentModalProps {
    isOpen: boolean;
    onClose: () => void;
    stats: any;
    batchLimit: number;
    setBatchLimit: (val: number) => void;
    force: boolean;
    setForce: (val: boolean) => void;
    reindexing: boolean;
    onStart: () => void;
    targetVersion: string;
}

export const IntentModal = ({
    isOpen, onClose, stats, batchLimit, setBatchLimit, force, setForce, reindexing, onStart, targetVersion
}: IntentModalProps) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div className="p-6">
                    <div className="flex justify-between items-start mb-4">
                        <h3 className="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <Lightbulb className="w-6 h-6 text-purple-500" />
                            Indexar Matérias & Assuntos
                        </h3>
                        <button onClick={onClose} className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <RefreshCw className="w-5 h-5" style={{ transform: 'rotate(45deg)' }} />
                        </button>
                    </div>

                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                        Vetorize Matérias e Assuntos no Qdrant para a Detecção de Intenção da busca semântica.
                        <span className="block mt-1 text-[11px] font-mono text-purple-500 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/20 w-fit px-1.5 py-0.5 rounded">
                            Pipeline Ativo: {stats?.config.pipeline_version || targetVersion}
                        </span>
                    </p>

                    <div className="grid grid-cols-1 gap-3 mb-6">
                        <div className="bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800 rounded-xl p-3">
                            <div className="flex justify-between items-center">
                                <span className="text-[10px] font-medium text-purple-700 dark:text-purple-400 uppercase tracking-wider">Matérias/Disciplinas Pendentes</span>
                                <span className="text-sm font-bold text-purple-800 dark:text-purple-200">
                                    {stats ? (force ? stats.overview.mysql_total_subjects : stats.overview.mysql_total_subjects - stats.overview.mysql_indexed_subjects) : '...'}
                                </span>
                            </div>
                        </div>

                        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-3">
                            <div className="flex justify-between items-center">
                                <span className="text-[10px] font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wider">Assuntos Pendentes</span>
                                <span className="text-sm font-bold text-blue-800 dark:text-blue-200">
                                    {stats ? (force ? stats.overview.mysql_total_topics : stats.overview.mysql_total_topics - stats.overview.mysql_indexed_topics) : '...'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-2 mb-8">
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Quantas itens indexar nesta leva?
                        </label>
                        <input
                            type="number"
                            min="1" max="100000"
                            value={batchLimit}
                            onChange={(e) => setBatchLimit(parseInt(e.target.value) || 0)}
                            className="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-purple-500 outline-none text-slate-800 dark:text-white"
                        />
                        <div className="flex justify-end">
                            <button
                                type="button"
                                onClick={() => {
                                    const pending = stats ? (force
                                        ? stats.overview.mysql_total_subjects + stats.overview.mysql_total_topics
                                        : (stats.overview.mysql_total_subjects - stats.overview.mysql_indexed_subjects) +
                                        (stats.overview.mysql_total_topics - stats.overview.mysql_indexed_topics)
                                    ) : 0;
                                    setBatchLimit(pending);
                                }}
                                className="text-[10px] font-bold text-purple-600 hover:text-purple-700 underline"
                            >
                                Indexar Tudo Pendente (Global)
                            </button>
                        </div>
                    </div>

                    <div className="mb-6">
                        <label className="flex items-center gap-3 cursor-pointer group">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    className="sr-only"
                                    checked={force}
                                    onChange={(e) => setForce(e.target.checked)}
                                />
                                <div className={clsx("block w-10 h-6 rounded-full transition-colors", force ? "bg-purple-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                <div className={clsx("dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform", force && "transform translate-x-4")}></div>
                            </div>
                            <div>
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Forçar Re-indexação</span>
                                <p className="text-[10px] text-slate-500">Re-indexa com novos IDs SHA-256 e formato de embedding atualizado.</p>
                            </div>
                        </label>
                    </div>

                    <div className="flex gap-3">
                        <button
                            onClick={onClose}
                            className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={onStart}
                            disabled={reindexing || batchLimit <= 0}
                            className="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                        >
                            {reindexing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <PlayCircle className="w-4 h-4" />}
                            Iniciar Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

interface ResetModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    confirmText: string;
    setConfirmText: (val: string) => void;
    resetting: boolean;
}

export const ResetModal = ({
    isOpen, onClose, onConfirm, confirmText, setConfirmText, resetting
}: ResetModalProps) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-red-200 dark:border-red-900/50 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div className="bg-red-600 p-6 text-white">
                    <h3 className="text-xl font-bold flex items-center gap-2">
                        <Trash2 className="w-6 h-6" />
                        Reset Completo da Busca
                    </h3>
                    <p className="text-red-100 text-sm mt-2">
                        Esta é uma ação destrutiva irreversível.
                    </p>
                </div>

                <div className="p-6">
                    <div className="space-y-4 mb-6">
                        <div className="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm border border-red-100 dark:border-red-800">
                            <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                            <ul className="list-disc pl-4 space-y-1">
                                <li>Apagará TODAS as coleções do Qdrant.</li>
                                <li>Limpará as tabelas question_vectors e ai_search_cache no MySQL.</li>
                                <li>Zerar o status de indexação de todas as matérias e assuntos.</li>
                                <li><strong>Você terá que rodar "Indexar Questões" e "Indexar Matérias/Assuntos" novamente do zero.</strong></li>
                            </ul>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Para confirmar, digite <strong>RESET</strong> no campo abaixo:
                            </label>
                            <input
                                type="text"
                                placeholder="Digite RESET"
                                value={confirmText}
                                onChange={(e) => setConfirmText(e.target.value)}
                                className="w-full rounded-lg border-red-300 dark:border-red-700/50 bg-white dark:bg-slate-900 px-4 py-2 focus:ring-2 focus:ring-red-500 outline-none text-red-600 dark:text-red-400 font-bold uppercase"
                            />
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <button
                            onClick={() => {
                                onClose();
                                setConfirmText('');
                            }}
                            className="flex-1 px-4 py-2.5 rounded-xl font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={onConfirm}
                            disabled={resetting || confirmText !== 'RESET'}
                            className="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-medium flex items-center justify-center gap-2 shadow-lg shadow-red-500/20 transition-all disabled:opacity-50 disabled:shadow-none"
                        >
                            {resetting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
                            Executar Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};
