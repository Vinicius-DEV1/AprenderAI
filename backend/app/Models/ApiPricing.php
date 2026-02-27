<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiPricing extends Model
{
    protected $table = 'api_pricing';

    protected $fillable = [
        'api_name',
        'model_key',
        'input_price_per_1m',
        'output_price_per_1m',
        'updated_by',
    ];

    protected $casts = [
        'input_price_per_1m' => 'float',
        'output_price_per_1m' => 'float',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiPricingLog::class, 'api_pricing_id');
    }
}
