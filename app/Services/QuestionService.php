<?php

namespace App\Services;

use App\Models\Question;
use App\Models\UserQuestionAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    /**
     * Busca questões completas com filtros dinâmicos.
     * Retorna paginação com questões prontas para resolução.
     */
    public function search(Request $request, ?int $userId = null): LengthAwarePaginator
    {
        $query = Question::with('subjects')
            ->complete() // Só questões com explicação + dificuldade
            ->orderByDesc('id');

        // Keyword (enunciado)
        if ($request->filled('keyword')) {
            $query->where('statement', 'like', '%' . $request->keyword . '%');
        }

        // Matéria (Subject via M2M)
        if ($request->filled('subject')) {
            $query->whereHas('subjects', function ($q) use ($request) {
                $q->where('subjects.name', $request->subject);
            });
        }

        // Assunto (Topic)
        if ($request->filled('topic')) {
            $query->where('topic', $request->topic);
        }

        // Ano
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        // Banca (Organization)
        if ($request->filled('organization')) {
            $query->where('organization', $request->organization);
        }

        // Órgão (Institution)
        if ($request->filled('institution')) {
            $query->where('institution', $request->institution);
        }

        // Cargo (Role)
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Dificuldade
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Tipo
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Status (respondida/não respondida/acertei/errei) — requer userId
        if ($request->filled('status') && $userId) {
            match ($request->status) {
                    'unanswered' => $query->whereDoesntHave('userAnswers', fn($q) => $q->where('user_id', $userId)),
                    'correct' => $query->whereHas('userAnswers', fn($q) => $q->where('user_id', $userId)->where('is_correct', true)),
                    'incorrect' => $query->whereHas('userAnswers', fn($q) => $q->where('user_id', $userId)->where('is_correct', false)),
                    'answered' => $query->whereHas('userAnswers', fn($q) => $q->where('user_id', $userId)),
                    default => null,
                };
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Retorna as opções de filtro disponíveis para a UI.
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
        ];
    }

    /**
     * Registra a resposta do aluno para uma questão avulsa.
     * Retorna os dados de feedback.
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
