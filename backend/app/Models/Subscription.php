<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'is_manual_grant',
        'is_sandbox',
        'granted_by',
        'granted_reason',
        'gateway',
        'gateway_id',
        'billing_type',
        'amount',
        'pix_payload',
        'pix_image',
        'pix_expires_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_manual_grant' => 'boolean',
        'is_sandbox' => 'boolean',
        'pix_expires_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
            $this->current_period_end &&
            $this->current_period_end->isFuture();
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopePaid($query)
    {
        return $query->where('is_manual_grant', false)->where('is_sandbox', false);
    }

    public function scopeGrants($query)
    {
        return $query->where('is_manual_grant', true);
    }
}
