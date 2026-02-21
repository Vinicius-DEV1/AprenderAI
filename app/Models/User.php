<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'plan_id',
        'plan_started_at',
        'plan_expires_at',
        'google_id',
        'avatar_url',
        'is_banned',
        'ai_questions_count',
        'last_reset_at',
        'essay_credits',
        'max_ai_questions_override',
    ];

    // ... (unchanged code) ...

    public function canCreateEssay(): bool
    {
        if (!$this->plan) {
            return false;
        }

        // Unlimited check
        if ($this->plan->isUnlimited('essays')) {
            return true;
        }

        // Free plan (0 limits) but might have bought credits? 
        // Logic says "Plano Básico" (2 limits) and "Plus" (15 limits).
        // If plan limit is 0, usually they can't create. 
        // But if they have credits, they should be able to.
        // However, the effective limit is plan_limit + essay_credits.

        $effectiveLimit = $this->plan->essays_limit + $this->essay_credits;

        if ($effectiveLimit === 0) {
            return false;
        }

        // Strict Count: essays where submitted_at is in current month/year
        $usage = $this->essays()
            ->whereNotNull('submitted_at')
            ->whereYear('submitted_at', now()->year)
            ->whereMonth('submitted_at', now()->month)
            ->count();

        return $usage < $effectiveLimit;
    }

    public function hasEssayAccess(): bool
    {
        // If they have credits, they effectively have access even if plan is 0?
        // But the requirement says "Limit base" (Plus/Basic).
        // Let's stick to plan checks for general access, but credits extend the numerical limit.
        return $this->plan && ($this->plan->essays_limit !== 0 || $this->essay_credits > 0);
    }

    public function monthlyEssayLimit(): int
    {
        return $this->plan ? ($this->plan->essays_limit + $this->essay_credits) : 0;
    }

    // ... (unchanged code) ...

    protected function resetUsageIfNeeded(): void
    {
        if (!$this->usage_reset_at || $this->usage_reset_at->isPast()) {
            $this->update([
                'simulations_used_this_month' => 0,
                'essays_used_this_month' => 0,
                'essay_credits' => 0, // Reset credits on cycle turn
                'usage_reset_at' => now()->addMonth(),
            ]);
        }
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'is_banned' => 'boolean',
            'usage_reset_at' => 'datetime',
            'last_reset_at' => 'datetime',
        ];
    }

    // Relacionamentos
    public function logs()
    {
        return $this->hasMany(UserLog::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function simulations()
    {
        return $this->hasMany(Simulation::class);
    }

    public function essays()
    {
        return $this->hasMany(Essay::class);
    }

    public function stats()
    {
        return $this->hasOne(UserStat::class);
    }

    public function studyPlans()
    {
        return $this->hasMany(StudyPlan::class);
    }

    public function questionAnswers()
    {
        return $this->hasMany(UserQuestionAnswer::class);
    }

    // Métodos auxiliares
    public function hasPlusPlan(): bool
    {
        return $this->plan && $this->plan->name === 'Plus';
    }

    public function hasCompletedSimulation(): bool
    {
        return $this->simulations()
            ->where('status', 'finished')
            ->whereHas('answers')
            ->exists();
    }

    public function canAccessStudyPlan(): bool
    {
        return $this->hasPlusPlan() && $this->hasCompletedSimulation();
    }
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canCreateSimulation(): bool
    {
        if (!$this->plan)
            return false;
        if ($this->plan->isUnlimited('simulations'))
            return true;

        $this->resetUsageIfNeeded();
        return $this->simulations_used_this_month < $this->plan->simulations_limit;
    }

    public function monthlyEssayUsed(): int
    {
        return $this->essays()
            ->whereNotNull('submitted_at')
            ->whereYear('submitted_at', now()->year)
            ->whereMonth('submitted_at', now()->month)
            ->count();
    }

    public function incrementSimulationUsage(): void
    {
        $this->resetUsageIfNeeded();
        $this->increment('simulations_used_this_month');
    }

    // Deprecated but kept for backward compatibility if needed, though logic now uses monthlyEssayUsed()
    public function incrementEssayUsage(): void
    {
        // No-op for new logic, or keep updating for legacy stats
        $this->resetUsageIfNeeded();
        $this->increment('essays_used_this_month');
    }

    public function aiQuotaLimit(): int
    {
        return $this->max_ai_questions_override ?? $this->plan->max_ai_questions ?? 0;
    }

    public function hasAiQuota(): bool
    {
        if (!$this->plan) {
            return false;
        }
        
        return $this->ai_questions_count < $this->aiQuotaLimit();
    }

    public function incrementAiUsage(): void
    {
        $this->increment('ai_questions_count');
    }
}
