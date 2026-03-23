<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSearchRequest;
use App\Models\SearchInteractionLog;
use App\Jobs\RunVectorSearchJob;
use App\Services\AI\AIService;
use App\Services\AI\EmbeddingTextBuilder;
use App\Services\AI\HybridSearchService;
use App\Services\AI\QueryExpansionService;
use App\Services\AI\QdrantService;
use App\Services\AI\ReRankService;
use App\Services\AI\SemanticCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Controller responsible for the Xavier AI Semantic Search pipeline.
 * Extracted from QuestionController to modularize AI-related logic.
 */
class XavierSearchController extends Controller
{
    /**
     * Submit a prompt to the AI Search (Busca Assistida)
     *
     * Implements the full 9-step Xavier Semantic Search pipeline when
     * VECTOR_SEARCH_ENABLED=true. Falls back to the legacy SQL pipeline
     * when the flag is false or Qdrant is unavailable.
     */
    public function aiSearch(Request $request)
    {
        $request->validate(['prompt' => 'required|string|max:500']);

        $user       = $request->user();
        $cacheService = app(SemanticCacheService::class);

        // ─── LEGACY PATH (feature flag off) ──────────────────────────────────
        $isEnabled = \App\Models\Configuration::get('xavier_vector_search_enabled', config('xavier.vector_search_enabled', false));
        if (is_string($isEnabled)) {
            $isEnabled = filter_var($isEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        if (!$isEnabled) {
            return $this->legacyAiSearch($request, $user, $cacheService);
        }

        // ─── VECTOR SEARCH PIPELINE (9 steps) ────────────────────────────────
        Log::info('[Xavier][Search] Starting vector search pipeline.', ['prompt' => $request->prompt]);

        $aiService     = app(AIService::class);
        $textBuilder   = app(EmbeddingTextBuilder::class);
        $qdrant        = app(QdrantService::class);
        $expansion     = app(QueryExpansionService::class);

        // ── Step 1b: Lexical Analysis ────────────────────────────────────────
        $lexical   = app(\App\Services\AI\QueryLexicalAnalyser::class);
        $analysis  = $lexical->analyse($request->prompt);
        $positivePrompt = implode(' ', $analysis['positive_terms']);
        $negativePrompt = implode(' ', $analysis['negative_terms']);

        // ── Step 2: Query Normalization ──────────────────────────────────────
        $normalizedQuery = $textBuilder->buildForQuery($positivePrompt);
        Log::info('[Xavier][Search] Step 2 done: query normalized.', ['original' => $request->prompt, 'normalized' => $normalizedQuery, 'negative' => $negativePrompt]);

        // ── Step 2: L1 Cache (exact hash) ────────────────────────────────────
        $cachedFilters = $cacheService->findExactMatch($normalizedQuery);
        if ($cachedFilters) {
            Log::info("[Xavier][Search] Step 2 HIT: L1 cache.");
            return $this->buildVectorSearchResponse($cachedFilters, $user, $request->prompt, "l1_cache", []);
        }

        // ── Step 3: Generic Embedding Generation ─────────────────────────────
        if (empty($normalizedQuery)) {
            Log::info('[Xavier][Search] Step 3 SKIPPED: normalized query is empty. Falling back to legacy.');
            return $this->legacyAiSearch($request, $user, $cacheService);
        }

        try {
            $queryVector = $aiService->generateEmbedding($normalizedQuery, $user->id, 'RETRIEVAL_QUERY');
            
            if (!$queryVector) {
                throw new \Exception("Embedding returned null");
            }
        } catch (\Exception $e) {
            Log::warning('[Xavier][Search] Step 3 FAILED: embedding exception. Falling back to legacy.', [
                'error' => $e->getMessage()
            ]);
            return $this->legacyAiSearch($request, $user, $cacheService);
        }

        Log::info('[Xavier][Search] Step 3 done: generic query embedding generated.');

        // ── Step 4: L2 Semantic Cache ────────────────────────────────────────
        $hasFilters = $analysis['is_restricted'] 
            || !empty($analysis['negative_terms']) 
            || !empty($analysis['organizations']) 
            || !empty($analysis['years']) 
            || $analysis['difficulty'] !== null;
            
        if (!$hasFilters) {
            $l2CachedFilters = $cacheService->findSimilarMatch($queryVector, 0.88);
            if ($l2CachedFilters) {
                Log::info('[Xavier][Search] Step 4 HIT: L2 semantic cache.');
                return $this->buildVectorSearchResponse($l2CachedFilters, $user, $request->prompt, 'l2_cache', []);
            }
        } else {
            Log::info('[Xavier][Search] Step 4 BYPASS: L2 semantic cache skipped due to explicit filters.');
        }

        // ── Step 5: Intent & Concept Detection (Qdrant) ──────────────────────
        $extractedSubjects = [];
        $extractedTopics   = [];
        $extractedOrgs     = [];
        $extractedInsts    = [];
        $searchPath = 'intent_match';

        try {
            $intentThreshold = (float) \App\Models\Configuration::get('xavier_concept_detection_threshold', config('xavier.embeddings.concept_detection_threshold', 0.75));
            $intentMatches = $qdrant->searchIntents($queryVector, 5, $intentThreshold);

            foreach ($intentMatches as $match) {
                $payload = $match['payload'] ?? [];
                $type = $payload['entity_type'] ?? '';

                if ($type === 'subject' && isset($payload['subject_id'])) {
                    $extractedSubjects[] = (int) $payload['subject_id'];
                } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                    $extractedTopics[] = (int) $payload['topic_id'];
                } elseif ($type === 'organization' && isset($payload['organization'])) {
                    $extractedOrgs[] = $payload['organization'];
                } elseif ($type === 'institution' && isset($payload['institution'])) {
                    $extractedInsts[] = $payload['institution'];
                }
            }

            $extractedSubjects = array_values(array_unique($extractedSubjects));
            $extractedTopics   = array_values(array_unique($extractedTopics));
            $extractedOrgs     = array_values(array_unique($extractedOrgs));
            $extractedInsts    = array_values(array_unique($extractedInsts));

            Log::info('[Xavier][Search] Step 5 done: intent detection.');
        } catch (\Exception $e) {
            Log::warning('[Xavier][Search] Step 5 FAILED: intent detection error.', ['err' => $e->getMessage()]);
        }

        // ── 5.1: Merge Lexical Detections ────────────────────────────────────
        if (!empty($analysis['organizations'])) {
            $extractedOrgs = array_merge($extractedOrgs, $analysis['organizations']);
        }
        if (!empty($analysis['institutions'])) {
            $extractedInsts = array_merge($extractedInsts, $analysis['institutions']);
        }

        $extractedOrgs  = array_values(array_unique($extractedOrgs));
        $extractedInsts = array_values(array_unique($extractedInsts));

        // ── Step 5b: Fallback if no intent found ─────────────────────────────
        if (empty($extractedSubjects) && empty($extractedTopics) && empty($extractedOrgs) && empty($extractedInsts)) {
            Log::info('[Xavier][Search] Step 5b: no intents found, proceeding with pure vector search.');
            $searchPath = 'vector_only';
        }

        // ── Step 5d: Negative Intents and Restrictions ───────────────────────
        $excludedOrgs       = [];
        $excludedInsts      = [];
        $excludedSubjects   = [];
        $excludedTopics     = [];
        $excludedType       = null;
        
        $mustOrgs           = [];
        $mustInsts          = [];
        $mustSubjects       = [];
        $mustTopics         = [];

        // 1. Process Negations
        if (!empty($negativePrompt)) {
            try {
                $negVector = $aiService->generateEmbedding($negativePrompt, $user->id, 'RETRIEVAL_QUERY');
                $negMatches = $qdrant->searchConcepts($negVector, 10, 0.55);
                
                foreach ($negMatches as $match) {
                    $payload = $match['payload'] ?? [];
                    $type = $payload['entity_type'] ?? '';
                    
                    if ($type === 'organization' && isset($payload['organization'])) {
                        $excludedOrgs[] = $payload['organization'];
                    } elseif ($type === 'institution' && isset($payload['institution'])) {
                        $excludedInsts[] = $payload['institution'];
                    } elseif ($type === 'subject' && isset($payload['subject_id'])) {
                        $excludedSubjects[] = (int) $payload['subject_id'];
                    } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                        $excludedTopics[] = (int) $payload['topic_id'];
                    }
                }

                $lowerNeg = mb_strtolower($negativePrompt);
                if (str_contains($lowerNeg, 'concurso')) $excludedType = 'concurso';
                if (str_contains($lowerNeg, 'enem')) $excludedType = 'enem';

            } catch (\Exception $e) {
                Log::warning('[Xavier][Search] Negative intent detection error.', ['err' => $e->getMessage()]);
            }
        }

        // 2. Process Restrictions
        if ($analysis['is_restricted'] && !empty($analysis['restricted_terms'])) {
            try {
                foreach ($analysis['restricted_terms'] as $term) {
                    $mustVector = $aiService->generateEmbedding($term, $user->id, 'RETRIEVAL_QUERY');
                    $mustMatches = $qdrant->searchConcepts($mustVector, 3, 0.70);
                    
                    foreach ($mustMatches as $match) {
                        $payload = $match['payload'] ?? [];
                        $type = $payload['entity_type'] ?? '';
                        
                        if ($type === 'organization' && isset($payload['organization'])) {
                            $mustOrgs[] = $payload['organization'];
                        } elseif ($type === 'institution' && isset($payload['institution'])) {
                            $mustInsts[] = $payload['institution'];
                        } elseif ($type === 'subject' && isset($payload['subject_id'])) {
                            $mustSubjects[] = (int) $payload['subject_id'];
                        } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                            $mustTopics[] = (int) $payload['topic_id'];
                        }
                    }

                    $lowerTerm = mb_strtolower($term);
                    if (str_contains($lowerTerm, 'concurso')) $sqlFilters['type'] = 'concurso';
                    if (str_contains($lowerTerm, 'enem')) $sqlFilters['type'] = 'enem';
                }
            } catch (\Exception $e) {
                Log::warning('[Xavier][Search] Restriction intent detection error.', ['err' => $e->getMessage()]);
            }
        }

