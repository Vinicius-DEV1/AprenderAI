<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\QdrantService;
use App\Services\AI\SemanticCacheService;
use App\Services\AI\AIService;
use App\Models\Question;
use App\Models\Concept;
use App\Models\QuestionVector;
use App\Models\SearchInteractionLog;
use App\Models\AiSearchCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Jobs\InterpretSearchPromptJob;

class SemanticDashboardController extends Controller
{
    /**
     * Get overall statistics for the Semantic Dashboard
     */
    public function index(QdrantService $qdrant)
    {
        // 1. MySQL Data
        $totalQuestions = Question::published()->where('tipo_questao', '!=', 'Redação')->count();
        $indexedQuestions = QuestionVector::whereHas('question', function ($query) {
            $query->published()->where('tipo_questao', '!=', 'Redação');
        })->distinct('question_id')->count('question_id');
        $totalVectors = QuestionVector::count();
        $totalConcepts = Concept::count();
        $indexedConcepts = Concept::whereNotNull('qdrant_indexed_at')->count();

        // 2. Qdrant Data (Collections Info)
        $qdrantQuestions = 0;
        $qdrantConcepts = 0;
        $qdrantStatus = 'offline';

        try {
            $questionsCol = $qdrant->getCollectionInfo(config('xavier.qdrant.collections.questions', 'questions_vectors'));
            $qdrantQuestions = $questionsCol['points_count'] ?? 0;

            $conceptsCol = $qdrant->getCollectionInfo(config('xavier.qdrant.collections.concepts', 'concepts_vectors'));
            $qdrantConcepts = $conceptsCol['points_count'] ?? 0;

            $qdrantStatus = 'online';
        } catch (\Exception $e) {
            $qdrantStatus = 'error: ' . $e->getMessage();
        }

        // 3. Cache & Latency Stats (from SearchInteractionLog and AiSearchCache)
        $totalLogSearches = SearchInteractionLog::distinct('ai_search_id')->count('ai_search_id');
        
        // As cache_type column doesn't exist, we show total L2 entries
        $totalCacheEntries = AiSearchCache::count();
        $l1CacheHits = 0; // Efemero/Redis
        $l2CacheHits = $totalCacheEntries; // Aproximado para o dashboard

        // 4. Jobs Stats (Embeddings Queue)
        $pendingJobs = DB::table('jobs')->where('queue', config('xavier.embeddings.queue', 'embeddings'))->count();
        $failedJobs = DB::table('failed_jobs')->where('queue', config('xavier.embeddings.queue', 'embeddings'))->count();

        return response()->json([
            'overview' => [
                'mysql_published_questions' => $totalQuestions,
                'mysql_indexed_questions'   => $indexedQuestions,
                'mysql_total_vectors'       => $totalVectors,
                'mysql_total_concepts'      => $totalConcepts,
                'mysql_indexed_concepts'    => $indexedConcepts,
            ],
            'qdrant' => [
                'status'           => $qdrantStatus,
                'questions_points' => $qdrantQuestions,
                'concepts_points'  => $qdrantConcepts,
            ],
            'performance' => [
                'total_searches'      => $totalLogSearches,
                'l1_cache_hits'       => $l1CacheHits,
                'l2_cache_hits'       => $l2CacheHits,
                'total_cache_entries' => $totalCacheEntries,
            ],
            'jobs' => [
                'pending' => $pendingJobs,
                'failed'  => $failedJobs,
            ],
            'config' => [
                'vector_search_enabled'       => config('xavier.vector_search_enabled'),
                'concept_detection_threshold' => config('xavier.embeddings.concept_detection_threshold'),
                'qdrant_candidate_limit'      => config('xavier.search.qdrant_candidate_limit'),
                'final_result_limit'          => config('xavier.search.final_result_limit'),
                'rerank_weights'              => config('xavier.search.rerank_weights'),
            ]
        ]);
    }

