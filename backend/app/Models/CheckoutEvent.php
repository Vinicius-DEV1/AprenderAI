<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks every meaningful event in the checkout journey.
 * Events: prices_viewed, plan_clicked, checkout_opened, payment_initiated,
 *         payment_success, payment_failed, pix_generated, pix_expired
 */
class CheckoutEvent extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'event_type',
        'plan_id',
        'checkout_step',
        'payment_method',
        'device',
        'browser',
        'ip',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Helper Scopes ────────────────────────────────────────────────────────

    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
