<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * NotificationController — internal notification bell management.
 *
 * Notifications are created server-side (by admin tools or jobs).
 * These endpoints allow users to read and mark them.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     * Returns 30 most recent notifications for the authenticated user.
     * Also includes unread_count for the bell badge.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $notifications = UserNotification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn($n) => [
                'id'                  => $n->id,
                'title'               => $n->title,
                'body'                => $n->body,
                'type'                => $n->type,
                'action_url'          => $n->action_url,
                'related_payment_id'  => $n->related_payment_id,
                'meta'                => $n->meta,
                'is_read'             => $n->isRead(),
                'created_at'          => $n->created_at->toISOString(),
            ]);

        $unreadCount = UserNotification::where('user_id', $userId)->unread()->count();

        return response()->json([
            'success'      => true,
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v1/notifications/read-all
     * Mark all unread notifications as read for the authenticated user.
     */
    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
