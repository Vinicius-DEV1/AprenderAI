<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsEvent
 * 
 * Responsável por armazenar métricas de conversão e eventos principais.
 */
class AnalyticsEvent extends Model
{
    protected $fillable = [
        'date',
        'event_name',
        'event_count',
        'users',
    ];

    protected $casts = [
        'date' => 'date',
        'event_count' => 'integer',
        'users' => 'integer',
    ];
}
