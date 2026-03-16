<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSuggestion;
use App\Models\SuggestionVote;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * SuggestionController — user-facing suggestions + voting.
 */
class SuggestionController extends Controller
{
    /**
     * GET /api/v1/suggestions
     * List all suggestions (visible to all users), sorted by votes.
     * Includes whether the current user has voted on each.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $suggestions = UserSuggestion::with('user:id,name')
            ->whereNotIn('status', ['rejected']) // hide rejected from users
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'title'       => $s->title,
                'body'        => $s->body,
                'votes_count' => $s->votes_count,
                'status'      => $s->status,
                'status_label' => $s->statusLabel(),
                'user_name'   => $s->user?->name ?? 'Usuário',
                'created_at'  => $s->created_at->toISOString(),
                'has_voted'   => SuggestionVote::where('suggestion_id', $s->id)
                                    ->where('user_id', $userId)->exists(),
                'is_mine'     => $s->user_id === $userId,
            ]);

        return response()->json(['success' => true, 'suggestions' => $suggestions]);
    }

    /**
     * POST /api/v1/suggestions
     * Submit a new suggestion.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body'  => 'nullable|string|max:2000',
        ]);

        $suggestion = UserSuggestion::create([
            'user_id' => $request->user()->id,
            'title'   => $validated['title'],
            'body'    => $validated['body'] ?? null,
        ]);

        return response()->json([
            'success'    => true,
            'suggestion' => ['id' => $suggestion->id, 'title' => $suggestion->title],
        ], 201);
    }

    /**
     * POST /api/v1/suggestions/{id}/vote
     * Toggle vote on a suggestion (vote if not voted, unvote if already voted).
     */
    public function vote(Request $request, int $id)
    {
        $suggestion = UserSuggestion::findOrFail($id);
        $userId = $request->user()->id;

        // Prevent voting on own suggestion
        if ($suggestion->user_id === $userId) {
            return response()->json(['success' => false, 'message' => 'Você não pode votar na sua própria sugestão.'], 422);
        }

        $existing = SuggestionVote::where('suggestion_id', $id)->where('user_id', $userId)->first();

        if ($existing) {
            // Toggle off: remove vote
            $existing->delete();
            $suggestion->decrement('votes_count');
            return response()->json(['success' => true, 'voted' => false, 'votes_count' => $suggestion->votes_count - 1]);
        }

        try {
            SuggestionVote::create(['suggestion_id' => $id, 'user_id' => $userId]);
            $suggestion->increment('votes_count');
        } catch (UniqueConstraintViolationException) {
            // Race condition safety: already voted
        }

        return response()->json(['success' => true, 'voted' => true, 'votes_count' => $suggestion->fresh()->votes_count]);
    }
}
