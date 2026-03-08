<?php

namespace App\Models\Concerns;

use App\Models\Plan;
use App\Models\Favorite;
use App\Models\Notebook;
use App\Models\Simulation;
use App\Models\StudyPlan;
use App\Models\Subscription;
use App\Models\UserQuestionAnswer;

/**
 * HasQuota Trait
 *
 * Encapsulates ALL quota/limit logic for the User model.
 * Extracted from User.php (which had 300+ lines of quota methods)
 * to keep each class focused on a single responsibility.
 *
 * This trait is safe to use via $user->canCreateSimulation() etc.
 * because PHP traits are compiled into the host class.
 */
trait HasQuota
{
    // =========================================================================
    // HELPERS
    // =========================================================================

    public function totalQuestionsAnswered(): int
    {
        return $this->questionAnswers()->count();
    }

    public function hasStudyPlanPrerequisites(): bool
    {
        if ($this->totalQuestionsAnswered() >= 50) {
            return true;
        }

        return $this->simulations()
            ->whereIn('status', ['finished', 'corrected'])
            ->has('answers', '>=', 50)
            ->exists();
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
        if ($this->isAdmin()) {
            return true;
        }

        return $this->hasPlusPlan() && $this->hasStudyPlanPrerequisites();
    }

    // =========================================================================
    // SIMULATION QUOTA
    // =========================================================================

    /**
     * Returns the effective monthly simulation limit for this user.
     * Priority: admin override > plan default.
     * Plan::UNLIMITED (9999) means unlimited.
     */
    public function simulationQuotaLimit(): int
    {
        if (!is_null($this->max_simulations_override)) {
            return $this->max_simulations_override;
        }

        if ($this->isAdmin()) {
            return Plan::UNLIMITED;
        }

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

    public function canCreateSimulation(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan) {
            return false;
        }

        $limit = $this->simulationQuotaLimit();

        if ($limit === Plan::UNLIMITED) {
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
     *   Plan essays_limit = 0  ⟹  essay feature not included in plan (BLOCKED)
     *   Override = 0           ⟹  admin has granted unlimited essays (Plan::UNLIMITED semantics)
     *
     * Priority: admin override > plan default + purchased credits.
     */
    public function essayQuotaLimit(): int
    {
        if (!is_null($this->max_essays_override)) {
            return $this->max_essays_override;
        }

        if ($this->isAdmin()) {
            return Plan::UNLIMITED;
        }

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
     * @deprecated Use essayQuotaLimit() directly.
     */
    public function monthlyEssayLimit(): int
    {
        return $this->essayQuotaLimit();
    }

    public function canCreateEssay(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan) {
            return false;
        }

        // PATH A: Admin override is active
        if (!is_null($this->max_essays_override)) {
            if ($this->max_essays_override === Plan::UNLIMITED) {
                return true;
            }
            return $this->monthlyEssayUsed() < $this->max_essays_override;
        }

        // PATH B: No override — use QuotaService (exceptions propagate intentionally)
        $usage = app(\App\Services\QuotaService::class)->getUsage($this, 'essays');
        $limit = $usage['limit'] ?? 0;

        if ($limit === null) {
            return true; // null = unlimited
        }

        if ($limit === 0) {
            return false; // plan has no essay access
        }

        return $usage['used'] < $limit;
    }

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
    // AI PROMPT QUOTA
    // =========================================================================

    public function aiQuotaLimit(): int
    {
        if ($this->isAdmin()) {
            return Plan::UNLIMITED;
        }
        return $this->max_ai_questions_override ?? $this->plan?->max_ai_questions ?? 0;
    }

    public function hasAiQuota(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->plan) {
            return false;
        }

        $limit = $this->aiQuotaLimit();
        if ($limit === Plan::UNLIMITED) {
            return true;
        }

        return $this->ai_questions_count < $limit;
    }

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
            return Plan::UNLIMITED;
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

        if (!$this->plan) {
            return false;
        }

        $limit = $this->dailyQuestionQuotaLimit();
        if ($limit === Plan::UNLIMITED) {
            return true;
        }

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
    // NOTEBOOK QUOTA
    // =========================================================================

    public function canCreateNotebook(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->hasActiveSubscription()) {
            return true;
        }

        // Free plan: max 1 notebook
        return $this->notebooks()->count() < 1;
    }
}
