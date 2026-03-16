<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

/**
 * SupportAdminController — admin panel endpoints for managing support tickets.
 *
 * Protected by the 'is.admin' middleware (see api.php).
 */
class SupportAdminController extends Controller
{
    /**
     * GET /api/v1/admin/support/tickets
     * List all tickets with optional status filter, sorted by last activity.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user:id,name,email', 'latestMessage'])
            ->orderByDesc('last_message_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->paginate(25);

        return response()->json([
            'success' => true,
            'tickets' => $tickets->items() ? collect($tickets->items())->map(fn($t) => [
                'id'              => $t->id,
                'subject'         => $t->subject,
                'status'          => $t->status,
                'status_label'    => $t->statusLabel(),
                'user'            => $t->user ? [
                    'id'    => $t->user->id,
                    'name'  => $t->user->name,
                    'email' => $t->user->email,
                ] : null,
                'last_message_at' => $t->last_message_at?->toISOString(),
                'latest_message'  => $t->latestMessage ? [
                    'body'        => $t->latestMessage->body,
                    'sender_type' => $t->latestMessage->sender_type,
                    'created_at'  => $t->latestMessage->created_at->toISOString(),
                ] : null,
            ]) : [],
            'pagination' => [
                'total'        => $tickets->total(),
                'per_page'     => $tickets->perPage(),
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/support/tickets/{id}
     * Full ticket details with all messages.
     */
    public function show(int $id)
    {
        $ticket = SupportTicket::with(['user:id,name,email', 'messages.sender:id,name,role'])
            ->findOrFail($id);

        // Mark user messages as read by admin
        $ticket->messages()->where('sender_type', 'user')->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'ticket'  => [
                'id'           => $ticket->id,
                'subject'      => $ticket->subject,
                'status'       => $ticket->status,
                'status_label' => $ticket->statusLabel(),
                'user'         => $ticket->user ? [
                    'id'    => $ticket->user->id,
                    'name'  => $ticket->user->name,
                    'email' => $ticket->user->email,
                ] : null,
                'messages'     => $ticket->messages->map(fn($m) => [
                    'id'             => $m->id,
                    'sender_type'    => $m->sender_type,
                    'sender_name'    => $m->sender?->name ?? 'Usuário',
                    'body'           => $m->body,
                    'attachment_url' => $m->attachmentUrl(),
                    'created_at'     => $m->created_at->toISOString(),
                ]),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/support/tickets/{id}/reply
     * Admin sends a reply message and updates ticket status.
     */
    public function reply(Request $request, int $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = SupportMessage::create([
            'ticket_id'   => $ticket->id,
            'sender_type' => 'admin',
            'sender_id'   => $request->user()->id,
            'body'        => $validated['message'],
        ]);

        $ticket->update([
            'status'          => 'waiting_user',
            'admin_user_id'   => $request->user()->id,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id'          => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $request->user()->name,
                'body'        => $message->body,
                'created_at'  => $message->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/support/tickets/{id}/status
     * Change the operational status of a ticket.
     */
    public function updateStatus(Request $request, int $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:new,waiting_admin,waiting_user,resolved,closed',
        ]);

        $ticket->update(['status' => $validated['status']]);

        return response()->json([
            'success'      => true,
            'status'       => $ticket->status,
            'status_label' => $ticket->statusLabel(),
        ]);
    }
}
