<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Jobs\AIBatchTriageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AIBatchTriageController extends Controller
{
    /**
     * Start the batch process.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:difficulty,explanation,both',
            'model' => 'nullable|string',
            'triage_status' => 'nullable|string',
            'triage_subject' => 'nullable|string',
            'triage_origin' => 'nullable|string',
        ]);

        // 1. Build the query to find pending questions based on current filters
        $query = Question::incomplete();

        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                'missing_difficulty' => $query->missingField('difficulty_reasoning'),
                'missing_explanation' => $query->missingField('explanation'),
                'both_missing' => $query->missingField('difficulty_reasoning')->missingField('explanation'),
                default => null,
            };
        }

        if ($request->filled('triage_subject')) {
            $query->whereHas('subjects', function ($q) use ($request) {
                $q->where('subjects.name', $request->triage_subject);
            });
        }

        if ($request->filled('triage_origin')) {
            $query->where('origin', $request->triage_origin);
        }

        $questions = $query->limit($validated['quantity'])->get();
        $total = $questions->count();

        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'Nenhuma questão encontrada para os critérios selecionados.']);
        }

        // 2. Create batch ID and initial progress record
        $batchId = Str::uuid()->toString();
        Cache::put("batch_progress_{$batchId}", [
            'total' => $total,
            'processed' => 0,
            'errors' => 0,
            'status' => 'processing',
            'message' => "Iniciando processamento de {$total} questões..."
        ], now()->addHours(2));

        // 3. Chunk and Dispatch Jobs
        $questions->chunk(10)->each(function ($chunk) use ($batchId, $validated) {
            AIBatchTriageJob::dispatch(
                $batchId, 
                $chunk->pluck('id')->toArray(), 
                $validated['type'], 
                $validated['model']
            );
        });

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'total' => $total
        ]);
    }

    /**
     * Stream progress via SSE.
     */
    public function progress(string $batchId)
    {
        return response()->stream(function () use ($batchId) {
            $key = "batch_progress_{$batchId}";
            
            while (true) {
                $data = Cache::get($key);

                if (!$data) {
                    echo "data: " . json_encode(['status' => 'not_found']) . "\n\n";
                    break;
                }

                echo "data: " . json_encode($data) . "\n\n";
                ob_flush();
                flush();

                if ($data['status'] === 'completed' || $data['status'] === 'failed') {
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // For Nginx
        ]);
    }
}
