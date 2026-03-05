<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

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
            'simulations' => $this->simulations_limit === 9999,
            'essays' => $this->essays_limit === 9999,
            'daily_questions' => $this->daily_question_limit === 9999,
            default => false,
        };
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
