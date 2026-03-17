<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


class PlatformSession extends Model
{
    use FiltersAdmins;

    protected $fillable = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'started_at',
        'ended_at',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'         => 'datetime',
            'ended_at'           => 'datetime',
            'last_heartbeat_at'  => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Duration in seconds (null if session not ended yet).
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }
        return (int) $this->started_at->diffInSeconds($this->ended_at);
    }
}
