<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionImport;
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

        $recentImports = QuestionImport::with('uploader')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.curadoria.index', compact('pendingImport', 'pendingTriage', 'recentImports'));
    }
}
