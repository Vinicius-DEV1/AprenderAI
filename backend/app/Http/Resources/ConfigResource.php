<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'app_name' => $this->resource['app_name'] ?? config('app.name', 'AprovadoAI'),
            'ai_name' => $this->resource['ai_name'] ?? 'Xavier',
            'app_version' => $this->resource['app_version'] ?? '1.0.0',
            'google_login_enabled' => $this->resource['google_login_enabled'] ?? false,
            'features' => $this->resource['features'] ?? [
                'essays' => true,
                'simulations' => true,
                'study_plan' => true,
                'question_bank' => true,
            ],
            'analytics' => $this->resource['analytics'] ?? [
                'enabled' => false,
                'measurement_id' => null,
            ],
            'plans' => PlanResource::collection($this->resource['plans'] ?? collect()),
        ];
    }
}
