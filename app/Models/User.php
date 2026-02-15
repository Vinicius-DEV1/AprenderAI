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
        'password',
        'role',
        'plan_id',
        'plan_started_at',
        'plan_expires_at',
    ];

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
            'usage_reset_at' => 'datetime',
        ];
    }

    // Relacionamentos
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

    // Métodos auxiliares
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

    public function canCreateEssay(): bool
    {
        if (!$this->plan)
            return false;
        if ($this->plan->isUnlimited('essays'))
            return true;

        $this->resetUsageIfNeeded();
        return $this->essays_used_this_month < $this->plan->essays_limit;
    }

    public function incrementSimulationUsage(): void
    {
        $this->resetUsageIfNeeded();
        $this->increment('simulations_used_this_month');
    }

    public function incrementEssayUsage(): void
    {
        $this->resetUsageIfNeeded();
        $this->increment('essays_used_this_month');
    }

    protected function resetUsageIfNeeded(): void
    {
        if (!$this->usage_reset_at || $this->usage_reset_at->isPast()) {
            $this->update([
                'simulations_used_this_month' => 0,
                'essays_used_this_month' => 0,
                'usage_reset_at' => now()->addMonth(),
            ]);
        }
    }
}
