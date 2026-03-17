<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'phone' => $this->phone,
            'role' => $this->role,
            'plan_id' => $this->plan_id,
            'avatar' => $this->avatar_url ?? null,
            'plan' => $this->activePlan() ? new PlanResource($this->activePlan()) : null,
            'subscription_active' => $this->hasActiveSubscription(),
            'subscription_start' => $this->plan_started_at,
            'subscription_end' => $this->plan_expires_at,
            'created_at' => $this->created_at,
            'stats' => [
                'total_simulations' => $this->stats->total_simulations ?? 0,
                'total_essays' => $this->stats->total_essays ?? 0,
                'questions_answered' => $this->stats->questions_answered ?? 0,
            ],
            'quotas' => [
                'simulations' => [
                    'limit' => $this->simulationQuotaLimit(),
                    'used' => $this->monthlySimulationUsed(),
                ],
                'essays' => [
                    'limit' => $this->essayQuotaLimit(),
                    'used' => $this->monthlyEssayUsed(),
                ],
                'daily_questions' => [
                    'limit' => $this->dailyQuestionQuotaLimit(),
                    'used' => $this->dailyQuestionUsed(),
                ],
                'ai_questions' => [
                    'limit' => $this->aiQuotaLimit(),
                    'used' => $this->ai_questions_count,
                ]
            ]
        ];
    }
}
