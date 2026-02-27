<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsHourly
 * 
 * Responsável por armazenar métricas agrupadas por hora (horários de pico).
 */
class AnalyticsHourly extends Model
{
    protected $fillable = [
        'date',
        'hour',
        'active_users',
        'sessions',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'active_users' => 'integer',
        'sessions' => 'integer',
    ];
}
