<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationRule extends Model
{
    protected $fillable = ['preset_id', 'category', 'configuration'];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function preset()
    {
        return $this->belongsTo(SimulationPreset::class, 'preset_id');
    }
}
