<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * SupportController — user-facing chat support endpoints.
 *
 * All methods require 'auth:sanctum'. Users can only access their own tickets.
 */
class SupportController extends Controller
{
    /**
     * GET /api/v1/support/tickets
     * List all tickets for the authenticated user.
     */
    public function index(Request $request)
    {
        $tickets = SupportTicket::with(['latestMessage'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($ticket) {
                $unreadCount = $ticket->messages()
                    ->where('sender_type', 'admin')
                    ->where('is_read', false)
                    ->count();

                return [
                    'id'             => $ticket->id,
                    'subject'        => $ticket->subject,
                    'status'         => $ticket->status,
                    'status_label'   => $ticket->statusLabel(),
                    'unread_count'   => $unreadCount,
                    'last_message_at' => $ticket->last_message_at?->toISOString(),
                    'latest_message' => $ticket->latestMessage ? [
                        'body'        => $ticket->latestMessage->body,
                        'sender_type' => $ticket->latestMessage->sender_type,
                        'created_at'  => $ticket->latestMessage->created_at->toISOString(),
                    ] : null,
                ];
            });

        return response()->json(['success' => true, 'tickets' => $tickets]);
    }

    /**
     * POST /api/v1/support/tickets
     * Open a new support ticket and send the first message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'    => 'nullable|string|max:255',
            'message'    => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5 MB
        ]);

        $ticket = SupportTicket::create([
            'user_id'         => $request->user()->id,
            'subject'         => $validated['subject'] ?? 'Sem título',
            'status'          => 'new',
            'last_message_at' => now(),
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('support-attachments', 'public');
        }

        SupportMessage::create([
            'ticket_id'       => $ticket->id,
            'sender_type'     => 'user',
            'sender_id'       => $request->user()->id,
            'body'            => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        // Notify every admin user so they are aware of the new ticket without
        // having to poll the support panel manually.
        \App\Models\UserNotification::notifyAdmins(
            "🎫 Novo ticket de suporte aberto",
            "De: {$request->user()->name} — \"{$ticket->subject}\"",
            'info',
            '/admin/suporte'
        );

        return response()->json([
            'success' => true,
            'ticket'  => ['id' => $ticket->id, 'subject' => $ticket->subject],
        ], 201);
    }

    /**
     * GET /api/v1/support/tickets/{id}
     * Retrieve full message history of a ticket (user's own only).
     */
    public function show(Request $request, int $id)
    {
        $ticket = SupportTicket::with('messages.sender')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Mark admin messages as read
        $ticket->messages()->where('sender_type', 'admin')->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'ticket'  => [
                'id'           => $ticket->id,
                'subject'      => $ticket->subject,
                'status'       => $ticket->status,
                'status_label' => $ticket->statusLabel(),
                'messages'     => $ticket->messages->map(fn($m) => [
                    'id'              => $m->id,
                    'sender_type'     => $m->sender_type,
                    'sender_name'     => $m->sender?->name ?? 'Suporte',
                    'body'            => $m->body,
                    'attachment_url'  => $m->attachmentUrl(),
                    'created_at'      => $m->created_at->toISOString(),
                ]),
            ],
        ]);
    }

    /**
     * POST /api/v1/support/tickets/{id}/messages
     * Send a new message in an existing ticket.
     */
    public function sendMessage(Request $request, int $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        // Prevent sending to closed tickets
        if ($ticket->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'Ticket encerrado.'], 422);
        }

        $validated = $request->validate([
            'message'    => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('support-attachments', 'public');
        }

        $message = SupportMessage::create([
            'ticket_id'       => $ticket->id,
            'sender_type'     => 'user',
            'sender_id'       => $request->user()->id,
            'body'            => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        $ticket->update([
            'status'          => 'waiting_admin',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id'             => $message->id,
                'sender_type'    => $message->sender_type,
                'body'           => $message->body,
                'attachment_url' => $message->attachmentUrl(),
                'created_at'     => $message->created_at->toISOString(),
            ],
        ], 201);
    }
}
