<?php

namespace App\Services;

use App\Models\QuestionEventLog;
use App\Models\Question;
use Illuminate\Http\Request;

class AnalyticsService
{
    /**
     * Registra quando um aluno visualiza uma questão na tela
     */
    public function logView(Question $question, int $userId, Request $request, ?string $source = 'banco'): void
    {
        QuestionEventLog::create([
            'user_id' => $userId,
            'question_id' => $question->id,
            'subject_id' => $question->subjects->first()->id ?? null,
            'action' => 'viewed',
            'is_correct' => null,
            'time_spent_seconds' => null,
            'source' => $source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Registra quando um aluno responde uma questão
     */
    public function logAnswer(Question $question, int $userId, bool $isCorrect, ?int $timeSpentSeconds, Request $request, ?string $source = 'banco'): void
    {
        QuestionEventLog::create([
            'user_id' => $userId,
            'question_id' => $question->id,
            'subject_id' => $question->subjects->first()->id ?? null,
            'action' => 'answered',
            'is_correct' => $isCorrect,
            'time_spent_seconds' => $timeSpentSeconds,
            'source' => $source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
