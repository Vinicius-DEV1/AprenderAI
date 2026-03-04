<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'total_answered',
        'total_correct',
    ];

    protected $casts = [
        'date' => 'date',
        'total_answered' => 'integer',
        'total_correct' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