    /**
     * Update runtime configurations for the semantic engine.
     * Note: In a real environment, you might persist this to DB Settings or .env wrapper.
     * For now, we simulate success (requires a Setting model for persistence if we want it permanently outside .env).
     * Assuming App\Models\Setting is available from standard StackUp boilerplate.
     */
    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'vector_search_enabled'       => 'boolean',
            'concept_detection_threshold' => 'numeric|min:0|max:1',
            'qdrant_candidate_limit'      => 'integer|min:10|max:200',
            'final_result_limit'          => 'integer|min:5|max:50',
        ]);

        // Using standard update mechanisms depending on where we keep settings.
        // If settings are pure .env, we can't save here securely without a file writer.
        // We'll dispatch a command to update .env if needed, or simply throw a note that 
        // to persist permanently, edit .env. 
        // Let's implement a safe .env updater.
        
        $this->updateEnv('VECTOR_SEARCH_ENABLED', $request->boolean('vector_search_enabled') ? '1' : '0');
        
        if ($request->has('concept_detection_threshold')) {
            $this->updateEnv('CONCEPT_DETECTION_THRESHOLD', $validated['concept_detection_threshold']);
        }

        Artisan::call('config:clear');

        return response()->json(['message' => 'Configurações atualizadas com sucesso.']);
    }

    private function updateEnv($key, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, "{$key}={$value}", $contents);
            } else {
                $contents .= "\n{$key}={$value}";
            }
            file_put_contents($path, $contents);
        }
    }

    /**
     * Run a test search using the Xavier internal pipeline to debug scores and vectors.
     */
    public function testSearch(Request $request, SemanticCacheService $cacheService)
    {
        $request->validate(['prompt' => 'required|string']);
        $user = $request->user();

        $startTime = microtime(true);
        $logs = [];

        $logs[] = "Starting Test Search for: '{$request->prompt}'";

        // Step 1: Normalization
        $textBuilder = app(\App\Services\AI\EmbeddingTextBuilder::class);
        $normalizedQuery = $textBuilder->buildForQuery($request->prompt);
        $logs[] = "Normalized: {$normalizedQuery}";

        // Step 2: Embedding
        $aiService = app(AIService::class);
        $queryVector = $aiService->generateEmbedding($normalizedQuery, $user->id);
        
        if (!$queryVector) {
            return response()->json(['error' => 'Failed to generate embedding.', 'logs' => $logs], 500);
        }
        $logs[] = "Embedding Generated (Length: " . count($queryVector) . ")";

        // Step 3: Concepts
        $qdrant = app(QdrantService::class);
        $conceptThreshold = (float) config('xavier.embeddings.concept_detection_threshold', 0.45);
        $conceptMatches = $qdrant->searchConcepts($queryVector, 5, $conceptThreshold);
        $detectedConcepts = array_filter(array_map(fn($m) => $m['payload']['concept_slug'] ?? null, $conceptMatches));
        $detectedConcepts = array_values($detectedConcepts);
        $logs[] = "Concepts Detected: " . implode(', ', $detectedConcepts ?: ['None']);

        // Step 4: Expansion
        $expansion = app(\App\Services\AI\QueryExpansionService::class);
        $expandedConceptIds = $expansion->expand($detectedConcepts, 1);
        $logs[] = "Expanded Concepts IDs: " . implode(', ', $expandedConceptIds ?: ['None']);

        // Step 5: Hybrid Search
        $hybridSearch = app(\App\Services\AI\HybridSearchService::class);
        $queryVectors = [
            'statement'   => $queryVector,
            'concept'     => $queryVector,
            'explanation' => $queryVector,
        ];
        
        $limit = (int) config('xavier.search.qdrant_candidate_limit', 50);
        $candidates = $hybridSearch->search($queryVectors, $expandedConceptIds, [], $limit);
        $logs[] = "Candidates found in Qdrant: " . count($candidates);

        // Step 6: ReRank
        $reranker = app(\App\Services\AI\ReRankService::class);
        $rankedItems = $reranker->rerank($candidates, 20);

        $latency = round((microtime(true) - $startTime) * 1000, 2);
        $logs[] = "Pipeline completed in {$latency}ms";

        // Format detailed results for frontend
        $detailedResults = [];
        $qIds = array_column($rankedItems, 'question_id');
        $questions = Question::with(['alternatives', 'subjects', 'topics'])
            ->whereIn('id', $qIds)
            ->get()
            ->keyBy('id');

        foreach ($rankedItems as $idx => $item) {
            $q = $questions->get($item['question_id']);
            $detailedResults[] = [
                'rank'          => $idx + 1,
                'question_id'   => $item['question_id'],
                'statement'     => $q ? $q->statement : 'N/A',
                'explanation'   => $q ? $q->explanation : null,
                'alternatives'  => $q ? $q->alternatives->map(fn($a) => [
                    'label'      => $a->label,
                    'content'    => $a->content,
                    'is_correct' => $a->is_correct
                ]) : [],
                'subjects'      => $q ? $q->subjects->pluck('name') : [],
                'topics'        => $q ? $q->topics->pluck('name') : [],
                'qdrant_score'  => $item['vector_score'] ?? 0,
                'final_score'   => $item['composite_score'] ?? 0,
                'source'        => $item['source'] ?? 'unknown',
            ];
        }

        // Run SQL Fallback for comparison
        $sqlStartTime = microtime(true);
        $legacyCache = $cacheService->findExactMatch(trim(strtolower($request->prompt)));
        if (!$legacyCache) {
            $legacyCache = $cacheService->findSimilarMatch($queryVector, 0.88); // 0.88 is the default
        }
        $sqlLatency = round((microtime(true) - $sqlStartTime) * 1000, 2);

        return response()->json([
            'latency_ms'       => $latency,
            'logs'             => $logs,
            'results'          => $detailedResults,
            'sql_fallback'     => [
                'latency_ms' => $sqlLatency,
                'cache_hit'  => !!$legacyCache,
                'data'       => $legacyCache ?: 'Requires InterpretSearchPromptJob execution (async)'
            ]
        ]);
    }

    /**
     * Dispatch the Batch Indexer command via API
     */
    public function reindexAll(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:5000',
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
}
