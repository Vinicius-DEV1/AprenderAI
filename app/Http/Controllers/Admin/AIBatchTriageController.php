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
            'type' => 'required|in:difficulty,explanation,classification,complete,both',
            'model' => 'nullable|string',
            'triage_status' => 'nullable|string',
            'triage_subject' => 'nullable|string',
            'triage_origin' => 'nullable|string',
        ]);

        // 1. Build the query to find pending questions based on current filters
        // Filter Gate: Apenas busca questões baseadas ESPECIFICAMENTE no que foi pedido.
        $query = Question::query();

        // Trava de Seleção baseada no Tipo de Ação Solicitada:
        if ($validated['type'] === 'difficulty') {
            $query->missingField('difficulty_reasoning');
        } elseif ($validated['type'] === 'explanation') {
            $query->missingField('explanation');
        } elseif ($validated['type'] === 'classification') {
            $query->where(function($q) {
                $q->whereDoesntHave('subjects')->orWhereDoesntHave('topics');
            });
        } else {
            // 'complete' ou 'both': pega as incompletas globais (qualquer campo faltando)
            $query->incomplete();
        }

        // Refinamento de Status (caso selecionado no Painel)
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
        
        // Persistência no Banco de Dados
        \App\Models\AiProcessingBatch::create([
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

        // 3. Chunk and Dispatch Jobs
        $questions->chunk(5)->each(function ($chunk) use ($batchId, $validated) {
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
        // 1. DESBLOQUEIO DE SESSÃO: Libera a trava do arquivo de sessão do PHP.
        // Isso impede que o "loading eterno" trave o sistema inteiro para o usuário
        // enquanto ele aguarda a resposta lenta do AI e do Stream.
        session_write_close();

        return response()->stream(function () use ($batchId) {
            $key = "batch_progress_{$batchId}";
            $startTime = time();
            $maxDuration = 60 * 5; // 5 minutes max per SSE connection

            $iteration = 0;
            
            while (true) {
                $iteration++;
                // Safety: check if connection is still active and duration is within limits
                if (connection_aborted() || (time() - $startTime) > $maxDuration) {
                    break;
                }

                $data = Cache::get($key);

                if (!$data) {
                    // Tenta recuperar do Banco de Dados se o Cache expirou
                    $dbBatch = \App\Models\AiProcessingBatch::where('batch_id', $batchId)->first();
                    if ($dbBatch) {
                        $data = [
                            'total' => $dbBatch->total_count,
                            'processed' => $dbBatch->processed_count,
                            'errors' => $dbBatch->error_count,
                            'status' => $dbBatch->status,
                            'last_error' => !empty($dbBatch->errors_log) ? end($dbBatch->errors_log)['error'] : null,
                            'errors_log' => $dbBatch->errors_log ?? []
                        ];
                    } else {
                        echo "data: " . json_encode(['status' => 'not_found', 'message' => 'Lote não encontrado.']) . "\n\n";
                        ob_flush();
                        flush();
                        break;
                    }
                }

                // Heartbeat do SSE: a cada 5 iterações (~10s), envia um ping silencioso
                // Isso impede que proxies (Nginx/Cloudflare) matem a conexão aberta por Inactivity Timeout
                if ($iteration % 5 === 0) {
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                }

                if ($data['status'] === 'completed' || $data['status'] === 'failed') {
                    break;
                }

                sleep(2); // Sleep cycle
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // For Nginx
        ]);
    }

    /**
     * View history of batches.
     */
    public function history()
    {
        $batches = \App\Models\AiProcessingBatch::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.questions.history', compact('batches'));
    }
}
