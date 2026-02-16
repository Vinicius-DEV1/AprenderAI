<?php

namespace App\Services;

use App\Models\StudyPlan;
use App\Models\User;
use App\Models\UserTopicStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudyPlanService
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function canGenerate(User $user): bool
    {
        if (!$user->canAccessStudyPlan()) {
            return false;
        }

        $lastPlan = $user->studyPlans()->latest()->first();
        if (!$lastPlan) {
            return true;
        }

        // Limit: 1 per month
        return $lastPlan->created_at->lt(now()->subDays(30));
    }

    public function canUpdate(User $user): bool
    {
        if (!$user->canAccessStudyPlan()) {
            return false;
        }

        $lastPlan = $user->studyPlans()->latest()->first();
        if (!$lastPlan) {
            return false; // Must generate first
        }

        // Limit: 1 update every 14 days
        // Note: Logic allows update if plan exists and next_update_at is past or null
        // However, the rule says "Update 1x every 14 days".
        // Let's check the last update time.
        // Assuming we store updates as new records or update existing?
        // "Salvar sempre no banco" -> suggests new records or history.
        // Let's assume we update the CURRENT plan or create a new version?
        // Requirement: "Salvar sempre no banco" (Always save to db).
        // Let's create a NEW record for every generation/update to keep history.

        return $lastPlan->created_at->lt(now()->subDays(14));
    }

    public function createPlaceholder(User $user, array $input)
    {
        // 1. Validate Access & Rate Limit
        if (!$this->canGenerate($user)) {
            $lastPlan = $user->studyPlans()->latest()->first();
            $nextDate = $lastPlan ? $lastPlan->created_at->addDays(30) : now();
            if ($lastPlan && $lastPlan->created_at->gte(now()->subDays(30))) {
                abort(429, 'Você só pode gerar um novo plano em ' . $nextDate->format('d/m/Y'));
            }
            abort(403, 'Acesso negado ou pré-requisitos não atendidos.');
        }

        // 2. Create Placeholder
        return StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => $input['exam_type'] ?? 'enem',
            'exam_name' => $input['exam_name'] ?? null,
            'exam_date' => $input['exam_date'] ?? null,
            'hours_per_day' => $input['hours_per_day'] ?? 2,
            'status' => 'processing',
            'plan_json' => null, // Will be filled by Job
            'stats_snapshot' => null, // Will be filled by Job
            'generated_at' => now(), // Creation time
            'next_generate_at' => now()->addDays(30),
            'next_update_at' => now()->addDays(14),
        ]);
    }

    public function generateContent(StudyPlan $plan)
    {
        $user = $plan->user;
        $input = [
            'hours_per_day' => $plan->hours_per_day,
            'exam_type' => $plan->exam_type,
            'exam_name' => $plan->exam_name,
            'exam_date' => $plan->exam_date ? $plan->exam_date->format('Y-m-d') : null,
        ];

        // 1. Build Stats
        $stats = $this->buildStats($user);

        // 2. Call AI
        $planJson = $this->aiService->generateStudyPlan($stats, $input);

        if (empty($planJson)) {
            // Retry once
            $planJson = $this->aiService->generateStudyPlan($stats, $input);
        }

        if (empty($planJson)) {
            throw new \Exception('Falha ao gerar plano com a IA. Tente novamente mais tarde.');
        }

        // 3. Update Plan
        $plan->update([
            'plan_json' => $planJson,
            'stats_snapshot' => $stats,
            'status' => 'ready',
            'finished_at' => now(),
        ]);

        return $plan;
    }

    // Deprecated or Wrapper for sync calls (if needed)
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

    public function buildStats(User $user): array
    {
        // Aggregation logic
        // We need: % acerto por matéria, topic, Top 5 weak, Top 5 strong, trend

        $stats = UserTopicStat::where('user_id', $user->id)->get();

        $bySubject = $stats->groupBy('subject')->map(function ($group) {
            $total = $group->sum('attempts');
            $correct = $group->sum('correct');
            return [
                'attempts' => $total,
                'correct' => $correct,
                'accuracy' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
            ];
        });

        $topWeak = $stats->sortBy('accuracy')->take(5)->map(fn($s) => [
            'subject' => $s->subject,
            'topic' => $s->topic,
            'accuracy' => $s->accuracy
        ])->values();

        $topStrong = $stats->sortByDesc('accuracy')->take(5)->map(fn($s) => [
            'subject' => $s->subject,
            'topic' => $s->topic,
            'accuracy' => $s->accuracy
        ])->values();

        // Calculate Trend (Simple logic: compare recent vs old? 
        // For now, simpler: just return "stable" or simulate based on last_attempt_at if we had history
        // Requirement requests "Tendência (melhorando/estável/piorando)".
        // Since UserTopicStat is a summary, we can't calculate trend easily without history log.
        // But we have `UserLog` or `Simulation`?
        // Let's use a placeholder heuristic or check recent simulations if needed.
        // For MVP speed as requested: return "Calculado por IA" or simplified.
        // Better: Compare last 3 sims accuracy vs overall accuracy.

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
        // Get last 5 sims
        $sims = $user->simulations()
            ->where('status', 'finished')
            ->latest()
            ->take(5)
            ->get()
            ->reverse(); // Oldest first

        if ($sims->count() < 2)
            return ['status' => 'estável', 'data' => []];

        $scores = $sims->map(fn($s) => $s->score)->values()->toArray();

        // Simple regression or diff
        $first = $scores[0];
        $last = end($scores);

        if ($last > $first + 50)
            return ['status' => 'melhorando'];
        if ($last < $first - 50)
            return ['status' => 'piorando'];
        return ['status' => 'estável'];
    }

    // Called by Event Listener
    public function updateUserStats(User $user, $simulation)
    {
        // Loop through answers
        foreach ($simulation->answers as $answer) {
            $question = $answer->question;
            if (!$question || !$question->topic)
                continue; // Topic required for granular stats

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
}
