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
            'plan' => $this->plan ? new PlanResource($this->plan) : null,
            'subscription_active' => $this->hasActiveSubscription(),
            'created_at' => $this->created_at,
            'stats' => [
                'simulations_completed' => $this->stats->simulations_completed ?? 0,
                'essays_submitted' => $this->stats->essays_submitted ?? 0,
                'questions_answered' => $this->stats->questions_answered ?? 0,
            ]
        ];
    }
}
