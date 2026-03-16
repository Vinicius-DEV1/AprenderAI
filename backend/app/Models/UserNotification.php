<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UserNotification — an internal in-app notification for a user.
 *
 * These are created programmatically (e.g. by admin, by jobs, or by events)
 * and displayed in the notification bell dropdown in the AppLayout header.
 */
class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Only unread notifications. */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /** Mark this notification as read right now. */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
