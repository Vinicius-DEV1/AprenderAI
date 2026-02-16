<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_type',
        'exam_name',
        'exam_date',
        'hours_per_day',
        'plan_json',
        'stats_snapshot',
        'generated_at',
        'status',
        'error_message',
        'started_at',
        'finished_at',
        'next_generate_at',
        'next_update_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'plan_json' => 'array',
        'stats_snapshot' => 'array',
        'generated_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'next_generate_at' => 'datetime',
        'next_update_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
