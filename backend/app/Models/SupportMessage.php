<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SupportMessage — a single message within a SupportTicket thread.
 *
 * sender_type distinguishes between user and admin messages,
 * allowing the chat UI to render them on opposite sides.
 */
class SupportMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'body',
        'attachment_path',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The ticket this message belongs to. */
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /** The user (or admin) who sent this message. */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Returns the public URL for the optional attachment. */
    public function attachmentUrl(): ?string
    {
        return $this->attachment_path
            ? asset('storage/' . $this->attachment_path)
            : null;
    }
}
