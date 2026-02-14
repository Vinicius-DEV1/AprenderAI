<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'simulation_id',
        'question_id',
        'user_answer',
        'is_correct',
        'time_spent',
        'marked_for_review',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'marked_for_review' => 'boolean',
    ];

    public function simulation()
    {
        return $this->belongsTo(Simulation::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
