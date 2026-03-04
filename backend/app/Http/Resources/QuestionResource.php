<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isList = !$request->routeIs('*.show') && !$request->boolean('include_details');

        return [
            'id' => $this->id,
            // Restore arrays for frontend compatibility (QuestionCard.tsx uses .map)
            'subjects' => $this->subjects->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
            'topics' => $this->topics->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
            'statement' => $isList ? \Str::limit($this->statement, 150) : $this->statement,
            'statement_html' => $this->statement_html, // Restored name and visibility
            'alternatives' => $this->whenLoaded('alternatives', function () {
                return $this->alternatives->map(function ($alt) {
                    $item = [
                        'id' => $alt->id,
                        'label' => $alt->label, // Restored property name
                        'content' => $alt->content,
                    ];

                    if (request()->boolean('include_answers') || $alt->is_correct) {
                        $item['is_correct'] = $alt->is_correct;
                    }
                    return $item;
                });
            }),
            'difficulty' => $this->difficulty,
            'organization' => $this->organization,
            'institution' => $this->institution,
            'role' => $this->role,
            'year' => $this->year,
            'type' => $this->type,
            'tipo_questao' => $this->tipo_questao,
            'discursive_answer' => $this->when($request->routeIs('*.show') || $request->boolean('include_answers'), $this->discursive_answer),
            'explanation' => $this->when(request()->boolean('include_answers'), $this->explanation),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($img) {
                    return ['url' => $img->url, 'caption' => $img->caption];
                });
            }),
            'already_answered' => $this->when(auth()->check(), function () {
                return $this->userAnswers()->where('user_id', auth()->id())->exists();
            }),
            'is_favorite' => $this->when(isset($this->is_favorite), fn() => (bool) $this->is_favorite),
            'has_notes' => $this->when(isset($this->has_notes), fn() => (bool) $this->has_notes),
            'notebook_ids' => $this->whenLoaded('notebooks', function () {
                return $this->notebooks->pluck('id');
            }),
        ];
    }
}
