<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchRequest extends Model
{
    protected $fillable = [
        'user_id',
        'prompt',
        'filters',
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
