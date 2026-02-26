<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * List all questions (including unpublished) for admin.
     */
    public function index(Request $request)
    {
        $query = Question::with(['subjects', 'topics']);

        // --- Filters ---
        if ($request->filled('status')) {
            $query->where('review_status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('statement', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('subject')) {
            $query->filterBySubject($request->subject);
        }

        if ($request->filled('organization')) {
            $query->where('organization', $request->organization);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Triage Filters
        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                'missing_difficulty' => $query->missingField('difficulty_reasoning'),
                'missing_explanation' => $query->missingField('explanation'),
                'missing_classification' => $query->whereDoesntHave('subjects')->orWhereDoesntHave('topics'),
                'both_missing' => $query->incomplete(),
                default => null
            };
        }

        // Pagination for main bank
        $questions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        // Separate query for triage list
        $triageQuery = Question::incomplete();
        if ($request->filled('triage_search')) {
            $triageQuery->where('statement', 'like', '%' . $request->triage_search . '%');
        }
        if ($request->filled('triage_subject')) {
            $triageQuery->filterBySubject($request->triage_subject);
        }
        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                'missing_difficulty' => $triageQuery->missingField('difficulty_reasoning'),
                'missing_explanation' => $triageQuery->missingField('explanation'),
                'missing_classification' => $triageQuery->whereDoesntHave('subjects')->orWhereDoesntHave('topics'),
                'both_missing' => $triageQuery->incomplete(),
                default => null
            };
        }
        $pendingQuestions = $triageQuery->orderByDesc('created_at')->paginate(10, ['*'], 'triage_page');

        // --- Metadata for Dashboard & Triage ---
        $stats = [
            'total_questions' => Question::count(),
            'ai_questions' => Question::where('source', 'ai_generated')->count(),
            'questions_by_organization' => Question::select('organization', \DB::raw('count(*) as total'))
                ->whereNotNull('organization')
                ->groupBy('organization')
                ->orderByDesc('total')
                ->take(2)
                ->get(),
        ];

        $counts = [
            'pending_total' => Question::incomplete()->count(),
            'missing_difficulty' => Question::missingField('difficulty_reasoning')->count(),
            'missing_explanation' => Question::missingField('explanation')->count(),
            'missing_classification' => Question::whereDoesntHave('subjects')->orWhereDoesntHave('topics')->count(),
            'both_missing' => Question::incomplete()->count(),
        ];

        return response()->json([
            'questions' => $questions,
            'pendingQuestions' => $pendingQuestions,
            'meta' => $stats,
            'counts' => $counts,
            'availableSubjects' => \App\Models\Subject::orderBy('name')->pluck('name'),
            'availableOrganizations' => Question::whereNotNull('organization')->distinct()->pluck('organization'),
        ]);
    }

    /**
     * Store a new question.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'statement' => 'required|string',
            'explanation' => 'nullable|string',
            'year' => 'nullable|integer',
            'organization' => 'nullable|string',
            'institution' => 'nullable|string',
            'role' => 'nullable|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'type' => 'required|in:enem,concurso',
            'is_published' => 'boolean',
        ]);

        $question = Question::create($validated);

        return response()->json([
            'message' => 'Questão criada com sucesso!',
            'question' => new QuestionResource($question)
        ], 201);
    }

    /**
     * Update a question.
     */
    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'statement' => 'required|string',
            'explanation' => 'nullable|string',
            'is_published' => 'boolean',
        ]);

        $question->update($validated);

        return response()->json([
            'message' => 'Questão atualizada com sucesso!',
            'question' => new QuestionResource($question)
        ]);
    }

    /**
     * Evaluate difficulty using AI.
     */
    public function evaluateDifficulty(Question $question)
    {
        \App\Jobs\EvaluateQuestionDifficultyJob::dispatch($question);
        return response()->json(['success' => true, 'message' => 'Avaliação de dificuldade iniciada!']);
    }

    /**
     * Generate explanation using AI.
     */
    public function generateExplanation(Question $question)
    {
        // Note: Original uses GenerateExplanationJob (singular)
        \App\Jobs\GenerateExplanationJob::dispatch($question);
        return response()->json(['success' => true, 'message' => 'Geração de explicação iniciada!']);
    }

    /**
     * Complete question (IA Full)
     */
    public function completeQuestion(Question $question)
    {
        \App\Jobs\CompleteQuestionJob::dispatch($question);
        return response()->json(['success' => true, 'message' => 'Processamento IA Full iniciado!']);
    }

    /**
     * Classify question (Subjects/Topics)
     */
    public function classifyQuestion(Question $question)
    {
        \App\Jobs\AIBatchTriageJob::dispatch(
            \Illuminate\Support\Str::uuid()->toString(),
            [$question->id],
            'classification',
            null
        );
        return response()->json(['success' => true, 'message' => 'Classificação iniciada!']);
    }

    /**
     * Remove a question.
     */
    public function destroy(Question $question)
    {
        $question->delete();
        return response()->json(['message' => 'Questão removida com sucesso!']);
    }
}
