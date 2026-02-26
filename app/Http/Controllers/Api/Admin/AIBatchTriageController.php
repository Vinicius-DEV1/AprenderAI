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
                    'status' => $q->incomplete() ? 'Incompleta' : 'Completa',
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
            'type' => 'required|in:difficulty,explanation,classification,complete,both',
            'model' => 'nullable|string',
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
            'status' => 'processing',
            'message' => "Iniciando processamento de {$total} questões...",
            'last_error' => null
        ], now()->addHours(2));

        $questions->chunk(5)->each(function ($chunk) use ($batchId, $validated) {
            AIBatchTriageJob::dispatch(
                $batchId,
                $chunk->pluck('id')->toArray(),
                $validated['type'],
                $validated['model'] ?? 'gpt-4'
            );
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
                return response()->json([
                    'total' => $batch->total_count,
                    'processed' => $batch->processed_count,
                    'errors' => $batch->error_count,
                    'status' => $batch->status,
                ]);
            }
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        return response()->json($data);
    }
}
