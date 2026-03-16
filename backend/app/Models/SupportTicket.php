<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SupportTicket — represents a single support conversation thread.
 *
 * A ticket is opened by a user and managed by an admin.
 * Each ticket has many SupportMessages exchanged between user and admin team.
 */
class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'status',
        'last_message_at',
        'admin_user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The user who opened the ticket. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Admin assigned to handle this ticket. */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /** All messages in this ticket thread. */
    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at');
    }

    /** Most recent message (useful for listing view). */
    public function latestMessage()
    {
        return $this->hasOne(SupportMessage::class, 'ticket_id')->latestOfMany();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Returns a human-readable label for the current status. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'new'          => 'Novo',
            'waiting_admin' => 'Aguardando Resposta',
            'waiting_user' => 'Respondido',
            'resolved'     => 'Resolvido',
            'closed'       => 'Fechado',
            default        => ucfirst($this->status),
        };
    }
}
