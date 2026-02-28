<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationModel extends Model
{
    protected $fillable = ['slug', 'nome', 'tipo', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Get the active rule for this model.
     */
    public function activeRule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SimulationEngineRule::class, 'model_id')->where('ativo', true)->latest();
    }

    /**
     * All rules for this model.
     */
    public function rules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SimulationEngineRule::class, 'model_id');
    }
}