        // ── Step 6: Query Expansion via Co-occurrence ────────────────────────
        $expandedSubjectIds = $extractedSubjects;
        $expandedTopicIds   = $extractedTopics;
        
        if (!empty($extractedSubjects) || !empty($extractedTopics)) {
            $expanded = $expansion->expand($extractedSubjects, $extractedTopics);
            $expandedSubjectIds = $expanded['subject_ids'];
            $expandedTopicIds   = $expanded['topic_ids'];
        }

        $extractedSubjects = $expandedSubjectIds;
        $extractedTopics   = $expandedTopicIds;

        // ── Step 5c: Type Detection via Keywords ─────────────────────────────
        $extractedType = null;
        $lowerPrompt = mb_strtolower($positivePrompt);
        if (str_contains($lowerPrompt, 'concurso')) {
            $extractedType = 'concurso';
        } elseif (str_contains($lowerPrompt, 'enem')) {
            $extractedType = 'enem';
        }

        // ── Generate all 5 query-side vectors in a single BATCH call ─────────
        $statementQueryText   = $textBuilder->buildStatementQuery($positivePrompt);
        $conceptQueryText     = $textBuilder->buildConceptQuery($positivePrompt);
        $explanationQueryText = $textBuilder->buildExplanationQuery($positivePrompt);
        $alternativesQueryText = $textBuilder->buildAlternativesQuery($positivePrompt);
        $skillsQueryText       = $textBuilder->buildSkillsQuery($positivePrompt);

