<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\AiProcessingBatch;
use Illuminate\Http\Request;

class CuradoriaController extends Controller
{
    /**
     * Get curation dashboard data.
     */
    public function index()
    {
        $pendingImport = Question::where('review_status', 'review')->count();
        $pendingTriage = Question::where(function ($query) {
            $query->whereNull('difficulty_reasoning')
                ->orWhereNull('explanation')
                ->orWhereDoesntHave('subjects')
                ->orWhereDoesntHave('topics');
        })->count();

        $recentImports = QuestionImport::with('uploader')
            ->latest()
            ->take(5)
            ->get();

        $recentBatches = AiProcessingBatch::latest()->take(5)->get();
        $totalBatchCount = AiProcessingBatch::count();

        return response()->json([
            'stats' => [
                'pending_import' => $pendingImport,
                'pending_triage' => $pendingTriage,
                'total_batches' => $totalBatchCount,
            ],
            'recent_imports' => $recentImports,
            'recent_batches' => $recentBatches
        ]);
    }
}
