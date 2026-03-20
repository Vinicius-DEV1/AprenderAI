<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSearchRequest;
use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\SearchInteractionLog;

use App\Jobs\GenerateQueryEmbeddingJob;
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

        // Filter by specific IDs (Semantic Results)
        if ($request->filled('question_ids')) {
            $ids = is_array($request->question_ids) ? $request->question_ids : explode(',', $request->question_ids);
            $query->whereIn('id', $ids);

            // Maintain order of IDs if they come from semantic search (ReRank order)
            $orderString = implode(',', $ids);
            $query->orderByRaw("FIELD(id, {$orderString})");
        } else {
            // --- Normal UI Filters (Ignored during AI Search) ---
            
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

            // Keyword/Search filter
            if ($request->filled('keyword')) {
                $query->where(function ($q) use ($request) {
                    $q->where('statement', 'like', '%' . $request->keyword . '%')
                        ->orWhere('explanation', 'like', '%' . $request->keyword . '%');
                });
            }

            // Sort by newest by default
            $query->orderBy('created_at', 'desc');
        }

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
        // Check if vector search is enabled (Database takes priority over .env)
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
        $hybridSearch  = app(HybridSearchService::class);
        $reranker      = app(ReRankService::class);

        // ── Step 1b: Lexical Analysis (Xavier 2.0) ───────────────────────────
        // Identifica termos positivos, negativos (negação) e restrição.
        $lexical   = app(\App\Services\AI\QueryLexicalAnalyser::class);
        $analysis  = $lexical->analyse($request->prompt);
        $positivePrompt = implode(' ', $analysis['positive_terms']);
        $negativePrompt = implode(' ', $analysis['negative_terms']);

        // ── Step 2: Normalização da Query ─────────────────────────────────────
        $normalizedQuery = $textBuilder->buildForQuery($positivePrompt);
        Log::info('[Xavier][Search] Step 2 done: query normalized.', ['original' => $request->prompt, 'normalized' => $normalizedQuery, 'negative' => $negativePrompt]);

        // â”€â”€ Step 2: L1 Cache (exact hash) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $cachedFilters = $cacheService->findExactMatch($normalizedQuery);
        if ($cachedFilters) {
            Log::info("[Xavier][Search] Step 2 HIT: L1 cache.");
            return $this->buildVectorSearchResponse($cachedFilters, $user, $request->prompt, "l1_cache", []);
        }

        // â”€â”€ Step 2.5: Query Intent Extraction (Heuristic) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // Removido: A heurÃ­stica antiga forÃ§ava filtros SQL estritos (subject_id)
        // para termos como "inglÃªs" ou "matemÃ¡tica", matando a busca semÃ¢ntica
        // e limitando os resultados artificialmente. Agora confiamos 100% nos vetores.
        $extractedSubjectId = null;
        $extractedTopicId = null;
        // ── Step 3: Geração de Embedding Genérico ──────────────────────────────
        // Usamos o prompt POSITIVO para a busca semântica principal.
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

        // ── Step 4: L2 Semantic Cache ─────────────────────────────────────────
        $l2CachedFilters = $cacheService->findSimilarMatch($queryVector, 0.88);
        if ($l2CachedFilters) {
            Log::info('[Xavier][Search] Step 4 HIT: L2 semantic cache.');
            return $this->buildVectorSearchResponse($l2CachedFilters, $user, $request->prompt, 'l2_cache', []);
        }

        // ── Step 5: Busca de Intenção e Conceitos via Qdrant ───────────────────────
        // Este é o coração da unificação semântica (Fase 3).
        // Em vez de apenas buscar conceitos, buscamos qualquer entidade semântica:
        // - Concepts: Termos técnicos para expansão (grafo de conhecimento).
        // - Subjects: Disciplinas principais (ex: Inglês, Matemática).
        // - Topics: Assuntos específicos (ex: Verbos, Geometria).
        $extractedSubjects = [];
        $extractedTopics   = [];
        $extractedOrgs     = [];
        $extractedInsts    = [];
        $searchPath = 'intent_match';

        try {
            // Obtém o threshold de detecção de intenção do banco de dados (default 0.75)
            $intentThreshold = (float) \App\Models\Configuration::get('xavier_concept_detection_threshold', config('xavier.embeddings.concept_detection_threshold', 0.75));

            // Busca na coleção de filtros semânticos as 5 entidades mais similares à query
            $intentMatches = $qdrant->searchIntents($queryVector, 5, $intentThreshold);

            foreach ($intentMatches as $match) {
                $payload = $match['payload'] ?? [];
                $type = $payload['entity_type'] ?? '';

                if ($type === 'subject' && isset($payload['subject_id'])) {
                    $extractedSubjects[] = (int) $payload['subject_id'];
                    Log::info("[Xavier][Search] INTENT: Subject #{$payload['subject_id']} ({$payload['name']})");
                } elseif ($type === 'topic' && isset($payload['topic_id'])) {
                    $extractedTopics[] = (int) $payload['topic_id'];
                    Log::info("[Xavier][Search] INTENT: Topic #{$payload['topic_id']} ({$payload['name']})");
                } elseif ($type === 'organization' && isset($payload['organization'])) {
                    $extractedOrgs[] = $payload['organization'];
                    Log::info("[Xavier][Search] INTENT: Org '{$payload['organization']}'");
                } elseif ($type === 'institution' && isset($payload['institution'])) {
                    $extractedInsts[] = $payload['institution'];
                    Log::info("[Xavier][Search] INTENT: Inst '{$payload['institution']}'");
                }
            }

            $extractedSubjects = array_values(array_unique($extractedSubjects));
            $extractedTopics   = array_values(array_unique($extractedTopics));
            $extractedOrgs     = array_values(array_unique($extractedOrgs));
            $extractedInsts    = array_values(array_unique($extractedInsts));

            Log::info('[Xavier][Search] Step 5 done: intent detection.', [
                'subject_ids'   => $extractedSubjects,
                'topic_ids'     => $extractedTopics,
                'organizations' => $extractedOrgs,
                'institutions'  => $extractedInsts,
            ]);
        } catch (\Exception $e) {
            Log::warning('[Xavier][Search] Step 5 FAILED: intent detection error.', ['err' => $e->getMessage()]);
        }

        // ── 5.1: Mesclar Detecções Léxicas (Fase 4) ───────────────────────────
        // Adicionamos o que o Analista Léxico detectou via Regex (ex: "FGV", "ENEM")
        // às detecções semânticas para garantir que nada passe despercebido.
        if (!empty($analysis['organizations'])) {
            $extractedOrgs = array_merge($extractedOrgs, $analysis['organizations']);
        }
        if (!empty($analysis['institutions'])) {
            $extractedInsts = array_merge($extractedInsts, $analysis['institutions']);
        }

        $extractedOrgs  = array_values(array_unique($extractedOrgs));
        $extractedInsts = array_values(array_unique($extractedInsts));

        // ── Step 5b: Fallback se nenhuma intenção for encontrada ───────────────
        if (empty($extractedSubjects) && empty($extractedTopics) && empty($extractedOrgs) && empty($extractedInsts)) {
            Log::info('[Xavier][Search] Step 5b: no intents found, proceeding with pure vector search.');
            $searchPath = 'vector_only';
        }

        // ── Step 5d: Detecção de Intenções Negativas e Restrições (Xavier 2.0) ──
        $excludedOrgs       = [];
        $excludedInsts      = [];
        $excludedSubjects   = [];
        $excludedTopics     = [];
        $excludedType       = null;
        
        // Entidades obrigatórias (MUST) vindas de "apenas / somente"
        $mustOrgs           = [];
        $mustInsts          = [];
        $mustSubjects       = [];
        $mustTopics         = [];

        // 1. Processar NEGAÇÕES (O que remover)
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

        // 2. Processar RESTRIÇÕES (O que travar como MUST)
        if ($analysis['is_restricted'] && !empty($analysis['restricted_terms'])) {
            try {
                foreach ($analysis['restricted_terms'] as $term) {
                    $mustVector = $aiService->generateEmbedding($term, $user->id, 'RETRIEVAL_QUERY');
                    $mustMatches = $qdrant->searchConcepts($mustVector, 3, 0.85); // Threshold ALTO para restrição ser precisa
                    
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

                    // Detecção de tipo via keyword no termo restrito
                    $lowerTerm = mb_strtolower($term);
                    if (str_contains($lowerTerm, 'concurso')) $sqlFilters['type'] = 'concurso';
                    if (str_contains($lowerTerm, 'enem')) $sqlFilters['type'] = 'enem';
                }
            } catch (\Exception $e) {
                Log::warning('[Xavier][Search] Restriction intent detection error.', ['err' => $e->getMessage()]);
            }
        }

        Log::info('[Xavier][Search] Intent Analysis Finalized.', [
            'exclusions'   => count($excludedOrgs) + count($excludedInsts) + count($excludedSubjects) + count($excludedTopics),
            'restrictions' => count($mustOrgs) + count($mustInsts) + count($mustSubjects) + count($mustTopics)
        ]);

        // ── Step 6: Expansão de Query via Co-ocorrência de Subjects/Topics ─────
        // Se detectamos Subjects ou Topics via Qdrant, expandimos para entidades
        // correlacionadas (e.g. Subject de Biologia → Topics como Genética, Citologia)
        // baseado em co-ocorrências reais nas questões do banco de dados.
        $expandedSubjectIds = $extractedSubjects;
        $expandedTopicIds   = $extractedTopics;
        
        if (!empty($extractedSubjects) || !empty($extractedTopics)) {
            $expanded = $expansion->expand($extractedSubjects, $extractedTopics);
            $expandedSubjectIds = $expanded['subject_ids'];
            $expandedTopicIds   = $expanded['topic_ids'];
        }

        Log::info('[Xavier][Search] Step 6 done: co-occurrence expansion.', [
            'original_subjects' => $extractedSubjects,
            'original_topics'   => $extractedTopics,
            'expanded_subjects' => $expandedSubjectIds,
            'expanded_topics' => $expandedTopicIds,
        ]);

        // Atualiza as intenções com os valores expandidos para uso no ReRank
        // (os expanded IDs substituem os originais no intent_filters)
        $extractedSubjects = $expandedSubjectIds;
        $extractedTopics   = $expandedTopicIds;

        // ── Step 5c: Detecção de Tipo (ENEM/Concurso) via Keywords ─────────────
        // Usamos o prompt POSITIVO para detectar o tipo desejado.
        $extractedType = null;
        $lowerPrompt = mb_strtolower($positivePrompt);
        if (str_contains($lowerPrompt, 'concurso')) {
            $extractedType = 'concurso';
        } elseif (str_contains($lowerPrompt, 'enem')) {
            $extractedType = 'enem';
        }

        // Xavier 2.0 (v8) — Generate all 5 query-side vectors in a single BATCH call
        // This is 5x faster than sequential and eliminates queue wait times for query generation.
        $statementQueryText   = $textBuilder->buildStatementQuery($positivePrompt);
        $conceptQueryText     = $textBuilder->buildConceptQuery($positivePrompt);
        $explanationQueryText = $textBuilder->buildExplanationQuery($positivePrompt);
        $alternativesQueryText = $textBuilder->buildAlternativesQuery($positivePrompt);
        $skillsQueryText       = $textBuilder->buildSkillsQuery($positivePrompt);

        // Prepare texts for batch
        $texts = [$statementQueryText, $conceptQueryText, $explanationQueryText, $alternativesQueryText, $skillsQueryText];
        $slots = ['statement', 'concept', 'explanation', 'alternatives', 'skills'];

        // Create the search request record
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt'  => $request->prompt,
            'status'  => 'generating',
        ]);

        $ttl = config('xavier.search_embeddings.ttl', 300);

        try {
            $vectors = $aiService->generateEmbeddingsBatch($texts, $user->id, 'RETRIEVAL_QUERY');

            if ($vectors && count($vectors) === 5) {
                // Store all vectors in Redis for RunVectorSearchJob to consume
                foreach ($slots as $idx => $slot) {
                    Cache::put("xavier:qembed:{$searchRequest->id}:{$slot}", $vectors[$idx], $ttl);
                }
                
                // Mark all 5 slots as "done" to trigger/satisfy the counter
                Redis::set("xavier:qembed_done:{$searchRequest->id}", 5);
                Redis::expire("xavier:qembed_done:{$searchRequest->id}", $ttl);
                
                Log::info("[Xavier][Search] Batch embeddings generated (5 vectors). Proceeding to Qdrant search.");
            } else {
                throw new \Exception("Batch embedding failed or returned incomplete results.");
            }
        } catch (\Exception $e) {
            Log::warning("[Xavier][Search] Batch embedding failed, search will use generic fallback. Error: " . $e->getMessage());
            // Counter must be 1 to trigger fallback in some logic or just handled by RunVectorSearchJob
            Redis::set("xavier:qembed_done:{$searchRequest->id}", 5); 
        }

        // Build search context for the runner
        $sqlFilters = ['keyword' => $request->prompt];
        if ($extractedType) $sqlFilters['type'] = $extractedType;
        if (!empty($extractedOrgs)) $sqlFilters['organization'] = $extractedOrgs;
        if (!empty($extractedInsts)) $sqlFilters['institution'] = $extractedInsts;
        
        // Exclusions/Restrictions (Xavier 2.0)
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

            'search_request_id' => $searchRequest->id,
            'slots'             => ['statement', 'concept', 'explanation'],
        ]);

        // Retorna imediatamente — frontend aguarda via polling leve no endpoint de status
        return response()->json([
            'status'     => 'generating',
            'request_id' => $searchRequest->id,
        ], 202);
    }


    /**

     * Legacy AI search path (SQL + LLM, existing behavior).
     * Used when VECTOR_SEARCH_ENABLED=false or when vector pipeline cannot proceed.
     * Mantido para backwards-compatibility — usa cache semântico L1/L2 antes
     * de disparar o InterpretSearchPromptJob assíncrono.
     */
    private function legacyAiSearch(Request $request, $user, SemanticCacheService $cacheService)
    {
        $userPrompt = trim(strtolower($request->prompt));

        // [Nível 1] Busca Exata (Hash MD5) - Instantâneo Síncrono
        $cachedFilters = $cacheService->findExactMatch($userPrompt);

        // [Nível 2] Busca por Similaridade (Embeddings) - Quase Instantâneo Síncrono
        if (!$cachedFilters) {
            $aiService = app(AIService::class);
            // Usa RETRIEVAL_QUERY para consistência com o pipeline vetorial
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

        // [Fallback Rápido] Realiza busca textual síncrona, ignorando LLM lento
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

        // Return question_ids if stored as vector result, otherwise return filters
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

        // if status is completed, we format the response to include all fields
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

