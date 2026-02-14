<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;

class PlanService
{
    public function assignPlanToUser(User $user, Plan $plan): void
    {
        $user->update([
            'plan_id' => $plan->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addMonth(),
            'simulations_used_this_month' => 0,
            'essays_used_this_month' => 0,
            'usage_reset_at' => now()->addMonth(),
        ]);
    }

    public function checkSimulationLimit(User $user): array
    {
        $user->load('plan');

        if (!$user->plan) {
            return [
                'can_create' => false,
                'message' => 'Você precisa de um plano ativo para criar simulados.'
            ];
        }

        if (!$user->canCreateSimulation()) {
            $limit = $user->plan->simulations_limit;
            return [
                'can_create' => false,
                'message' => "Você atingiu o limite de $limit simulados no plano {$user->plan->name}. Faça upgrade para continuar!"
            ];
        }

        return [
            'can_create' => true,
            'remaining' => $user->plan->isUnlimited('simulations')
                ? 'ilimitado'
                : ($user->plan->simulations_limit - $user->simulations_used_this_month)
        ];
    }

    public function checkEssayLimit(User $user): array
    {
        $user->load('plan');

        if (!$user->plan) {
            return [
                'can_create' => false,
                'message' => 'Você precisa de um plano ativo para enviar redações.'
            ];
        }

        if (!$user->canCreateEssay()) {
            $limit = $user->plan->essays_limit;
            if ($limit === 0) {
                return [
                    'can_create' => false,
                    'message' => "Redações não estão disponíveis no plano {$user->plan->name}. Faça upgrade!"
                ];
            }
            return [
                'can_create' => false,
                'message' => "Você atingiu o limite de $limit redações no plano {$user->plan->name}. Faça upgrade!"
            ];
        }

        return [
            'can_create' => true,
            'remaining' => $user->plan->isUnlimited('essays')
                ? 'ilimitado'
                : ($user->plan->essays_limit - $user->essays_used_this_month)
        ];
    }

    public function getFreePlan(): Plan
    {
        return Plan::where('slug', 'free')->firstOrFail();
    }
}
