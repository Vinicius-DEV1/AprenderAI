<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AiBatchItem - Represents a single question within an AI processing batch.
 *
 * Each record tracks the processing state and snapshots (before/after) of one question,
 * enabling per-question rollback, retry, and detailed audit views in the admin history.
 *
 * Relationships:
 *   - question(): The Question that was processed
 *   - batch(): The parent AiProcessingBatch (matched via batch_id string)
 */
class AiBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'question_id',
        'status',
        'snapshot_before',
        'snapshot_after',
        'error_message',
    ];

    protected $casts = [
        'snapshot_before' => 'array',
        'snapshot_after' => 'array',
    ];

    /**
     * The question this batch item refers to.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * The parent batch this item belongs to.
     * Uses batch_id (UUID string) instead of FK because that's how batches are identified.
     */
    public function batch()
    {
        return $this->belongsTo(AiProcessingBatch::class , 'batch_id', 'batch_id');
    }

    /**
     * Scope: filter by batch UUID
     */
    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope: only items that can be retried (failed or stagnated)
     */
    public function scopeRetryable($query)
    {
        return $query->whereIn('status', ['failed', 'pending']);
    }
}
