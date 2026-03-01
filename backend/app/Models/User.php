<?php

namespace App\Models;

// Uncommented MustVerifyEmail
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Model
 *
 * Central model containing all business rules related to quota/limit enforcement.
 * All quota decisions (can the user create X?) should be made here, not in controllers,
 * so that the logic is reusable across controllers, jobs, and services.
 *
 * QUOTA PRIORITY (from highest to lowest):
 *  1. Individual Admin Override (max_*_override columns) — per-user setting by admin
 *  2. Plan Default (plan->simulations_limit, plan->essays_limit)
 *  3. Zero / Blocked — if no plan or plan limit is 0
 *
 * This follows the same pattern as AI Prompts: `max_ai_questions_override`.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // =========================================================================
    // MASS ASSIGNABLE
    // =========================================================================

    /**
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
        // AI Prompt quota
        'ai_questions_count',
        'last_reset_at',
        'max_ai_questions_override',
        // Essay credit purchases
        'essay_credits',
        // Individual quota overrides (set by admin per user)
        'max_simulations_override',
        'max_essays_override',
        // Asaas gateway customer reference — used to avoid duplicate customers
        'asaas_customer_id',
    ];

    // =========================================================================
    // HIDDEN & CASTS
    // =========================================================================

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
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

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

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

    public function promptLogs()
    {
        return $this->hasMany(AiRequestLog::class);
    }

    // =========================================================================
    // ROLE & PLAN HELPERS
    // =========================================================================

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

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

    // =========================================================================
    // SIMULATION QUOTA
    // =========================================================================

    /**
     * Returns the effective monthly simulation limit for this user.
     *
     * Priority: admin override > plan default.
     * A value of 0 means "unlimited" (consistent with Plan::isUnlimited).
     */
    public function simulationQuotaLimit(): int
    {
        // Admin has set an individual override for this user
        if (!is_null($this->max_simulations_override)) {
            return $this->max_simulations_override;
        }

        // Fall back to the plan's default limit
        return $this->plan?->simulations_limit ?? 0;
    }

    /**
     * How many simulations the user has created in the current billing cycle.
     * Uses the `simulations_used_this_month` counter (reset via resetUsageIfNeeded).
     */
    public function monthlySimulationUsed(): int
    {
        $this->resetUsageIfNeeded();
        return $this->simulations_used_this_month ?? 0;
    }

    /**
     * Whether the user can create a new simulation this month.
     *
     * "Unlimited" is represented by limit === 0 (same as Plan::isUnlimited).
     */
    public function canCreateSimulation(): bool
    {
        if (!$this->plan) {
            return false;
        }

        $limit = $this->simulationQuotaLimit();

        // 0 = unlimited (plan-level OR override-level)
        if ($limit === 0) {
            return true;
        }

        $this->resetUsageIfNeeded();

        return $this->simulations_used_this_month < $limit;
    }

    /**
     * Increment the simulation usage counter within the current billing cycle.
     * Always calls resetUsageIfNeeded() first to ensure the cycle is current.
     */
    public function incrementSimulationUsage(): void
    {
        $this->resetUsageIfNeeded();
        $this->increment('simulations_used_this_month');
    }

    // =========================================================================
    // ESSAY QUOTA
    // =========================================================================

    /**
     * Returns the effective monthly essay limit for this user.
     *
     * IMPORTANT SEMANTIC DISTINCTION:
     *
     *   Plan essays_limit = 0  ⟹  essay feature not included in this plan (BLOCKED)
     *   Override = 0           ⟹  this admin has granted unlimited essays to this user
     *
     * Priority: admin override > plan default + purchased credits.
     * When an admin override is set, credits are intentionally ignored (admin has full control).
     *
     * A return value of 0 (from the override) is treated as "unlimited" in canCreateEssay().
     * A return value of 0 (from the plan) means the plan has no essay access.
     * These two cases are disambiguated by checking !is_null($this->max_essays_override).
     */
    public function essayQuotaLimit(): int
    {
        // Admin has set an individual override — 0 means unlimited in this context
        if (!is_null($this->max_essays_override)) {
            return $this->max_essays_override;
        }

        // Plan limit + any purchased essay credits
        return ($this->plan?->essays_limit ?? 0) + ($this->essay_credits ?? 0);
    }

    /**
     * How many essays the user has submitted in the current calendar month.
     * Repassado para a Query unificada do QuotaService (Ledgers do ciclo ativo).
     */
    public function monthlyEssayUsed(): int
    {
        try {
            return app(\App\Services\QuotaService::class)->getUsage($this, 'essays')['used'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * The displayed monthly essay limit (for UI).
     *
     * @deprecated Use essayQuotaLimit() directly. Kept for backward compatibility.
     */
    public function monthlyEssayLimit(): int
    {
        return $this->essayQuotaLimit();
    }

    /**
     * Whether the user can create (and submit) a new essay this month.
     * Centralizado no QuotaService (Lógica de Ciclo e Acumulação).
     */
    public function canCreateEssay(): bool
    {
        if (!$this->plan)
            return false;

        // --- PATH A: Admin override is active ---
        if (!is_null($this->max_essays_override)) {
            if ($this->max_essays_override === 0)
                return true; // unlimited
            return $this->monthlyEssayUsed() < $this->max_essays_override;
        }

        // --- PATH B: No override, verifica QuotaService real ---
        try {
            $usage = app(\App\Services\QuotaService::class)->getUsage($this, 'essays');
            $limit = $usage['limit'] ?? 0;

            // Ilimitado no JSON (ex: Plus Plan)
            if ($limit === null)
                return true;

            return $usage['used'] < $limit;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Whether the plan grants any essay access at all (regardless of current usage).
     * Used to show/hide the essay feature in the UI for free plan users.
     */
    public function hasEssayAccess(): bool
    {
        return $this->plan && ($this->plan->essays_limit !== 0 || ($this->essay_credits ?? 0) > 0);
    }

    /**
     * Increment essay usage counter (for legacy stats tracking).
     *
     * NOTE: The primary essay quota check uses monthlyEssayUsed() which counts
     * submissions from the essays table. This counter is kept for admin stats only.
     *
     * @deprecated The essays_used_this_month column is a legacy stat counter.
     */
    public function incrementEssayUsage(): void
    {
        $this->resetUsageIfNeeded();
        $this->increment('essays_used_this_month');
    }

    // =========================================================================
    // AI PROMPT QUOTA (existing, unchanged logic — documented for consistency)
    // =========================================================================

    /**
     * Returns the effective AI question limit for this user.
     * Priority: admin override > plan default.
     */
    public function aiQuotaLimit(): int
    {
        return $this->max_ai_questions_override ?? $this->plan?->max_ai_questions ?? 0;
    }

    /**
     * Whether the user still has AI prompt quota remaining this billing period.
     */
    public function hasAiQuota(): bool
    {
        if (!$this->plan) {
            return false;
        }

        return $this->ai_questions_count < $this->aiQuotaLimit();
    }

    /**
     * Increment the AI usage counter. Called after every successful AI request.
     */
    public function incrementAiUsage(): void
    {
        $this->increment('ai_questions_count');
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Reset monthly simulation/essay counters if the billing cycle has turned over.
     *
     * BILLING CYCLE:
     * The cycle is defined by `usage_reset_at`. When it is past, counters are
     * zeroed and the next reset is scheduled +1 month forward.
     *
     * NOTE: essay_credits are also zeroed on cycle reset — purchased credits
     * do not roll over across billing periods.
     */
    protected function resetUsageIfNeeded(): void
    {
        if (!$this->usage_reset_at || $this->usage_reset_at->isPast()) {
            $this->update([
                'simulations_used_this_month' => 0,
                'essays_used_this_month' => 0,
                'essay_credits' => 0,
                'usage_reset_at' => now()->addMonth(),
            ]);
        }
    }
    // =========================================================================
    // NATIVE EMAIL VERIFICATION OVERRIDE
    // =========================================================================

    /**
     * Override default email verification notification dispatch.
     * Native Laravel Behavior: Dispatches the SendEmailVerificationNotification synchronously when
     * the Registered event is fired.
     *
     * New Behavior: Do nothing here. We handle asynchronous dispatch manually when the user requests it
     * via point `POST /api/v1/email/resend-verification`.
     */
    public function sendEmailVerificationNotification()
    {
        // Intentionally left blank to disable automatic email sending on registration.
    }
}
