<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDailyStat;
use Illuminate\Support\Carbon;

class UserEngagementService
{
    /**
     * Atualiza as estatísticas diárias do usuário após uma resposta.
     */
    public function updateDailyStats(int $userId, bool $isCorrect): void
    {
        $today = Carbon::today()->toDateString();
        $stat = UserDailyStat::firstOrCreate(
            ['user_id' => $userId, 'date' => $today],
            ['total_answered' => 0, 'total_correct' => 0]
        );

        $stat->increment('total_answered');
        if ($isCorrect) {
            $stat->increment('total_correct');
        }
    }

    /**
     * Calcula a sequência (streak) de dias consecutivos de atividade do usuário.
     */
    public function calculateStreak(int $userId): int
    {
        $stats = UserDailyStat::where('user_id', $userId)
            ->where('total_answered', '>', 0)
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        // Se não tem respostas, streak é 0
        if (empty($stats)) {
            return 0;
        }

        $streak = 0;
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Se respondeu hoje ou ontem, o streak está vivo.
        // Se a data mais recente for antes de ontem, streak quebrou e é 0.
        if ($stats[0] !== $today && $stats[0] !== $yesterday) {
            return 0;
        }

        // Se respondeu hoje, já começamos o streak em 1
        // Se não respondeu hoje, mas sim ontem, começamos em 1 também (o de ontem).
        $expectedDate = Carbon::parse($stats[0]);

        foreach ($stats as $dateStr) {
            if ($dateStr === $expectedDate->toDateString()) {
                $streak++;
                $expectedDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Retorna o consolidado de engajamento para o Micro Dashboard.
     */
    public function getEngagementData(int $userId): array
    {
        $user = User::find($userId);
        $today = Carbon::today()->toDateString();

        // Get lifetime stats from user_daily_stats (mais rápido que ler question answers)
        $lifetimeStats = clone UserDailyStat::where('user_id', $userId);

        $totalAnswered = (clone clone $lifetimeStats)->sum('total_answered');
        $totalCorrect = (clone clone $lifetimeStats)->sum('total_correct');
        $accuracyRate = $totalAnswered > 0 ? round(($totalCorrect / $totalAnswered) * 100) : 0;

        $todayStats = UserDailyStat::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        // Gerar dados para o Sparkline (14 dias)
        $sparkline = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $dayStat = UserDailyStat::where('user_id', $userId)->where('date', $date)->first();
            $sparkline[] = [
                'date' => $date,
                'value' => $dayStat ? $dayStat->total_answered : 0
            ];
        }

        return [
            'total_answered' => $totalAnswered,
            'accuracy_rate' => $accuracyRate,
            'today_count' => $todayStats ? $todayStats->total_answered : 0,
            'streak_days' => $this->calculateStreak($userId),
            'daily_goal' => $user->daily_goal ?? 10,
            'sparkline' => $sparkline
        ];
    }
}
