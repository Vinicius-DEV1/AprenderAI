<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionEventLog extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'subject_id',
        'action',
        'is_correct',
        'time_spent_seconds',
        'source',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'time_spent_seconds' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
