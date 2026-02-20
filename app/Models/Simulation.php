<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Simulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'configuration',
        'started_at',
        'finished_at',
        'time_elapsed',
        'status',
        'score',
        'scores_by_subject',
        'analysis_by_theme',
    ];

    protected $casts = [
        'configuration' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'score' => 'decimal:2',
        'scores_by_subject' => 'array',
        'analysis_by_theme' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(SimulationAnswer::class)->orderBy('id');
    }

    public function correction()
    {
        return $this->morphOne(Correction::class, 'correctable');
    }

    public function essay()
    {
        return $this->hasOne(Essay::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['finished', 'corrected']);
    }

    public function isCorrected(): bool
    {
        return $this->status === 'corrected';
    }

    public function startSimulation(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function finishSimulation(): void
    {
        $this->update([
            'status' => 'finished',
            'finished_at' => now(),
            'time_elapsed' => now()->diffInSeconds($this->started_at),
        ]);
    }
}
