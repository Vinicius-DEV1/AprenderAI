<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SuggestionVote — a single vote by a user on a UserSuggestion.
 *
 * The unique constraint (suggestion_id, user_id) is enforced both at
 * DB level and in the controller to prevent duplicate votes.
 */
class SuggestionVote extends Model
{
    protected $fillable = [
        'suggestion_id',
        'user_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function suggestion()
    {
        return $this->belongsTo(UserSuggestion::class, 'suggestion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
