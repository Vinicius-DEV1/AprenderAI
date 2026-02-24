<?php

namespace App\Services\Study;

use App\Models\StudyPlan;
use App\Models\User;
use App\Services\AI\AIService;
use Carbon\Carbon;

class StudyPlanGenerator
{
    protected $aiService;
    protected $statsService;

    public function __construct(AIService $aiService, StudyStatsService $statsService)
    {
        $this->aiService = $aiService;
        $this->statsService = $statsService;
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

        $stats = $this->statsService->buildStats($user);
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
}
