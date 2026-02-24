<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuestionController
 * 
 * Controlador central para gerenciamento do banco de questões no Painel Administrativo.
 * Lida com a triagem de questões incompletas (IA), edição manual e comandos de processamento em lote.
 */
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
                'missing_classification' => $pendingQuery->where(function ($q) {
                    $q->whereDoesntHave('subjects')->orWhereDoesntHave('topics');
                }),
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
        if ($request->filled('triage_organization')) {
            $pendingQuery->where('organization', $request->triage_organization);
        }

        $pendingCount = $pendingQuery->count();
        $pendingQuestions = $pendingQuery->paginate(10, ['*'], 'triage_page');

        // Sub-counters by type (global, unfiltered)
        $missingDifficultyCount = Question::missingField('difficulty_reasoning')->count();
        $missingExplanationCount = Question::missingField('explanation')->count();
        $missingClassificationCount = Question::whereDoesntHave('subjects')->orWhereDoesntHave('topics')->count();
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

        // Filter by organization
        if ($request->filled('organization')) {
            $query->where('organization', $request->organization);
        }

        $questions = $query->orderByDesc('id')->paginate(15);

        // Fetch all unique subject names for the filter
        $availableSubjects = \App\Models\Subject::orderBy('name')->pluck('name');

        // Fetch unique organizations for triage filter
        $availableOrganizations = Question::select('organization')
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->distinct()
            ->orderBy('organization')
            ->pluck('organization');

        // --- Mini-Dashboard Stats ---
        $totalQuestions = Question::count();
        $aiQuestions = Question::where('source', 'ai_generated')->count();

        // Group by organization
        $questionsByOrganization = Question::select('organization', DB::raw('count(*) as total'))
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->where('organization', '!=', 'IA')
            ->groupBy('organization')
            ->orderByDesc('total')
            ->get();

        // Active AI Models (unique by preferred_model)
        $aiModels = \App\Models\ApiKey::where('is_active', true)
            ->where('status', 'online')
            ->orderBy('is_primary', 'desc')
            ->get(['provider', 'preferred_model'])
            ->unique('preferred_model');

        return view('admin.questions.index', compact(
            'questions',
            'pendingQuestions',
            'pendingCount',
            'missingDifficultyCount',
            'missingExplanationCount',
            'missingClassificationCount',
            'bothMissingCount',
            'totalQuestions',
            'aiQuestions',
            'questionsByOrganization',
            'availableSubjects',
            'availableOrganizations',
            'aiModels'
        ));
    }

    public function create()
    {
        $subjects = \App\Models\Subject::orderBy('name')->get();
        $topics = \App\Models\Topic::orderBy('name')->get();
        return view('admin.questions.form', compact('subjects', 'topics'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'               => 'required|string',
            'topic'                 => 'nullable|string',
            'type'                  => 'required|in:enem,concurso',
            'format'                => 'required|in:multiple_choice,true_false',
            'statement'             => 'required|string',
            'alternatives'          => 'required|array',
            'correct_answer'        => 'required|string',
            'explanation'           => 'nullable|string',
            'source'                => 'required|in:manual,ai_generated',
            'year'                  => 'nullable|integer',
            'difficulty'            => 'required|in:easy,medium,hard',
            'difficulty_reasoning'  => 'nullable|string',
            'organization'          => 'nullable|string|max:255',
        ]);

        // Extrai campos que NÃO são colunas da tabela questions (foram migrados)
        $subjectName   = $validated['subject'];      
        $topicName     = $validated['topic'] ?? null;
        $alternativas  = $validated['alternatives']; 
        $correctAnswer = $validated['correct_answer']; 
        unset($validated['subject'], $validated['topic'], $validated['alternatives'], $validated['correct_answer']);

        $question = Question::create($validated);

        // Salva cada alternativa como uma linha em question_alternatives
        foreach ($alternativas as $label => $content) {
            $question->alternatives()->create([
                'label'      => strtoupper($label),
                'content'    => $content,
                'is_correct' => (strtoupper($label) === strtoupper($correctAnswer)),
            ]);
        }

        // Vincula a disciplina via pivot question_subject
        $subject = \App\Models\Subject::where('name', 'like', $subjectName)->first();
        if ($subject) {
            $question->subjects()->sync([$subject->id]);
        }

        // Vincula o tópico via pivot question_topic (N:N)
        if ($topicName) {
            $topic = \App\Models\Topic::where('name', 'like', $topicName)->first();
            if ($topic) {
                $question->topics()->sync([$topic->id]);
            }
        }

        return redirect()->route('admin.questions.index')
            ->with('success', 'Questão criada com sucesso!');
    }

    public function edit(Question $question)
    {
        $question->load('alternatives', 'subjects', 'topics');
        $subjects = \App\Models\Subject::orderBy('name')->get();
        $topics = \App\Models\Topic::orderBy('name')->get();
        return view('admin.questions.form', compact('question', 'subjects', 'topics'));
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'subject'               => 'required|string',
            'topic'                 => 'nullable|string',
            'type'                  => 'required|in:enem,concurso',
            'format'                => 'required|in:multiple_choice,true_false',
            'statement'             => 'required|string',
            'alternatives'          => 'required|array',
            'correct_answer'        => 'required|string',
            'explanation'           => 'nullable|string',
            'source'                => 'required|in:manual,ai_generated',
            'year'                  => 'nullable|integer',
            'difficulty'            => 'required|in:easy,medium,hard',
            'difficulty_reasoning'  => 'nullable|string',
            'organization'          => 'nullable|string|max:255',
        ]);

        // Extrai campos que NÃO são colunas da tabela questions
        $subjectName   = $validated['subject'];
        $topicName     = $validated['topic'] ?? null;
        $alternativas  = $validated['alternatives'];
        $correctAnswer = $validated['correct_answer'];
        unset($validated['subject'], $validated['topic'], $validated['alternatives'], $validated['correct_answer']);

        $question->update($validated);

        // Limpa alternativas que não fazem mais parte do formato (ex: mudar de MC para TF deleta A,B,D,E)
        $question->alternatives()->whereNotIn('label', array_map('strtoupper', array_keys($alternativas)))->delete();

        // Atualiza as alternativas existentes ou cria novas sem excluir os metadados antigos (ex: image_path)
        foreach ($alternativas as $label => $content) {
            \App\Models\QuestionAlternative::updateOrCreate(
                [
                    'question_id' => $question->id,
                    'label'       => strtoupper($label),
                ],
                [
                    'content'     => $content ?? '',
                    'is_correct'  => (strtoupper($label) === strtoupper($correctAnswer)),
                ]
            );
        }

        // Atualiza a disciplina via pivot
        $subject = \App\Models\Subject::where('name', 'like', $subjectName)->first();
        if ($subject) {
            $question->subjects()->sync([$subject->id]);
        }

        // Atualiza o tópico via pivot (N:N)
        if ($topicName) {
            $topic = \App\Models\Topic::where('name', 'like', $topicName)->first();
            if ($topic) {
                $question->topics()->sync([$topic->id]);
            }
        } else {
            $question->topics()->detach();
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

    public function classifyQuestion(Question $question)
    {
        Log::info('[IA_QUEUE] Despachando classificação N:N individual para fila', [
            'question_id' => $question->id
        ]);

        // Reusing the CompleteJob (which we will adapt or trust it handles the new type logic internally by routing as a batch of 1)
        // Alternatively we can use a fresh Job, but for now we route to CompleteJob and we'll ensure AIBatchService handles it.
        // Actually, since AIBatchTriageJob handles 'classification' cleanly, let's use it as a batch of 1.
        \App\Jobs\AIBatchTriageJob::dispatch(
            \Illuminate\Support\Str::uuid()->toString(),
            [$question->id],
            'classification',
            null // uses default model
        );

        return response()->json([
            'success' => true,
            'message' => 'Classificação de Matéria e Assunto iniciada em segundo plano.'
        ]);
    }

    public function batchEvaluateDifficulty(\App\Services\AI\AIService $aiService)
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
