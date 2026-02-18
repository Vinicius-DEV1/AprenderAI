<?php

namespace App\Services;

use App\Models\Question;
use App\Models\UserQuestionAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class QuestionService
{
    /**
     * Busca questões com filtros dinâmicos e cumulativos.
     * Cada filtro é opcional — apenas os preenchidos são aplicados.
     */
    public function search(Request $request, ?int $userId = null): LengthAwarePaginator
    {
        $query = Question::with('subjects')
            ->complete()
            ->when($request->filled('keyword'), fn($q) =>
        $q->where('statement', 'like', '%' . $request->keyword . '%')
        )
            ->when($request->filled('type'), fn($q) =>
        $q->where('type', $request->type)
        )
            ->when($request->filled('subject'), fn($q) =>
        $q->whereHas('subjects', fn($s) =>
        $s->where('subjects.name', $request->subject)
        )
        )
            ->when($request->filled('topic'), fn($q) =>
        $q->where('topic', $request->topic)
        )
            ->when($request->filled('year'), fn($q) =>
        $q->where('year', $request->year)
        )
            ->when($request->filled('difficulty'), fn($q) =>
        $q->where('difficulty', $request->difficulty)
        )
            // Concurso-only filters — ignored when type=enem
            ->when($request->filled('organization') && $request->type !== 'enem', fn($q) =>
        $q->where('organization', $request->organization)
        )
            ->when($request->filled('institution') && $request->type !== 'enem', fn($q) =>
        $q->where('institution', $request->institution)
        )
            ->when($request->filled('role') && $request->type !== 'enem', fn($q) =>
        $q->where('role', $request->role)
        )
            ->when($request->filled('status') && $userId, function ($q) use ($request, $userId) {
            match ($request->status) {
                    'unanswered' => $q->whereDoesntHave('userAnswers', fn($r) => $r->where('user_id', $userId)),
                    'correct' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)->where('is_correct', true)),
                    'incorrect' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)->where('is_correct', false)),
                    'answered' => $q->whereHas('userAnswers', fn($r) => $r->where('user_id', $userId)),
                    default => null,
                };
        })
            ->orderByDesc('id');

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Opções de filtro globais para a UI.
     */
    public function getFilterOptions(): array
    {
        return [
            'subjects' => \App\Models\Subject::orderBy('name')->pluck('name'),

            'topics' => Question::select('topic')
            ->whereNotNull('topic')->where('topic', '!=', '')
            ->distinct()->orderBy('topic')->pluck('topic'),

            'years' => Question::select('year')
            ->whereNotNull('year')
            ->distinct()->orderByDesc('year')->pluck('year'),

            'organizations' => Question::select('organization')
            ->whereNotNull('organization')->where('organization', '!=', '')
            ->distinct()->orderBy('organization')->pluck('organization'),

            'institutions' => Question::select('institution')
            ->whereNotNull('institution')->where('institution', '!=', '')
            ->distinct()->orderBy('institution')->pluck('institution'),

            'roles' => Question::select('role')
            ->whereNotNull('role')->where('role', '!=', '')
            ->distinct()->orderBy('role')->pluck('role'),

            // Mapa de matérias por tipo — para Alpine.js filtrar dinamicamente
            'subjectsByType' => [
                'enem' => \App\Models\Subject::whereHas('questions', fn($q) => $q->where('type', 'enem'))
                ->orderBy('name')->pluck('name'),
                'concurso' => \App\Models\Subject::whereHas('questions', fn($q) => $q->where('type', 'concurso'))
                ->orderBy('name')->pluck('name'),
            ],
        ];
    }

    /**
     * Registra a resposta do aluno para uma questão avulsa.
     */
    public function answerQuestion(int $userId, Question $question, string $selectedAnswer): array
    {
        $isCorrect = $question->isCorrect($selectedAnswer);

        UserQuestionAnswer::updateOrCreate(
        ['user_id' => $userId, 'question_id' => $question->id],
        [
            'selected_answer' => strtoupper($selectedAnswer),
            'is_correct' => $isCorrect,
            'answered_at' => now(),
        ]
        );

        return [
            'correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'difficulty' => $question->difficulty,
            'difficulty_reasoning' => $question->difficulty_reasoning,
        ];
    }
}
