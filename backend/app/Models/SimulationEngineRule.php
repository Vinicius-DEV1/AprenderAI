<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationEngineRule extends Model
{
    protected $table = 'simulation_engine_rules';

    protected $fillable = [
        'model_id',
        'total_questoes',
        'tempo_minutos',
        'percentual_ia',
        'nao_repetir_ultimos_simulados',
        'difficulty_mode',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'total_questoes' => 'integer',
        'tempo_minutos' => 'integer',
        'percentual_ia' => 'float',
        'nao_repetir_ultimos_simulados' => 'integer',
    ];

    /**
     * The model this rule belongs to.
     */
    public function simulationModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SimulationModel::class, 'model_id');
    }

    /**
     * Discipline distributions for this rule.
     */
    public function distributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SimulationDisciplineDistribution::class, 'rule_id')->orderBy('ordem');
    }
}
