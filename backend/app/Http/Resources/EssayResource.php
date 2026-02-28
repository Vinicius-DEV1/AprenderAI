<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EssayResource extends JsonResource
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
            'theme' => $this->theme,
            'status' => $this->status, // 'draft', 'submitted', 'corrected'
            'score' => $this->score,
            'content' => $this->content,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'ocr_status' => $this->ocr_status,
            'ocr_error' => $this->ocr_error,
            'feedback' => $this->feedback, // JSON structure with competencies
            'created_at' => $this->created_at,
            'submitted_at' => $this->submitted_at,
            'evaluated_at' => $this->evaluated_at,
            'correction_details' => $this->whenLoaded('correction', function () {
                return [
                    'competency_1' => $this->correction->competency_1 ?? 0,
                    'competency_2' => $this->correction->competency_2 ?? 0,
                    'competency_3' => $this->correction->competency_3 ?? 0,
                    'competency_4' => $this->correction->competency_4 ?? 0,
                    'competency_5' => $this->correction->competency_5 ?? 0,
                    'general_comment' => $this->correction->general_comment ?? '',
                ];
            }),
        ];
    }
}
