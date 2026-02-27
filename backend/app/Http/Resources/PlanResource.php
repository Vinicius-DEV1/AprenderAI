<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [
                'id' => null,
                'name' => 'Grátis',
                'slug' => 'free',
                'features' => [],
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'discount_percentage' => $this->discount_percentage,
            'interval' => $this->interval,
            'simulations_limit' => $this->simulations_limit,
            'essays_limit' => $this->essays_limit,
            'max_ai_questions' => $this->max_ai_questions,
            'features' => $this->features,
            'is_active' => $this->is_active,
        ];
    }
}
