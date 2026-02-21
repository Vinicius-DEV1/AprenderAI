<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'api_key_id',
        'api_key_name',
        'provider',
        'model',
        'prompt_text',
        'response_text',
        'prompt_preview',
        'response_preview',
        'tokens_used_input',
        'tokens_used_output',
        'tokens_used_total',
        'execution_time',
        'execution_time_ms',
        'estimated_cost',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
