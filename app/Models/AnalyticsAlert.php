<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsAlert
 * 
 * Responsável por armazenar os alertas sistêmicos gerados pelo módulo de inteligência.
 */
class AnalyticsAlert extends Model
{
    protected $fillable = [
        'type', // drop, peak, growth, anomaly
        'message',
        'details',
        'read_at',
    ];

    protected $casts = [
        'details' => 'array',
        'read_at' => 'datetime',
    ];
}
