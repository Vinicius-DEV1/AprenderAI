<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Essay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'simulation_id',
        'type',
        'time_limit',
        'title',
        'theme', // Deprecated but preserved
        'topic_description',
        'content',
        'input_type',
        'image_path',
        'extracted_text',
        'ocr_status',
        'ocr_error',
        'status',
        'score',
        'competencies',
        'feedback',
        'feedback_json',
        'ai_suggestions',
        'improved_version',
        'example_essay',
        'topic_regen_count',
        'topic_hash',
        'started_at',
        'submitted_at',
        'evaluated_at',
        'off_topic',
        'off_topic_reason',
        'final_score_locked',
    ];

    protected $casts = [
        'competencies' => 'array',
        // ai_suggestions is stored as a plain string (correction text), NOT a JSON array
        'feedback_json' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'topic_regen_count' => 'integer',
        'time_limit' => 'integer',
        'off_topic' => 'boolean',
        'final_score_locked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function simulation()
    {
        return $this->belongsTo(Simulation::class);
    }

    public function correction()
    {
        return $this->morphOne(Correction::class, 'correctable');
    }

    public function isCorrected(): bool
    {
        return $this->status === 'corrected';
    }
}
