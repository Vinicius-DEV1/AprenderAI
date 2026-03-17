<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


/**
 * Records all checkout-related failures for diagnostics and alerting.
 * Types: payment_failed, gateway_error, validation_error, frontend_error,
 *        timeout, network_error, unknown
 */
class CheckoutError extends Model
{
    use FiltersAdmins;


    protected $fillable = [
        'user_id',
        'plan_id',
        'error_type',
        'error_message',
        'stack_trace',
        'gateway_response',
        'checkout_step',
        'payment_method',
        'device',
        'browser',
        'ip',
    ];

    protected $casts = [
        'gateway_response' => 'array',
    ];

    // Error type constants
    public const TYPE_PAYMENT_FAILED   = 'payment_failed';
    public const TYPE_GATEWAY_ERROR    = 'gateway_error';
    public const TYPE_VALIDATION_ERROR = 'validation_error';
    public const TYPE_FRONTEND_ERROR   = 'frontend_error';
    public const TYPE_TIMEOUT          = 'timeout';
    public const TYPE_NETWORK_ERROR    = 'network_error';
    public const TYPE_UNKNOWN          = 'unknown';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByType($query, string $type)
    {
        return $query->where('error_type', $type);
    }

    public function scopeRecent($query, int $hours = 2)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
