<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSuggestion;
use Illuminate\Http\Request;

/**
 * SuggestionAdminController — admin management of user suggestions.
 *
 * Admins can view all suggestions (including rejected) and update their status.
 */
class SuggestionAdminController extends Controller
{
    /**
     * GET /api/v1/admin/suggestions
     * List all suggestions including rejected, sorted by votes then date.
     */
    public function index(Request $request)
    {
        $query = UserSuggestion::with('user:id,name,email')
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $suggestions = $query->paginate(25);

        return response()->json([
            'success' => true,
            'suggestions' => collect($suggestions->items())->map(fn($s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'body'         => $s->body,
                'votes_count'  => $s->votes_count,
                'status'       => $s->status,
                'status_label' => $s->statusLabel(),
                'user'         => $s->user ? [
                    'id'    => $s->user->id,
                    'name'  => $s->user->name,
                    'email' => $s->user->email,
                ] : null,
                'created_at'   => $s->created_at->toISOString(),
            ]),
            'pagination' => [
                'total'        => $suggestions->total(),
                'per_page'     => $suggestions->perPage(),
                'current_page' => $suggestions->currentPage(),
                'last_page'    => $suggestions->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/suggestions/{suggestion}/status
     * Update the moderation status of a suggestion.
     */
    public function updateStatus(Request $request, UserSuggestion $suggestion)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,planned,done,rejected',
        ]);

        $suggestion->update(['status' => $validated['status']]);

        return response()->json([
            'success'      => true,
            'status'       => $suggestion->status,
            'status_label' => $suggestion->statusLabel(),
        ]);
    }
}
