<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSearchCache extends Model
{
    protected $table = 'ai_search_cache';

    protected $fillable = [
        'prompt_hash',
        'prompt_text',
        'embedding',
        'filters_result',
        'concept_ids',
        'last_used_at',
    ];

    protected $casts = [
        'embedding'      => 'array',
        'filters_result' => 'array',
        'concept_ids'    => 'array',
        'last_used_at'   => 'datetime',
    ];
}

