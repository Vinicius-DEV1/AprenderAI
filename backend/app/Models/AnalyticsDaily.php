<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsDaily
 * 
 * Responsável por armazenar as métricas diárias agregadas do Google Analytics.
 */
class AnalyticsDaily extends Model
{
    protected $fillable = [
        'date',
        'active_users',
        'sessions',
        'new_users',
        'bounce_rate',
        'avg_session_duration',
        'screen_page_views_per_session',
    ];

    protected $casts = [
        'date' => 'date',
        'active_users' => 'integer',
        'sessions' => 'integer',
        'new_users' => 'integer',
        'bounce_rate' => 'float',
        'avg_session_duration' => 'float',
        'screen_page_views_per_session' => 'float',
    ];
}
