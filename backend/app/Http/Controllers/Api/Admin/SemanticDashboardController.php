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
use App\Models\AiSearchRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


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
        $totalSubjects = \App\Models\Subject::count();
        $indexedSubjects = \App\Models\Subject::whereNotNull('qdrant_indexed_at')->count();
        
        $totalTopics = \App\Models\Topic::count();
        $indexedTopics = \App\Models\Topic::whereNotNull('qdrant_indexed_at')->count();

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

        // 4. Jobs Stats (Embeddings Queues)
        $embeddingQueues = [
            config('xavier.embeddings.queue', 'embeddings'),
            config('xavier.search_embeddings.queue', 'search_embeddings')
        ];
        
        $pendingJobs = DB::table('jobs')->whereIn('queue', $embeddingQueues)->count();
        $failedJobs = DB::table('failed_jobs')->whereIn('queue', $embeddingQueues)->count();

        // 5. Detailed Failed Jobs
        $failedJobsDetails = DB::table('failed_jobs')
            ->whereIn('queue', $embeddingQueues)
            ->orderBy('failed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($job) {
                // Extract just the first line of the exception for readability
                $exceptionLines = explode("\n", $job->exception);
                return [
                    'id' => $job->id,
                    'failed_at' => $job->failed_at,
                    'payload' => json_decode($job->payload, true)['displayName'] ?? 'Unknown Job',
                    'error_preview' => $exceptionLines[0] ?? 'Unknown Error',
                ];
            });

        // 6. Recent Searches (from AiSearchRequest which logs user prompts)
        $recentSearches = \App\Models\AiSearchRequest::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user_name' => $req->user ? $req->user->name : 'System/Guest',
                    'prompt' => $req->prompt,
                    'status' => $req->status,
                    'created_at' => \Carbon\Carbon::parse($req->created_at)->format('d/m/Y H:i:s'),
                    'similarity_threshold' => $req->similarity_threshold,
                ];
            });

        // 7. Analytics (absorvidos da antiga Xavier Insights page)
        // Taxa de sucesso das buscas + termos mais buscados + gráfico 7 dias
        $totalAiRequests   = AiSearchRequest::count();
        $successAiRequests = AiSearchRequest::where('status', 'completed')->count();
        $successRate       = $totalAiRequests > 0
            ? round(($successAiRequests / $totalAiRequests) * 100, 1)
            : 0;

        $topPrompts = AiSearchRequest::select('prompt', DB::raw('count(*) as total'))
            ->groupBy('prompt')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $chartData = AiSearchRequest::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success"),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 8. Checar versão do Índice do Qdrant (Garante que as coleções existem antes)
        $qdrant->ensureQuestionsCollection();
        $qdrant->ensureConceptsCollection();
        
        $currentPipeline = config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser');
        $questionsVersionCheck = $qdrant->checkIndexVersion(config('xavier.qdrant.collections.questions'), $currentPipeline);
        $conceptsVersionCheck  = $qdrant->checkIndexVersion(config('xavier.qdrant.collections.concepts'), $currentPipeline);

        return response()->json([
            'overview' => [
                'mysql_published_questions' => $totalQuestions,
                'mysql_indexed_questions'   => $indexedQuestions,
                'mysql_total_vectors'       => $totalVectors,
                'mysql_total_subjects'      => $totalSubjects,
                'mysql_indexed_subjects'    => $indexedSubjects,
                'mysql_total_topics'        => $totalTopics,
                'mysql_indexed_topics'      => $indexedTopics,
            ],
            'qdrant' => [
                'status'           => $qdrantStatus,
                'questions_points' => $qdrantQuestions,
                'concepts_points'  => $qdrantConcepts,
                'index_version_status' => [
                    'expected'  => $currentPipeline,
                    'questions' => $questionsVersionCheck,
                    'concepts'  => $conceptsVersionCheck,
                ]
            ],
            'top_concepts' => \App\Models\Subject::whereNotNull('qdrant_indexed_at')
                ->withCount('questions')
                ->orderByDesc('questions_count')
                ->limit(20)
                ->get(['id', 'name'])
                ->map(fn($s) => ['name' => $s->name, 'count' => $s->questions_count, 'type' => 'subject'])
                ->concat(
                    \App\Models\Topic::whereNotNull('qdrant_indexed_at')
                        ->withCount('questions')
                        ->orderByDesc('questions_count')
                        ->limit(30)
                        ->get(['id', 'name'])
                        ->map(fn($t) => ['name' => $t->name, 'count' => $t->questions_count, 'type' => 'topic'])
                )
                ->sortByDesc('count')
                ->values(),
            'performance' => [
                'total_searches'      => $totalLogSearches,
                'l1_cache_hits'       => $l1CacheHits,
                'l2_cache_hits'       => $l2CacheHits,
                'total_cache_entries' => $totalCacheEntries,
            ],
            'jobs' => [
                'pending' => $pendingJobs,
                'failed'  => $failedJobs,
                'recent_failures' => $failedJobsDetails,
                'waiting_list'    => app(\App\Services\AI\AIService::class)->getCongestionList(),
            ],
            'analytics' => [
                'total_ai_requests' => $totalAiRequests,
                'success_rate'      => $successRate,
                'top_prompts'       => $topPrompts,
                'chart_data'        => $chartData,
            ],
            'recent_searches' => $recentSearches,
            'config' => [
                'vector_search_enabled'       => \App\Models\Configuration::get('xavier_vector_search_enabled', config('xavier.vector_search_enabled')),
                'concept_detection_threshold' => \App\Models\Configuration::get('xavier_concept_detection_threshold', config('xavier.embeddings.concept_detection_threshold')),
                'search_threshold'           => \App\Models\Configuration::get('xavier_search_threshold', config('xavier.embeddings.search_threshold')),
                'qdrant_candidate_limit'      => \App\Models\Configuration::get('xavier_qdrant_candidate_limit', config('xavier.search.qdrant_candidate_limit')),
                'final_result_limit'          => \App\Models\Configuration::get('xavier_final_result_limit', config('xavier.search.final_result_limit')),
                'rerank_weights'              => json_decode(\App\Models\Configuration::get('xavier_rerank_weights', json_encode(config('xavier.search.rerank_weights'))), true),
                'pipeline_version'            => config('xavier.embeddings.pipeline_version'),
            ]
        ]);
    }

    /**
     * Update runtime configurations for the semantic engine.
     * Persists settings to the database configurations table.
     */
    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'vector_search_enabled'       => 'boolean',
            'concept_detection_threshold' => 'numeric|min:0',
            'search_threshold'           => 'numeric|min:0',
            'qdrant_candidate_limit'      => 'integer|min:10|max:300',
            'final_result_limit'          => 'integer|min:5|max:150',
            'rerank_weights'              => 'nullable|array',
            'rerank_weights.vector'       => 'numeric|min:0|max:1',
            'rerank_weights.popularity'   => 'numeric|min:0|max:1',
            'rerank_weights.quality'      => 'numeric|min:0|max:1',
            'rerank_weights.recency'      => 'numeric|min:0|max:1',
        ]);

        if ($request->has('vector_search_enabled')) {
            \App\Models\Configuration::set('xavier_vector_search_enabled', $request->boolean('vector_search_enabled') ? '1' : '0');
        }

        if ($request->has('concept_detection_threshold')) {
            \App\Models\Configuration::set('xavier_concept_detection_threshold', (string) $validated['concept_detection_threshold']);
        }

        if ($request->has('search_threshold')) {
            \App\Models\Configuration::set('xavier_search_threshold', (string) $validated['search_threshold']);
        }

        if ($request->has('qdrant_candidate_limit')) {
            \App\Models\Configuration::set('xavier_qdrant_candidate_limit', (string) $validated['qdrant_candidate_limit']);
        }

        if ($request->has('final_result_limit')) {
            \App\Models\Configuration::set('xavier_final_result_limit', (string) $validated['final_result_limit']);
        }

        if ($request->has('rerank_weights')) {
            \App\Models\Configuration::set('xavier_rerank_weights', json_encode($validated['rerank_weights']));
        }

        // Clear config cache to ensure changes take effect immediately
        Artisan::call('config:clear');

        return response()->json(['message' => 'Configurações atualizadas no banco de dados com sucesso.']);
    }

    /**
     * Run a test search using the Xavier internal pipeline to debug scores and vectors.
     *
     * IMPORTANTE: Este método espelha EXATAMENTE o pipeline real do
     * QuestionController::aiSearch para que os resultados do Debug Console
     * sejam idênticos aos que o usuário final veria.
     *
     * Pipeline:
     *   1. Normaliza a query via EmbeddingTextBuilder::buildForQuery
     *   2. Gera embedding genérico (para concept detection)
     *   3. Detecta conceitos no Qdrant via embedding genérico
     *   4. Expande conceitos via Knowledge Graph
     *   5. Gera 3 embeddings format-aligned (statement/concept/explanation)
     *   6. Busca híbrida no Qdrant com os 3 vetores distintos
     *   7. Re-ranking composto (vector + popularidade + qualidade + recência)
     */
    public function testSearch(Request $request, SemanticCacheService $cacheService)
    {
        $request->validate(['prompt' => 'required|string']);
        $user = $request->user();

        $startTime = microtime(true);
        $logs = [];

        $logs[] = "Starting Test Search for: '{$request->prompt}'";

        // ── Step 1: Normalização ──────────────────────────────────────────────
        $textBuilder = app(\App\Services\AI\EmbeddingTextBuilder::class);
        $normalizedQuery = $textBuilder->buildForQuery($request->prompt);
        $logs[] = "Normalized: {$normalizedQuery}";

        // ── Step 1b: Lexical Analysis (Xavier 2.0) ───────────────────────────
        $lexical = app(\App\Services\AI\QueryLexicalAnalyser::class);
        $logs[] = "LEXICAL START: Processing prompt '{$request->prompt}'";
        $analysis = $lexical->analyse($request->prompt);
        $positivePrompt = implode(' ', $analysis['positive_terms']);
        $negativePrompt = implode(' ', $analysis['negative_terms']);
        $logs[] = "LEXICAL DONE: Positive tokens: ['" . implode("', '", $analysis['positive_terms']) . "'], Negative: ['" . implode("', '", $analysis['negative_terms']) . "']";
        
        if ($analysis['difficulty']) $logs[] = "FILTER DETECTED: Difficulty is '{$analysis['difficulty']}'";
        if (!empty($analysis['years'])) $logs[] = "FILTER DETECTED: Date filter '{$analysis['year_operator']} {$analysis['years'][0]}'";
        if (!empty($analysis['organizations'])) $logs[] = "FILTER DETECTED: Organizations: [" . implode(", ", $analysis['organizations']) . "]";
        if (!empty($analysis['institutions'])) $logs[] = "FILTER DETECTED: Institutions: [" . implode(", ", $analysis['institutions']) . "]";

        $logs[] = "Xavier 2.0 Lexical Analysis:";
        $logs[] = " - Positive: '{$positivePrompt}'";
        if (!empty($negativePrompt)) {
            $logs[] = " - Negative: '{$negativePrompt}' (Exclusion Mode)";
        }

        if ($analysis['difficulty']) {
            $logs[] = "DIFFICULTY DETECTED: " . strtoupper($analysis['difficulty']);
        }

        if (!empty($analysis['years'])) {
            $logs[] = "TEMPORAL OPERATOR: " . $analysis['year_operator'] . " " . $analysis['years'][0];
        }

        if (!empty($analysis['organizations'])) {
            foreach ($analysis['organizations'] as $org) {
                $logs[] = "ORG DETECTED: " . strtoupper($org);
            }
        }

        if (!empty($analysis['institutions'])) {
            foreach ($analysis['institutions'] as $inst) {
                $logs[] = "INST DETECTED: " . strtoupper($inst);
            }
        }

        // ── Step 2: Gerar Embedding Genérico ──────────────────────────────────
        $textBuilder = app(\App\Services\AI\EmbeddingTextBuilder::class);
        $aiService = app(\App\Services\AI\AIService::class);
        
        // Usamos o prompt POSITIVO para a busca semântica principal.
        try {
            $queryVector = $aiService->generateEmbedding($positivePrompt, $user->id, 'RETRIEVAL_QUERY');
            if (!$queryVector) {
                throw new \Exception("Vetor retornado vazio.");
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Falha Crítica no Embedding: ' . $e->getMessage(),
                'logs' => array_merge($logs, ["ERROR: " . $e->getMessage(), "Search aborted."])
            ], 200); // 200 so the frontend shows the logs
        }
        $logs[] = "Generic Query Embedding generated (using positive prompt). (Length: " . count($queryVector) . ", taskType: RETRIEVAL_QUERY)";

        // ── Step 3: Concept Detection ─────────────────────────────────────────
        $qdrant = app(\App\Services\AI\QdrantService::class);
        $conceptThreshold = (float) \App\Models\Configuration::get(
            'xavier_concept_detection_threshold',
            config('xavier.embeddings.concept_detection_threshold', 0.75)
        );
        $conceptMatches = $qdrant->searchConcepts($queryVector, 5, $conceptThreshold);
        
        $detectedConcepts = [];
        $extractedSubjects = [];
        $extractedTopics = [];
        $extractedOrgs = [];
        $extractedInsts = [];
        $extractedType = null;

        foreach ($conceptMatches as $match) {
            $payload = $match['payload'] ?? [];
            $type = $payload['entity_type'] ?? 'concept';
            
            if ($type === 'concept' && isset($payload['concept_slug'])) {
                $detectedConcepts[] = $payload['concept_slug'];
            } elseif ($type === 'subject' && isset($payload['subject_id'])) {
                $extractedSubjects[] = $payload['subject_id'];
                $logs[] = "INTENT DETECTED: Subject #{$payload['subject_id']} ({$payload['name']})";
            } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                $extractedTopics[] = $payload['topic_id'];
                $logs[] = "INTENT DETECTED: Topic #{$payload['topic_id']} ({$payload['name']})";
            } elseif ($type === 'organization' && isset($payload['organization'])) {
                $extractedOrgs[] = $payload['organization'];
                $logs[] = "INTENT DETECTED: Organization '{$payload['organization']}'";
            } elseif ($type === 'institution' && isset($payload['institution'])) {
                $extractedInsts[] = $payload['institution'];
                $logs[] = "INTENT DETECTED: Institution '{$payload['institution']}'";
            }
        }

        // ── Step 3.1: Mesclar Detecções Léxicas (Fase 4) ──────────────────────
        if (!empty($analysis['organizations'])) {
            $extractedOrgs = array_merge($extractedOrgs, $analysis['organizations']);
        }
        if (!empty($analysis['institutions'])) {
            $extractedInsts = array_merge($extractedInsts, $analysis['institutions']);
        }

        $extractedOrgs  = array_values(array_unique($extractedOrgs));
        $extractedInsts = array_values(array_unique($extractedInsts));

        // ── Step 3.5: Detecção de Tipo (ENEM/Concurso) via Keywords ───────────
        // Usamos o prompt POSITIVO para detecção de tipo
        $lowerPrompt = mb_strtolower($positivePrompt);
        if (str_contains($lowerPrompt, 'concurso')) {
            $extractedType = 'concurso';
        } elseif (str_contains($lowerPrompt, 'enem')) {
            $extractedType = 'enem';
        }

        // ── Step 3.6: Detecção de Intenções Negativas (Excluir) ───────────────
        $excludedOrgs = [];
        $excludedInsts = [];
        $excludedSubjectIds = [];
        $excludedTopicIds = [];
        $excludedType = null;
        if (!empty($negativePrompt)) {
            try {
                $negVector = $aiService->generateEmbedding($negativePrompt, $user->id, 'RETRIEVAL_QUERY');
                $negMatches = $qdrant->searchConcepts($negVector, 3, 0.80);
                foreach ($negMatches as $match) {
                    $payload = $match['payload'] ?? [];
                    $type = $payload['entity_type'] ?? '';
                    if ($type === 'organization' && isset($payload['organization'])) {
                        $excludedOrgs[] = $payload['organization'];
                        $logs[] = "EXCLUSION DETECTED: Organization '{$payload['organization']}'";
                    } elseif ($type === 'institution' && isset($payload['institution'])) {
                        $excludedInsts[] = $payload['institution'];
                        $logs[] = "EXCLUSION DETECTED: Institution '{$payload['institution']}'";
                    } elseif ($type === 'subject' && isset($payload['subject_id'])) {
                        $excludedSubjectIds[] = $payload['subject_id'];
                        $logs[] = "EXCLUSION DETECTED: Subject #{$payload['subject_id']} ({$payload['name']})";
                    } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                        $excludedTopicIds[] = $payload['topic_id'];
                        $logs[] = "EXCLUSION DETECTED: Topic #{$payload['topic_id']} ({$payload['name']})";
                    }
                }
            } catch (\Exception $e) {
                $logs[] = "WARNING: Negative embedding failed: " . $e->getMessage() . ". Exclusion filters might be incomplete.";
            }
            $lowerNeg = mb_strtolower($negativePrompt);
            if (str_contains($lowerNeg, 'concurso')) $excludedType = 'concurso';
            if (str_contains($lowerNeg, 'enem')) $excludedType = 'enem';
        }

        $detectedConcepts = array_values(array_unique($detectedConcepts));
        $logs[] = "Concepts Detected: " . implode(', ', $detectedConcepts ?: ['None']);

        // ── Step 4: Query Expansion via Knowledge Graph ───────────────────────
        $expansion = app(\App\Services\AI\QueryExpansionService::class);
        $expandedConceptIds = !empty($detectedConcepts) ? $expansion->expand($detectedConcepts, 1) : [];
        $logs[] = "Expanded Concepts IDs: " . implode(', ', $expandedConceptIds ?: ['None']);

        // ── Step 5: Gerar 3 embeddings format-aligned ─────────────────────────
        // Cada named vector no Qdrant foi indexado com formato diferente.
        // Para maximizar a similaridade de cosseno, geramos um embedding
        // alinhado para cada named vector.
        $statementQueryText   = $textBuilder->buildStatementQuery($positivePrompt);
        $conceptQueryText     = $textBuilder->buildConceptQuery($positivePrompt);
        $explanationQueryText = $textBuilder->buildExplanationQuery($positivePrompt);

        $statementVector = null;
        $conceptVectorQ = null;
        $explanationVector = null;

        try {
            $statementVector   = $aiService->generateEmbedding($statementQueryText,   $user->id, 'RETRIEVAL_QUERY');
            $conceptVectorQ    = $aiService->generateEmbedding($conceptQueryText,     $user->id, 'RETRIEVAL_QUERY');
            $explanationVector = $aiService->generateEmbedding($explanationQueryText, $user->id, 'RETRIEVAL_QUERY');
        } catch (\Exception $e) {
            $logs[] = "WARNING: Aligned embeddings partially failed: " . $e->getMessage() . ". Falling back to generic vector.";
        }

        // Fallback: se algum dos 3 falhar, usa o genérico
        $queryVectors = [
            'statement'   => $statementVector   ?? $queryVector,
            'concept'     => $conceptVectorQ    ?? $queryVector,
            'explanation' => $explanationVector  ?? $queryVector,
        ];

        $logs[] = "Format-aligned embeddings: statement=" . ($statementVector ? 'OK' : 'FALLBACK')
                . ", concept=" . ($conceptVectorQ ? 'OK' : 'FALLBACK')
                . ", explanation=" . ($explanationVector ? 'OK' : 'FALLBACK');

        // ── Step 6: Hybrid Search ─────────────────────────────────────────────
        $hybridSearch = app(\App\Services\AI\HybridSearchService::class);
        $limit = (int) \App\Models\Configuration::get('xavier_qdrant_candidate_limit', config('xavier.search.qdrant_candidate_limit', 200));
        
        // Ensure SQL fallback actually filters by the text if Qdrant is empty
        $sqlFilters = ['keyword' => $request->prompt];
        $intentFilters = []; // Initialize intentFilters here
        if ($extractedType) {
            $intentFilters['type'] = [$extractedType];
        }
        if (!empty($extractedOrgs)) {
            $intentFilters['organization'] = $extractedOrgs;
        }
        if (!empty($extractedInsts)) {
            $intentFilters['institution'] = $extractedInsts;
        }
        // Intent Detection Logging
        $intentService = app(\App\Services\AI\IntentDetectionService::class);
        if (empty($analysis['positive_terms'])) {
            $logs[] = "INTENT SKIP: No positive tokens found. Using raw prompt for semantic search.";
            $intentFilters = [];
        } else {
            $intentPrompt = implode(' ', $analysis['positive_terms']);
            $logs[] = "INTENT START: Detecting intent for '{$intentPrompt}'...";
            $intentFilters = $intentService->detectIntent($intentPrompt);
            $logs[] = "INTENT DONE: Found " . count($intentFilters['subject_id'] ?? []) . " subjects, " . count($intentFilters['topic_id'] ?? []) . " topics, " . count($intentFilters['organization'] ?? []) . " orgs.";
        }

        if ($extractedType) {
            $sqlFilters['type'] = $extractedType;
        }
        if (!empty($extractedOrgs)) {
            $sqlFilters['organization'] = $extractedOrgs;
        }
        if (!empty($extractedInsts)) {
            $sqlFilters['institution'] = $extractedInsts;
        }

        // Aplicar filtros de exclusão
        if (!empty($excludedOrgs)) {
            $sqlFilters['exclude_organization'] = $excludedOrgs;
        }
        if (!empty($excludedInsts)) {
            $sqlFilters['exclude_institution'] = $excludedInsts;
        }
        if ($excludedType) {
            $sqlFilters['exclude_type'] = $excludedType;
        }
        if (!empty($excludedSubjectIds)) {
            $sqlFilters['exclude_subject_id'] = $excludedSubjectIds;
        }
        if (!empty($excludedTopicIds)) {
            $sqlFilters['exclude_topic_id'] = $excludedTopicIds;
        }
        if (!empty($excludedOrgs)) {
            $sqlFilters['exclude_org'] = $excludedOrgs;
        }
        if (!empty($excludedInsts)) {
            $sqlFilters['exclude_inst'] = $excludedInsts;
        }

        // Xavier 2.0 Fase 3: Filtros de Ano e Dificuldade
        if ($analysis['difficulty']) {
            $sqlFilters['difficulty'] = $analysis['difficulty'];
        }
        if (!empty($analysis['years'])) {
            $sqlFilters['year'] = $analysis['years'][0];
            $sqlFilters['year_operator'] = $analysis['year_operator'];
        }
        
        $logs[] = "SEARCH START: Requesting hybrid results (Limit: {$limit})";
        $candidates = $hybridSearch->search($queryVectors, $expandedConceptIds, $sqlFilters, $limit);
        $logs[] = "SEARCH DONE: Found " . count($candidates) . " candidates.";
        
        $qdrantCount = collect($candidates)->where('source', 'qdrant')->count();
        $sqlCount    = collect($candidates)->where('source', 'sql_fallback')->count();
        $logs[] = "SEARCH BREAKDOWN: Qdrant: {$qdrantCount}, SQL Fallback: {$sqlCount}";

        // ── Step 7: ReRank ────────────────────────────────────────────────────
        $reranker = app(\App\Services\AI\ReRankService::class);
        $finalLimit = (int) \App\Models\Configuration::get('xavier_final_result_limit', config('xavier.search.final_result_limit', 100));

        }
        if (!empty($extractedTopics)) {
            $intentFilters['topic_id'] = $extractedTopics;
        }

        $rankedItems = $reranker->rerank($candidates, $finalLimit, $intentFilters);

        $latency = round((microtime(true) - $startTime) * 1000, 2);
        $logs[] = "Pipeline completed in {$latency}ms (4 embeddings total)";

        // ── Format detailed results ───────────────────────────────────────────
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
                'score_details' => $item['details'] ?? [],
            ];
        }

        // ── SQL Fallback comparison ───────────────────────────────────────────
        $sqlStartTime = microtime(true);
        $legacyCache = $cacheService->findExactMatch(trim(strtolower($request->prompt)));
        if (!$legacyCache) {
            $legacyCache = $cacheService->findSimilarMatch($queryVector, 0.88);
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
     * Dispatch the Batch Indexer command via API (Questions).
     * Enfileira o comando xavier:index-all para vetorizar questões publicadas.
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
     * Enfileira o comando xavier:index-concepts para vetorizar conceitos
     * na coleção concepts_vectors do Qdrant.
     *
     * Essencial quando:
     *   - Os IDs dos pontos mudaram (ex: migração CRC32 → SHA-256)
     *   - Novos conceitos foram extraídos pelo ConceptExtractionJob
     *   - O formato de embedding mudou e precisa re-indexar
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

            Log::info("[Xavier][Queue] Queue '{$queue}' cleared by admin. Jobs: {$deleted}, Failed: {$deletedFailed}.");

            return response()->json([
                'message' => "Fila '{$queue}' limpa com sucesso. Jobs removidos: {$deleted}. Falhas removidas: {$deletedFailed}."
            ]);
        } catch (\Exception $e) {
            Log::error("[Xavier][Queue] Failed to clear queue: " . $e->getMessage());
            return response()->json(['error' => 'Falha ao limpar fila.'], 500);
        }
    }

    /**
     * Reset complete de toda a inteligência semântica.
     * Cuidado: Isso apaga TODAS as coleções do Qdrant e obriga a re-indexar tudo do zero.
     *
     * ⚠️ AÇÃO DESTRUTIVA. Esta operação:
     *   1. Deleta as coleções questions_vectors e concepts_vectors do Qdrant
     *   2. Recria ambas as coleções (vazias)
     *   3. Trunca a tabela question_vectors MySQL
     *   4. Trunca a tabela ai_search_cache (cache L2)
     *   5. Reseta qdrant_indexed_at em todos os conceitos
     *   6. Limpa o cache da aplicação
     *
     * Após esta operação: É NECESSÁRIO rodar Indexar Questões + Indexar Conceitos
     * para reconstruir os vetores do zero.
     *
     * Requer parâmetro `confirm=RESET` no body como segurança extra.
     */
    public function resetEmbeddings(Request $request, QdrantService $qdrant)
    {
        // Segurança: exige confirmação explícita
        $request->validate([
            'confirm' => 'required|string|in:RESET',
        ]);

        try {
            Log::warning('[Xavier][Reset] Full embedding reset initiated by admin.');

            // 1. Deletar coleções do Qdrant
            $questionsCol = config('xavier.qdrant.collections.questions', 'questions_vectors');
            $conceptsCol  = config('xavier.qdrant.collections.concepts', 'concepts_vectors');

            $qdrant->deleteCollection($questionsCol);
            $qdrant->deleteCollection($conceptsCol);
            Log::info('[Xavier][Reset] Qdrant collections deleted.');

            // 2. Recriar coleções vazias com a estrutura correta
            $qdrant->ensureQuestionsCollection();
            $qdrant->ensureConceptsCollection();
            Log::info('[Xavier][Reset] Qdrant collections recreated (empty).');

            // 3. Truncar tabela de vetores MySQL
            DB::table('question_vectors')->truncate();
            Log::info('[Xavier][Reset] question_vectors table truncated.');

            // 4. Truncar cache L2
            DB::table('ai_search_cache')->truncate();
            Log::info('[Xavier][Reset] ai_search_cache table truncated.');

            // 5. Resetar timestamps de indexação
            Concept::query()->update(['qdrant_indexed_at' => null]);
            \App\Models\Subject::query()->update(['qdrant_indexed_at' => null]);
            \App\Models\Topic::query()->update(['qdrant_indexed_at' => null]);
            Log::info('[Xavier][Reset] Entity indexing timestamps cleared.');

            // 6. Limpar cache da aplicação
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
