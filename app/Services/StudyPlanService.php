<?php

namespace App\Services;

use App\Models\StudyPlan;
use App\Models\User;
use App\Models\UserTopicStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudyPlanService
{
    protected $aiService;

    // Subject display names and targets
    protected array $subjectMeta = [
        'Matemática' => ['target' => 65, 'label' => 'Matemática'],
        'Matematica' => ['target' => 65, 'label' => 'Matemática'],
        'Língua Portuguesa' => ['target' => 70, 'label' => 'Português'],
        'Portugues' => ['target' => 70, 'label' => 'Português'],
        'Português' => ['target' => 70, 'label' => 'Português'],
        'Ciências da Natureza' => ['target' => 60, 'label' => 'Natureza'],
        'Natureza' => ['target' => 60, 'label' => 'Natureza'],
        'Ciências Humanas' => ['target' => 65, 'label' => 'Humanas'],
        'Humanas' => ['target' => 65, 'label' => 'Humanas'],
        'Linguagens' => ['target' => 70, 'label' => 'Linguagens'],
        'Redação' => ['target' => 900, 'label' => 'Redação'],
    ];

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    // =========================================================
    // EXISTING METHODS (preserved)
    // =========================================================

    public function canGenerate(User $user): bool
    {
        if (!$user->canAccessStudyPlan()) {
            return false;
        }

        $lastPlan = $user->studyPlans()->latest()->first();
        if (!$lastPlan) {
            return true;
        }

        return $lastPlan->created_at->lt(now()->subDays(30));
    }

    public function canUpdate(User $user): bool
    {
        if (!$user->canAccessStudyPlan()) {
            return false;
        }

        $lastPlan = $user->studyPlans()->latest()->first();
        if (!$lastPlan) {
            return false;
        }

        // Enforce 14-day rule using next_update_at
        if ($lastPlan->next_update_at) {
            return $lastPlan->next_update_at->isPast();
        }

        return $lastPlan->created_at->lt(now()->subDays(14));
    }

    public function createPlaceholder(User $user, array $input)
    {
        if (!$this->canGenerate($user)) {
            $lastPlan = $user->studyPlans()->latest()->first();
            $nextDate = $lastPlan ? $lastPlan->created_at->addDays(30) : now();
            if ($lastPlan && $lastPlan->created_at->gte(now()->subDays(30))) {
                abort(429, 'Você só pode gerar um novo plano em ' . $nextDate->format('d/m/Y'));
            }
            abort(403, 'Acesso negado ou pré-requisitos não atendidos.');
        }

        return StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => $input['exam_type'] ?? 'enem',
            'exam_name' => $input['exam_name'] ?? null,
            'exam_date' => $input['exam_date'] ?? null,
            'hours_per_day' => $input['hours_per_day'] ?? 2,
            'status' => 'processing',
            'plan_json' => null,
            'stats_snapshot' => null,
            'generated_at' => now(),
            'next_generate_at' => now()->addDays(30),
            'next_update_at' => now()->addDays(14),
        ]);
    }

    public function generateContent(StudyPlan $plan)
    {
        $user = $plan->user;
        $exam_date = $plan->exam_date;
        $formatted_date = null;
        if (!empty($exam_date)) {
            if ($exam_date instanceof \DateTimeInterface) {
                $formatted_date = $exam_date->format('Y-m-d');
            } else {
                $formatted_date = Carbon::parse($exam_date)->format('Y-m-d');
            }
        }

        $input = [
            'hours_per_day' => $plan->hours_per_day,
            'exam_type' => $plan->exam_type,
            'exam_name' => $plan->exam_name,
            'exam_date' => $formatted_date,
        ];

        $stats = $this->buildStats($user);
        $planJson = $this->aiService->generateStudyPlan($stats, $input);

        if (empty($planJson)) {
            $planJson = $this->aiService->generateStudyPlan($stats, $input);
        }

        if (empty($planJson)) {
            throw new \Exception('Falha ao gerar plano com a IA. Tente novamente mais tarde.');
        }

        $plan->update([
            'plan_json' => $planJson,
            'stats_snapshot' => $stats,
            'status' => 'ready',
            'finished_at' => now(),
        ]);

        return $plan;
    }

    public function generate(User $user, array $input)
    {
        if (!$this->canGenerate($user)) {
            $lastPlan = $user->studyPlans()->latest()->first();
            $nextDate = $lastPlan ? $lastPlan->created_at->addDays(30) : now();
            abort(429, 'Você só pode gerar um novo plano em ' . $nextDate->format('d/m/Y'));
        }

        $plan = $this->createPlaceholder($user, $input);
        return $this->generateContent($plan);
    }

    /**
     * Update the study plan (allowed once every 14 days).
     * Creates a new plan record to preserve history, then dispatches the generation job.
     */
    public function update(User $user): StudyPlan
    {
        if (!$this->canUpdate($user)) {
            $lastPlan = $user->studyPlans()->latest()->first();
            $nextDate = $lastPlan?->next_update_at?->format('d/m/Y') ?? 'em breve';
            abort(403, "Seu plano só pode ser atualizado em {$nextDate}.");
        }

        $lastPlan = $user->studyPlans()->latest()->first();

        // Create a new plan record (preserves history)
        $newPlan = StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => $lastPlan?->exam_type ?? 'enem',
            'exam_name' => $lastPlan?->exam_name,
            'exam_date' => $lastPlan?->exam_date,
            'hours_per_day' => $lastPlan?->hours_per_day ?? 2,
            'status' => 'processing',
            'plan_json' => null,
            'stats_snapshot' => null,
            'generated_at' => now(),
            'next_generate_at' => now()->addDays(30),
            'next_update_at' => now()->addDays(14),
        ]);

        \App\Jobs\GenerateStudyPlanJob::dispatch($newPlan->id);

        return $newPlan;
    }

    public function buildStats(User $user): array
    {
        $stats = UserTopicStat::where('user_id', $user->id)->get();

        $aggregated = [];
        foreach ($stats as $s) {
            $norm = $this->normalizeSubjectName($s->subject);
            if (!isset($aggregated[$norm])) {
                $aggregated[$norm] = [
                    'attempts' => 0,
                    'correct' => 0,
                ];
            }
            $aggregated[$norm]['attempts'] += $s->attempts;
            $aggregated[$norm]['correct'] += $s->correct;
        }

        $bySubject = collect($aggregated)->map(function ($data) {
            $total = $data['attempts'];
            $correct = $data['correct'];
            return [
                'attempts' => $total,
                'correct' => $correct,
                'accuracy' => $total >= 2 ? round(($correct / $total) * 100, 2) : null,
            ];
        });

        $topWeak = $stats->sortBy('accuracy')->take(5)->map(fn($s) => [
            'subject' => $this->resolveSubjectMeta($this->normalizeSubjectName($s->subject))['label'],
            'topic' => $s->topic,
            'accuracy' => $s->accuracy,
        ])->values();

        $topStrong = $stats->sortByDesc('accuracy')->take(5)->map(fn($s) => [
            'subject' => $this->resolveSubjectMeta($this->normalizeSubjectName($s->subject))['label'],
            'topic' => $s->topic,
            'accuracy' => $s->accuracy,
        ])->values();

        $trend = $this->calculateTrend($user);

        return [
            'subjects' => $bySubject,
            'weak_topics' => $topWeak,
            'strong_topics' => $topStrong,
            'trend' => $trend,
        ];
    }

    protected function calculateTrend(User $user): array
    {
        $sims = $user->simulations()
            ->where('status', 'finished')
            ->latest()
            ->take(5)
            ->get()
            ->reverse();

        if ($sims->count() < 2) {
            return ['status' => 'estável', 'data' => []];
        }

        $scores = $sims->map(fn($s) => $s->score)->values()->toArray();
        $first = $scores[0];
        $last = end($scores);

        if ($last > $first + 50)
            return ['status' => 'melhorando'];
        if ($last < $first - 50)
            return ['status' => 'piorando'];
        return ['status' => 'estável'];
    }

    public function updateUserStats(User $user, $simulation)
    {
        foreach ($simulation->answers as $answer) {
            $question = $answer->question;
            if (!$question || !$question->topic)
                continue;

            $stat = UserTopicStat::firstOrNew([
                'user_id' => $user->id,
                'subject' => $question->subject,
                'topic' => $question->topic,
            ]);

            $stat->attempts++;
            if ($answer->is_correct) {
                $stat->correct++;
            }
            $stat->accuracy = ($stat->correct / $stat->attempts) * 100;
            $stat->last_attempt_at = now();
            $stat->save();
        }
    }

    // =========================================================
    // NEW METHODS — Data-driven dashboard
    // =========================================================

    /**
     * Master orchestrator — returns all dashboard data in one call.
     * Results are cached per user for 30 minutes (except weekly_schedule).
     */
    public function buildDashboardData(User $user, StudyPlan $plan): array
    {
        $cacheKey = "study_plan_dashboard_{$user->id}";

        // Dynamic data (Real-time updates, cache removed as requested)
        $dynamic = [
            'confidence' => $this->getDataConfidence($user),
            'diagnostics' => $this->buildDiagnostics($user),
            'projection' => $this->buildProjection($user),
            'weak_strong' => $this->buildWeakStrong($user),
            'recommendations' => $this->buildWeeklyRecommendations($user),
            'exam_strategy' => $this->buildExamStrategy($user),
            'motivation' => $this->buildMotivationalMessage($user),
        ];

        // Static plan data (never cached — must reflect live DB state)
        $canUpdate = $this->canUpdate($user);
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

    /**
     * Statistical confidence level based on total questions answered.
     */
    public function getDataConfidence(User $user): array
    {
        $total = (int) UserTopicStat::where('user_id', $user->id)->sum('attempts');

        if ($total >= 100) {
            $level = 'high';
            $label = 'Alta confiança estatística';
        } elseif ($total >= 50) {
            $level = 'medium';
            $label = 'Confiança estatística moderada';
        } else {
            $level = 'low';
            $label = 'Baixa confiança estatística';
        }

        return [
            'level' => $level,
            'label' => $label,
            'total' => $total,
            'warning' => $level === 'low'
                ? 'Análise com baixa confiança estatística. Complete ao menos 100 questões para maior precisão.'
                : null,
        ];
    }

    /**
     * Per-subject diagnostics: accuracy, target, status, essay average.
     */
    public function buildDiagnostics(User $user): array
    {
        // Fetch all stats and aggregate in memory to handle normalized subjects
        $allStats = UserTopicStat::where('user_id', $user->id)->get();

        $aggregated = [];
        foreach ($allStats as $stat) {
            $normalized = $this->normalizeSubjectName($stat->subject);
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

            $meta = $this->resolveSubjectMeta($normalizedName);
            $target = $meta['target'];
            $label = $meta['label'];

            $subjects[] = [
                'subject' => $data['subject'] ?? $normalizedName,
                'label' => $label,
                'accuracy' => $accuracy,
                'target' => $target,
                'attempts' => $attempts,
                'correct' => $correct, // Standardizing contract
                'status' => $accuracy !== null ? $this->resolveAccuracyStatus($accuracy, $attempts) : 'observacao',
                'gap' => round($accuracy - $target, 1),
            ];
        }

        // Sort by accuracy ascending (worst first)
        usort($subjects, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        // Essay average (last 5 evaluated essays)
        $essayAvg = $user->essays()
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->latest('evaluated_at')
            ->limit(5)
            ->get()
            ->avg(function ($essay) {
                return $essay->score <= 100 ? $essay->score * 10 : $essay->score;
            });

        // Simulation average (last 5)
        $simAvg = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('score')
            ->latest('finished_at')
            ->limit(5)
            ->avg('score');

        // Performance last 7 and 14 days
        $perf7 = $this->recentAccuracy($user, 7);
        $perf14 = $this->recentAccuracy($user, 14);

        return [
            'subjects' => $subjects,
            'essay_avg' => $essayAvg ? round($essayAvg, 0) : null,
            'sim_avg' => $simAvg ? round($simAvg, 0) : null,
            'perf_7days' => $perf7,
            'perf_14days' => $perf14,
        ];
    }

    /**
     * Score projection: current, 3-month, +1h/day.
     * Uses clamped, safe arithmetic — no unbounded growth.
     */
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

        // Trend: avg last 14 days vs avg 14-28 days ago
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

        // 3-month projection (6 bi-weekly periods), clamped
        $rawProjection = $currentScore + ($trendDelta * 6);
        $threeMonths = (int) max(
            $currentScore - 60,
            min($currentScore + 120, $rawProjection)
        );

        // +1h projection (only if confidence is high)
        $confidence = $this->getDataConfidence($user);
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

    /**
     * Weak topics (<60%, min 5 attempts) and strong topics (>75%, min 5 attempts).
     */
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

    /**
     * 2–5 practical weekly recommendations based on real data.
     * Does NOT modify weekly_schedule.
     */
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

            // UX Enhancement for real 0% with context (Defensive Fix)
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

        // 4. Time management (if sim data available)
        $avgTime = $user->simulations()
            ->where('status', 'finished')
            ->whereNotNull('time_elapsed')
            ->latest('finished_at')
            ->limit(5)
            ->avg('time_elapsed');

        if ($avgTime) {
            $avgPerQuestion = round($avgTime / 90, 0); // 90 questions ENEM
            if ($avgPerQuestion > 120) { // > 2 min per question
                $recommendations[] = [
                    'icon' => 'clock',
                    'title' => 'Gestão de tempo',
                    'detail' => "Você usa em média {$avgPerQuestion}s por questão. Treine blocos cronometrados de 45 questões em 60 minutos.",
                ];
            }
        }

        // 5. Consistency nudge
        $lastActivity = UserTopicStat::where('user_id', $user->id)
            ->max('last_attempt_at');

        if ($lastActivity && Carbon::parse($lastActivity)->lt(now()->subDays(3))) {
            $recommendations[] = [
                'icon' => 'calendar',
                'title' => 'Consistência',
                'detail' => 'Você não pratica há ' . Carbon::parse($lastActivity)->diffInDays(now()) . ' dias. Retome com pelo menos 20 questões hoje.',
            ];
        }

        // Reindex recommendations to ensure correct priority numbering
        $recommendations = array_values($recommendations);
        foreach ($recommendations as $i => &$rec) {
            $rec['priority'] = $i + 1;
        }

        return array_slice($recommendations, 0, 5);
    }

    /**
     * Exam strategy: suggested order, time per subject, where user loses most time.
     */
    public function buildExamStrategy(User $user): array
    {
        $allStats = UserTopicStat::where('user_id', $user->id)->get();
        $aggregated = [];
        foreach ($allStats as $stat) {
            $normalized = $this->normalizeSubjectName($stat->subject);
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
                'label' => $this->resolveSubjectMeta($normalizedName)['label'],
                'accuracy' => round(($data['total_correct'] / $data['total_attempts']) * 100, 1),
                'attempts' => (int) $data['total_attempts'],
            ];
        }

        usort($subjectData, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']); // Start with strongest

        // Avg time per question from simulations
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
            $totalQuestions = $recentSims->count() * 90; // ENEM standard
            $avgSecondsPerQuestion = round($totalTime / $totalQuestions, 0);

            if ($avgSecondsPerQuestion > 180) {
                $mins = round($avgSecondsPerQuestion / 60, 1);
                $timeWarning = "Você usa em média {$mins} min/questão. Treinar blocos cronometrados reduz esse tempo.";
            }
        }

        // Suggested time per subject (minutes)
        $timePerSubject = [
            'Matemática' => 54,
            'Língua Portuguesa' => 45,
            'Ciências da Natureza' => 54,
            'Ciências Humanas' => 45,
        ];

        // Logic to determine if starting with strong subject is viable
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

    /**
     * Short motivational message based on real recent data.
     */
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
            return "Ainda não há dados suficientes para identificar sua melhor área com precisão estatística. Continue resolvendo simulados para calibrar seu diagnóstico.";
        }

        $subjectsAggregation = [];
        foreach ($allStats as $stat) {
            $normalized = $this->normalizeSubjectName($stat->subject);
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
                    $best = $this->resolveSubjectMeta($name)['label'];
                }
            }

            if ($best && $bestAcc > 0) {
                $accFormatted = round($bestAcc, 0);
                return "Seu melhor desempenho atual está em {$best} com {$accFormatted}% de acerto. Mantenha o foco para consolidar essa liderança.";
            }
        }

        return "Ainda não há dados suficientes para identificar sua melhor área com precisão estatística. Continue resolvendo simulados para calibrar seu diagnóstico.";
    }

    private function normalizeSubjectName(string $subject): string
    {
        $subject = mb_strtolower(trim($subject), 'UTF-8');
        $subject = preg_replace('/[áàâãä]/u', 'a', $subject);
        $subject = preg_replace('/[éèêë]/u', 'e', $subject);
        $subject = preg_replace('/[íìîï]/u', 'i', $subject);
        $subject = preg_replace('/[óòôõö]/u', 'o', $subject);
        $subject = preg_replace('/[úùûü]/u', 'u', $subject);
        $subject = preg_replace('/[ç]/u', 'c', $subject);

        // Map common variations
        if (str_contains($subject, 'matematica'))
            return 'matematica';
        if (str_contains($subject, 'portugues') || str_contains($subject, 'linguagem'))
            return 'portugues';
        if (str_contains($subject, 'natureza'))
            return 'natureza';
        if (str_contains($subject, 'humana'))
            return 'humanas';
        if (str_contains($subject, 'redaca'))
            return 'redacao';

        return $subject;
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    protected function resolveSubjectMeta(string $subject): array
    {
        $normalizedInput = $this->normalizeSubjectName($subject);

        foreach ($this->subjectMeta as $key => $meta) {
            $normalizedKey = $this->normalizeSubjectName($key);
            if ($normalizedInput === $normalizedKey) {
                return $meta;
            }
        }

        return ['target' => 65, 'label' => $subject];
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

    protected function recentAccuracy(User $user, int $days): ?float
    {
        $stats = UserTopicStat::where('user_id', $user->id)
            ->where('last_attempt_at', '>=', now()->subDays($days))
            ->get();

        if ($stats->isEmpty())
            return null;

        $total = $stats->sum('attempts');
        $correct = $stats->sum('correct');

        return $total > 0 ? round(($correct / $total) * 100, 1) : null;
    }
}
