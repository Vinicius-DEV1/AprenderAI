<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\EssayResource;

class SimulationResource extends JsonResource
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
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status, // 'generating', 'ready', 'in_progress', 'completed'
            'score' => $this->score,
            'calculated_score' => $this->isFinished() ? ($this->answers->count() > 0 ? round(($this->answers->where('is_correct', true)->count() / $this->answers->count()) * 100, 1) : 0) : null,
            'formatted_date' => $this->created_at->format('d/m/Y'),
            // time_elapsed is the real DB column (finishSimulation() saves seconds elapsed there)
            'time_spent' => $this->time_elapsed,
            'questions_count' => $this->questions_count ?? $this->answers_count ?? $this->answers()->count(),
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'configuration' => $this->configuration,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'essay' => new EssayResource($this->whenLoaded('essay')),
            'answers' => $this->whenLoaded('answers', function () {
                return $this->answers->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'question_id' => $answer->question_id,
                        'selected_alternative_id' => $answer->selected_alternative_id,
                        'user_answer' => $answer->user_answer,
                        'is_correct' => $answer->is_correct,
                        'time_spent' => $answer->time_spent,
                        'question' => $answer->relationLoaded('question') ? new QuestionResource($answer->question) : null,
                    ];
                });
            }),
        ];
    }
}
