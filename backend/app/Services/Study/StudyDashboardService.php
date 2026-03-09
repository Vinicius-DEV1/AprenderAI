<?php

namespace App\Services\Study;

use App\Models\StudyPlan;
use App\Models\User;
use App\Models\UserTopicStat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudyDashboardService
{
    protected $statsService;

    public function __construct(StudyStatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function buildDashboardData(User $user, StudyPlan $plan): array
    {
        $dynamic = [
            'confidence' => $this->statsService->getDataConfidence($user),
            'diagnostics' => $this->buildDiagnostics($user),
            'projection' => $this->buildProjection($user),
            'weak_strong' => $this->buildWeakStrong($user),
            'recommendations' => $this->buildWeeklyRecommendations($user),
            'exam_strategy' => $this->buildExamStrategy($user),
            'motivation' => $this->buildMotivationalMessage($user),
        ];

        $generator = app(\App\Services\Study\StudyPlanGenerator::class);
        $canUpdate = $generator->canUpdate($user);

        $nextUpdateAt = $plan->next_update_at;
        $daysUntilUpdate = $nextUpdateAt && $nextUpdateAt->isFuture()
            ? (int) now()->diffInDays($nextUpdateAt, false) + 1
            : 0;

        return array_merge($dynamic, [
            'plan' => $plan,
            'can_update' => $canUpdate,
            'next_update_at' => $nextUpdateAt,
            'days_until_update' => $daysUntilUpdate,
        ]);
    }

    public function buildDiagnostics(User $user): array
    {
        $allStats = UserTopicStat::where('user_id', $user->id)->get();

        $aggregated = [];
        foreach ($allStats as $stat) {
            $normalized = $this->statsService->normalizeSubjectName($stat->subject);
            if (!isset($aggregated[$normalized])) {
                $aggregated[$normalized] = [
                    'subject' => $stat->subject,
                    'total_attempts' => 0,
                    'total_correct' => 0,
                ];
            }
            $aggregated[$normalized]['total_attempts'] += $stat->attempts;
            $aggregated[$normalized]['total_correct'] += $stat->correct;
        }

        $subjects = [];
        foreach ($aggregated as $normalizedName => $data) {
            $attempts = (int) $data['total_attempts'];
            $correct = (int) $data['total_correct'];

            $accuracy = $attempts > 0
                ? round(($correct / $attempts) * 100, 1)
                : 0;

            $meta = $this->statsService->resolveSubjectMeta($normalizedName);
            $target = $meta['target'];
            $label = $meta['label'];

            $subjects[] = [
                'subject' => $data['subject'] ?? $normalizedName,
                'label' => $label,
                'accuracy' => $accuracy,
                'target' => $target,
                'attempts' => $attempts,
                'correct' => $correct,
                'status' => $accuracy !== null ? $this->resolveAccuracyStatus($accuracy, $attempts) : 'observacao',
                'gap' => round($accuracy - $target, 1),
                'insight' => $this->resolveSubjectInsight($attempts, $correct),
            ];
        }

        usort($subjects, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        $essayAvg = $user->essays()
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->latest('evaluated_at')
            ->limit(5)
            ->get()
            ->avg(function ($essay) {
                return $essay->score <= 100 ? $essay->score * 10 : $essay->score;
            });

        $simAvg = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->latest('finished_at')
            ->limit(5)
            ->avg('score');

        return [
            'subjects' => $subjects,
            'essay_avg' => $essayAvg ? round($essayAvg, 0) : null,
            'sim_avg' => $simAvg ? round($simAvg, 0) : null,
            'perf_7days' => $this->statsService->recentAccuracy($user, 7),
            'perf_14days' => $this->statsService->recentAccuracy($user, 14),
        ];
    }

    public function buildProjection(User $user): array
    {
        $recentSims = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->latest('finished_at')
            ->limit(5)
            ->get();

        if ($recentSims->count() < 2) {
            return [
                'current' => null,
                'three_months' => null,
                'plus_one_hour' => null,
                'trend_delta' => null,
                'unavailable' => true,
                'reason' => 'Projeção indisponível por falta de dados históricos suficientes.',
            ];
        }

        $currentScore = round($recentSims->avg('score'), 0);
        $now = now();
        $recent14 = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->whereBetween('finished_at', [$now->copy()->subDays(14), $now])
            ->avg('score');

        $prior14 = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->whereBetween('finished_at', [$now->copy()->subDays(28), $now->copy()->subDays(14)])
            ->avg('score');

        $trendDelta = ($recent14 && $prior14) ? round($recent14 - $prior14, 1) : 0;
        $rawProjection = $currentScore + ($trendDelta * 6);
        $threeMonths = (int) max(
            $currentScore - 60,
            min($currentScore + 120, $rawProjection)
        );

        $confidence = $this->statsService->getDataConfidence($user);
        $plusOneHour = null;
        if ($confidence['level'] === 'high') {
            $plusOneHour = (int) min($threeMonths + 40, $currentScore + 120);
        }

        return [
            'current' => (int) $currentScore,
            'three_months' => $threeMonths,
            'plus_one_hour' => $plusOneHour,
            'trend_delta' => $trendDelta,
            'unavailable' => false,
            'reason' => null,
        ];
    }

    public function buildWeakStrong(User $user): array
    {
        $stats = UserTopicStat::where('user_id', $user->id)
            ->where('topic', '!=', 'Geral')
            ->where('attempts', '>=', 5)
            ->get();

        $weak = $stats
            ->filter(fn($s) => $s->accuracy < 60)
            ->sortBy('accuracy')
            ->take(5)
            ->map(fn($s) => [
                'topic' => $s->topic,
                'subject' => $s->subject,
                'accuracy' => round((float) $s->accuracy, 1),
                'attempts' => $s->attempts,
            ])
            ->values()
            ->toArray();

        $strong = $stats
            ->filter(fn($s) => $s->accuracy > 75)
            ->sortByDesc('accuracy')
            ->take(5)
            ->map(fn($s) => [
                'topic' => $s->topic,
                'subject' => $s->subject,
                'accuracy' => round((float) $s->accuracy, 1),
                'attempts' => $s->attempts,
            ])
            ->values()
            ->toArray();

        return [
            'weak' => $weak,
            'strong' => $strong,
        ];
    }

    public function buildWeeklyRecommendations(User $user): array
    {
        $recommendations = [];

        // 1. Worst topic
        $worstTopic = UserTopicStat::where('user_id', $user->id)
            ->where('topic', '!=', 'Geral')
            ->where('attempts', '>=', 5)
            ->orderBy('accuracy')
            ->first();

        if ($worstTopic) {
            $recommendations[] = [
                'icon' => 'target',
                'title' => "Foco em {$worstTopic->topic}",
                'detail' => "Resolva 30 questões de {$worstTopic->topic} ({$worstTopic->subject}). Acerto atual: " . round((float) $worstTopic->accuracy, 0) . "%.",
            ];
        }

        // 2. Weakest subject
        $subjectStats = UserTopicStat::where('user_id', $user->id)
            ->select('subject', DB::raw('SUM(attempts) as attempts'), DB::raw('SUM(correct) as correct'))
            ->groupBy('subject')
            ->having('attempts', '>=', 10)
            ->get()
            ->map(fn($r) => [
                'subject' => $r->subject,
                'attempts' => (int) $r->attempts,
                'correct' => (int) $r->correct,
                'accuracy' => $r->attempts > 0 ? ($r->correct / $r->attempts) * 100 : 0,
            ])
            ->sortBy('accuracy')
            ->first();

        if ($subjectStats && (!$worstTopic || ($subjectStats['subject'] ?? '') !== $worstTopic->subject)) {
            $attempts = (int) ($subjectStats['attempts'] ?? 0);
            $accuracy = $subjectStats['accuracy'] ?? null;
            $acc = $accuracy === null ? null : round((float) $accuracy, 1);

            if ($acc !== null && $acc <= 0 && $attempts >= 20) {
                $recommendations[] = [
                    'icon' => 'target',
                    'title' => "Foco crítico: " . ($subjectStats['subject'] ?? 'Disciplina'),
                    'detail' => ($subjectStats['subject'] ?? 'Disciplina') . " (0% em {$attempts} questões): faça 20 questões fáceis + revisão de 2 tópicos base.",
                ];
            } else {
                $recommendations[] = [
                    'icon' => 'book',
                    'title' => "Reforço em " . ($subjectStats['subject'] ?? 'Disciplina'),
                    'detail' => "Dedique blocos extras a " . ($subjectStats['subject'] ?? 'Disciplina') . " (" . ($acc ?? 0) . "% de acerto). Meta: atingir 65%.",
                ];
            }
        }

        // 3. Essay recommendation
        $lastEssay = $user->essays()
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->latest('evaluated_at')
            ->first();

        $essayScore = $lastEssay ? ($lastEssay->score <= 100 ? $lastEssay->score * 10 : $lastEssay->score) : 0;
        if ($essayScore < 900) {
            $recommendations[] = [
                'icon' => 'pen',
                'title' => 'Redação: repertório sociocultural',
                'detail' => 'Escreva 1 redação com foco em repertório sociocultural diversificado. Última nota: ' . ($lastEssay ? (int) $essayScore : 'sem dados') . '/1000.',
            ];
        }

        // 4. Time management
        $avgTime = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('time_elapsed')
            ->latest('finished_at')
            ->limit(5)
            ->avg('time_elapsed');

        if ($avgTime) {
            $avgPerQuestion = round($avgTime / 90, 0);
            if ($avgPerQuestion > 120) {
                $recommendations[] = [
                    'icon' => 'clock',
                    'title' => 'Gestão de tempo',
                    'detail' => "Você usa em média {$avgPerQuestion}s por questão. Treine blocos cronometrados de 45 questões em 60 minutos.",
                ];
            }
        }

        // 5. Consistency
        $lastActivity = UserTopicStat::where('user_id', $user->id)
            ->max('last_attempt_at');

        if ($lastActivity && Carbon::parse($lastActivity)->lt(now()->subDays(3))) {
            $recommendations[] = [
                'icon' => 'calendar',
                'title' => 'Consistência',
                'detail' => 'Você não pratica há ' . Carbon::parse($lastActivity)->diffInDays(now()) . ' dias. Retome com pelo menos 20 questões hoje.',
            ];
        }

        $recommendations = array_values($recommendations);
        foreach ($recommendations as $i => &$rec) {
            $rec['priority'] = $i + 1;
        }

        return array_slice($recommendations, 0, 5);
    }

    public function buildExamStrategy(User $user): array
    {
        $allStats = UserTopicStat::where('user_id', $user->id)->get();
        $aggregated = [];
        foreach ($allStats as $stat) {
            $normalized = $this->statsService->normalizeSubjectName($stat->subject);
            if (!isset($aggregated[$normalized])) {
                $aggregated[$normalized] = [
                    'subject' => $stat->subject,
                    'total_attempts' => 0,
                    'total_correct' => 0,
                ];
            }
            $aggregated[$normalized]['total_attempts'] += $stat->attempts;
            $aggregated[$normalized]['total_correct'] += $stat->correct;
        }

        $subjectData = [];
        foreach ($aggregated as $normalizedName => $data) {
            if ($data['total_attempts'] < 5)
                continue;

            $subjectData[] = [
                'subject' => $data['subject'],
                'label' => $this->statsService->resolveSubjectMeta($normalizedName)['label'],
                'accuracy' => round(($data['total_correct'] / $data['total_attempts']) * 100, 1),
                'attempts' => (int) $data['total_attempts'],
            ];
        }

        usort($subjectData, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);

        $recentSims = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('time_elapsed')
            ->latest('finished_at')
            ->limit(5)
            ->get();

        $avgSecondsPerQuestion = null;
        $timeWarning = null;

        if ($recentSims->isNotEmpty()) {
            $totalTime = $recentSims->sum('time_elapsed');
            $totalQuestions = $recentSims->count() * 90;
            $avgSecondsPerQuestion = round($totalTime / $totalQuestions, 0);

            if ($avgSecondsPerQuestion > 180) {
                $mins = round($avgSecondsPerQuestion / 60, 1);
                $timeWarning = "Você usa em média {$mins} min/questão. Treinar blocos cronometrados reduz esse tempo.";
            }
        }

        $timePerSubject = [
            'Matemática' => 54,
            'Língua Portuguesa' => 45,
            'Ciências da Natureza' => 54,
            'Ciências Humanas' => 45,
        ];

        $highestAcc = 0;
        $secondHighestAcc = 0;
        if (count($subjectData) > 0) {
            $highestAcc = $subjectData[0]['accuracy'];
            $secondHighestAcc = $subjectData[1]['accuracy'] ?? 0;
        }

        $isClearWinner = ($highestAcc - $secondHighestAcc) >= 10;
        $tip = 'Comece por Linguagens ou Ciências Humanas para aquecer e ganhar pontos rápidos, deixando Matemática e Natureza para o meio da prova.';

        if ($isClearWinner && $highestAcc > 60) {
            $bestLabel = $subjectData[0]['label'];
            $tip = "Sua vantagem competitiva em {$bestLabel} é clara. Comece por ela para garantir autoconfiança e uma boa base de pontos inicial.";
        }

        return [
            'suggested_order' => $subjectData,
            'avg_seconds_per_question' => $avgSecondsPerQuestion,
            'time_warning' => $timeWarning,
            'time_per_subject' => $timePerSubject,
            'tip' => $tip,
        ];
    }

    public function buildMotivationalMessage(User $user): ?string
    {
        $now = now();
        $recentSims = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->whereBetween('finished_at', [$now->copy()->subDays(14), $now])
            ->get();

        $priorSims = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->whereBetween('finished_at', [$now->copy()->subDays(28), $now->copy()->subDays(14)])
            ->get();

        if ($recentSims->count() >= 1 && $priorSims->count() >= 1) {
            $recentAvg = $recentSims->avg('score');
            $priorAvg = $priorSims->avg('score');
            $delta = round($recentAvg - $priorAvg, 0);

            if ($delta > 0) {
                return "Nas últimas 2 semanas você evoluiu +{$delta} pontos. Mantendo esse ritmo, sua meta é plenamente alcançável.";
            } elseif ($delta < 0) {
                return "Seu desempenho recente caiu " . abs($delta) . " pontos. Foque nos pontos fracos identificados acima para retomar a trajetória.";
            } else {
                return "Seu desempenho está estável. Pequenos ajustes nas disciplinas críticas podem fazer grande diferença.";
            }
        }

        // Subject-based logic
        $allStats = UserTopicStat::where('user_id', $user->id)->get();
        if ($allStats->isEmpty()) {
            return "O Xavier está calibrando suas métricas. Continue resolvendo questões para uma análise precisa de sua melhor área.";
        }

        $subjectsAggregation = [];
        foreach ($allStats as $stat) {
            $normalized = $this->statsService->normalizeSubjectName($stat->subject);
            if (!isset($subjectsAggregation[$normalized])) {
                $subjectsAggregation[$normalized] = ['attempts' => 0, 'correct' => 0];
            }
            $subjectsAggregation[$normalized]['attempts'] += $stat->attempts;
            $subjectsAggregation[$normalized]['correct'] += $stat->correct;
        }

        $qualifiedSubjects = array_filter($subjectsAggregation, fn($s) => $s['attempts'] >= 10);

        if (count($qualifiedSubjects) >= 2) {
            $best = null;
            $bestAcc = -1;

            foreach ($qualifiedSubjects as $name => $data) {
                $acc = ($data['correct'] / $data['attempts']) * 100;
                if ($acc > $bestAcc) {
                    $bestAcc = $acc;
                    $best = $this->statsService->resolveSubjectMeta($name)['label'];
                }
            }

            if ($best && $bestAcc > 0) {
                $accFormatted = round($bestAcc, 0);
                return "Seu melhor desempenho atual está em {$best} com {$accFormatted}% de acerto. Mantenha o foco para consolidar essa liderança.";
            }
        }

        return "O Xavier está calibrando suas métricas. Continue resolvendo questões para uma análise precisa de sua melhor área.";
    }

    protected function resolveAccuracyStatus(float $accuracy, int $attempts = 0): string
    {
        if ($attempts < 20)
            return 'observacao';
        if ($accuracy < 50)
            return 'critico';
        if ($accuracy < 65)
            return 'atencao';
        if ($accuracy < 75)
            return 'estavel';
        return 'bom';
    }

    protected function resolveSubjectInsight(int $attempts, int $correct): string
    {
        if ($attempts < 20) {
            return "Continue praticando para consolidar seu diagnóstico individual.";
        }
        if ($correct == 0) {
            return "🚨 Você errou todas as {$attempts} questões. Recomendamos exercícios guiados e revisão de fundamentos.";
        }
        if ($correct == 1) {
            return "✅ Você acertou 1 questão de {$attempts}.";
        }
        return "✅ Você acertou {$correct} questões de {$attempts}.";
    }
}
