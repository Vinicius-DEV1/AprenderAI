<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


/**
 * Tracks each click on a plan subscription button and follows through to conversion.
 * Enables tracking of purchase intent → abandoned vs converted.
 */
class PurchaseIntention extends Model
{
    use FiltersAdmins;


    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_amount',
        'had_coupon',
        'source_page',
        'device',
        'browser',
        'ip',
        'status',
        'converted_at',
        'time_to_convert_seconds',
        'subscription_id',
    ];

    protected $casts = [
        'plan_amount'     => 'decimal:2',
        'converted_at'    => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_ABANDONED = 'abandoned';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', self::STATUS_CONVERTED);
    }

    public function scopeAbandoned($query)
    {
        return $query->where('status', self::STATUS_ABANDONED);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
