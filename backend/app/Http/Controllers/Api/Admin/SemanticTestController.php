<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\SemanticCacheService;
use App\Models\Question;

/**
 * Controller responsible for the interactive Semantic Search tester
 * in the Admin Dashboard.
 */
class SemanticTestController extends Controller
{
    /**
     * Run a test search using the Xavier internal pipeline to debug scores and vectors.
     *
     * IMPORTANTE: Este método espelha EXATAMENTE o pipeline real do
     * XavierSearchController::aiSearch para que os resultados do Debug Console
     * sejam idênticos aos que o usuário final veria.
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

        // ── Step 3.6: Detecção de Intenções Negativas e Restrições (Xavier 2.0) ──
        $excludedOrgs = [];
        $excludedInsts = [];
        $excludedSubjectIds = [];
        $excludedTopicIds = [];
        $excludedType = null;
        
        $mustOrgs = [];
        $mustInsts = [];
        $mustSubjectIds = [];
        $mustTopicIds = [];

        if (!empty($negativePrompt)) {
            try {
                $negVector = $aiService->generateEmbedding($negativePrompt, $user->id, 'RETRIEVAL_QUERY');
                $negMatches = $qdrant->searchConcepts($negVector, 10, 0.55);
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

        // 2. Processar RESTRIÇÕES (O que travar como MUST)
        if ($analysis['is_restricted'] && !empty($analysis['restricted_terms'])) {
            try {
                foreach ($analysis['restricted_terms'] as $term) {
                    $mustVector = $aiService->generateEmbedding($term, $user->id, 'RETRIEVAL_QUERY');
                    $mustMatches = $qdrant->searchConcepts($mustVector, 3, 0.70); // Threshold 0.70
                    
                    foreach ($mustMatches as $match) {
                        $payload = $match['payload'] ?? [];
                        $type = $payload['entity_type'] ?? '';
                        
                        if ($type === 'organization' && isset($payload['organization'])) {
                            $mustOrgs[] = $payload['organization'];
                            $logs[] = "MUST DETECTED: Organization '{$payload['organization']}'";
                        } elseif ($type === 'institution' && isset($payload['institution'])) {
                            $mustInsts[] = $payload['institution'];
                            $logs[] = "MUST DETECTED: Institution '{$payload['institution']}'";
                        } elseif ($type === 'subject' && isset($payload['subject_id'])) {
                            $mustSubjectIds[] = (int) $payload['subject_id'];
                            $logs[] = "MUST DETECTED: Subject #{$payload['subject_id']}";
                        } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                            $mustTopicIds[] = (int) $payload['topic_id'];
                            $logs[] = "MUST DETECTED: Topic #{$payload['topic_id']}";
                        }
                    }

                    // Detecção de tipo via keyword no termo restrito
                    $lowerTerm = mb_strtolower($term);
                    if (str_contains($lowerTerm, 'concurso')) {
                        $sqlFilters['type'] = 'concurso';
                    }
                    if (str_contains($lowerTerm, 'enem')) {
                        $sqlFilters['type'] = 'enem';
                    }
                }
            } catch (\Exception $e) {
                $logs[] = "WARNING: Restriction embedding failed: " . $e->getMessage() . ". Must filters might be incomplete.";
            }
        }

        $detectedConcepts = array_values(array_unique($detectedConcepts));
        $logs[] = "Concepts Detected: " . implode(', ', $detectedConcepts ?: ['None']);

        // ── Step 4: Query Expansion via Knowledge Graph ───────────────────────
        $expansion = app(\App\Services\AI\QueryExpansionService::class);
        $expandedConceptIds = !empty($detectedConcepts) ? $expansion->expand($detectedConcepts, 1) : [];
        $logs[] = "Expanded Concepts IDs: " . implode(', ', $expandedConceptIds ?: ['None']);

        // ── Step 5: Gerar embeddings format-aligned ─────────────────────────
        $statementQueryText   = $textBuilder->buildStatementQuery($positivePrompt);
        $conceptQueryText     = $textBuilder->buildConceptQuery($positivePrompt);
        $explanationQueryText = $textBuilder->buildExplanationQuery($positivePrompt);
        $alternativesQueryText = $textBuilder->buildAlternativesQuery($positivePrompt);
        $skillsQueryText       = $textBuilder->buildSkillsQuery($positivePrompt);

        $queryVectors = [];

        try {
            $texts = [
                $statementQueryText,
                $conceptQueryText,
                $explanationQueryText,
                $alternativesQueryText,
                $skillsQueryText
            ];

            $vectors = $aiService->generateEmbeddingsBatch($texts, $user->id, 'RETRIEVAL_QUERY');

            if ($vectors && count($vectors) === 5) {
                $queryVectors = [
                    'statement'    => $vectors[0],
                    'concept'      => $vectors[1],
                    'explanation'  => $vectors[2],
                    'alternatives' => $vectors[3],
                    'skills'       => $vectors[4],
                ];
                $logs[] = "Format-aligned embeddings (Batch): statement, concept, explanation, alternatives, skills = OK";
            } else {
                throw new \Exception("Batch embedding returned incomplete results.");
            }
        } catch (\Exception $e) {
            $logs[] = "WARNING: Batch embeddings failed: " . $e->getMessage() . ". Falling back to single generic vector.";
            $queryVectors = [
                'statement'    => $queryVector,
                'concept'      => $queryVector,
                'explanation'  => $queryVector,
                'alternatives' => $queryVector,
                'skills'       => $queryVector,
            ];
        }

        $logs[] = "Format-aligned embeddings (Batch): OK (" . count($queryVectors) . " vectors)";
        
        // ── Step 6: Hybrid Search ─────────────────────────────────────────────
        $hybridSearch = app(\App\Services\AI\HybridSearchService::class);
        $limit = (int) \App\Models\Configuration::get('xavier_qdrant_candidate_limit', config('xavier.search.qdrant_candidate_limit', 200));
        
        // Ensure SQL fallback actually filters by the text if Qdrant is empty
        $sqlFilters = ['keyword' => $request->prompt];
        $intentFilters = [
            'subject_id'   => $extractedSubjects,
            'topic_id'     => $extractedTopics,
            'organization' => $extractedOrgs,
            'institution'  => $extractedInsts,
            'type'         => $extractedType ? [$extractedType] : [],
        ];

        $logs[] = "INTENT MAP: " . count($extractedSubjects) . " subjects, " . count($extractedTopics) . " topics found in initial pass.";

        if ($extractedType) {
            $sqlFilters['type'] = $extractedType;
        }
        if (!empty($extractedOrgs)) {
            $sqlFilters['organization'] = $extractedOrgs;
        }
        if (!empty($extractedInsts)) {
            $sqlFilters['institution'] = $extractedInsts;
        }

        // Apply restrictions (must filters override general extracted filters)
        if (!empty($mustOrgs))       $sqlFilters['organization'] = $mustOrgs;
        if (!empty($mustInsts))      $sqlFilters['institution']  = $mustInsts;
        if (!empty($mustSubjectIds)) $sqlFilters['subject']      = $mustSubjectIds[0];
        if (!empty($mustTopicIds))   $sqlFilters['topic']        = $mustTopicIds[0];

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
                'payload'       => $item['payload'] ?? [],
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
}
