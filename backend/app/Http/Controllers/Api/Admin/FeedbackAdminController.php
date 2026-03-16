<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FeedbackAdminController — admin view of feature feedback aggregates.
 *
 * Shows feature-level stats: positive/negative counts and approval rate.
 */
class FeedbackAdminController extends Controller
{
    /**
     * GET /api/v1/admin/feedback
     * Returns feedback grouped by feature_key with counts.
     */
    public function index()
    {
        $stats = FeatureFeedback::select('feature_key')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_positive = 1 THEN 1 ELSE 0 END) as positive')
            ->selectRaw('SUM(CASE WHEN is_positive = 0 THEN 1 ELSE 0 END) as negative')
            ->groupBy('feature_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'feature_key'    => $row->feature_key,
                'total'          => $row->total,
                'positive'       => $row->positive,
                'negative'       => $row->negative,
                'approval_rate'  => $row->total > 0
                    ? round(($row->positive / $row->total) * 100, 1)
                    : 0,
            ]);

        return response()->json(['success' => true, 'feedback' => $stats]);
    }
}
