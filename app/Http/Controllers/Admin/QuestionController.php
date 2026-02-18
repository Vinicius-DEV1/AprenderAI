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
        // === AI TRIAGE QUEUE (Unified: missing difficulty OR explanation) ===
        $pendingQuery = Question::with('subjects')->incomplete()->orderByDesc('id');

        // Triage-specific filters
        if ($request->filled('triage_search')) {
            $pendingQuery->where('statement', 'like', '%' . $request->triage_search . '%');
        }
        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                    'missing_difficulty' => $pendingQuery->missingField('difficulty_reasoning'),
                    'missing_explanation' => $pendingQuery->missingField('explanation'),
                    'both_missing' => $pendingQuery->missingField('difficulty_reasoning')
                    ->missingField('explanation'),
                    default => null,
                };
        }
        if ($request->filled('triage_subject')) {
            $pendingQuery->whereHas('subjects', function ($q) use ($request) {
                $q->where('subjects.name', $request->triage_subject);
            });
        }
        if ($request->filled('triage_origin')) {
            $pendingQuery->where('origin', $request->triage_origin);
        }

        $pendingCount = $pendingQuery->count();
        $pendingQuestions = $pendingQuery->paginate(10, ['*'], 'triage_page');

        // Sub-counters by type (global, unfiltered)
        $missingDifficultyCount = Question::missingField('difficulty_reasoning')->count();
        $missingExplanationCount = Question::missingField('explanation')->count();
        $bothMissingCount = Question::missingField('difficulty_reasoning')
            ->missingField('explanation')->count();

        // === GENERAL BANK (Only 100% complete questions) ===
        $query = Question::with('subjects')->complete();

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

        // Filter by origin
        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }

        $questions = $query->orderByDesc('id')->paginate(15);

        // Fetch all unique subject names for the filter
        $availableSubjects = \App\Models\Subject::orderBy('name')->pluck('name');

        // Fetch unique origins for triage filter
        $availableOrigins = Question::select('origin')
            ->whereNotNull('origin')
            ->where('origin', '!=', '')
            ->distinct()
            ->orderBy('origin')
            ->pluck('origin');

        // --- Mini-Dashboard Stats ---
        $totalQuestions = Question::count();
        $aiQuestions = Question::where('source', 'ai_generated')->count();

        // Group by origin
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
            'missingDifficultyCount',
            'missingExplanationCount',
            'bothMissingCount',
            'totalQuestions',
            'aiQuestions',
            'questionsByOrigin',
            'availableSubjects',
            'availableOrigins'
        ));
    }

    public function create()
    {
        return view('admin.questions.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string', // Name of the subject
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
            'difficulty_reasoning' => 'nullable|string',
            'origin' => 'nullable|string|max:255',
        ]);

        // Remove subject from validated before creation as column is dropped
        $subjectName = $validated['subject'];
        unset($validated['subject']);

        $question = Question::create($validated);

        // Sync Subject
        $subject = \App\Models\Subject::where('name', 'like', $subjectName)->first();
        if ($subject) {
            $question->subjects()->sync([$subject->id]);
        }

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
            'subject' => 'required|string', // Name of the subject
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
            'difficulty_reasoning' => 'nullable|string',
            'origin' => 'nullable|string|max:255',
        ]);

        // Remove subject from validated before update as column is dropped
        $subjectName = $validated['subject'];
        unset($validated['subject']);

        $question->update($validated);

        // Sync Subject
        $subject = \App\Models\Subject::where('name', 'like', $subjectName)->first();
        if ($subject) {
            $question->subjects()->sync([$subject->id]);
        }

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
            'message' => 'Avaliação de dificuldade iniciada em segundo plano.'
        ]);
    }

    public function generateExplanation(Question $question)
    {
        Log::info('[IA_QUEUE] Despachando geração de explicação para fila', [
            'question_id' => $question->id
        ]);

        \App\Jobs\GenerateExplanationJob::dispatch($question);

        return response()->json([
            'success' => true,
            'message' => 'Geração de explicação iniciada em segundo plano.'
        ]);
    }

    public function completeQuestion(Question $question)
    {
        Log::info('[IA_QUEUE] Despachando completar questão para fila', [
            'question_id' => $question->id
        ]);

        \App\Jobs\CompleteQuestionJob::dispatch($question);

        return response()->json([
            'success' => true,
            'message' => 'Processamento completo iniciado em segundo plano. Dificuldade e explicação serão preenchidas.'
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

    public function batchCompleteQuestions()
    {
        $count = Question::incomplete()->count();

        if ($count === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Não há questões pendentes para completar.'
            ]);
        }

        \App\Jobs\ProcessTriageBatchJob::dispatch(10);

        return response()->json([
            'success' => true,
            'message' => 'O processamento em lote foi iniciado em segundo plano. Dificuldade e explicação serão preenchidas.',
            'total_pending' => $count
        ]);
    }
}
