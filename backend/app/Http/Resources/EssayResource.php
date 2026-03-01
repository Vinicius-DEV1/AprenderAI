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
        // Safe decode of feedback_json
        $feedbackJson = is_string($this->feedback_json)
            ? json_decode($this->feedback_json, true)
            : ($this->feedback_json ?? []);

        return [
            'id' => $this->id,
            'essay_type' => $this->type, // Expose explicitly for denominator calculation
            'max_score' => $this->type === 'enem' ? 1000 : 100,
            'theme' => $this->theme,
            'status' => $this->status, // 'draft', 'submitted', 'corrected'
            'score' => $this->score,
            'content' => $this->content,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'ocr_status' => $this->ocr_status,
            'ocr_error' => $this->ocr_error,
            'feedback' => $this->feedback, // Kept for legacy compatibility if needed somewhere
            'feedback_json' => $feedbackJson, // Fix for blank tabs (Bug #1)
            'competency_details' => $this->resolveCompetencyDetails($feedbackJson), // Premium C1-C5
            'off_topic' => $this->off_topic ?? false,
            'off_topic_reason' => $this->off_topic_reason ?? null,
            'final_score_locked' => $this->final_score_locked ?? false,
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

    protected function resolveCompetencyDetails(array $feedbackJson): array
    {
        // 1) From relation if loaded
        if ($this->relationLoaded('correction') && $this->correction) {
            $maxPer = $this->type === 'enem' ? 200 : 20;
            return [
                'c1' => ['score' => $this->correction->competency_1 ?? 0, 'max_score' => $maxPer],
                'c2' => ['score' => $this->correction->competency_2 ?? 0, 'max_score' => $maxPer],
                'c3' => ['score' => $this->correction->competency_3 ?? 0, 'max_score' => $maxPer],
                'c4' => ['score' => $this->correction->competency_4 ?? 0, 'max_score' => $maxPer],
                'c5' => ['score' => $this->correction->competency_5 ?? 0, 'max_score' => $maxPer],
            ];
        }

        // 2) From premium feedback_json if available
        if (isset($feedbackJson['competencies']) && is_array($feedbackJson['competencies'])) {
            $mapped = [];
            foreach ($feedbackJson['competencies'] as $key => $data) {
                // Ensure proper structure even if AI hallucinated some keys
                $mapped[$key] = [
                    'score' => $data['score'] ?? 0,
                    'max_score' => $this->type === 'enem' ? 200 : 20,
                    'name' => $data['name'] ?? 'Competência',
                    'justification' => $data['justification'] ?? '',
                    'what_was_done' => $data['what_was_done'] ?? '',
                    'how_to_improve' => $data['how_to_improve'] ?? '',
                ];
            }
            if (!empty($mapped))
                return $mapped;
        }

        // 3) Fallback (blank schema so UI doesn't crash)
        $maxPer = $this->type === 'enem' ? 200 : 20;
        return [
            'c1' => ['score' => 0, 'max_score' => $maxPer, 'name' => 'Competência 1', 'justification' => 'N/A', 'what_was_done' => 'N/A', 'how_to_improve' => 'N/A'],
            'c2' => ['score' => 0, 'max_score' => $maxPer, 'name' => 'Competência 2', 'justification' => 'N/A', 'what_was_done' => 'N/A', 'how_to_improve' => 'N/A'],
            'c3' => ['score' => 0, 'max_score' => $maxPer, 'name' => 'Competência 3', 'justification' => 'N/A', 'what_was_done' => 'N/A', 'how_to_improve' => 'N/A'],
            'c4' => ['score' => 0, 'max_score' => $maxPer, 'name' => 'Competência 4', 'justification' => 'N/A', 'what_was_done' => 'N/A', 'how_to_improve' => 'N/A'],
            'c5' => ['score' => 0, 'max_score' => $maxPer, 'name' => 'Competência 5', 'justification' => 'N/A', 'what_was_done' => 'N/A', 'how_to_improve' => 'N/A'],
        ];
    }
}
