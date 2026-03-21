<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QuestionGoalController extends Controller
{
    /**
     * Get engagement data for the micro dashboard.
     */
    public function engagement(Request $request)
    {
        $data = app(\App\Services\UserEngagementService::class)->getEngagementData($request->user()->id);
        return response()->json($data);
    }

    /**
     * Update user daily goal.
     */
    public function updateGoal(Request $request)
    {
        $request->validate([
            'daily_goal' => 'required|integer|min:1|max:500',
        ]);

        $user = $request->user();
        $user->daily_goal = $request->daily_goal;
        $user->save();

        return response()->json([
            'message' => 'Meta diária atualizada com sucesso.',
            'daily_goal' => $user->daily_goal
        ]);
    }
}
