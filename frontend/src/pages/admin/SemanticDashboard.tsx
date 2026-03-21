/**
 * SemanticDashboard
 * Main entry point for the Semantic Engine administration.
 * Orchestrates health monitoring, vector index management, and search simulation.
 */
/**
 * SemanticDashboard
 * Main entry point for the Semantic Engine administration.
 * Orchestrates health monitoring, vector index management, and search simulation.
 */
import { 
    RefreshCw, 
    Rocket, 
    Zap 
} from 'lucide-react';
import { IndexModal, IntentModal, ResetModal } from './semantic/SemanticModals';

// Modular Components & Hook
import { TARGET_PIPELINE_VERSION } from './semantic/Types';
import { useSemanticDashboard } from './semantic/useSemanticDashboard';
import SemanticHeader from './semantic/SemanticHeader';
import MonitoringCenter from './semantic/MonitoringCenter';
import PerformanceAnalytics from './semantic/PerformanceAnalytics';
import CongestionMonitor from './semantic/CongestionMonitor';
import ConceptCloud from './semantic/ConceptCloud';
import ConfigurationPanel from './semantic/ConfigurationPanel';
import RankingPriorities from './semantic/RankingPriorities';
import MaestroTester from './semantic/MaestroTester';
import LogsAndCache from './semantic/LogsAndCache';
import RecentFailures from './semantic/RecentFailures';
import ApiKeysHealthPanel from './semantic/ApiKeysHealthPanel';

