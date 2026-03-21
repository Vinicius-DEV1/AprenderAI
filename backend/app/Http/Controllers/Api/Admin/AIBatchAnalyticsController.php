<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProcessingBatch;
use App\Models\AiBatchItem;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AIBatchAnalyticsController extends Controller
{
    /**
     * Get the currently active batch (processing).
     */
    public function active()
    {
        // Pega o lote em processamento ou o último concluído nos últimos 15 minutos
        // Isso mantém o ícone flutuante visível para o usuário ver o resumo após terminar.
        $batch = AiProcessingBatch::where('status', 'processing')
            ->orWhere(function ($q) {
                $q->whereIn('status', ['completed', 'failed', 'cancelled'])
                    ->where('updated_at', '>=', now()->subMinutes(15));
            })
            ->latest()
            ->first();

        if ($batch) {
            return response()->json([
                'success' => true,
                'batch_id' => $batch->batch_id,
                'status' => $batch->status
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Nenhum lote ativo.']);
    }

    /**
     * Get progress for a batch.
     */
    public function status($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->first();
        $data = Cache::get("batch_progress_{$batchId}");

        if (!$batch) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        // Se não houver cache, gera as informações básicas a partir do banco
        if (!$data) {
            $logs = $batch->errors_log ?? [];
            $lastError = count($logs) > 0 ? end($logs)['error'] : null;

            $status = $batch->status;
            $message = "Processando...";
            if ($status === 'completed')
                $message = "Concluído";
            if ($status === 'failed')
                $message = "Falha no Processamento";
            if ($status === 'cancelled')
                $message = "Cancelado";
            if ($lastError && $status === 'completed')
                $message = "Finalizado com Erros";

            $data = [
                'total' => $batch->total_count,
                'processed' => (int) $batch->processed_count,
                'errors' => (int) $batch->error_count,
                'input_tokens' => (int) ($batch->input_tokens ?? 0),
                'output_tokens' => (int) ($batch->output_tokens ?? 0),
                'estimated_cost' => (float) ($batch->estimated_cost ?? 0),
                'status' => $status,
                'last_error' => $lastError,
                'message' => $message,
                'stats' => $batch->stats ?? [
                    'difficulty' => 0,
                    'explanation' => 0,
                    'subjects' => 0,
                    'topics' => 0,
                ],
            ];
        } else {
            // Prioriza o que está no Cache para o front-end acompanhar o progresso em tempo real
            $data['status'] = $batch->status;
            
            // Se o cache tem stats, mantemos o do cache (que é o agregado atual)
            // Se não tem, pega do banco
            if (!isset($data['stats']) || empty($data['stats'])) {
                $data['stats'] = $batch->stats;
            }
            
            // Atualiza os contadores atômicos vindos do banco para garantir precisão total
            $data['total'] = (int) $batch->total_count;
            $data['processed'] = (int) $batch->processed_count;
            $data['errors'] = (int) $batch->error_count;
            
            // Se o cache estiver vazio de tokens, pega do banco
            if (empty($data['input_tokens'])) {
                $data['input_tokens'] = (int) ($batch->input_tokens ?? 0);
                $data['output_tokens'] = (int) ($batch->output_tokens ?? 0);
                $data['estimated_cost'] = (float) ($batch->estimated_cost ?? 0);
            }
        }
        // --- FAIL-SAFE DE CONCLUSÃO ---
        // Se a soma de processados + erros atingiu o total, o lote ACABOU.
        // Forçamos o status concluído para o frontend mudar de tela, mesmo que o banco 
        // ainda esteja pendente de um update de status ou preso em race condition.
        if (($data['processed'] + $data['errors']) >= $data['total'] && $data['total'] > 0) {
            if ($data['status'] === 'processing') {
                $data['status'] = 'completed';
                // Se houver erros, a mensagem deve refletir isso
                $data['message'] = $data['errors'] > 0 ? "Finalizado com Erros" : "Concluído!";

                // Garantir que o banco seja atualizado para não ficar preso como processing
                $batch->update(['status' => 'completed']);
            }
        }

        // Enrich com dados de rastreamento de chunk e chave ativa
        $data['chunk_status'] = Cache::get("batch_chunk_status_{$batchId}");
        $data['active_key'] = Cache::get("batch_active_key_{$batchId}");

        return response()->json($data);
    }

    /**
     * Get list of API keys configured for triage capability.
     * Used by the frontend modal to display active key/model info (read-only).
     */
    public function activeKeys()
    {
        $keys = ApiKey::getKeysForCapability(ApiKey::CAPABILITY_TRIAGE);

        $result = $keys->map(function ($key) {
            return [
                'id' => $key->id,
                'name' => $key->vault?->nickname ?? "Chave #{$key->id}",
                'provider' => $key->effective_provider,
                'model' => $key->preferred_model,
                'status' => $key->status,
                'is_primary' => (bool) $key->is_primary,
            ];
        })->values();

        return response()->json(['keys' => $result]);
    }

    /**
     * Get batch history (paginated)
     */
    public function history(Request $request)
    {
        $batches = AiProcessingBatch::orderBy('created_at', 'desc')->paginate(15);
        return response()->json($batches);
    }

    /**
     * Get details (items) of a specific batch
     */
    public function details($batchId)
    {
        $batch = AiProcessingBatch::where('batch_id', $batchId)->firstOrFail();
        $items = AiBatchItem::with('question')->where('batch_id', $batchId)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'question_id' => $item->question_id,
                'statement' => $item->question ? \Illuminate\Support\Str::limit(strip_tags($item->question->statement), 100) : 'Questão excluída',
                'status' => $item->status,
                'before' => $item->snapshot_before,
                'after' => $item->snapshot_after,
            ];
        });
        // Summary of statuses for the top cards
        $itemsSummary = [
            'total' => $items->count(),
            'success' => $items->where('status', 'completed')->count(),
            'failed' => $items->where('status', 'failed')->count(),
            'reverted' => $items->where('status', 'reverted')->count(),
            'pending' => $items->where('status', 'pending')->count(),
        ];

        // List of errors for the specific error section
        $recentErrors = $items->where('status', 'failed')->map(function ($item) {
            return [
                'question_id' => $item['question_id'],
                'error_message' => 'Erro no processamento da questão.',
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'batch' => $batch,
            'items' => $items,
            'items_summary' => $itemsSummary,
            'recent_errors' => $recentErrors
        ]);
    }
}
