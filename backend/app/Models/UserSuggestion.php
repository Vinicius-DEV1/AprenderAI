<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UserSuggestion — an improvement idea submitted by a user.
 *
 * Other users can vote on suggestions (one vote per user, stored in suggestion_votes).
 * votes_count is denormalized for fast sorting — updated atomically via increment/decrement.
 */
class UserSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'votes_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The user who submitted this suggestion. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** All vote records for this suggestion. */
    public function votes()
    {
        return $this->hasMany(SuggestionVote::class, 'suggestion_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Check whether a specific user has voted for this suggestion. */
    public function hasVotedBy(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    /** Human-readable label for admin display. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'      => 'Pendente',
            'under_review' => 'Em Análise',
            'planned'      => 'Planejado',
            'done'         => 'Implementado',
            'rejected'     => 'Rejeitado',
            default        => ucfirst($this->status),
        };
    }
}
