<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Jobs\AIBatchTriageJob;
use App\Models\AiProcessingBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AIBatchTriageController extends Controller
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

        $query = Question::query();

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

        $questions = $query->limit($validated['quantity'])->get();

        return response()->json([
            'success' => true,
            'questions' => $questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'statement' => Str::limit(strip_tags($q->statement), 150),
                    'status' => ($q->incomplete ?? true) ? 'Incompleta' : 'Completa',
                    'subject' => $q->subjects->first()?->name ?? 'N/A',
                    'organization' => $q->organization ?? 'N/A',
                ];
            })
        ]);
    }

    /**
     * Start the batch process via API.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'chunk_size' => 'nullable|integer|min:1|max:50',
            'type' => 'required|in:difficulty,explanation,classification,complete,both',
            'model' => 'nullable|string',
            'reprocess' => 'nullable|boolean',
            'question_ids' => 'nullable|array',
            'triage_status' => 'nullable|string',
            'triage_subject' => 'nullable|string',
            'triage_origin' => 'nullable|string',
        ]);

        if ($request->filled('question_ids')) {
            $questions = Question::whereIn('id', $request->question_ids)->get();
        } else {
            $query = Question::query();

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

            $questions = $query->limit($validated['quantity'])->get();
        }

        $total = $questions->count();

        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'Nenhuma questão encontrada para os critérios selecionados.'], 404);
        }

        $batchId = Str::uuid()->toString();

        AiProcessingBatch::create([
            'batch_id' => $batchId,
            'model' => $validated['model'] ?? 'default',
            'type' => $validated['type'],
            'total_count' => $total,
            'status' => 'processing',
        ]);

        Cache::put("batch_progress_{$batchId}", [
            'total' => $total,
            'processed' => 0,
            'errors' => 0,
            'input_tokens' => 0, // Novo campo
            'output_tokens' => 0, // Novo campo
            'status' => 'processing',
            'message' => "Iniciando processamento de {$total} questões...",
            'last_error' => null
        ], now()->addHours(2));

        $chunkSize = $validated['chunk_size'] ?? 5;
        $reprocess = $validated['reprocess'] ?? false;
        $delaySeconds = $request->input('delay_seconds', 0);

        $questions->chunk($chunkSize)->each(function ($chunk, $index) use ($batchId, $validated, $reprocess, $delaySeconds) {
            $job = new AIBatchTriageJob(
                $batchId,
                $chunk->pluck('id')->toArray(),
                $validated['type'],
                $validated['model'] ?? 'gpt-4o',
                $reprocess
            );

            // Envia para a fila dedicada e aplica o delay progressivo
            $job->onQueue('ai-batches');

            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($index * $delaySeconds));
            }

            dispatch($job);
        });

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
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Lote não encontrado.'], 404);
    }

    /**
     * Get progress for a batch.
     */
    public function status($batchId)
    {
        $data = Cache::get("batch_progress_{$batchId}");

        if (!$data) {
            $batch = AiProcessingBatch::where('batch_id', $batchId)->first();
            if ($batch) {
                $logs = $batch->errors_log ?? [];
                $lastError = count($logs) > 0 ? end($logs)['error'] : null;

                return response()->json([
                    'total' => $batch->total_count,
                    'processed' => $batch->processed_count,
                    'errors' => $batch->error_count,
                    'input_tokens' => $batch->input_tokens ?? 0,
                    'output_tokens' => $batch->output_tokens ?? 0,
                    'status' => $batch->status,
                    'last_error' => $lastError,
                    'message' => $lastError ? "Finalizado com Erros" : "Concluído",
                ]);
            }
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        return response()->json($data);
    }

    /**
     * Get batch history (paginated)
     */
    public function history(Request $request)
    {
        $batches = \App\Models\AiProcessingBatch::orderBy('created_at', 'desc')->paginate(15);
        return response()->json($batches);
    }

    /**
     * Get details (items) of a specific batch
     */
    public function details($batchId)
    {
        $batch = \App\Models\AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();
        $items = \App\Models\AiBatchItem::with('question')->where('batch_id', $batchId)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'question_id' => $item->question_id,
                'statement' => $item->question ? \Illuminate\Support\Str::limit(strip_tags($item->question->statement), 100) : 'Questão excluída',
                'status' => $item->status,
                'before' => $item->snapshot_before,
                'after' => $item->snapshot_after,
            ];
        });

        return response()->json([
            'batch' => $batch,
            'items' => $items
        ]);
    }

    /**
     * Undo all processed items in a batch
     */
    public function undoBatch($batchId)
    {
        $batch = \App\Models\AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();
        $items = \App\Models\AiBatchItem::where('batch_id', $batchId)->where('status', 'processed')->get();

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
        $item = \App\Models\AiBatchItem::findOrFail($itemId);

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
        $batch = \App\Models\AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();

        $failedItems = \App\Models\AiBatchItem::where('batch_id', $batchId)
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
            'processed' => 0, // Reset progress for the retry view
            'errors' => 0,
            'input_tokens' => $batch->input_tokens,
            'output_tokens' => $batch->output_tokens,
            'status' => 'processing',
            'message' => "Reprocessando " . $failedItems->count() . " questões...",
            'last_error' => null
        ], now()->addHours(2));

        $failedItems->chunk(5)->each(function ($chunk, $index) use ($batchId, $batch) {
            $job = new \App\Jobs\AIBatchTriageJob(
                $batchId,
                $chunk->pluck('question_id')->toArray(),
                $batch->type,
                $batch->model,
                false // reprocess
            );
            $job->onQueue('ai-batches');
            dispatch($job);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Revert AI changes for a single item by re-applying the snapshot_before.
     */
    protected function performUndo($item)
    {
        $question = \App\Models\Question::find($item->question_id);
        if ($question && $item->snapshot_before) {
            $before = $item->snapshot_before;

            $question->update([
                'difficulty' => $before['difficulty'] ?? $question->difficulty,
                'difficulty_reasoning' => $before['difficulty_reasoning'] ?? $question->difficulty_reasoning,
                'explanation' => $before['explanation'] ?? $question->explanation,
                // optionally review_status => 'pending' could be applied, but keeping original behavior is safer unless tracked
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
