<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsSource
 * 
 * Responsável por armazenar métricas por origem de tráfego, país e cidade.
 */
class AnalyticsSource extends Model
{
    protected $fillable = [
        'date',
        'source_medium',
        'country',
        'city',
        'sessions',
        'users',
    ];

    protected $casts = [
        'date' => 'date',
        'sessions' => 'integer',
        'users' => 'integer',
    ];
}
