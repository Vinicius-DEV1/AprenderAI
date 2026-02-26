<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyPlanResource extends JsonResource
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
            'title' => $this->title,
            'objective' => $this->objective, // e.g. "ENEM 2024", "FUVEST"
            'status' => $this->status, // 'active', 'completed', 'archived'
            'progress_percentage' => floor($this->progress_percentage ?? 0),
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'modules' => $this->whenLoaded('modules', function () {
                // Assuming a JSON structure or relationship for modules inside the plan
                return is_string($this->modules) ? json_decode($this->modules) : $this->modules;
            }),
            'weekly_hours_commitment' => $this->weekly_hours_commitment ?? 10,
            'created_at' => $this->created_at,
        ];
    }
}
