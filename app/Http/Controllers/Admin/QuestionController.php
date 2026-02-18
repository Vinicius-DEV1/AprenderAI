<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        // === AI TRIAGE QUEUE ===
        // Questions without AI evaluation (difficulty_reasoning is null or empty)
        $pendingQuery = Question::with('subjects')
            ->where(function ($q) {
            $q->whereNull('difficulty_reasoning')
                ->orWhere('difficulty_reasoning', '')
                ->orWhereRaw("TRIM(difficulty_reasoning) = ''");
        })
            ->orderByDesc('id');

        $pendingCount = $pendingQuery->count();
        $pendingQuestions = $pendingQuery->limit(5)->get(); // Initial 5 for preview

        // === GENERAL BANK ===
        // Questions that have been evaluated by AI (with non-empty reasoning)
        $query = Question::with('subjects')
            ->whereNotNull('difficulty_reasoning')
            ->where('difficulty_reasoning', '!=', '')
            ->whereRaw("TRIM(difficulty_reasoning) != ''"); // Exclude pending and empty

        // Apply existing filters (only to general bank)
        if ($request->filled('search')) {
            $query->where('statement', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('subject')) {
            $query->whereHas('subjects', function ($q) use ($request) {
                $q->where('subjects.name', $request->subject);
            });
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Filter for missing explanations (to help admin prioritize)
        if ($request->boolean('missing_explanation')) {
            $query->where(function ($q) {
                $q->whereNull('explanation')->orWhere('explanation', '');
            });
        }

        // Filter by origin (new)
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }

        $questions = $query->orderByDesc('id')->paginate(15);

        // Fetch all unique subject names for the filter
        $availableSubjects = \App\Models\Subject::orderBy('name')->pluck('name');

        // --- Mini-Dashboard Stats ---
        $totalQuestions = Question::count();
        $aiQuestions = Question::where('source', 'ai_generated')->count();

        // Group by origin (excluding null/empty which are likely generic manual or AI)
        $questionsByOrigin = Question::select('origin', DB::raw('count(*) as total'))
            ->whereNotNull('origin')
            ->where('origin', '!=', '')
            ->where('origin', '!=', 'IA')
            ->groupBy('origin')
            ->orderByDesc('total')
            ->get();

        return view('admin.questions.index', compact(
            'questions',
            'pendingQuestions',
            'pendingCount',
            'totalQuestions',
            'aiQuestions',
            'questionsByOrigin',
            'availableSubjects'
        ));
    }

    public function create()
    {
        return view('admin.questions.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|in:matemática,português',
            'type' => 'required|in:enem,concurso',
            'statement' => 'required|string',
            'alternatives' => 'required|array|min:5', // A, B, C, D, E
            'alternatives.A' => 'required|string',
            'alternatives.B' => 'required|string',
            'alternatives.C' => 'required|string',
            'alternatives.D' => 'required|string',
            'alternatives.E' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D,E',
            'explanation' => 'nullable|string',
            'source' => 'required|in:manual,ai_generated',
            'year' => 'nullable|integer',
            'difficulty' => 'required|in:easy,medium,hard',
            'origin' => 'nullable|string|max:255',
        ]);

        Question::create($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão criada com sucesso!');
    }

    public function edit(Question $question)
    {
        return view('admin.questions.form', compact('question'));
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'subject' => 'required|in:matemática,português',
            'type' => 'required|in:enem,concurso',
            'statement' => 'required|string',
            'alternatives' => 'required|array|min:5',
            'alternatives.A' => 'required|string',
            'alternatives.B' => 'required|string',
            'alternatives.C' => 'required|string',
            'alternatives.D' => 'required|string',
            'alternatives.E' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D,E',
            'explanation' => 'nullable|string',
            'source' => 'required|in:manual,ai_generated',
            'year' => 'nullable|integer',
            'difficulty' => 'required|in:easy,medium,hard',
            'origin' => 'nullable|string|max:255',
        ]);

        $question->update($validated);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão atualizada com sucesso!');
    }

    public function destroy(Question $question)
    {
        $question->delete();
        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão removida!');
    }

    public function evaluateDifficulty(Question $question)
    {
        Log::info('[IA_QUEUE] Despachando avaliação individual para fila', [
            'question_id' => $question->id
        ]);

        \App\Jobs\EvaluateQuestionDifficultyJob::dispatch($question);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação iniciada em segundo plano. A questão será processada em breve.'
        ]);
    }

    public function batchEvaluateDifficulty(\App\Services\AIService $aiService)
    {
        $count = Question::whereNull('difficulty_reasoning')->count();

        if ($count === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Não há questões pendentes para processar.'
            ]);
        }

        \App\Jobs\ProcessDifficultyBatchJob::dispatch(10);

        return response()->json([
            'success' => true,
            'message' => 'O processamento em lote foi iniciado em segundo plano. Isso levará alguns minutos.',
            'total_pending' => $count
        ]);
    }
}
