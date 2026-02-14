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
        'title',
        'content',
        'status',
        'score',
        'competencies',
        'feedback',
        'ai_suggestions',
        'example_essay',
    ];

    protected $casts = [
        'competencies' => 'array',
        'ai_suggestions' => 'array',
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