const SemanticDashboard = () => {
    const {
        stats,
        loading,
        configLoading,
        testLoading,
        reindexing,
        reindexingIntents,
        clearingCache,
        clearingQueue,
        resetting,
        isResetModalOpen,
        setIsResetModalOpen,
        resetConfirmText,
        setResetConfirmText,
        wakingUp,
        actionsMenuOpen,
        setActionsMenuOpen,
        actionsMenuRef,
        configState,
        setConfigState,
        isIndexModalOpen,
        setIsIndexModalOpen,
        indexBatchLimit,
        setIndexBatchLimit,
        indexForce,
        setIndexForce,
        isIntentModalOpen,
        setIsIntentModalOpen,
        intentBatchLimit,
        setIntentBatchLimit,
        intentForce,
        setIntentForce,
        searchPrompt,
        setSearchPrompt,
        searchResults,
        // setSearchResults, // Removed unused
        parsePipelineSteps,
        handleReplay,
        loadStats,
        handleSaveConfig,
        handleTestSearch,
        handleReindex,
        handleReindexIntents,
        handleClearCache,
        handleClearQueue,
        handleResetEmbeddings,
        handleClearCongestion,
        handleClearTriageQueue,
        clearingTriage
    } = useSemanticDashboard();

    if (loading && !stats) {
        return <div className="p-8 flex justify-center"><RefreshCw className="w-8 h-8 animate-spin text-indigo-500" /></div>;
    }

    return (
        <>
            <div className="p-6 max-w-7xl mx-auto space-y-8">
                {/* Header Section */}
                <SemanticHeader 
                    stats={stats}
                    loading={loading}
                    loadStats={loadStats}
                    setIsIndexModalOpen={setIsIndexModalOpen}
                    reindexing={reindexing}
                    setIsIntentModalOpen={setIsIntentModalOpen}
                    reindexingIntents={reindexingIntents}
                    actionsMenuOpen={actionsMenuOpen}
                    setActionsMenuOpen={setActionsMenuOpen}
                    actionsMenuRef={actionsMenuRef}
                    handleClearCache={handleClearCache}
                    clearingCache={clearingCache}
                    handleClearQueue={handleClearQueue}
                    clearingQueue={clearingQueue}
                    handleClearTriageQueue={handleClearTriageQueue}
                    clearingTriage={clearingTriage}
                    setIsResetModalOpen={setIsResetModalOpen}
                />

                {stats && (
                    <>
                        <MonitoringCenter stats={stats} />
                        
                        <PerformanceAnalytics stats={stats} />

                        <ApiKeysHealthPanel stats={stats} loading={loading} />

                        {/* QDRANT INDEX HEALTH ALERTS (Inlined for importance) */}
                        {stats.qdrant?.index_version_status && (
                            (stats.qdrant.index_version_status.questions.status === 'outdated' ||
                                stats.qdrant.index_version_status.concepts.status === 'outdated' ||
                                stats.qdrant.index_version_status.questions.status === 'empty' ||
                                stats.qdrant.index_version_status.concepts.status === 'empty'
                            ) && (
                                <div className="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 rounded-xl p-4 shadow-sm">
                                    <h3 className="text-rose-800 dark:text-rose-400 font-bold mb-2 flex items-center gap-2">
                                        <div className="p-1 bg-rose-100 dark:bg-rose-900/50 rounded-lg"><Rocket className="w-5 h-5 text-rose-500" /></div>
                                        Atenção: Atualização de Banco Vetorial Necessária
                                    </h3>
                                    <p className="text-sm text-rose-700 dark:text-rose-300 mb-3">
                                        O formato dos dados salvos no Qdrant está desatualizado (Versão esperada: <span className="font-mono bg-rose-100 dark:bg-rose-900/50 px-1 rounded">{stats.qdrant.index_version_status.expected}</span>).
                                    </p>
                                    <div className="flex gap-4 text-xs">
                                        {stats.qdrant.index_version_status.questions.status !== 'ok' && (
                                            <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                                <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                                                <strong>Questões:</strong> {stats.qdrant.index_version_status.questions.message}
                                            </div>
                                        )}
                                        {stats.qdrant.index_version_status.concepts.status !== 'ok' && (
                                            <div className="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                                <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                                                <strong>Conceitos:</strong> {stats.qdrant.index_version_status.concepts.message}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )
                        )}

                        {/* V8 UPGRADE BANNER */}
                        {stats.config.pipeline_version === TARGET_PIPELINE_VERSION &&
                            stats.overview.mysql_indexed_questions > 0 &&
                            stats.qdrant.index_version_status?.questions.status === 'outdated' && (
                                <div className="bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl p-5 shadow-xl shadow-indigo-500/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="p-2 bg-white/20 rounded-xl shrink-0">
                                            <Rocket className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-base flex items-center gap-2">
                                                Upgrade Disponível: Pipeline {TARGET_PIPELINE_VERSION}
                                                <span className="bg-white/20 text-[10px] font-black px-2 py-0.5 rounded-full tracking-wider">NOVO</span>
                                            </h3>
                                            <p className="text-sm text-indigo-200 mt-1">
                                                5 vetores por questão + payload rico. As questões já indexadas usam o formato antigo.
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => {
                                            setIndexForce(true);
                                            setIndexBatchLimit(stats.overview.mysql_published_questions || 9999);
                                            setIsIndexModalOpen(true);
                                        }}
                                        className="shrink-0 bg-white text-indigo-700 hover:bg-indigo-50 font-bold px-5 py-2.5 rounded-xl text-sm shadow-lg transition-all active:scale-95 flex items-center gap-2"
                                    >
                                        <Zap className="w-4 h-4" />
                                        Forçar Re-indexação (v8)
                                    </button>
                                </div>
                            )}

                        <CongestionMonitor 
                            stats={stats}
                            wakingUp={wakingUp}
                            handleClearCongestion={handleClearCongestion}
                        />

                        <ConceptCloud stats={stats} />

                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            <ConfigurationPanel 
                                stats={stats}
                                configState={configState}
                                setConfigState={setConfigState}
                                handleSaveConfig={handleSaveConfig}
                                configLoading={configLoading}
                            />
                            
                            <RecentFailures stats={stats} />
                        </div>

                        <RankingPriorities 
                            stats={stats}
                            configState={configState}
                            setConfigState={setConfigState}
                        />

                        <MaestroTester 
                            stats={stats}
                            searchPrompt={searchPrompt}
                            setSearchPrompt={setSearchPrompt}
                            handleTestSearch={handleTestSearch}
                            testLoading={testLoading}
                            searchResults={searchResults}
                            parsePipelineSteps={parsePipelineSteps}
                            TARGET_PIPELINE_VERSION={TARGET_PIPELINE_VERSION}
                        />

                        <LogsAndCache 
                            stats={stats}
                            handleReplay={handleReplay}
                            handleClearCache={handleClearCache}
                            clearingCache={clearingCache}
                        />
                    </>
                )}
            </div>

            {/* MODALS */}
            <IndexModal
                isOpen={isIndexModalOpen}
                onClose={() => setIsIndexModalOpen(false)}
                stats={stats}
                indexBatchLimit={indexBatchLimit}
                setIndexBatchLimit={setIndexBatchLimit}
                indexForce={indexForce}
                setIndexForce={setIndexForce}
                reindexing={reindexing}
                onReindex={handleReindex}
                targetPipelineVersion={TARGET_PIPELINE_VERSION}
            />

            <IntentModal
                isOpen={isIntentModalOpen}
                onClose={() => setIsIntentModalOpen(false)}
                stats={stats}
                intentBatchLimit={intentBatchLimit}
                setIntentBatchLimit={setIntentBatchLimit}
                intentForce={intentForce}
                setIntentForce={setIntentForce}
                reindexingIntents={reindexingIntents}
                onReindexIntents={handleReindexIntents}
                targetPipelineVersion={TARGET_PIPELINE_VERSION}
            />

            <ResetModal
                isOpen={isResetModalOpen}
                onClose={() => {
                    setIsResetModalOpen(false);
                    setResetConfirmText('');
                }}
                resetting={resetting}
                confirmText={resetConfirmText}
                setConfirmText={setResetConfirmText}
                onConfirm={handleResetEmbeddings}
            />
        </>
    );
};

export default SemanticDashboard;
