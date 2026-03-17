<?php

namespace App\Models;

// Uncommented MustVerifyEmail
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasQuota;
use App\Models\Concerns\FiltersAdmins;


/**
 * User Model
 *
 * Central authentication and identity model.
 *
 * Quota & limit logic has been extracted to the HasQuota trait
 * (app/Models/Concerns/HasQuota.php) to keep this class focused
 * on identity, relationships, and role helpers.
 *
 * QUOTA PRIORITY (from highest to lowest):
 *  1. Individual Admin Override (max_*_override columns) — per-user setting by admin
 *  2. Plan Default (plan->simulations_limit, plan->essays_limit)
 *  3. Zero / Blocked — if no plan or plan limit is 0
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasQuota, FiltersAdmins;


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

    public function notebooks()
    {
        return $this->hasMany(Notebook::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function questionReports()
    {
        return $this->hasMany(QuestionReport::class);
    }

    public function questionNotes()
    {
        return $this->hasMany(QuestionNote::class);
    }

    // =========================================================================
    // ROLE & PLAN HELPERS
    // =========================================================================

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function activePlan()
    {
        // Gets all valid subscriptions (active or canceled but still within period)
        $validSubs = $this->subscriptions()
            ->whereIn('status', ['active', 'canceled'])
            ->where('current_period_end', '>', now())
            ->with('plan')
            ->get();

        if ($validSubs->isNotEmpty()) {
            // Prioritize the most expensive plan (for scheduled downgrade scenarios)
            $bestSub = $validSubs->sortByDesc(function ($sub) {
                return $sub->plan ? $sub->plan->price : 0;
            })->first();

            if ($bestSub && $bestSub->plan) {
                return $bestSub->plan;
            }
        }

        // Fallback to the plan_id on user table
        return $this->plan;
    }

    public function hasPlusPlan(): bool
    {
        if (!$this->relationLoaded('plan')) {
            $this->loadMissing('plan');
        }

        $plan = $this->activePlan();
        if (!$plan) {
            return false;
        }

        return strtolower($plan->slug ?? '') === 'plus' || str_contains(strtolower($plan->name), 'plus');
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
