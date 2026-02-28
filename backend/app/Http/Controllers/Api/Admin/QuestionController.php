<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Get support data for the question form.
     */
    public function supportData()
    {
        return response()->json([
            'subjects' => \App\Models\Subject::orderBy('name')->get(),
            'topics' => \App\Models\Topic::orderBy('name')->get(),
        ]);
    }

    /**
     * List all questions (including unpublished) for admin.
     */
    public function index(Request $request)
    {
        // --- 1. Main Bank Query ---
        // Exibimos apenas questões 100% classificadas no Banco Completo
        // Aplicamos redundância de filtros para garantir a exclusão de sem-matéria
        $query = Question::complete()
            ->has('subjects')
            ->has('topics')
            ->with(['subjects:id,name', 'topics:id,name']);

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

        $questions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        // --- 2. Triage Bank Query ---
        $triageQuery = Question::incomplete()->with(['subjects:id,name', 'topics:id,name']);

        if ($request->filled('triage_search')) {
            $triageQuery->where('statement', 'like', '%' . $request->triage_search . '%');
        }
        if ($request->filled('triage_status')) {
            match ($request->triage_status) {
                'missing_difficulty' => $triageQuery->missingField('difficulty_reasoning'),
                'missing_explanation' => $triageQuery->missingField('explanation'),
                'missing_classification' => $triageQuery->whereDoesntHave('subjects')->orWhereDoesntHave('topics'),
                'both_missing' => $triageQuery->missingField('difficulty_reasoning')->missingField('explanation')->whereDoesntHave('subjects')->whereDoesntHave('topics'),
                default => null
            };
        }
        if ($request->filled('triage_subject')) {
            $triageQuery->filterBySubject($request->triage_subject);
        }
        if ($request->filled('triage_organization')) {
            $triageQuery->where('organization', $request->triage_organization);
        }

        $triageQuestions = $triageQuery->orderByDesc('created_at')->paginate(10, ['*'], 'triage_page', $request->get('triage_page', 1));

        // --- 3. Stats & Available Filters ---
        $stats = [
            'total_questions' => Question::count(),
            'ai_questions' => Question::where('source', 'ai_generated')->count(),
            'questions_by_organization' => \DB::table('questions')
                ->select('organization', \DB::raw('count(*) as total'))
                ->whereNotNull('organization')
                ->groupBy('organization')
                ->orderByDesc('total')
                ->get()
        ];

        $counts = [
            'pending_total' => Question::incomplete()->count(),
            'missing_difficulty' => Question::missingField('difficulty_reasoning')->count(),
            'missing_explanation' => Question::missingField('explanation')->count(),
            'missing_classification' => Question::whereDoesntHave('subjects')->orWhereDoesntHave('topics')->count(),
            'both_missing' => Question::incomplete()->count(), // Simplified to avoid complex intersection query for counts, just total incomplete
        ];

        return response()->json([
            'questions' => $questions,
            'pendingQuestions' => $triageQuestions,
            'meta' => $stats,
            'counts' => $counts,
            'availableSubjects' => \App\Models\Subject::orderBy('name')->pluck('name'),
            'availableOrganizations' => \DB::table('questions')->whereNotNull('organization')->distinct()->pluck('organization'),
            'DEBUG_CODE_VERSION' => 'FILTER_V2_' . time(),
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
            'correct_answer' => 'required|string',
            'year' => 'nullable|integer',
            'organization' => 'nullable|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'difficulty_reasoning' => 'nullable|string',
            'type' => 'required|in:enem,concurso',
            'format' => 'required|in:multiple_choice,true_false',
            'source' => 'nullable|string',
            'subject' => 'nullable|string',
            'topic' => 'nullable|string',
            'alternatives' => 'nullable|array',
            'tipo_questao' => 'nullable|string',
            'discursive_answer' => 'nullable',
        ]);

        \DB::beginTransaction();
        try {
            $questionData = collect($validated)->except(['subject', 'topic', 'alternatives'])->toArray();
            $question = Question::create($questionData);

            // Sync Subject
            if ($request->filled('subject')) {
                $subject = \App\Models\Subject::firstOrCreate(['name' => $request->subject, 'slug' => \Str::slug($request->subject)]);
                $question->subjects()->sync([$subject->id]);
            }

            // Sync Topic
            if ($request->filled('topic')) {
                $topic = \App\Models\Topic::firstOrCreate(['name' => $request->topic, 'slug' => \Str::slug($request->topic)]);
                $question->topics()->sync([$topic->id]);
            }

            // Sync Alternatives
            if ($request->filled('alternatives')) {
                foreach ($request->alternatives as $label => $content) {
                    $question->alternatives()->create([
                        'label' => $label,
                        'content' => $content,
                        'is_correct' => $label === $validated['correct_answer']
                    ]);
                }
            }

            \DB::commit();
            return response()->json(['message' => 'Questão criada!', 'question' => $question], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Erro ao criar: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $question = Question::with(['subjects', 'topics', 'alternatives'])->findOrFail($id);
        return response()->json($question);
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'statement' => 'required|string',
            'explanation' => 'nullable|string',
            'correct_answer' => 'required|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'difficulty_reasoning' => 'nullable|string',
            'type' => 'required|in:enem,concurso',
            'format' => 'required|in:multiple_choice,true_false',
            'subject' => 'nullable|string',
            'topic' => 'nullable|string',
            'alternatives' => 'nullable|array',
            'tipo_questao' => 'nullable|string',
            'discursive_answer' => 'nullable',
        ]);

        \DB::beginTransaction();
        try {
            $question->update(collect($validated)->except(['subject', 'topic', 'alternatives'])->toArray());

            if ($request->filled('subject')) {
                $subject = \App\Models\Subject::firstOrCreate(['name' => $request->subject, 'slug' => \Str::slug($request->subject)]);
                $question->subjects()->sync([$subject->id]);
            }

            if ($request->filled('topic')) {
                $topic = \App\Models\Topic::firstOrCreate(['name' => $request->topic, 'slug' => \Str::slug($request->topic)]);
                $question->topics()->sync([$topic->id]);
            }

            if ($request->filled('alternatives')) {
                $question->alternatives()->delete();
                foreach ($request->alternatives as $label => $content) {
                    $question->alternatives()->create([
                        'label' => $label,
                        'content' => $content,
                        'is_correct' => $label === $validated['correct_answer']
                    ]);
                }
            }

            \DB::commit();
            return response()->json(['message' => 'Questão atualizada!', 'question' => $question]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
        }
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
