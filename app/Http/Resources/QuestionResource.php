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
        return [
            'id' => $this->id,
            'subject' => $this->subjects->first() ? ['id' => $this->subjects->first()->id, 'name' => $this->subjects->first()->name] : null,
            'topic' => $this->topics->first() ? ['id' => $this->topics->first()->id, 'name' => $this->topics->first()->name] : null,
            'statement' => $this->statement,
            'html_statement' => $this->statement_html, // Use the accessor from Question model
            'alternatives' => $this->whenLoaded('alternatives', function () {
                return $this->alternatives->map(function ($alt) {
                    $item = [
                        'id' => $alt->id,
                        'letter' => $alt->label, // Syncing with DB column 'label'
                        'text' => $alt->content, // Syncing with DB column 'content'
                    ];

                    if (request()->boolean('include_answers')) {
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
            'explanation' => $this->when(request()->boolean('include_answers'), $this->explanation),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($img) {
                    return [
                        'url' => $img->url,
                        'caption' => $img->caption
                    ];
                });
            }),
        ];
    }
}
