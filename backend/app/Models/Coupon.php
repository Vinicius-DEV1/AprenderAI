<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'start_date',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'start_date' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('order_id')->withTimestamps();
    }

    public function isValid(?User $user = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }
        
        // Check if user already used this coupon
        if ($user) {
             // Assuming coupons are single use per user for now, or we can add a setting for that.
             // For this requirements "Tipo (Uso Único ou Múltiplo)" seems to refer to global usage?
             // "Quantidade Máxima de Usos" -> Global limit.
             // Usually coupons are 1 per user. Let's enforce that for now.
             if ($this->users()->where('user_id', $user->id)->exists()) {
                 return false;
             }
        }

        return true;
    }
}
