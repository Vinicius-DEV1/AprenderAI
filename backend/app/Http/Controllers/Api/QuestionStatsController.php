<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionStatsController extends Controller
{
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
     * Statistics for a specific question.
     */
    public function questionStats(Request $request, Question $question)
    {
        $showStats = filter_var(env('SHOW_QUESTION_STATS_TO_USERS', false), FILTER_VALIDATE_BOOLEAN);

        if (!$showStats && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Estatísticas indisponíveis. Ocultas no momento.'], 403);
        }

        $answers = \App\Models\UserQuestionAnswer::where('question_id', $question->id)->get();
        if ($answers->isEmpty()) {
            return response()->json(['total_responses' => 0]);
        }

        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();

        $stats = [
            'difficulty' => $question->difficulty ?? 'N/A',
            'total_responses' => $total,
            'correct_percentage' => round(($correct / $total) * 100, 2),
            'incorrect_percentage' => round((($total - $correct) / $total) * 100, 2),
            'average_time_seconds' => round($answers->avg('time_spent_seconds') ?? 0, 2),
            'alternative_distribution' => $answers->groupBy('selected_answer')->map(fn($group) => round(($group->count() / $total) * 100, 2)),
        ];

        return response()->json($stats);
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
