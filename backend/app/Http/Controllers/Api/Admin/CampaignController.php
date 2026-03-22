<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index()
    {
        return response()->json(Campaign::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'utm_source' => 'required|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'base_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        $validated['base_url'] = $validated['base_url'] ?: '/';

        $campaign = Campaign::create($validated);

        return response()->json($campaign, 211);
    }

    public function show(Campaign $campaign)
    {
        return response()->json($campaign);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'utm_source' => 'sometimes|required|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'base_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $campaign->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        }

        $campaign->update($validated);

        return response()->json($campaign);
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();
        return response()->json(['message' => 'Campanha removida com sucesso.']);
    }

    public function toggle(Campaign $campaign)
    {
        $campaign->update(['is_active' => !$campaign->is_active]);
        return response()->json($campaign);
    }
}
