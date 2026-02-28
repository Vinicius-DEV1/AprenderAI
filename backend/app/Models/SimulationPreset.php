<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationPreset extends Model
{
    protected $fillable = ['name', 'description', 'type', 'is_active'];

    public function rules()
    {
        return $this->hasMany(SimulationRule::class, 'preset_id');
    }
}
