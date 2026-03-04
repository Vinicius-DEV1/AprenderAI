<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\AiSearchRequest;
use App\Jobs\RespondToStandaloneChatJob;
use App\Jobs\InterpretSearchPromptJob;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Display a listing of questions for the question bank.
     */
    public function index(Request $request)
    {
        $userId = $request->user('sanctum')?->id;
        $query = Question::published()->with(['subjects', 'topics', 'alternatives', 'images']);

        if ($userId) {
            $query->withExists(['favorites as is_favorite' => fn($q) => $q->where('user_id', $userId)])
                ->withExists(['notes as has_notes' => fn($q) => $q->where('user_id', $userId)])
                ->with(['notebooks' => fn($q) => $q->where('user_id', $userId)]);
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
                        echo "data: " . $chunkBuffer . "\n\n";
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
     */
    public function aiSearch(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:500',
        ]);

        $user = $request->user();

        if (!$user->hasAiQuota()) {
            return response()->json([
                'status' => 'error',
                'code' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de consultas de inteligência artificial do seu plano.',
                'upgrade_url' => '/plans'
            ], 403);
        }

        $userPrompt = trim(strtolower($request->prompt));
        $cacheService = app(\App\Services\AI\SemanticCacheService::class);

        // [Nível 1] Busca Exata (Hash MD5) - Instantâneo Síncrono
        $cachedFilters = $cacheService->findExactMatch($userPrompt);

        // [Nível 2] Busca por Similaridade (Embeddings) - Quase Instantâneo Síncrono (~500ms)
        if (!$cachedFilters) {
            $aiService = app(\App\Services\AI\AIService::class);
            $vector = $aiService->generateEmbedding($userPrompt);
            if ($vector) {
                $cachedFilters = $cacheService->findSimilarMatch($vector, 0.94);
            }
        }

        // Se encontrou no Cache Instantâneo da Request HTTTP:
        if ($cachedFilters) {
            $user->incrementAiUsage();

            // Grava histórico p/ Analytics
            AiSearchRequest::create([
                'user_id' => $user->id,
                'prompt' => $request->prompt,
                'status' => 'completed',
                'filters' => $cachedFilters,
            ]);

            return response()->json([
                'status' => 'completed',
                'filters' => $cachedFilters,
                'suggestion_tip' => $cachedFilters['suggestion_tip'] ?? null,
                'suggestions' => $cachedFilters['suggestions'] ?? [],
            ], 200);
        }

        // Caso Inédito: Cria o registro e manda pro Xavier trabalhar na Fila Background
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt' => $request->prompt,
            'status' => 'pending',
        ]);

        $user->incrementAiUsage();
        InterpretSearchPromptJob::dispatch($searchRequest);

        return response()->json([
            'status' => 'queued',
            'request_id' => $searchRequest->id
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
