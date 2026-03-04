<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * List all exams grouped by metadata.
     */
    public function index(Request $request)
    {
        $query = Question::select(
            'arquivo_origem',
            'year',
            'organization',
            'institution',
            'role',
            DB::raw('COUNT(*) as total_questions')
        )
            ->groupBy('arquivo_origem', 'year', 'organization', 'institution', 'role');

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('organization')) {
            $query->where('organization', 'like', '%' . $request->organization . '%');
        }
        if ($request->filled('institution')) {
            $query->where('institution', 'like', '%' . $request->institution . '%');
        }

        // Order by year descending and organization
        $query->orderBy('year', 'desc')
            ->orderBy('organization', 'asc');

        $exams = $query->paginate(20);

        return response()->json($exams);
    }

    /**
     * Show a specific exam's questions chronologically.
     */
    public function show(Request $request, $id)
    {
        $examId = base64_decode($id);

        $query = Question::with(['alternatives', 'images', 'discursiveResponses']);

        if (!empty($examId) && $examId !== 'null') {
            $query->where('arquivo_origem', $examId);
        } else {
            // Complex fallback if arquivo_origem is null, using other metadata
            // Realistically, we should pass year/org/inst/role in query params 
            // if we are trying to find an exam without an arquivo_origem.
            if ($request->filled('year'))
                $query->where('year', $request->year);
            if ($request->filled('organization'))
                $query->where('organization', $request->organization);
            if ($request->filled('institution'))
                $query->where('institution', $request->institution);
            if ($request->filled('role'))
                $query->where('role', $request->role);
        }

        // Must be exactly ID ASC down to the chronological timeline of the PDF parsing.
        $questions = $query->orderBy('id', 'asc')->get();

        return response()->json(['data' => $questions]);
    }
}
