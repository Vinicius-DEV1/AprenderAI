<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BannerInteraction — records a single user interaction with a banner.
 *
 * interaction_type can be: view | click | close
 * Used for stats aggregation and frequency-control logic.
 */
class BannerInteraction extends Model
{
    protected $fillable = [
        'banner_id',
        'user_id',
        'interaction_type',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