        $texts = [$statementQueryText, $conceptQueryText, $explanationQueryText, $alternativesQueryText, $skillsQueryText];
        $slots = ['statement', 'concept', 'explanation', 'alternatives', 'skills'];

        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt'  => $request->prompt,
            'status'  => 'generating',
        ]);

        $ttl = config('xavier.search_embeddings.ttl', 300);

        try {
            $vectors = $aiService->generateEmbeddingsBatch($texts, $user->id, 'RETRIEVAL_QUERY');

            if ($vectors && count($vectors) === 5) {
                foreach ($slots as $idx => $slot) {
                    \Illuminate\Support\Facades\Cache::put("xavier:qembed:{$searchRequest->id}:{$slot}", $vectors[$idx], $ttl);
                }
                
                \Illuminate\Support\Facades\Redis::set("xavier:qembed_done:{$searchRequest->id}", 5);
                \Illuminate\Support\Facades\Redis::expire("xavier:qembed_done:{$searchRequest->id}", $ttl);
                
                Log::info("[Xavier][Search] Batch embeddings generated (5 vectors). Proceeding to Qdrant search.");
            } else {
                throw new \Exception("Batch embedding failed or returned incomplete results.");
            }
        } catch (\Exception $e) {
            Log::warning("[Xavier][Search] Batch embedding failed, search will use generic fallback. Error: " . $e->getMessage());
            \Illuminate\Support\Facades\Redis::set("xavier:qembed_done:{$searchRequest->id}", 5); 
        }

        // Build search context for RunVectorSearchJob
        $sqlFilters = ['keyword' => $request->prompt];
        if ($extractedType) $sqlFilters['type'] = $extractedType;
        if (!empty($extractedOrgs)) $sqlFilters['organization'] = $extractedOrgs;
        if (!empty($extractedInsts)) $sqlFilters['institution'] = $extractedInsts;
        
        if (!empty($mustOrgs))     $sqlFilters['organization'] = $mustOrgs;
        if (!empty($mustInsts))    $sqlFilters['institution']  = $mustInsts;
        if (!empty($mustSubjects)) $sqlFilters['subject']     = $mustSubjects[0];
        if (!empty($mustTopics))   $sqlFilters['topic']       = $mustTopics[0];
        
        if (!empty($excludedOrgs))     $sqlFilters['exclude_organization'] = $excludedOrgs;
        if (!empty($excludedInsts))    $sqlFilters['exclude_institution']  = $excludedInsts;
        if ($excludedType)             $sqlFilters['exclude_type']         = $excludedType;
        if (!empty($excludedSubjects)) $sqlFilters['exclude_subject_id']   = $excludedSubjects;
        if (!empty($excludedTopics))   $sqlFilters['exclude_topic_id']     = $excludedTopics;

        if ($analysis['difficulty']) $sqlFilters['difficulty'] = $analysis['difficulty'];
        if (!empty($analysis['years'])) {
            $sqlFilters['year'] = $analysis['years'][0];
            $sqlFilters['year_operator'] = $analysis['year_operator'];
        }

        $searchContext = [
            'prompt'              => $request->prompt,
            'normalized_query'    => $normalizedQuery,
            'query_vector'        => $queryVector,
            'sql_filters'         => $sqlFilters,
            'is_restricted'       => $analysis['is_restricted'],
            'restricted_terms'    => $analysis['restricted_terms'],
            'intent_filters'      => array_filter([
                'subject_id'   => $extractedSubjects ?: null,
                'topic_id'     => $extractedTopics   ?: null,
                'organization' => $extractedOrgs     ?: null,
                'institution'  => $extractedInsts    ?: null,
                'type'         => $extractedType ? [$extractedType] : null,
            ]),
            'search_path'         => $searchPath,
            'candidate_limit'     => (int) \App\Models\Configuration::get('xavier_qdrant_candidate_limit', config('xavier.search.qdrant_candidate_limit', 50)),
            'final_limit'         => (int) \App\Models\Configuration::get('xavier_final_result_limit', config('xavier.search.final_result_limit', 100)),
        ];

        \Illuminate\Support\Facades\Cache::put("xavier:qembed_ctx:{$searchRequest->id}", json_encode($searchContext), $ttl);

        RunVectorSearchJob::dispatch($searchRequest->id, $user->id)
            ->onQueue(config('xavier.search_embeddings.queue', 'search_embeddings'));

        Log::info('[Xavier][Search] Search runner dispatched.', ['search_request_id' => $searchRequest->id]);

        return response()->json([
            'status'     => 'generating',
            'request_id' => $searchRequest->id,
        ], 202);
    }

    /**
     * Legacy AI search path (SQL + LLM, existing behavior).
     * Maintained for backwards compatibility when VECTOR_SEARCH_ENABLED=false.
     */
    private function legacyAiSearch(Request $request, $user, SemanticCacheService $cacheService)
    {
        $userPrompt = trim(strtolower($request->prompt));

        $cachedFilters = $cacheService->findExactMatch($userPrompt);

        if (!$cachedFilters) {
            $aiService = app(AIService::class);
            $vector    = $aiService->generateEmbedding($userPrompt, $user->id, 'RETRIEVAL_QUERY');
            if ($vector) {
                $cachedFilters = $cacheService->findSimilarMatch($vector, AiSearchRequest::DEFAULT_THRESHOLD);
            }
        }

        if ($cachedFilters) {
            AiSearchRequest::create([
                'user_id'              => $user->id,
                'prompt'               => $request->prompt,
                'status'               => 'completed',
                'filters'              => $cachedFilters,
                'similarity_threshold' => AiSearchRequest::DEFAULT_THRESHOLD,
            ]);

            return response()->json([
                'status'         => 'completed',
                'filters'        => $cachedFilters,
                'suggestion_tip' => $cachedFilters['suggestion_tip'] ?? null,
                'suggestions'    => $cachedFilters['suggestions'] ?? [],
            ], 200);
        }

        // Fast minimal SQL fallback for legacy
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt'  => $request->prompt,
            'status'  => 'completed',
            'filters' => ['keyword' => $request->prompt]
        ]);

        return response()->json([
            'status'         => 'completed',
            'filters'        => ['keyword' => $request->prompt],
            'suggestion_tip' => null,
            'suggestions'    => [],
        ], 200);
    }

    /**
     * Build a completed response from cached filters for the vector pipeline.
     */
    private function buildVectorSearchResponse(array $cachedFilters, $user, string $prompt, string $searchPath, array $conceptIds)
    {
        AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt'  => $prompt,
            'status'  => 'completed',
            'filters' => $cachedFilters,
        ]);

        if (isset($cachedFilters['question_ids'])) {
            return response()->json([
                'status'       => 'completed',
                'search_mode'  => 'vector',
                'search_path'  => $searchPath,
                'question_ids' => $cachedFilters['question_ids'],
                'score_details' => $cachedFilters['score_details'] ?? [],
                'total'        => count($cachedFilters['question_ids']),
                'concepts'     => $conceptIds,
            ], 200);
        }

        return response()->json([
            'status'         => 'completed',
            'filters'        => $cachedFilters,
            'suggestion_tip' => $cachedFilters['suggestion_tip'] ?? null,
            'suggestions'    => $cachedFilters['suggestions'] ?? [],
        ], 200);
    }

    /**
     * Poll the status of an active AI Search request
     */
    public function aiSearchStatus(Request $request, AiSearchRequest $aiSearchRequest)
    {
        if ($aiSearchRequest->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($aiSearchRequest->status === 'completed') {
            $filters = $aiSearchRequest->filters ?? [];
            return response()->json([
                'status'        => 'completed',
                'search_mode'   => 'vector',
                'search_path'   => $filters['search_path']   ?? 'vector_only',
                'question_ids'  => $filters['question_ids']  ?? [],
                'score_details' => $filters['score_details'] ?? [],
                'concepts'      => $filters['concepts']      ?? [],
                'total'         => count($filters['question_ids'] ?? []),
                'request_id'    => $aiSearchRequest->id,
            ], 200);
        }

        return response()->json([
            'status' => $aiSearchRequest->status,
            'filters' => $aiSearchRequest->filters,
            'score_details' => $aiSearchRequest->filters['score_details'] ?? [],
            'suggestion_tip' => $aiSearchRequest->filters['suggestion_tip'] ?? null,
            'suggestions' => $aiSearchRequest->filters['suggestions'] ?? [],
            'error' => $aiSearchRequest->error
        ], 200);
    }
}
