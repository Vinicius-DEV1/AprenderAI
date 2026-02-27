<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStat extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $dates = ['updated_at'];

    protected $fillable = [
        'user_id',
        'total_simulations',
        'total_essays',
        'average_math_score',
        'average_portuguese_score',
        'average_overall_score',
        'total_time_studied',
        'weak_themes',
        'strong_themes',
    ];

    protected $casts = [
        'average_math_score' => 'decimal:2',
        'average_portuguese_score' => 'decimal:2',
        'average_overall_score' => 'decimal:2',
        'weak_themes' => 'array',
        'strong_themes' => 'array',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
