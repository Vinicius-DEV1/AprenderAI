<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsDevice
 * 
 * Responsável por armazenar métricas por dispositivo (aquisição/mobile vs desktop).
 */
class AnalyticsDevice extends Model
{
    protected $fillable = [
        'date',
        'device_category', // Desktop, Mobile, Tablet
        'sessions',
        'users',
    ];

    protected $casts = [
        'date' => 'date',
        'sessions' => 'integer',
        'users' => 'integer',
    ];
}
