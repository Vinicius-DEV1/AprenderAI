<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Jobs\AIBatchTriageJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AiProcessingBatch;
use App\Models\AiBatchItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AIBatchJobController extends Controller
{
    /**
     * Preview questions that will be included in a batch.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:difficulty,explanation,classification,complete,both',
            'triage_status' => 'nullable|string',
            'triage_subject' => 'nullable|string',
            'triage_origin' => 'nullable|string',
        ]);

        $query = Question::with('alternatives');

        if ($validated['type'] === 'difficulty') {
            $query->missingField('difficulty_reasoning');
        } elseif ($validated['type'] === 'explanation') {
            $query->missingField('explanation');
        } elseif ($validated['type'] === 'classification') {
            $query->whereDoesntHave('subjects')->orWhereDoesntHave('topics');
        } else {
            $query->incomplete();
        }

        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                'missing_difficulty' => $query->missingField('difficulty_reasoning'),
                'missing_explanation' => $query->missingField('explanation'),
                'both_missing' => $query->missingField('difficulty_reasoning')->missingField('explanation'),
                default => null,
            };
        }

        if ($request->filled('triage_subject')) {
            $query->filterBySubject($request->triage_subject);
        }

        $questions = $query->limit($validated['quantity'] * 2)->get();

        $brokenQuestions = $questions->filter(function ($q) {
            if ($q->type !== 'discursive' && $q->tipo_questao !== 'redacao' && !in_array(strtolower($q->format ?? ''), ['redacao', 'discursiva'])) {
                if ($q->alternatives->isEmpty() || $q->alternatives->whereNull('content')->count() > 0 || $q->alternatives->where('content', '')->count() > 0) {
                    return true;
                }
            }
            return false;
        });

        $validQuestions = $questions->diff($brokenQuestions)->take($validated['quantity']);

        return response()->json([
            'success' => true,
            'questions' => $validQuestions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'statement' => Str::limit(strip_tags($q->statement), 150),
                    'status' => ($q->incomplete ?? true) ? 'Incompleta' : 'Completa',
                    'subject' => $q->subjects->first()?->name ?? 'N/A',
                    'organization' => $q->organization ?? 'N/A',
                ];
            }),
            'ignored_count' => $brokenQuestions->count()
        ]);
    }

    /**
     * Start the batch process via API.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'chunk_size' => 'nullable|integer|min:1',
            'type' => 'required|in:difficulty,explanation,classification,complete,both',
            'model' => 'nullable|string',
            'reprocess' => 'nullable|boolean',
            'question_ids' => 'nullable|array',
            'triage_status' => 'nullable|string',
            'triage_subject' => 'nullable|string',
            'triage_origin' => 'nullable|string',
        ]);

        if ($request->filled('question_ids')) {
            $questions = Question::with('alternatives')->whereIn('id', $request->question_ids)->get();
        } else {
            $query = Question::with('alternatives');

            if ($validated['type'] === 'difficulty') {
                $query->missingField('difficulty_reasoning');
            } elseif ($validated['type'] === 'explanation') {
                $query->missingField('explanation');
            } elseif ($validated['type'] === 'classification') {
                $query->whereDoesntHave('subjects')->orWhereDoesntHave('topics');
            } else {
                $query->incomplete();
            }

            if ($request->filled('triage_status')) {
                match ($request->triage_status) {
                    'missing_difficulty' => $query->missingField('difficulty_reasoning'),
                    'missing_explanation' => $query->missingField('explanation'),
                    'both_missing' => $query->missingField('difficulty_reasoning')->missingField('explanation'),
                    default => null,
                };
            }

            if ($request->filled('triage_subject')) {
                $query->filterBySubject($request->triage_subject);
            }

            $questions = $query->limit($validated['quantity'] * 2)->get();
        }

        $brokenQuestions = $questions->filter(function ($q) {
            if ($q->type !== 'discursive' && $q->tipo_questao !== 'redacao' && !in_array(strtolower($q->format ?? ''), ['redacao', 'discursiva'])) {
                if ($q->alternatives->isEmpty() || $q->alternatives->whereNull('content')->count() > 0 || $q->alternatives->where('content', '')->count() > 0) {
                    return true;
                }
            }
            return false;
        });

        if ($brokenQuestions->count() > 0) {
            Log::info("[AIBATCH] Interceptando {$brokenQuestions->count()} questões com alternativas vazias e movendo para curadoria manual.");
            foreach ($brokenQuestions as $bq) {
                $bq->update([
                    'review_status' => 'review',
                    'is_active' => false
                ]);
            }
        }

        $validQuestions = $questions->diff($brokenQuestions)->take($validated['quantity']);
        $total = $validQuestions->count();
        $questions = $validQuestions;

        if ($total === 0) {
            $msg = 'Nenhuma questão encontrada para os critérios selecionados.';
            if ($brokenQuestions->count() > 0) {
                $msg = "Atenção: {$brokenQuestions->count()} questões tinham formato inválido (alternativas vazias) e foram enviadas automaticamente para a Revisão Manual.";
            }
            return response()->json(['success' => false, 'message' => $msg], 404);
        }

        $batchId = Str::uuid()->toString();

        AiProcessingBatch::create([
            'batch_id' => $batchId,
            'model' => $validated['model'] ?? 'default',
            'type' => $validated['type'],
            'total_count' => $total,
            'status' => 'processing',
        ]);

        $chunkSize = $validated['chunk_size'] ?? 5;
        $totalChunks = ceil($total / $chunkSize);

        Cache::put("batch_progress_{$batchId}", [
            'total' => $total,
            'processed' => 0,
            'errors' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'status' => 'processing',
            'message' => "Iniciando processamento de {$total} questões...",
            'last_error' => null,
            'initial_chunks_total' => $totalChunks,
            'initial_chunks_processed' => 0,
            'retry_chunks_total' => 0,
            'retry_chunks_processed' => 0,
            'retries_count' => 0,
            'retries_success' => 0,
            'retries_failed' => 0,
            'stats' => [
                'difficulty' => 0,
                'explanation' => 0,
                'subjects' => 0,
                'topics' => 0,
                'sent_to_review' => 0,
                'approved' => 0,
                'low_quality' => 0,
            ]
        ], now()->addHours(2));

        $reprocess = $validated['reprocess'] ?? false;
        $delaySeconds = $request->input('delay_seconds', 0);

        Log::info("[AIBATCH] Dispatching jobs for batch", ['batch_id' => $batchId, 'total' => $total, 'chunk_size' => $chunkSize]);

        $userId = auth()->id();
        Log::info("[AIBATCH] Starting chunking with size: " . $chunkSize);
        $questions->chunk($chunkSize)->each(function ($chunk, $index) use ($batchId, $validated, $reprocess, $delaySeconds, $userId) {
            Log::info("[AIBATCH] Dispatching chunk {$index} for batch {$batchId}", ['count' => $chunk->count(), 'question_ids' => $chunk->pluck('id')->toArray()]);

            $job = new AIBatchTriageJob(
                $batchId,
                $chunk->pluck('id')->toArray(),
                $validated['type'],
                null,
                $reprocess,
                $userId,
                $index,
                $delaySeconds,
                0
            );

            $job->onQueue(config('xavier.embeddings.batch_queue', 'embeddings'));
            dispatch($job);
        });

        Log::info("[AIBATCH] All jobs dispatched for batch", ['batch_id' => $batchId]);

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'total' => $total
        ]);
    }

    /**
     * Cancel an active batch.
     */
    public function cancel($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->first();

        if ($batch) {
            $batch->update(['status' => 'cancelled']);
            Cache::forget("batch_progress_{$batchId}");

            // Purge pending jobs from the queue associated with this batch
            try {
                DB::table('jobs')
                    ->where('queue', config('xavier.embeddings.batch_queue', 'embeddings'))
                    ->where('payload', 'like', '%' . $batchId . '%')
                    ->delete();
            } catch (\Exception $e) {
                Log::error("[AIBATCH] Error purging jobs for cancelled batch {$batchId}: " . $e->getMessage());
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Lote não encontrado.'], 404);
    }

    /**
     * Cancel an active batch AND revert all processed items so far.
     */
    public function cancelAndRevert($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->first();

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Lote não encontrado.'], 404);
        }

        $batch->update(['status' => 'cancelled']);
        Cache::forget("batch_progress_{$batchId}");

        try {
            DB::beginTransaction();
            $items = AiBatchItem::where('batch_id', $batchId)->where('status', 'success')->get();

            $revertedCount = 0;
            foreach ($items as $item) {
                $question = Question::find($item->question_id);
                if ($question && $item->snapshot_before) {
                    $snap = $item->snapshot_before;

                    $question->update([
                        'difficulty' => $snap['difficulty'] ?? null,
                        'difficulty_reasoning' => $snap['difficulty_reasoning'] ?? null,
                        'explanation' => $snap['explanation'] ?? null,
                    ]);

                    if (isset($snap['subjects'])) {
                        $question->subjects()->sync($snap['subjects']);
                    }
                    if (isset($snap['topics'])) {
                        $question->topics()->sync($snap['topics']);
                    }

                    $item->update(['status' => 'reverted']);
                    $revertedCount++;
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Lote cancelado. {$revertedCount} questões processadas foram revertidas com sucesso."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AIBATCH] Error during cancelAndRevert: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao reverter questões do lote.'], 500);
        }
    }

    /**
     * Undo all processed items in a batch
     */
    public function undoBatch($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();
        $items = AiBatchItem::where('batch_id', $batchId)->where('status', 'processed')->get();

        foreach ($items as $item) {
            $this->performUndo($item);
        }

        $batch->update(['status' => 'reverted']);
        return response()->json(['success' => true]);
    }

    /**
     * Undo a specific item in a batch
     */
    public function undoItem($itemId)
    {
        $item = AiBatchItem::findOrFail($itemId);

        if ($item->status === 'processed') {
            $this->performUndo($item);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Retry failed items in a batch
     */
    public function retry($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();

        $failedItems = AiBatchItem::where('batch_id', $batchId)
            ->whereIn('status', ['failed', 'pending'])
            ->get();

        if ($failedItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nenhuma questão pendente ou com falha neste lote.']);
        }

        $batch->update([
            'status' => 'processing',
        ]);

        Cache::put("batch_progress_{$batchId}", [
            'total' => $batch->total_count,
            'processed' => 0,
            'errors' => 0,
            'input_tokens' => $batch->input_tokens,
            'output_tokens' => $batch->output_tokens,
            'status' => 'processing',
            'message' => "Reprocessando " . $failedItems->count() . " questões...",
            'last_error' => null
        ], now()->addHours(2));

        $failedItems->chunk(5)->each(function ($chunk, $index) use ($batchId, $batch) {
            $job = new AIBatchTriageJob(
                $batchId,
                $chunk->pluck('question_id')->toArray(),
                $batch->type,
                $batch->model,
                false
            );
            $job->onQueue(config('xavier.embeddings.batch_queue', 'embeddings'));
            dispatch($job);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Revert AI changes for a single item by re-applying the snapshot_before.
     */
    protected function performUndo($item)
    {
        $question = Question::find($item->question_id);
        if ($question && $item->snapshot_before) {
            $before = $item->snapshot_before;

            $question->update([
                'difficulty' => $before['difficulty'] ?? $question->difficulty,
                'difficulty_reasoning' => $before['difficulty_reasoning'] ?? $question->difficulty_reasoning,
                'explanation' => $before['explanation'] ?? $question->explanation,
            ]);

            if (isset($before['subjects']) && is_array($before['subjects'])) {
                $question->subjects()->sync($before['subjects']);
            }
            if (isset($before['topics']) && is_array($before['topics'])) {
                $question->topics()->sync($before['topics']);
            }

            $item->update(['status' => 'reverted']);
        }
    }
}
