<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\BannerInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * BannerAdminController — CRUD for banners/comunicados + stats.
 *
 * Protected by the 'is.admin' middleware (see api.php).
 */
class BannerAdminController extends Controller
{
    /**
     * GET /api/v1/admin/banners
     * List all banners with aggregated interaction stats.
     */
    public function index()
    {
        $banners = Banner::with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => array_merge($this->format($b), [
                'views'  => $b->viewCount(),
                'clicks' => $b->clickCount(),
                'closes' => $b->closeCount(),
            ]));

        return response()->json(['success' => true, 'banners' => $banners]);
    }

    /**
     * POST /api/v1/admin/banners
     * Create a new banner.
     */
    public function store(Request $request)
    {
        $validated = $this->validateBanner($request);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create(array_merge($validated, [
            'image_path' => $imagePath,
            'created_by' => $request->user()->id,
        ]));

        return response()->json(['success' => true, 'banner' => $this->format($banner)], 201);
    }

    /**
     * GET /api/v1/admin/banners/{banner}
     * Single banner detail.
     */
    public function show(Banner $banner)
    {
        return response()->json(['success' => true, 'banner' => $this->format($banner)]);
    }

    /**
     * PUT /api/v1/admin/banners/{banner}
     * Update an existing banner.
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $this->validateBanner($request);

        if ($request->hasFile('image')) {
            // Delete old image if present
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($validated);

        return response()->json(['success' => true, 'banner' => $this->format($banner->fresh())]);
    }

    /**
     * DELETE /api/v1/admin/banners/{banner}
     * Delete a banner and its image.
     */
    public function destroy(Banner $banner)
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/admin/banners/{banner}/stats
     * Detailed interaction stats for a banner.
     */
    public function stats(Banner $banner)
    {
        $views  = $banner->viewCount();
        $clicks = $banner->clickCount();
        $closes = $banner->closeCount();

        return response()->json([
            'success' => true,
            'stats'   => [
                'views'      => $views,
                'clicks'     => $clicks,
                'closes'     => $closes,
                'ctr'        => $views > 0 ? round(($clicks / $views) * 100, 1) : 0, // click-through rate %
                'close_rate' => $views > 0 ? round(($closes / $views) * 100, 1) : 0,
            ],
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateBanner(Request $request): array
    {
        return $request->validate([
            'title'               => 'required|string|max:255',
            'body'                => 'nullable|string',
            'image'               => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'background_color'    => 'nullable|string|max:50',
            'display_type'        => 'required|in:modal,bar,notification,card',
            'button_text'         => 'nullable|string|max:100',
            'button_url'          => 'nullable|url|max:500',
            'starts_at'           => 'nullable|date',
            'ends_at'             => 'nullable|date|after_or_equal:starts_at',
            'frequency'           => 'required|in:once,limited,until_close,always',
            'max_views_per_user'  => 'nullable|integer|min:1|max:100',
            'target_segment'      => 'required|in:all,new,old,inactive,no_simulation,no_essay,free,premium',
            'is_active'           => 'nullable|boolean',
        ]);
    }

    private function format(Banner $b): array
    {
        return [
            'id'                  => $b->id,
            'title'               => $b->title,
            'body'                => $b->body,
            'image_url'           => $b->imageUrl(),
            'background_color'    => $b->background_color,
            'display_type'        => $b->display_type,
            'button_text'         => $b->button_text,
            'button_url'          => $b->button_url,
            'starts_at'           => $b->starts_at?->toISOString(),
            'ends_at'             => $b->ends_at?->toISOString(),
            'frequency'           => $b->frequency,
            'max_views_per_user'  => $b->max_views_per_user,
            'target_segment'      => $b->target_segment,
            'is_active'           => $b->is_active,
            'created_at'          => $b->created_at->toISOString(),
            'creator'             => $b->creator ? $b->creator->name : null,
        ];
    }
}
