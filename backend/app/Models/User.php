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
        'max_daily_questions_override',
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

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->exists();
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

        if ($this->isAdmin()) {
            return 9999;
        }

        // Fall back to the plan's default limit
        return $this->plan?->simulations_limit ?? 0;
    }

    public function monthlySimulationUsed(): int
    {
        try {
            return (int) app(\App\Services\QuotaService::class)->getUsage($this, 'simulations')['used'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Whether the user can create a new simulation this month.
     *
     * "Unlimited" is represented by limit === 0 (same as Plan::isUnlimited).
     */
    public function canCreateSimulation(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan) {
            return false;
        }

        $limit = $this->simulationQuotaLimit();

        // 9999 = unlimited (plan-level OR override-level)
        if ($limit === 9999) {
            return true;
        }

        return $this->monthlySimulationUsed() < $limit;
    }

    public function incrementSimulationUsage(): void
    {
        try {
            app(\App\Services\QuotaService::class)->consumeQuota($this, 'simulations');
        } catch (\Exception $e) {
        }
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

        if ($this->isAdmin()) {
            return 9999;
        }

        // Plan limit + any purchased essay credits
        return ($this->plan?->essays_limit ?? 0) + ($this->essay_credits ?? 0);
    }

    public function monthlyEssayUsed(): int
    {
        try {
            return (int) app(\App\Services\QuotaService::class)->getUsage($this, 'essays')['used'];
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
     *
     * NOTE: This method no longer swallows unexpected exceptions.
     * It returns false only for genuine "quota exhausted" cases.
     * Unexpected exceptions are re-thrown so callers (EssayController::store)
     * can log them and return HTTP 500 instead of silently returning false
     * which caused the misleading 403 "Você atingiu o limite" error.
     */
    public function canCreateEssay(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan)
            return false;

        // --- PATH A: Admin override is active ---
        if (!is_null($this->max_essays_override)) {
            if ($this->max_essays_override === 9999)
                return true; // unlimited
            return $this->monthlyEssayUsed() < $this->max_essays_override;
        }

        // --- PATH B: No override — use QuotaService.
        // We intentionally do NOT catch exceptions here anymore.
        // A database/infrastructure failure should propagate so that
        // EssayController::store() can log it and return HTTP 500.
        $usage = app(\App\Services\QuotaService::class)->getUsage($this, 'essays');
        $limit = $usage['limit'] ?? 0;

        // null limit means "unlimited" (e.g. Plus plan)
        if ($limit === null)
            return true;

        // 0 limit means the plan does not include essay access
        if ($limit === 0)
            return false;

        return $usage['used'] < $limit;
    }

    /**
     * Whether the plan grants any essay access at all (regardless of current usage).
     * Used to show/hide the essay feature in the UI for free plan users.
     */
    public function hasEssayAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->plan && ($this->plan->essays_limit > 0 || ($this->essay_credits ?? 0) > 0);
    }

    public function incrementEssayUsage(): void
    {
        try {
            app(\App\Services\QuotaService::class)->consumeQuota($this, 'essays');
        } catch (\Exception $e) {
        }
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
        if ($this->isAdmin()) {
            return 9999;
        }
        return $this->max_ai_questions_override ?? $this->plan?->max_ai_questions ?? 0;
    }

    /**
     * Whether the user still has AI prompt quota remaining this billing period.
     */
    public function hasAiQuota(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan) {
            return false;
        }

        $limit = $this->aiQuotaLimit();
        if ($limit === 9999)
            return true;

        return $this->ai_questions_count < $limit;
    }

    /**
     * Increment the AI usage counter. Called after every successful AI request.
     */
    public function incrementAiUsage(): void
    {
        $this->increment('ai_questions_count');
    }

    // =========================================================================
    // DAILY QUESTIONS QUOTA
    // =========================================================================

    public function dailyQuestionQuotaLimit(): int
    {
        if ($this->isAdmin()) {
            return 9999;
        }
        if (!is_null($this->max_daily_questions_override)) {
            return $this->max_daily_questions_override;
        }
        return $this->plan?->daily_question_limit ?? 0;
    }

    public function dailyQuestionUsed(): int
    {
        try {
            return (int) app(\App\Services\QuotaService::class)->getUsage($this, 'daily_questions')['used'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function canAnswerDailyQuestion(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan)
            return false;

        $limit = $this->dailyQuestionQuotaLimit();
        if ($limit === 9999)
            return true;

        return $this->dailyQuestionUsed() < $limit;
    }

    public function incrementDailyQuestionUsage(): void
    {
        try {
            app(\App\Services\QuotaService::class)->consumeQuota($this, 'daily_questions');
        } catch (\Exception $e) {
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
