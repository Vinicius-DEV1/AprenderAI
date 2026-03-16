<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Banner — configurable promotional/comunicado unit shown to users.
 *
 * Admins create banners via the admin panel. The API endpoint validates
 * scheduling, segment, and frequency rules before returning banners to users.
 */
class Banner extends Model
{
    protected $fillable = [
        'title',
        'body',
        'image_path',
        'background_color',
        'display_type',
        'button_text',
        'button_url',
        'starts_at',
        'ends_at',
        'frequency',
        'max_views_per_user',
        'target_segment',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'starts_at'          => 'datetime',
            'ends_at'            => 'datetime',
            'max_views_per_user' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Admin who created this banner. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** All interaction records for this banner. */
    public function interactions()
    {
        return $this->hasMany(BannerInteraction::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter only banners that are currently active and within schedule.
     * "No date" = always active; dates are inclusive.
     */
    public function scopeCurrentlyActive($query)
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Number of unique users who viewed this banner. */
    public function viewCount(): int
    {
        return $this->interactions()->where('interaction_type', 'view')->count();
    }

    /** Number of unique clicks on the CTA button. */
    public function clickCount(): int
    {
        return $this->interactions()->where('interaction_type', 'click')->count();
    }

    /** Number of times users explicitly closed/dismissed this banner. */
    public function closeCount(): int
    {
        return $this->interactions()->where('interaction_type', 'close')->count();
    }

    /** Public URL for the banner image. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }
}
