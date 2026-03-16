<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\BannerInteraction;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * BannerController — user-facing banner endpoints.
 *
 * Returns banners that the user should see, respecting:
 *  - schedule (starts_at / ends_at)
 *  - frequency control (once / limited / until_close / always)
 *  - segment targeting
 */
class BannerController extends Controller
{
    /**
     * GET /api/v1/banners/active
     * Returns banners the authenticated user should currently see.
     */
    public function active(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $banners = Banner::currentlyActive()->get()->filter(function ($banner) use ($user, $now) {
            // 1. Segment check
            if (!$this->matchesSegment($banner, $user, $now)) {
                return false;
            }

            // 2. Frequency check based on existing interactions
            $views  = BannerInteraction::where('banner_id', $banner->id)
                ->where('user_id', $user->id)
                ->where('interaction_type', 'view')
                ->count();

            $closed = BannerInteraction::where('banner_id', $banner->id)
                ->where('user_id', $user->id)
                ->where('interaction_type', 'close')
                ->exists();

            return match ($banner->frequency) {
                'once'        => $views === 0,                                             // show only if never shown
                'limited'     => $views < ($banner->max_views_per_user ?? 1),             // show up to N times
                'until_close' => !$closed,                                                 // show until user closes
                'always'      => true,                                                     // always show
                default       => false,
            };
        })->values();

        return response()->json([
            'success' => true,
            'banners' => $banners->map(fn($b) => [
                'id'               => $b->id,
                'title'            => $b->title,
                'body'             => $b->body,
                'image_url'        => $b->imageUrl(),
                'background_color' => $b->background_color,
                'display_type'     => $b->display_type,
                'button_text'      => $b->button_text,
                'button_url'       => $b->button_url,
            ]),
        ]);
    }

    /**
     * POST /api/v1/banners/{banner}/interact
     * Record a user interaction (view, click, or close) with a banner.
     */
    public function interact(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'type' => 'required|in:view,click,close',
        ]);

        BannerInteraction::create([
            'banner_id'        => $banner->id,
            'user_id'          => $request->user()->id,
            'interaction_type' => $validated['type'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Determine whether a banner's target_segment matches the given user.
     */
    private function matchesSegment(Banner $banner, $user, Carbon $now): bool
    {
        return match ($banner->target_segment) {
            'all' => true,

            // Registered in the last 7 days
            'new' => $user->created_at >= $now->copy()->subDays(7),

            // Registered more than 30 days ago
            'old' => $user->created_at <= $now->copy()->subDays(30),

            // No login in the last 7 days (uses platform_sessions if available, else created_at as proxy)
            'inactive' => $user->updated_at <= $now->copy()->subDays(7),

            // Never started a simulation
            'no_simulation' => $user->simulations()->count() === 0,

            // Never submitted an essay
            'no_essay' => $user->essays()->count() === 0,

            // Has no active paid plan
            'free' => $user->plan_id === null || $user->plan_expires_at?->isPast(),

            // Has an active paid plan
            'premium' => $user->plan_id !== null && !($user->plan_expires_at?->isPast()),

            default => true,
        };
    }
}
