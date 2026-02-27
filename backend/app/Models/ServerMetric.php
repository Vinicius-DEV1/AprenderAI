<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'cpu_usage',
        'ram_usage',
        'net_rx_speed',
        'net_tx_speed',
    ];

    protected $casts = [
        'cpu_usage' => 'float',
        'ram_usage' => 'float',
        'net_rx_speed' => 'integer',
        'net_tx_speed' => 'integer',
    ];
}
