<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FeatureFeedback — stores a user's 👍 or 👎 vote for a specific feature.
 *
 * The unique constraint on (user_id, feature_key) ensures one vote per user
 * per feature. The feature_key is a developer-defined string identifying
 * the product area (e.g. "simulado-resultado", "correcao-redacao").
 */
class FeatureFeedback extends Model
{
    protected $fillable = [
        'user_id',
        'feature_key',
        'is_positive',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'is_positive' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
