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
        'related_payment_id',
        'related_essay_id',
        'meta',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'meta'    => 'array',
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

    /**
     * Create a notification for a user ONLY if there is no existing unread
     * notification of the same type for the same subscription (deduplication).
     *
     * @param int         $userId
     * @param string      $type            payment_pending | payment_expired
     * @param int         $subscriptionId
     * @param array       $data            title, body, action_url, meta
     * @return self|null  The created notification, or null if already exists.
     */
    public static function notifyUserOnce(
        int $userId,
        string $type,
        int $subscriptionId,
        array $data
    ): ?self {
        try {
            $exists = self::where('user_id', $userId)
                ->where('type', $type)
                ->where('related_payment_id', $subscriptionId)
                ->exists();

            if ($exists) {
                return null;
            }

            return self::create(array_merge($data, [
                'user_id'            => $userId,
                'type'               => $type,
                'related_payment_id' => $subscriptionId,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notifications] notifyUserOnce failed', [
                'user_id'        => $userId,
                'type'           => $type,
                'subscription_id'=> $subscriptionId,
                'error'          => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a notification for a user ONLY if there is no existing unread
     * essay_pending notification for the same essay (deduplication via related_essay_id).
     *
     * Uses related_essay_id (no FK constraint) instead of related_payment_id.
     *
     * @param int   $userId
     * @param int   $essayId
     * @param array $data   title, body, action_url, meta
     * @return self|null
     */
    public static function notifyEssayOnce(
        int $userId,
        int $essayId,
        array $data,
        string $type = 'essay_pending'
    ): ?self {
        try {
            $exists = self::where('user_id', $userId)
                ->where('type', $type)
                ->where('related_essay_id', $essayId)
                ->exists();

            if ($exists) {
                return null;
            }

            return self::create(array_merge($data, [
                'user_id'          => $userId,
                'type'             => $type,
                'related_essay_id' => $essayId,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notifications] notifyEssayOnce failed', [
                'user_id'  => $userId,
                'essay_id' => $essayId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark all Pix recovery notifications for a given subscription as read.
     * Called when payment is confirmed to clean up pending/expired notices.
     *
     * @param int $subscriptionId
     */
    public static function invalidatePixNotifications(int $subscriptionId): void
    {
        static::invalidateByRelatedId($subscriptionId, ['payment_pending', 'payment_expired']);
    }

    /**
     * Generic helper: mark all unread notifications of the given type(s)
     * for the given related entity ID as read (uses related_payment_id).
     *
     * @param int          $relatedId
     * @param string|array $types
     */
    public static function invalidateByRelatedId(int $relatedId, string|array $types): void
    {
        try {
            self::where('related_payment_id', $relatedId)
                ->whereIn('type', (array) $types)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notifications] invalidateByRelatedId failed', [
                'related_id' => $relatedId,
                'types'      => (array) $types,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark essay_pending notifications for a given essay as read.
     * Uses related_essay_id (not related_payment_id).
     */
    public static function invalidateEssayNotifications(int $essayId): void
    {
        try {
            self::where('related_essay_id', $essayId)
                ->where('type', 'essay_pending')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notifications] invalidateEssayNotifications failed', [
                'essay_id' => $essayId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fire a notification to every admin user.
     *
     * @param string      $title   Short title shown in the bell dropdown
     * @param string|null $body    Optional supporting detail text
     * @param string      $type    info | success | warning | tip
     * @param string|null $url     Optional deep-link (e.g. to admin/checkout)
     */
    public static function notifyAdmins(
        string $title,
        ?string $body = null,
        string $type = 'info',
        ?string $url = null,
    ): void {
        try {
            $adminIds = User::where('role', 'admin')->pluck('id');
            foreach ($adminIds as $adminId) {
                self::create([
                    'user_id'    => $adminId,
                    'title'      => $title,
                    'body'       => $body,
                    'type'       => $type,
                    'action_url' => $url,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notifications] notifyAdmins failed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
