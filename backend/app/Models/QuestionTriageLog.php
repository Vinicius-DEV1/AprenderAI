<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionTriageLog extends Model
{
    public $timestamps = false; // Only created_at, managed manually

    protected $fillable = [
        'question_id',
        'triage_type',
        'status',
        'issues_detected',
        'quality_score',
        'changes_made',
        'processed_by',
        'created_at',
    ];

    protected $casts = [
        'issues_detected' => 'array',
        'changes_made' => 'array',
        'quality_score' => 'integer',
        'created_at' => 'datetime',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
