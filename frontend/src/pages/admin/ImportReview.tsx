/**
 * ImportReview
 * Main entry point for the Question Review and Triage interface.
 * Orchestrates metadata display, content review, and image cropping.
 */
/**
 * ImportReview Component (Modularized)
 * 
 * Handles the multi-step triage process for imported questions, including
 * metadata verification, content review, and image cropping.
 * 
 * @module ImportReview
 */
import { useImportReview } from './import-review/useImportReview';
import ImportReviewHeader from './import-review/ImportReviewHeader';
import QuestionMetadata from './import-review/QuestionMetadata';
import QuestionContent from './import-review/QuestionContent';
import ControlPanel from './import-review/ControlPanel';
import HistoryTimeline from './import-review/HistoryTimeline';
import CropEditor from './import-review/CropEditor';

const ImportReview = () => {
    const {
        data,
        isLoading,
        historyData,
        searchParams,
        navigate,
        activeTarget,
        setActiveTarget,
        saving,
        cropperEls,
        approveMutation,
        revertMutation,
        deleteImageMutation,
        handleSaveCrop,
        isComplete,
        getCacheBustedUrl,
        apiUrl
    } = useImportReview();

    if (isLoading) return <div className="p-8">Carregando revisão...</div>;
    if (!data?.question) return <div className="p-8 text-red-500">Questão não encontrada.</div>;

    const { question, importItem, prev_id, next_id } = data;
    const completion = isComplete(question);
    const hasImageModels = question?.images?.length > 0;
    const latestAILog = historyData?.find((log: any) => log.triage_type === 'ai_batch');

    return (
        <div className="py-4 px-2 md:px-4 w-full">
            <div className="w-full">
                <ImportReviewHeader 
                    question={question} 
                    searchParams={searchParams} 
                />

                <div className={`grid grid-cols-1 ${hasImageModels ? 'lg:grid-cols-2' : 'lg:grid-cols-[1fr_350px]'} gap-4 items-start`}>
                    {/* Left Column: Data Review */}
                    <div className="space-y-3">
                        <QuestionMetadata 
                            question={question}
                            importItem={importItem}
                            latestAILog={latestAILog}
                        />

                        <QuestionContent 
                            question={question}
                            getCacheBustedUrl={getCacheBustedUrl}
                            apiUrl={apiUrl}
                        />

                        {/* Mobile Actions for Text-Only */}
                        {!hasImageModels && (
                            <div className="lg:hidden">
                                <ControlPanel 
                                    question={question}
                                    completion={completion}
                                    approveMutation={approveMutation}
                                    revertMutation={revertMutation}
                                    prevId={prev_id}
                                    nextId={next_id}
                                    navigate={navigate}
                                    searchParams={searchParams}
                                    isCompact={true}
                                />
                            </div>
                        )}

                        <HistoryTimeline historyData={historyData} />
                    </div>

                    {/* Right Column: Editor OR Control Panel */}
                    <div>
                        {hasImageModels ? (
                            <div className="space-y-6">
                                <CropEditor 
                                    images={question.images}
                                    cropperEls={cropperEls}
                                    activeTarget={activeTarget}
                                    setActiveTarget={setActiveTarget}
                                    handleSaveCrop={handleSaveCrop}
                                    deleteImageMutation={deleteImageMutation}
                                    saving={saving}
                                    apiUrl={apiUrl}
                                />
                                <div className="sticky top-4 z-20">
                                    <ControlPanel 
                                        question={question}
                                        completion={completion}
                                        approveMutation={approveMutation}
                                        revertMutation={revertMutation}
                                        prevId={prev_id}
                                        nextId={next_id}
                                        navigate={navigate}
                                        searchParams={searchParams}
                                    />
                                </div>
                            </div>
                        ) : (
                            <div className="sticky top-4">
                                <ControlPanel 
                                    question={question}
                                    completion={completion}
                                    approveMutation={approveMutation}
                                    revertMutation={revertMutation}
                                    prevId={prev_id}
                                    nextId={next_id}
                                    navigate={navigate}
                                    searchParams={searchParams}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ImportReview;
