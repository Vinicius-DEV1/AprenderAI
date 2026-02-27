<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiPricingLog extends Model
{
    protected $fillable = [
        'api_pricing_id',
        'updated_by',
        'old_input_price_per_1m',
        'old_output_price_per_1m',
        'new_input_price_per_1m',
        'new_output_price_per_1m',
    ];

    protected $casts = [
        'old_input_price_per_1m' => 'float',
        'old_output_price_per_1m' => 'float',
        'new_input_price_per_1m' => 'float',
        'new_output_price_per_1m' => 'float',
    ];

    public function apiPricing(): BelongsTo
    {
        return $this->belongsTo(ApiPricing::class, 'api_pricing_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
