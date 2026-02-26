<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Http\Resources\EssayResource;
use Illuminate\Http\Request;

class EssayController extends Controller
{
    public function index(Request $request)
    {
        $essays = $request->user()->essays()->latest()->paginate(10);
        return EssayResource::collection($essays);
    }

    public function show(Request $request, Essay $essay)
    {
        if ($essay->user_id !== $request->user()->id) {
            abort(403);
        }

        // Load specific correction details if present
        $essay->load(['correction']);

        return new EssayResource($essay);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|string|max:255',
            'content' => 'required|string|min:100', // Basic length validation
        ]);

        $essay = $request->user()->essays()->create([
            'theme' => $validated['theme'],
            'content' => $validated['content'],
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // In a real scenario, an event or job is dispatched here to call the AI correction API

        return new EssayResource($essay);
    }
}
