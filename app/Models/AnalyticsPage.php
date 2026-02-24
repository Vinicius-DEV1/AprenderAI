<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsPage
 * 
 * Responsável por armazenar métricas por página (comportamento).
 */
class AnalyticsPage extends Model
{
    protected $fillable = [
        'date',
        'page_path',
        'page_title',
        'views',
        'avg_time_on_page',
        'exit_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'views' => 'integer',
        'avg_time_on_page' => 'float',
        'exit_rate' => 'float',
    ];
}
