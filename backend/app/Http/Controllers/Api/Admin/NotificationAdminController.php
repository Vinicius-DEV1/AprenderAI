<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * NotificationAdminController — admin tool for sending internal notifications.
 *
 * Admins can send notifications to specific users or broadcast to all users.
 * Also provides a list of all sent notifications for auditing.
 */
class NotificationAdminController extends Controller
{
    /**
     * GET /api/v1/admin/notifications
     * List recently created notifications (newest first, limited).
     */
    public function index(Request $request)
    {
        $notifications = UserNotification::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'body'       => $n->body,
                'type'       => $n->type,
                'action_url' => $n->action_url,
                'is_read'    => $n->isRead(),
                'user'       => $n->user ? ['id' => $n->user->id, 'name' => $n->user->name, 'email' => $n->user->email] : null,
                'created_at' => $n->created_at->toISOString(),
            ]);

        return response()->json(['success' => true, 'notifications' => $notifications]);
    }

    /**
     * POST /api/v1/admin/notifications/send
     * Send a notification to a specific user or broadcast to all users.
     *
     * Body: { user_id?: int, broadcast: bool, title, body, type, action_url? }
     * If broadcast=true, sends to all users (batched, avoids memory issues).
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'user_id'    => 'nullable|exists:users,id',
            'broadcast'  => 'nullable|boolean',
            'title'      => 'required|string|max:200',
            'user_id'      => 'nullable|exists:users,id',
            'broadcast'    => 'nullable|boolean',
            'title'        => 'required|string|max:200',
            'body'         => 'nullable|string|max:1000',
            'type'         => 'nullable|in:info,success,warning,tip',
            'action_url'   => 'nullable|string|max:500',
            'target_group' => 'nullable|in:all,basic,plus,admin',
        ]);

        $isBroadcast = $validated['broadcast'] ?? false;
        $targetGroup = $request->input('target_group', 'all');
        $type        = $validated['type'] ?? 'info';
 
        if ($isBroadcast || $targetGroup !== 'all') {
            $query = User::select('id');
            
            if ($targetGroup === 'basic') {
                $query->whereHas('plan', fn($q) => $q->where('name', 'LIKE', '%Básico%')->orWhere('name', 'LIKE', '%basico%'));
            } elseif ($targetGroup === 'plus') {
                $query->whereHas('plan', fn($q) => $q->where('name', 'LIKE', '%Plus%'));
            } elseif ($targetGroup === 'admin') {
                $query->where('role', 'admin');
            }
 
            // Chunked insert to avoid memory issues with large user bases
            $count = 0;
            $query->chunk(500, function ($users) use ($validated, $type, &$count) {
                $now  = now();
                $rows = $users->map(fn($u) => [
                    'user_id'    => $u->id,
                    'title'      => $validated['title'],
                    'body'       => $validated['body'] ?? null,
                    'type'       => $type,
                    'action_url' => $validated['action_url'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();
                UserNotification::insert($rows);
                $count += count($rows);
            });
 
            return response()->json(['success' => true, 'sent_to' => $count]);
        }

        // Single user
        $notification = UserNotification::create([
            'user_id'    => $validated['user_id'],
            'title'      => $validated['title'],
            'body'       => $validated['body'] ?? null,
            'type'       => $type,
            'action_url' => $validated['action_url'] ?? null,
        ]);

        return response()->json(['success' => true, 'notification_id' => $notification->id], 201);
    }
}
