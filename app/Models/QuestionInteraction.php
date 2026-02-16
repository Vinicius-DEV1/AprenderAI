<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'simulation_id',
        'question_id',
        'user_id',
        'role',
        'message',
    ];

    public function simulation()
    {
        return $this->belongsTo(Simulation::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
