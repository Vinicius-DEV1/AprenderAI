import React from 'react';
import { Settings, HelpCircle, RefreshCw, Save, Database } from 'lucide-react';
import { clsx } from 'clsx';
import { DashboardStats, ConfigState } from './Types';

interface ConfigurationPanelProps {
    stats: DashboardStats | null;
    configState: ConfigState;
    setConfigState: (state: ConfigState | ((v: ConfigState) => ConfigState)) => void;
    handleSaveConfig: () => Promise<void>;
    configLoading: boolean;
}

const ConfigurationPanel: React.FC<ConfigurationPanelProps> = ({
    stats,
    configState,
    setConfigState,
    handleSaveConfig,
    configLoading
}) => {
    return (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                    <Settings className="w-5 h-5 text-slate-400" />
                    Configurações em Tempo Real
                </h2>
                <p className="text-xs text-slate-500 mb-6">
                    As alterações aqui são salvas diretamente no banco de dados e entram em vigor imediatamente na próxima busca.
                </p>

                <div className="space-y-5">
                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    className="sr-only"
                                    checked={configState.vector_search_enabled}
                                    onChange={(e) => setConfigState({ ...configState, vector_search_enabled: e.target.checked })}
                                />
                                <div className={clsx("block w-14 h-8 rounded-full transition-colors", configState.vector_search_enabled ? "bg-emerald-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                <div className={clsx("dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform", configState.vector_search_enabled && "transform translate-x-6")}></div>
                            </div>
                            <div className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                <div>
                                    <span className="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-1">
                                        Motor Semântico
                                        <div className="cursor-help text-slate-400" title="Quando ativado, o Xavier utiliza o Qdrant (Vetores) para entender o significado das perguntas, não apenas as palavras exatas.">
                                            <HelpCircle className="w-3.5 h-3.5" />
                                        </div>
                                    </span>
                                    <p className="text-[10px] text-slate-500">Uso de Embeddings (Cérebro IA)</p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <hr className="border-slate-100 dark:border-slate-700" />

                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <div className="relative">
                                <input
                                    type="checkbox"
                                    className="sr-only"
                                    checked={configState.search_cache_enabled}
                                    onChange={(e) => setConfigState({ ...configState, search_cache_enabled: e.target.checked })}
                                />
                                <div className={clsx("block w-14 h-8 rounded-full transition-colors", configState.search_cache_enabled ? "bg-indigo-500" : "bg-slate-300 dark:bg-slate-600")}></div>
                                <div className={clsx("dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform", configState.search_cache_enabled && "transform translate-x-6")}></div>
                            </div>
                            <div className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                <div>
                                    <span className="text-sm font-semibold text-slate-800 dark:text-white flex items-center gap-1">
                                        Cache de Busca (L2)
                                        <div className="cursor-help text-slate-400" title="Quando ativado, economiza custos e melhora a velocidade ao reutilizar resultados de buscas idênticas ou muito próximas.">
                                            <HelpCircle className="w-3.5 h-3.5" />
                                        </div>
                                    </span>
                                    <p className="text-[10px] text-slate-500">Persistência em MySQL (Xavier Cache)</p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <hr className="border-slate-100 dark:border-slate-700" />

                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Tolerância Semântica (Concept Threshold)
                        </label>
                        <div className="flex items-center gap-4">
                            <input
                                type="range"
                                min="0.20" max="0.95" step="0.01"
                                value={configState.concept_detection_threshold}
                                onChange={(e) => setConfigState({ ...configState, concept_detection_threshold: parseFloat(e.target.value) })}
                                className="w-full accent-indigo-600"
                            />
                            <span className="w-12 text-center text-sm font-mono text-slate-600 dark:text-slate-400">
                                {configState.concept_detection_threshold.toFixed(2)}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 mt-2">
                            Define o Score mínimo para a IA associar automaticamente um conceito à busca. Valores baixos = maior expansão (pode trazer ruído).
                        </p>
                    </div>

                    <button
                        onClick={handleSaveConfig}
                        disabled={configLoading}
                        className="w-full btn btn-primary bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center gap-2 py-2"
                    >
                        {configLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                        Salvar no Banco (Runtime)
                    </button>
                </div>
            </div>

            <div className="flex flex-col gap-6">
                {stats && stats.config.pipeline_version && (
                    <div className="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/50 rounded-xl p-6 shadow-sm flex items-start gap-4">
                        <div className="p-3 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-lg">
                            <Database className="w-6 h-6" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-slate-800 dark:text-white">Pipeline Ativo</h3>
                            <div className="mt-1 flex items-center gap-2">
                                <span className="font-mono text-lg font-bold text-indigo-700 dark:text-indigo-400">
                                    {stats.config.pipeline_version}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-2">
                                Versão de indexação atual. Útil para debugar se as questões já adotaram novos pesos.
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ConfigurationPanel;
