<?php

namespace App\Observers;

use App\Jobs\ConceptExtractionJob;
use App\Jobs\IndexQuestionVectorJob;
use App\Models\Question;
use Illuminate\Support\Facades\Log;

/**
 * QuestionObserver
 *
 * Watches for Question model events and dispatches indexing jobs.
 *
 * Triggers:
 * - On approval (review_status changes → 'approved')
 *   → IndexQuestionVectorJob + ConceptExtractionJob
 *
 * - On edit of an already-approved question (statement/explanation changed)
 *   → IndexQuestionVectorJob only (hash mismatch will be detected in job)
 *
 * Questions with review_status = null (manually created) are treated as
 * always published — observer treats them as approved for indexing purposes.
 */
class QuestionObserver
{
    public function saved(Question $question): void
    {
        // Determine if question is eligible for indexing
        $isApproved = $question->review_status === 'approved' || is_null($question->review_status);

        if (!$isApproved) {
            return;
        }

        $wasJustApproved = $question->wasChanged('review_status')
            && $question->review_status === 'approved';

        $contentChanged = $question->wasChanged(['statement', 'explanation']);

        if ($wasJustApproved) {
            // Fresh approval — full indexing + concept extraction
            Log::info("[Xavier][Observer] Question #{$question->id} approved. Dispatching vector indexing + concept extraction.");
            IndexQuestionVectorJob::dispatch($question->id);
            ConceptExtractionJob::dispatch($question->id);
            return;
        }

        if ($contentChanged && $isApproved) {
            // Approved question content edited — reindex vectors
            Log::info("[Xavier][Observer] Question #{$question->id} content changed. Dispatching reindex.");
            IndexQuestionVectorJob::dispatch($question->id);
        }
    }
}
