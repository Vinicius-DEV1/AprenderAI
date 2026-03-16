<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureFeedback;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * FeatureFeedbackController — quick 👍/👎 feedback per feature.
 *
 * One vote per user per feature_key (enforced by DB unique constraint).
 */
class FeatureFeedbackController extends Controller
{
    /**
     * POST /api/v1/feedback
     * Submit a positive or negative feedback vote for a feature.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'feature_key' => 'required|string|max:100',
            'is_positive' => 'required|boolean',
            'comment'     => 'nullable|string|max:500',
        ]);

        try {
            FeatureFeedback::create([
                'user_id'     => $request->user()->id,
                'feature_key' => $validated['feature_key'],
                'is_positive' => $validated['is_positive'],
                'comment'     => $validated['comment'] ?? null,
            ]);

            return response()->json(['success' => true]);
        } catch (UniqueConstraintViolationException) {
            // User already voted — silently succeed (idempotent)
            return response()->json(['success' => true, 'already_voted' => true]);
        }
    }
}
