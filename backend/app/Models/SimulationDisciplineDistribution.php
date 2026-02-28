<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationDisciplineDistribution extends Model
{
    protected $table = 'simulation_discipline_distributions';

    protected $fillable = [
        'rule_id',
        'disciplina',
        'percentual',
        'dificuldade',
        'ordem',
    ];

    protected $casts = [
        'percentual' => 'float',
        'ordem' => 'integer',
    ];

    public function rule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SimulationEngineRule::class, 'rule_id');
    }
}
