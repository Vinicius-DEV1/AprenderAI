<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
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
        $question->load(['subject', 'topic', 'alternatives', 'images']);
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
}
