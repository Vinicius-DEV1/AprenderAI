<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log for all Asaas payment events.
 *
 * IMPORTANT – PCI COMPLIANCE:
 * Never store creditCard.number, creditCard.ccv or creditCard.expiryMonth/Year.
 * The raw_response column must have sensitive fields stripped before saving.
 */
class PaymentLog extends Model
{
    protected $fillable = [
        'user_id',
        'gateway',
        'is_sandbox',
        'gateway_payment_id',
        'gateway_subscription_id',
        'event',
        'status',
        'raw_response',
        'error_message',
    ];

    protected $casts = [
        'is_sandbox' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePaid($query)
    {
        return $query->where('is_sandbox', false);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Strip sensitive card fields from a response body before persisting.
     * Accepts a JSON string or an array.
     *
     * @param string|array $rawResponse
     * @return string  Safe JSON string
     */
    public static function sanitize(string|array $rawResponse): string
    {
        if (is_string($rawResponse)) {
            $data = json_decode($rawResponse, true) ?? [];
        } else {
            $data = $rawResponse;
        }

        // Remove sensitive card fields at any nesting level
        $sensitiveKeys = ['number', 'ccv', 'cvv', 'creditCard', 'creditCardHolderInfo'];
        foreach ($sensitiveKeys as $key) {
            unset($data[$key]);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
