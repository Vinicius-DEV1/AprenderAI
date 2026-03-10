<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchRequest extends Model
{
    const DEFAULT_THRESHOLD = 0.88;

    protected $fillable = [
        'user_id',
        'prompt',
        'filters',
        'similarity_threshold',
        'status',
        'error',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
