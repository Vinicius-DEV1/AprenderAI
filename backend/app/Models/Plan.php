<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * Sentinel value representing an unlimited quota.
     *
     * Using 9999 (instead of 0 or null) avoids ambiguity:
     *   - Plan::UNLIMITED  → unlimited (admin or premium feature)
     *   - 0                → feature blocked / not included in plan
     *   - null             → not set (different from unlimited or blocked)
     *
     * All quota comparisons across the codebase must use this constant.
     */
    public const UNLIMITED = 9999;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'monthly_price',
        'annual_price',
        'discount_percentage',
        'interval',
        'simulations_limit',
        'essays_limit',
        'daily_question_limit',
        'features',
        'is_active',
        'max_ai_questions',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'simulations_limit' => 'integer',
        'essays_limit' => 'integer',
        'daily_question_limit' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimited(string $feature): bool
    {
        return match ($feature) {
            'simulations' => $this->simulations_limit === self::UNLIMITED,
            'essays' => $this->essays_limit === self::UNLIMITED,
            'daily_questions' => $this->daily_question_limit === self::UNLIMITED,
            default => false,
        };
    }

    public function isAnnual(): bool
    {
        return $this->interval === 'yearly';
    }

    public function isInstallmentEligible(): bool
    {
        return $this->isAnnual() && $this->annual_price > 0;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}

