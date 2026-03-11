<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchInteractionLog extends Model
{
    const UPDATED_AT = null; // only created_at

    protected $fillable = [
        'ai_search_id',
        'user_id',
        'question_id',
        'rank_position',
        'was_clicked',
        'time_to_click_ms',
        'expanded_concept_ids',
        'search_path',
    ];

    protected $casts = [
        'was_clicked'          => 'boolean',
        'expanded_concept_ids' => 'array',
    ];

    public function aiSearchRequest(): BelongsTo
    {
        return $this->belongsTo(AiSearchRequest::class, 'ai_search_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
