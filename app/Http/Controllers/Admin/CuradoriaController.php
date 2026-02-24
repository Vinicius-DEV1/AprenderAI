<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionImport;
use App\Models\AiProcessingBatch;
use App\Models\Question;
use Illuminate\Http\Request;

class CuradoriaController extends Controller
{
    public function index()
    {
        $pendingImport = Question::where('review_status', 'review')->count();
        $pendingTriage = Question::where(function ($query) {
            $query->whereNull('difficulty_reasoning')
                ->orWhereNull('explanation')
                ->orWhereDoesntHave('subjects')
                ->orWhereDoesntHave('topics');
        })->count();

        // Recent import batches (ZIP uploads)
        $recentImports = QuestionImport::with('uploader')
            ->latest()
            ->take(5)
            ->get();

        // Recent AI triage batches — this is what the 'Lotes Processados' card should show.
        // Previously this only counted QuestionImport (ZIP) records, ignoring AI batches entirely.
        $recentBatches = AiProcessingBatch::latest()->take(5)->get();
        $totalBatchCount = AiProcessingBatch::count();

        return view('admin.curadoria.index', compact(
            'pendingImport', 'pendingTriage', 'recentImports', 'recentBatches', 'totalBatchCount'
        ));
    }
}
