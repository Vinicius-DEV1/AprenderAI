<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Services\AI\QdrantService;
use App\Models\Concept;

/**
 * Controller responsible for all administrative actions,
 * queue manipulations, resets and cache clearing for the AI Search.
 */
class SemanticActionController extends Controller
{
    /**
     * Dispatch the Batch Indexer command via API (Questions).
     * Enqueues the xavier:index-all command to vectorize published questions.
     */
    public function reindexAll(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100000',
            'force' => 'nullable|boolean'
        ]);

        $params = [];
        if ($request->has('limit')) {
            $params['--limit'] = (int) $validated['limit'];
        }

        if ($request->boolean('force')) {
            $params['--force'] = true;
        }

        // Don't wait for completion, it can take hours
        Artisan::queue('xavier:index-all', $params);

        return response()->json([
            'message' => 'Job xavier:index-all enfileirado com sucesso.' . ($request->has('limit') ? " Limite: {$validated['limit']} questões." : "")
        ]);
    }

    /**
     * Dispatch the Concept Indexer command via API.
     * Enqueues the xavier:index-concepts command to vectorize concepts
     * in the concepts_vectors Qdrant collection.
     */
    public function reindexConcepts(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100000',
            'force' => 'nullable|boolean'
        ]);

        $params = [];
        if ($request->has('limit')) {
            $params['--limit'] = (int) $validated['limit'];
        }

        if ($request->boolean('force')) {
            $params['--force'] = true;
        }

        Artisan::queue('xavier:index-concepts', $params);

        return response()->json([
            'message' => 'Job xavier:index-concepts enfileirado com sucesso.' . ($request->has('limit') ? " Limite: {$validated['limit']} conceitos." : "")
        ]);
    }

    /**
     * Clear all semantic search caches and interaction logs to force fresh processing.
     */
    public function clearCache()
    {
        try {
            // Truncate cache table
            \Illuminate\Support\Facades\DB::table('ai_search_cache')->truncate();
            
            // Clear application cache
            \Illuminate\Support\Facades\Artisan::call('cache:clear');

            // Clear AI Key Blacklist (Wake up sleeping keys)
            \App\Models\ApiKey::clearBlacklist();

            // Clear Congestion History (Clean up Waiting Room UI)
            app(\App\Services\AI\AIService::class)->clearCongestionList();

            return response()->json(['message' => 'Cache de busca semântica limpo com sucesso. Todas as próximas buscas serão processadas do zero pelo Xavier.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha ao limpar cache: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Clear all pending jobs in the embeddings queue.
     */
    public function clearQueue()
    {
        $queue = config('xavier.embeddings.queue', 'embeddings');

        try {
            $deleted = DB::table('jobs')->where('queue', $queue)->delete();
            $deletedFailed = DB::table('failed_jobs')->where('queue', $queue)->delete();
            // Also clear the 'concepts' queue if it's separate
            $deletedConcepts = DB::table('jobs')->where('queue', 'concepts')->delete();
            $deletedFailedConcepts = DB::table('failed_jobs')->where('queue', 'concepts')->delete();

            Log::info("[Xavier][Queue] Queue '{$queue}' cleared by admin. Jobs: {$deleted}, Failed: {$deletedFailed}. Concepts Jobs: {$deletedConcepts}, Concepts Failed: {$deletedFailedConcepts}.");

            return response()->json([
                'message' => "Fila '{$queue}' e 'concepts' limpas com sucesso. Jobs removidos: {$deleted} (embeddings), {$deletedConcepts} (concepts). Falhas removidas: {$deletedFailed} (embeddings), {$deletedFailedConcepts} (concepts)."
            ]);
        } catch (\Exception $e) {
            Log::error("[Xavier][Queue] Failed to clear queue: " . $e->getMessage());
            return response()->json(['error' => 'Falha ao limpar fila.'], 500);
        }
    }

    /**
     * Clear all congestion tracking in Redis.
     */
    public function clearCongestion()
    {
        try {
            app(\App\Services\AI\AIService::class)->clearCongestionList();
            return response()->json(['message' => 'Lista de congestionamento limpa com sucesso. Todos os jobs foram acordados para retentar.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha ao limpar lista de congestionamento.'], 500);
        }
    }

    /**
     * Complete reset of semantic intelligence.
     * Warning: This deletes ALL Qdrant collections and forces a total re-indexing.
     */
    public function resetEmbeddings(Request $request, QdrantService $qdrant)
    {
        // Security: demands explicit confirmation
        $request->validate([
            'confirm' => 'required|string|in:RESET',
        ]);

        try {
            Log::warning('[Xavier][Reset] Full embedding reset initiated by admin.');

            // 1. Delete Qdrant collections
            $questionsCol = config('xavier.qdrant.collections.questions', 'questions_vectors');
            $conceptsCol  = config('xavier.qdrant.collections.concepts', 'concepts_vectors');

            $qdrant->deleteCollection($questionsCol);
            $qdrant->deleteCollection($conceptsCol);
            Log::info('[Xavier][Reset] Qdrant collections deleted.');

            // 2. Recreate empty collections with correct schema
            $qdrant->ensureQuestionsCollection();
            $qdrant->ensureConceptsCollection();
            Log::info('[Xavier][Reset] Qdrant collections recreated (empty).');

            // 3. Truncate MySQL vectors table
            DB::table('question_vectors')->truncate();
            Log::info('[Xavier][Reset] question_vectors table truncated.');

            // 4. Truncate L2 cache
            DB::table('ai_search_cache')->truncate();
            Log::info('[Xavier][Reset] ai_search_cache table truncated.');

            // 5. Reset indexing timestamps
            Concept::query()->update(['qdrant_indexed_at' => null]);
            \App\Models\Subject::query()->update(['qdrant_indexed_at' => null]);
            \App\Models\Topic::query()->update(['qdrant_indexed_at' => null]);
            Log::info('[Xavier][Reset] Entity indexing timestamps cleared.');

            // 6. Clear all pending jobs in embeddings queues only
            try {
                $aiQueues = [
                    config('xavier.embeddings.queue', 'embeddings'),
                    config('xavier.embeddings.batch_queue', 'embeddings'),
                    'concepts'
                ];
                DB::table('jobs')->whereIn('queue', array_unique($aiQueues))->delete();
            } catch (\Exception $e) {
                Log::error("[Xavier][Reset] Failed to clear jobs: " . $e->getMessage());
            }

            // 7. Clear application cache
            Artisan::call('cache:clear');

            return response()->json([
                'message' => 'Reset completo executado com sucesso. Coleções Qdrant recriadas vazias. '
                           . 'Execute "Indexar Questões" + "Indexar Conceitos" para reconstruir os vetores.'
            ]);
        } catch (\Exception $e) {
            Log::error('[Xavier][Reset] Failed: ' . $e->getMessage());
            return response()->json(['error' => 'Falha no reset: ' . $e->getMessage()], 500);
        }
    }
}
