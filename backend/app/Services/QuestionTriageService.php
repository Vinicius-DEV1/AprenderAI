<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionTriageLog;

/**
 * QuestionTriageService
 *
 * Responsible ONLY for writing triage logs to the database.
 * Structural detection is performed by the AI (via AIBatchService).
 * This service is called after AI processing or after manual admin actions.
 */
class QuestionTriageService
{
    /**
     * Log an automatic (AI batch) triage result for a question.
     *
     * @param Question $question
     * @param array    $issues       List of issue codes detected by the AI (e.g. ['no_alternatives', 'missing_image'])
     * @param int      $qualityScore Quality score 0-100 assigned by the AI
     * @param array    $changes      Snapshot of field changes applied (before/after)
     */
    public function logAutoTriage(
        Question $question,
        array $issues,
        int $qualityScore,
        array $changes = []
    ): void {
        $status = (!empty($issues) || $qualityScore < 60) ? 'manual_review' : 'approved';

        QuestionTriageLog::create([
            'question_id' => $question->id,
            'triage_type' => 'ai_batch',
            'status' => $status,
            'issues_detected' => $issues,
            'quality_score' => $qualityScore,
            'changes_made' => $changes,
            'processed_by' => 'system',
            'created_at' => now(),
        ]);
    }

    /**
     * Log a manual admin triage action for a question.
     *
     * @param Question   $question
     * @param string     $status   'approved' | 'manual_review'
     * @param array      $changes  Any field changes made during manual review
     * @param int|string $adminId  Admin user ID or name
     */
    public function logManualAction(
        Question $question,
        string $status,
        array $changes,
        $adminId
    ): void {
        QuestionTriageLog::create([
            'question_id' => $question->id,
            'triage_type' => 'manual',
            'status' => $status,
            'issues_detected' => [],
            'quality_score' => null,
            'changes_made' => $changes,
            'processed_by' => (string) $adminId,
            'created_at' => now(),
        ]);
    }
}
