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
        $query = Question::published()->with(['subjects', 'topics', 'alternatives', 'images']);

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
            'selected_answer' => 'required|string|size:1|in:A,B,C,D,E',
        ]);

        $feedback = app(\App\Services\QuestionService::class)->answerQuestion(
            $request->user()->id,
            $question,
            $request->selected_answer
        );

        // Consome a cota diária do usuário
        $request->user()->incrementDailyQuestionUsage();

        return response()->json($feedback);
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

        // 5. Dispatch AI background job
        $lastAnswer = \App\Models\UserQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->orderByDesc('answered_at')
            ->value('selected_answer') ?? 'Não respondida';

        RespondToStandaloneChatJob::dispatch(
            $question,
            $lastAnswer,
            $request->message,
            $history,
            $user->id
        );

        return response()->json(['status' => 'queued']);
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

        // Create the tracking record
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt' => $request->prompt,
            'status' => 'pending',
        ]);

        // Increment quota usage immediately
        $user->incrementAiUsage();

        // Dispatch background job to interpret the prompt
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
}
