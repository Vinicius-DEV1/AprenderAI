<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'subject',
        'theme',
        'difficulty',
        'year',
        'statement',
        'alternatives',
        'correct_answer',
        'explanation',
        'source',
    ];

    protected $casts = [
        'alternatives' => 'array',
    ];

    public function simulationAnswers()
    {
        return $this->hasMany(SimulationAnswer::class);
    }

    public function isCorrect(string $answer): bool
    {
        return strtoupper($answer) === strtoupper($this->correct_answer);
    }
}
