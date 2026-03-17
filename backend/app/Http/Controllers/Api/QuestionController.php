<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSearchRequest;
use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\SearchInteractionLog;
use App\Jobs\InterpretSearchPromptJob;
use App\Jobs\RespondToStandaloneChatJob;
use App\Http\Resources\QuestionResource;
use App\Services\AI\AIService;
use App\Services\AI\EmbeddingTextBuilder;
use App\Services\AI\HybridSearchService;
use App\Services\AI\QueryExpansionService;
use App\Services\AI\QdrantService;
use App\Services\AI\ReRankService;
use App\Services\AI\SemanticCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{
    /**
     * Display a listing of questions for the question bank.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Question::published()->with(['subjects', 'topics', 'alternatives', 'images']);

        if ($userId) {
            $query->withExists(['favorites as is_favorite' => fn($q) => $q->where('user_id', $userId)])
                ->withExists(['notes as has_notes' => fn($q) => $q->where('user_id', $userId)])
                ->with(['notebooks' => fn($q) => $q->where('user_id', $userId)]);

            if (Auth::user()->isAdmin()) {
                $query->with(['triageLogs', 'reports.user']);
            }
        }

        // Base Type Blocks: Never return Redação in student endpoints
        $query->where('tipo_questao', '!=', 'Redação');

        // Optional Discursive Filter
        if ($request->boolean('include_discursive')) {
            $query->whereIn('tipo_questao', ['Objetiva', 'Discursiva']);
        } else {
            $query->where('tipo_questao', 'Objetiva');
        }

        // Type filter (enem, concurso, etc)
        $query->filterByType($request->type);

        // Relational filters
        $query->filterBySubject($request->subject); // Handles subject ID
        $query->filterByTopic($request->topic);     // Handles topic ID

        // Favorites filter
        if ($userId && $request->boolean('favorites_only')) {
            $query->whereHas('favorites', fn($q) => $q->where('user_id', $userId));
        }

        // Notes filter
        if ($userId && $request->boolean('has_notes')) {
            $query->whereHas('notes', fn($q) => $q->where('user_id', $userId));
        }

        if ($userId && $request->boolean('exclude_favorites')) {
            $query->whereDoesntHave('favorites', fn($q) => $q->where('user_id', $userId));
        }

        // Notebooks filter (array or single ID)
        if ($userId && $request->filled('notebook_id')) {
            $notebookIds = (array) $request->notebook_id;
            $query->whereHas('notebooks', fn($q) => $q->whereIn('notebooks.id', $notebookIds)->where('user_id', $userId));
        }
        if ($userId && $request->filled('exclude_notebook_id')) {
            $excludeNotebookIds = (array) $request->exclude_notebook_id;
            $query->whereDoesntHave('notebooks', fn($q) => $q->whereIn('notebooks.id', $excludeNotebookIds)->where('user_id', $userId));
        }

        // Attribute filters
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('organization')) {
            $query->where('organization', 'like', '%' . $request->organization . '%');
        }
        if ($request->filled('institution')) {
            $query->where('institution', 'like', '%' . $request->institution . '%');
        }
        if ($request->filled('role')) {
            $query->where('role', 'like', '%' . $request->role . '%');
        }

        // Filter by specific IDs (Semantic Results)
        if ($request->filled('question_ids')) {
            $ids = is_array($request->question_ids) ? $request->question_ids : explode(',', $request->question_ids);
            $query->whereIn('id', $ids);

            // Maintain order of IDs if they come from semantic search (ReRank order)
            $orderString = implode(',', $ids);
            $query->orderByRaw("FIELD(id, {$orderString})");
        }

        // Keyword/Search filter
        if ($request->filled('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('statement', 'like', '%' . $request->keyword . '%')
                    ->orWhere('explanation', 'like', '%' . $request->keyword . '%');
            });
        }

        // Sort by newest by default
        $query->orderBy('created_at', 'desc');

        $questions = $query->paginate($request->get('per_page', 15));

        return QuestionResource::collection($questions);
    }

    /**
     * Display a specific question (with explanation and answer if included).
     */
    public function show(Request $request, Question $question)
    {
        $question->load(['subjects', 'topics', 'alternatives', 'images']);

        $userId = $request->user('sanctum')?->id;
        if ($userId) {
            $question->loadExists(['favorites as is_favorite' => fn($q) => $q->where('user_id', $userId)]);
            $question->loadExists(['notes as has_notes' => fn($q) => $q->where('user_id', $userId)]);
            $question->load(['notebooks' => fn($q) => $q->where('user_id', $userId)]);

            if (Auth::user()->isAdmin()) {
                $question->load(['triageLogs', 'reports.user']);
            }
        }

        return new QuestionResource($question);
    }

    /**
     * Subjects for filtering (AJAX).
     */
    public function subjects(Request $request)
    {
        $type = $request->get('type');
        $subjects = \App\Models\Subject::query()
            ->when($type, function ($q) use ($type) {
                $q->whereHas('questions', fn($q2) => $q2->filterByType($type));
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Ensure we return data in a structure that matches the original Blade AJAX
        return response()->json($subjects);
    }

    /**
     * Topics for filtering (AJAX).
     */
    public function topics(Request $request)
    {
        $subjectId = $request->get('subject');
        $type = $request->get('type');

        $topics = \App\Models\Topic::query()
            ->when($subjectId, function ($q) use ($subjectId) {
                $q->whereHas('questions', fn($q2) => $q2->filterBySubject($subjectId));
            })
            ->when($type, function ($q) use ($type) {
                $q->whereHas('questions', fn($q2) => $q2->filterByType($type));
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($topics);
    }

    /**
     * Answer a question.
     */
    public function answer(Request $request, Question $question)
    {
        $request->validate([
            'selected_answer' => 'required_without:respostas_discursivas|string|size:1|in:A,B,C,D,E',
            'respostas_discursivas' => 'array',
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        $feedback = app(\App\Services\QuestionService::class)->answerQuestion(
            $request->user()->id,
            $question,
            $request->selected_answer ?? 'DISCURSIVA'
        );

        // Record atomic analytics log
        app(\App\Services\AnalyticsService::class)->logAnswer(
            $question,
            $request->user()->id,
            $feedback['correct'],
            $request->time_spent_seconds,
            $request
        );

        // Consome a cota diária do usuário e atualiza estatísticas de engajamento
        $user = $request->user();
        $user->incrementDailyQuestionUsage();

        app(\App\Services\UserEngagementService::class)->updateDailyStats($user->id, $feedback['correct']);

        return response()->json($feedback);
    }

    /**
     * Log a question view (Analytics)
     */
    public function logView(Request $request, Question $question)
    {
        app(\App\Services\AnalyticsService::class)->logView(
            $question,
            $request->user()->id,
            $request
        );

        return response()->json(['status' => 'logged']);
    }

    /**
     * Stats for the user.
     */
    public function stats(Request $request)
    {
        $statsService = app(\App\Services\StatsService::class);
        $userId = $request->user()->id;

        return response()->json([
            'overview' => $statsService->getOverview($userId),
            'bySubject' => $statsService->getPerformanceBySubject($userId),
            'temporal' => $statsService->getTemporalEvolution($userId),
            'byDifficulty' => $statsService->getDifficultyHeatmap($userId),
        ]);
    }

    /**
     * Statistics for a specific question.
     */
    public function questionStats(Request $request, Question $question)
    {
        $showStats = filter_var(env('SHOW_QUESTION_STATS_TO_USERS', false), FILTER_VALIDATE_BOOLEAN);

        if (!$showStats && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Estatísticas indisponíveis. Ocultas no momento.'], 403);
        }

        $answers = \App\Models\UserQuestionAnswer::where('question_id', $question->id)->get();
        if ($answers->isEmpty()) {
            return response()->json(['total_responses' => 0]);
        }

        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();

        $stats = [
            'difficulty' => $question->difficulty ?? 'N/A',
            'total_responses' => $total,
            'correct_percentage' => round(($correct / $total) * 100, 2),
            'incorrect_percentage' => round((($total - $correct) / $total) * 100, 2),
            'average_time_seconds' => round($answers->avg('time_spent_seconds') ?? 0, 2),
            'alternative_distribution' => $answers->groupBy('selected_answer')->map(fn($group) => round(($group->count() / $total) * 100, 2)),
        ];

        return response()->json($stats);
    }

    /**
     * Answer history for a specific question.
     */
    public function history(Request $request, Question $question)
    {
        $userId = $request->user()->id;
        $history = \App\Models\UserQuestionAnswer::where('user_id', $userId)
            ->where('question_id', $question->id)
            ->orderByDesc('answered_at')
            ->get(['selected_answer', 'is_correct', 'answered_at']);

        return response()->json($history);
    }

    /**
     * Unique options for filtering (Banca, Orgão, Cargo).
     */
    public function filterOptions(Request $request)
    {
        return response()->json([
            'organizations' => Question::whereNotNull('organization')->distinct()->orderBy('organization')->pluck('organization'),
            'institutions' => Question::whereNotNull('institution')->distinct()->orderBy('institution')->pluck('institution'),
            'roles' => Question::whereNotNull('role')->distinct()->orderBy('role')->pluck('role'),
        ]);
    }

    /**
     * List essay themes (tipo_questao = 'Redação') for the Step 2 theme picker.
     * This is intentionally SEPARATE from index() which blocks Redação.
     */
    public function essayThemes(Request $request)
    {
        $query = Question::published()
            ->where('tipo_questao', 'Redação')
            ->select('id', 'statement', 'year', 'institution', 'type');

        // Filter by essay type (enem / concurso) if provided
        if ($request->filled('type')) {
            $query->filterByType($request->type);
        }

        // Keyword search on the statement (theme text)
        if ($request->filled('keyword')) {
            $query->where('statement', 'like', '%' . $request->keyword . '%');
        }

        $themes = $query->orderByDesc('year')->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $themes->map(fn($q) => [
                'id' => $q->id,
                'title' => $q->statement,
                'year' => $q->year,
                'institution' => $q->institution,
                'type' => $q->type,
            ]),
            'meta' => [
                'current_page' => $themes->currentPage(),
                'last_page' => $themes->lastPage(),
                'total' => $themes->total(),
            ],
        ]);
    }

    /**
     * Get chat history for a specific question.
     */
    public function chat(Request $request, Question $question)
    {
        $interactions = QuestionInteraction::where('user_id', $request->user()->id)
            ->where('question_id', $question->id)
            ->whereNull('simulation_id') // Standalone chat
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message', 'created_at']);

        return response()->json($interactions);
    }

    /**
     * Send a new chat message to Xavier.
     */
    public function sendChat(Request $request, Question $question)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();

        // 1. Check AI Quota
        if (!$user->hasAiQuota()) {
            return response()->json([
                'status' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de dúvidas do seu plano.',
                'upgrade_url' => '/plans'
            ]);
        }

        // 2. Save User Message
        QuestionInteraction::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        // 3. Increment usage immediately (prevent race conditions)
        $user->incrementAiUsage();

        // 4. Get history for context
        $history = QuestionInteraction::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->whereNull('simulation_id')
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message'])
            ->toArray();

        // 5. Stream response directly
        $lastAnswer = \App\Models\UserQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->orderByDesc('answered_at')
            ->value('selected_answer') ?? 'Não respondida';

        $aiService = app(\App\Services\AI\AIService::class);

        return response()->stream(function () use ($aiService, $question, $lastAnswer, $request, $history, $user) {
            // Disable buffering and compression for real-time streaming
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            while (ob_get_level()) {
                ob_end_flush();
            }

            try {
                $stream = $aiService->streamChatAboutStandaloneQuestion(
                    $question,
                    $lastAnswer,
                    $request->message,
                    $history,
                    $user->id
                );

                $fullResponse = '';
                $chunkBuffer = "";
                $chunkCounter = 0;
                foreach ($stream as $chunk) {
                    $fullResponse .= $chunk;
                    $chunkBuffer .= $chunk;
                    $chunkCounter++;

                    // Send first chunk immediately, then every 3-4 chunks to look "faster"
                    // Also flush on newlines to preserve formatting
                    if ($chunkCounter === 1 || $chunkCounter % 3 === 0 || str_contains($chunk, "\n")) {
                        // Correctly prefix every line with 'data: ' for SSE compatibility
                        $lines = explode("\n", $chunkBuffer);
                        foreach ($lines as $index => $line) {
                            echo "data: " . $line . "\n";
                        }
                        echo "\n"; // End of SSE event

                        $chunkBuffer = "";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                    }
                }

                // Final flush for remaining content
                if ($chunkBuffer !== "") {
                    echo "data: " . $chunkBuffer . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                }

                QuestionInteraction::create([
                    'simulation_id' => null,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $fullResponse ?: 'Desculpe, ocorreu um erro na IA.',
                ]);
            } catch (\Exception $e) {
                // If streaming crashes mid-way, just stop and log
                \Illuminate\Support\Facades\Log::error("Chat streaming aborted: " . $e->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none',
        ]);
    }

    /**
     * Submit a prompt to the AI Search (Busca Assistida)
     *
     * Implements the full 9-step Xavier Semantic Search pipeline when
     * VECTOR_SEARCH_ENABLED=true. Falls back to the legacy SQL+LLM pipeline
     * (InterpretSearchPromptJob) when the flag is false or Qdrant is unavailable.
     */
    public function aiSearch(Request $request)
    {
        $request->validate(['prompt' => 'required|string|max:500']);

        $user       = $request->user();
        $cacheService = app(SemanticCacheService::class);

        // ─── LEGACY PATH (feature flag off) ──────────────────────────────────
        $isEnabled = config('xavier.vector_search_enabled');
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
        $hybridSearch  = app(HybridSearchService::class);
        $reranker      = app(ReRankService::class);

        // ── Step 1: Query Normalization ──────────────────────────────────────
        $normalizedQuery = $textBuilder->buildForQuery($request->prompt);
        Log::info('[Xavier][Search] Step 1 done: normalized query.', ['q' => $normalizedQuery]);

        // ── Step 2: L1 Cache (exact hash) ────────────────────────────────────
        $cachedFilters = $cacheService->findExactMatch($normalizedQuery);
        if ($cachedFilters) {
            Log::info('[Xavier][Search] Step 2 HIT: L1 cache.');
            return $this->buildVectorSearchResponse($cachedFilters, $user, $request->prompt, 'l1_cache', []);
        }

        // ── Step 2.5: Query Intent Extraction (Heuristic) ─────────────────────
        // If the user searches for a pure subject/topic like "Inglês", vector search
        // might falsely match math questions with high "textual interpretation" scores.
        // This heuristic enforces a hard SQL filter for obvious subject matches.
        $extractedSubjectId = null;
        $extractedTopicId = null;

        $cleanedPromptForIntent = trim(preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ0-9\s]/u', '', strtolower($request->prompt)));
        $intentWords = explode(' ', $cleanedPromptForIntent);

        if (count($intentWords) <= 3) {
            // Highly likely to be a direct category attempt if it's very short
            $guessedSubject = \App\Models\Subject::where(function ($q) use ($intentWords) {
                foreach ($intentWords as $word) {
                    if (strlen($word) > 3) {
                        $q->orWhere('name', 'like', "%{$word}%");
                    }
                }
            })->first();

            if ($guessedSubject) {
                $extractedSubjectId = (string) $guessedSubject->id;
                Log::info('[Xavier][Search] Intent Extractor: found Subject match.', ['subject' => $guessedSubject->name]);
            } else {
                // Try for a Topic if Subject wasn't found
                $guessedTopic = \App\Models\Topic::where(function ($q) use ($intentWords) {
                    foreach ($intentWords as $word) {
                        if (strlen($word) > 4) {
                            $q->orWhere('name', 'like', "%{$word}%")
                              ->orWhere('slug', 'like', "%{$word}%");
                        }
                    }
                })->first();
                if ($guessedTopic) {
                     $extractedTopicId = (string) $guessedTopic->id;
                     Log::info('[Xavier][Search] Intent Extractor: found Topic match.', ['topic' => $guessedTopic->name]);
                }
            }
        }

        // ── Step 3: Query Embedding ───────────────────────────────────────────
        $queryVector = $aiService->generateEmbedding($normalizedQuery, $user->id);

        if (!$queryVector) {
            Log::warning('[Xavier][Search] Step 3 FAILED: embedding null. Falling back to legacy.');
            return $this->legacyAiSearch($request, $user, $cacheService);
        }

        Log::info('[Xavier][Search] Step 3 done: embedding generated.');

        // ── Step 4: L2 Semantic Cache ─────────────────────────────────────────
        $l2CachedFilters = $cacheService->findSimilarMatch($queryVector, 0.88);
        if ($l2CachedFilters) {
            Log::info('[Xavier][Search] Step 4 HIT: L2 semantic cache.');
            return $this->buildVectorSearchResponse($l2CachedFilters, $user, $request->prompt, 'l2_cache', []);
        }

        // ── Step 5: Concept Detection via Qdrant ──────────────────────────────
        $detectedConcepts = [];
        $searchPath = 'concept';

        try {
            $conceptThreshold = (float) config('xavier.embeddings.concept_detection_threshold', 0.75);
            $conceptMatches = $qdrant->searchConcepts($queryVector, 5, $conceptThreshold);
            
            // Extract concept slugs from payload — Correctly mapping without using array as index key
            $detectedConcepts = array_filter(array_map(
                fn($match) => $match['payload']['concept_slug'] ?? null,
                $conceptMatches
            ));
            
            $detectedConcepts = array_values(array_unique($detectedConcepts));
            Log::info('[Xavier][Search] Step 5 done: concept detection.', ['concepts' => $detectedConcepts]);
        } catch (\Exception $e) {
            Log::warning('[Xavier][Search] Step 5 FAILED: concept detection error.', ['err' => $e->getMessage()]);
        }

        // ── Step 5b: Log if zero concepts detected (no longer force fallback) ──
        if (empty($detectedConcepts)) {
            Log::info('[Xavier][Search] Step 5b: no concepts found, proceeding with pure vector search.');
            $searchPath = 'vector_only';
        }

        // ── Step 6: Query Expansion via Knowledge Graph ───────────────────────
        $expandedConceptIds = $expansion->expand($detectedConcepts, depth: 1);
        Log::info('[Xavier][Search] Step 6 done: query expansion.', ['expanded' => $expandedConceptIds]);

        // ── Step 7: Hybrid Search (Qdrant + SQL fallback) ─────────────────────
        $queryVectors = [
            'statement'   => $queryVector,
            'concept'     => $queryVector,
            'explanation' => $queryVector,
        ];

        $sqlFilters = array_filter([
            'subject'    => $request->get('subject') ?: $extractedSubjectId,
            'topic'      => $request->get('topic') ?: $extractedTopicId,
            'type'       => $request->get('type'),
            'difficulty' => $request->get('difficulty'),
            'keyword'    => $request->get('keyword'),
        ]);

        $candidateLimit = (int) config('xavier.search.qdrant_candidate_limit', 50);
        $candidates = $hybridSearch->search($queryVectors, $expandedConceptIds, $sqlFilters, $candidateLimit);
        Log::info('[Xavier][Search] Step 7 done: hybrid search.', ['candidates' => count($candidates)]);

        // ── Step 8: ReRank (top-50 → top-20) ─────────────────────────────────
        $finalLimit  = (int) config('xavier.search.final_result_limit', 20);
        $rankedItems = $reranker->rerank($candidates, $finalLimit);
        $questionIds = array_column($rankedItems, 'question_id');
        Log::info('[Xavier][Search] Step 8 done: reranked.', ['top' => count($rankedItems)]);

        // ── Step 9: Logging + Learning Loop ──────────────────────────────────
        $searchRequest = AiSearchRequest::create([
            'user_id'  => $user->id,
            'prompt'   => $request->prompt,
            'status'   => 'completed',
            'filters'  => ['vector_search' => true, 'question_ids' => $questionIds],
        ]);

        // Log each result in search_interaction_logs (position tracking)
        foreach (array_slice($rankedItems, 0, 20) as $idx => $item) {
            SearchInteractionLog::create([
                'ai_search_id'        => $searchRequest->id,
                'user_id'             => $user->id,
                'question_id'         => $item['question_id'],
                'rank_position'       => $idx + 1,
                'was_clicked'         => false,
                'expanded_concept_ids' => $expandedConceptIds,
                'search_path'         => $searchPath,
            ]);
        }

        // Store in L2 semantic cache for future similar queries
        $cacheService->storeInCache(
            $normalizedQuery,
            $queryVector,
            ['vector_search' => true, 'question_ids' => $questionIds],
            $expandedConceptIds
        );

        Log::info('[Xavier][Search] Step 9 done: logged and cached.', [
            'request_id' => $searchRequest->id,
            'total' => count($questionIds),
        ]);

        return response()->json([
            'status'        => 'completed',
            'search_mode'   => 'vector',
            'search_path'   => $searchPath,
            'question_ids'  => $questionIds,
            'total'         => count($questionIds),
            'concepts'      => $detectedConcepts,
            'request_id'    => $searchRequest->id,
        ], 200);
    }

    /**
     * Legacy AI search path (SQL + LLM, existing behavior).
     * Used when VECTOR_SEARCH_ENABLED=false or when vector pipeline cannot proceed.
     */
    private function legacyAiSearch(Request $request, $user, SemanticCacheService $cacheService)
    {
        $userPrompt = trim(strtolower($request->prompt));

        // [Nível 1] Busca Exata (Hash MD5) - Instantâneo Síncrono
        $cachedFilters = $cacheService->findExactMatch($userPrompt);

        // [Nível 2] Busca por Similaridade (Embeddings) - Quase Instantâneo Síncrono
        if (!$cachedFilters) {
            $aiService = app(AIService::class);
            $vector    = $aiService->generateEmbedding($userPrompt, $user->id);
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

        // Caso inédito: dispara o Xavier Background
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt'  => $request->prompt,
            'status'  => 'pending',
        ]);

        InterpretSearchPromptJob::dispatch($searchRequest);

        return response()->json([
            'status'     => 'queued',
            'request_id' => $searchRequest->id,
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

        // Return question_ids if stored as vector result, otherwise return filters
        if (isset($cachedFilters['question_ids'])) {
            return response()->json([
                'status'       => 'completed',
                'search_mode'  => 'vector',
                'search_path'  => $searchPath,
                'question_ids' => $cachedFilters['question_ids'],
                'total'        => count($cachedFilters['question_ids']),
                'concepts'     => $conceptIds,
            ], 200);
        }

        // Legacy cache format (filters-based)
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
        // Ensure the user owns this request
        if ($aiSearchRequest->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => $aiSearchRequest->status,
            'filters' => $aiSearchRequest->filters,
            'suggestion_tip' => $aiSearchRequest->filters['suggestion_tip'] ?? null,
            'suggestions' => $aiSearchRequest->filters['suggestions'] ?? [],
            'error' => $aiSearchRequest->error
        ], 200);
    }

    /**
     * Get engagement data for the micro dashboard.
     */
    public function engagement(Request $request)
    {
        $data = app(\App\Services\UserEngagementService::class)->getEngagementData($request->user()->id);
        return response()->json($data);
    }

    /**
     * Update user daily goal.
     */
    public function updateGoal(Request $request)
    {
        $request->validate([
            'daily_goal' => 'required|integer|min:1|max:500',
        ]);

        $user = $request->user();
        $user->daily_goal = $request->daily_goal;
        $user->save();

        return response()->json([
            'message' => 'Meta diária atualizada com sucesso.',
            'daily_goal' => $user->daily_goal
        ]);
    }
}
