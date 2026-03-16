<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks when users start but do not complete a checkout.
 * Populated by the frontend's beforeunload event or by the backend detecting
 * a checkout_opened event with no subsequent payment_success within a session.
 */
class CheckoutAbandonment extends Model
{
    protected $table = 'checkout_abandonment';

    protected $fillable = [
        'user_id',
        'plan_id',
        'last_step_reached',
        'time_spent_seconds',
        'payment_method_selected',
        'had_coupon',
        'ip',
        'device',
        'browser',
    ];

    protected $casts = [
        'had_coupon' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
