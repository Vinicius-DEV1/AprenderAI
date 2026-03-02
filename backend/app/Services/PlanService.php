<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;

/**
 * PlanService — Centralized Plan & Quota Logic
 *
 * This service orchestrates limit checks as structured responses for controllers.
 * It delegates the actual math to the User model (which holds the override-aware
 * quota methods), keeping this service as a thin orchestration layer.
 *
 * DESIGN PRINCIPLE:
 *  - User model owns "can I?", "what's my limit?", "how much did I use?"
 *  - PlanService owns "give me a structured check result for the controller"
 *  - Controllers own "how do I respond to the HTTP request?"
 */
class PlanService
{
    // =========================================================================
    // PLAN ASSIGNMENT
    // =========================================================================

    /**
     * Assign a plan to a user, resetting monthly usage counters accordingly.
     * Called on subscription creation or upgrade.
     */
    public function assignPlanToUser(User $user, Plan $plan): void
    {
        $user->update([
            'plan_id' => $plan->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addMonth(),
        ]);
    }

    // =========================================================================
    // SIMULATION LIMIT CHECK
    // =========================================================================

    /**
     * Check whether a user can create a new simulation.
     *
     * Returns a structured array so controllers can act on it without
     * re-implementing the logic. The `remaining` key is included only
     * when `can_create` is true (for display in the UI).
     *
     * @return array{can_create: bool, message?: string, remaining?: int|string, limit?: int, used?: int}
     */
    public function checkSimulationLimit(User $user): array
    {
        $user->loadMissing('plan');

        $limit = $user->simulationQuotaLimit();
        $used = $user->monthlySimulationUsed();
        $remaining = ($limit === 9999) ? 'ilimitado' : max(0, $limit - $used);

        if (!$user->plan) {
            return [
                'can_create' => false,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'message' => 'Você precisa de um plano ativo para criar simulados.',
            ];
        }

        if (!$user->canCreateSimulation()) {
            $planName = $user->plan->name;

            return [
                'can_create' => false,
                'limit' => $limit,
                'used' => $used,
                'remaining' => 0,
                'message' => $limit === 0
                    ? "Simulados não estão disponíveis no plano {$planName}. Faça upgrade!"
                    : "Você atingiu o limite de {$limit} simulados no plano {$planName}. Faça upgrade para continuar!",
            ];
        }

        return [
            'can_create' => true,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
        ];
    }

    // =========================================================================
    // ESSAY LIMIT CHECK
    // =========================================================================

    /**
     * Check whether a user can create (and submit) a new essay.
     *
     * Same structured response pattern as checkSimulationLimit.
     *
     * @return array{can_create: bool, message?: string, remaining?: int|string, limit?: int, used?: int}
     */
    public function checkEssayLimit(User $user): array
    {
        $user->loadMissing('plan');

        $limit = $user->essayQuotaLimit();
        $used = $user->monthlyEssayUsed();
        $remaining = ($limit === 9999) ? 'ilimitado' : max(0, $limit - $used);

        if (!$user->plan) {
            return [
                'can_create' => false,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'message' => 'Você precisa de um plano ativo para enviar redações.',
            ];
        }

        if (!$user->canCreateEssay()) {
            $planName = $user->plan->name;

            return [
                'can_create' => false,
                'limit' => $limit,
                'used' => $used,
                'remaining' => 0,
                'message' => $limit === 0
                    ? "Redações não estão disponíveis no plano {$planName}. Faça upgrade!"
                    : "Você atingiu o limite de {$limit} redações no plano {$planName}. Faça upgrade para continuar!",
            ];
        }

        return [
            'can_create' => true,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
        ];
    }

    // =========================================================================
    // DAILY QUESTION LIMIT CHECK
    // =========================================================================

    /**
     * Check whether a user can answer a new daily question.
     *
     * @return array{can_create: bool, message?: string, remaining?: int|string, limit?: int, used?: int}
     */
    public function checkDailyQuestionLimit(User $user): array
    {
        $user->loadMissing('plan');

        $limit = $user->dailyQuestionQuotaLimit();
        $used = $user->dailyQuestionUsed();
        $remaining = ($limit === 9999) ? 'ilimitado' : max(0, $limit - $used);

        if (!$user->plan) {
            return [
                'can_create' => false,
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'message' => 'Você precisa de um plano ativo para responder questões.',
            ];
        }

        if (!$user->canAnswerDailyQuestion()) {
            $planName = $user->plan->name;

            return [
                'can_create' => false,
                'limit' => $limit,
                'used' => $used,
                'remaining' => 0,
                'message' => $limit === 0
                    ? "Questões não estão disponíveis no plano {$planName}. Faça upgrade!"
                    : "Você atingiu o limite de {$limit} questões por dia no plano {$planName}. Faça upgrade para continuar!",
            ];
        }

        return [
            'can_create' => true,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Retrieve the free plan model (used for post-cancellation demotion).
     */
    public function getFreePlan(): Plan
    {
        return Plan::where('slug', 'free')->firstOrFail();
    }
}
