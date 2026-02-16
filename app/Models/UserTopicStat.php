<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTopicStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'topic',
        'attempts',
        'correct',
        'accuracy',
        'last_attempt_at',
    ];

    protected $casts = [
        'accuracy' => 'decimal:2',
        'last_attempt_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
