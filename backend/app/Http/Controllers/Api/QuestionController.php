<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSearchRequest;
use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\SearchInteractionLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

use App\Jobs\GenerateQueryEmbeddingJob;
use App\Jobs\RunVectorSearchJob;
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

            // Sort by year and newest ID by default
            $query->orderBy('year', 'desc')->orderBy('id', 'desc');
        }

        $questions = $query->paginate($request->get('per_page', 20));

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

}

