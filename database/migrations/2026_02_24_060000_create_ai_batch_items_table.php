<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the ai_batch_items table for per-question tracking within AI processing batches.
 *
 * PURPOSE:
 * The existing ai_processing_batches table only stores aggregate counters (total, processed, errors).
 * This new table tracks each individual question within a batch, enabling:
 *   1. "View Batch Details" modal with per-question before/after comparison
 *   2. Individual question undo (rollback from snapshot_before)
 *   3. Full batch undo (rollback all items)
 *   4. Retry of failed/stagnated items
 *
 * SNAPSHOT DESIGN:
 * snapshot_before and snapshot_after store JSON representations of the question's state
 * at the time of processing. This includes: difficulty, difficulty_reasoning, explanation,
 * subject_ids, and topic_ids — all fields that AI triage can modify.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_batch_items', function (Blueprint $table) {
            $table->id();

            // Links to the parent batch (string UUID, not FK — matches existing batch_id format)
            $table->string('batch_id')->index();

            // The question that was processed in this batch
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            // Individual item status within the batch
            // pending   = queued but not yet processed
            // processed = successfully updated by AI
            // failed    = AI processing threw an error
            // reverted  = admin manually rolled back this item
            $table->string('status')->default('pending');

            // JSON snapshots of the question state before and after AI processing.
            // Used for the "before/after" comparison UI and for undo operations.
            $table->json('snapshot_before')->nullable();
            $table->json('snapshot_after')->nullable();

            // Error details if this specific question failed during processing
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Compound index for efficient batch detail queries
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_batch_items');
    }
};
