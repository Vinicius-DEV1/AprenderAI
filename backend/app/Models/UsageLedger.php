<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_cycle_id',
        'feature_name',
        'amount',
        'type',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'subscription_cycle_id');
    }
}
