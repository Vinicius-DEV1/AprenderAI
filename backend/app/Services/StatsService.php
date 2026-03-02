<?php

namespace App\Services;

use App\Models\UserQuestionAnswer;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class StatsService
{
    /**
     * Retorna estatísticas gerais do aluno no banco de questões.
     */
    public function getOverview(int $userId): array
    {
        return Cache::remember("stats_overview_{$userId}", 300, function () use ($userId) {
            $answers = UserQuestionAnswer::forUser($userId);

            $total = (clone $answers)->count();
            $correct = (clone $answers)->correct()->count();
            $incorrect = $total - $correct;
            $accuracy = $total > 0 ? round(($correct / $total) * 100, 1) : 0;

            return [
                'total' => $total,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'accuracy' => $accuracy,
            ];
        });
    }

    /**
     * Performance por Matéria — dados para Chart.js (bar/radar).
     * Retorna: [{ subject: 'Matemática', total: 50, correct: 35, accuracy: 70.0 }, ...]
     */
    public function getPerformanceBySubject(int $userId): array
    {
        return Cache::remember("stats_subject_{$userId}", 300, function () use ($userId) {
            return DB::table('user_question_answers as uqa')
                ->join('question_subject as qs', 'uqa.question_id', '=', 'qs.question_id')
                ->join('subjects as s', 'qs.subject_id', '=', 's.id')
                ->where('uqa.user_id', $userId)
                ->groupBy('s.name')
                ->select([
                    's.name as subject',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN uqa.is_correct = 1 THEN 1 ELSE 0 END) as correct'),
                    DB::raw('ROUND(SUM(CASE WHEN uqa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as accuracy'),
                ])
                ->orderByDesc('total')
                ->get()
                ->toArray();
        });
    }

    /**
     * Evolução temporal — questões resolvidas por dia (últimos 30 dias).
     * Retorna: [{ date: '2026-02-18', total: 12, correct: 8 }, ...]
     */
    public function getTemporalEvolution(int $userId, int $days = 30): array
    {
        return Cache::remember("stats_temporal_{$userId}_{$days}", 300, function () use ($userId, $days) {
            return DB::table('user_question_answers')
                ->where('user_id', $userId)
                ->where('answered_at', '>=', Carbon::now()->subDays($days))
                ->groupBy('date')
                ->select([
                    DB::raw('DATE(answered_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct'),
                ])
                ->orderBy('date')
                ->get()
                ->toArray();
        });
    }

    /**
     * Heatmap de Dificuldade — distribuição de erros/acertos por difficulty.
     * Retorna: [{ difficulty: 'easy', total: 30, correct: 25, accuracy: 83.3 }, ...]
     */
    public function getDifficultyHeatmap(int $userId): array
    {
        return Cache::remember("stats_difficulty_{$userId}", 300, function () use ($userId) {
            return DB::table('user_question_answers as uqa')
                ->join('questions as q', 'uqa.question_id', '=', 'q.id')
                ->where('uqa.user_id', $userId)
                ->groupBy('q.difficulty')
                ->select([
                    'q.difficulty',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN uqa.is_correct = 1 THEN 1 ELSE 0 END) as correct'),
                    DB::raw('ROUND(SUM(CASE WHEN uqa.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as accuracy'),
                ])
                ->orderByRaw("FIELD(q.difficulty, 'easy', 'medium', 'hard')")
                ->get()
                ->toArray();
        });
    }
}
