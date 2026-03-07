<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
     * Explorer: Returns a hierarchical distribution (subject → topics) for a given organization.
     * Optimized with a single GROUP BY query + PHP-side tree building.
     */
    public function explorer(Request $request, string $organization)
    {
        $cacheKey = 'exam_explorer_' . md5($organization);

        $data = Cache::remember($cacheKey, 300, function () use ($organization) {
            // Single optimized query: subjects + topics grouped together
            $rows = DB::table('questions as q')
                ->join('question_subject as qs', 'qs.question_id', '=', 'q.id')
                ->join('subjects as s', 's.id', '=', 'qs.subject_id')
                ->leftJoin('question_topic as qt', 'qt.question_id', '=', 'q.id')
                ->leftJoin('topics as t', 't.id', '=', 'qt.topic_id')
                ->select(
                    's.id as subject_id',
                    's.name as subject',
                    't.id as topic_id',
                    't.name as topic',
                    DB::raw('COUNT(DISTINCT q.id) as total')
                )
                ->where('q.organization', $organization)
                ->where('q.is_active', true)
                ->whereNull('q.deleted_at')
                ->groupBy('s.id', 's.name', 't.id', 't.name')
                ->orderByDesc('total')
                ->get();

            // Build nested tree: subjects → topics
            $subjects = [];
            foreach ($rows as $row) {
                $sid = $row->subject_id;
                if (!isset($subjects[$sid])) {
                    $subjects[$sid] = [
                        'id' => $sid,
                        'name' => $row->subject,
                        'total' => 0,
                        'topics' => [],
                    ];
                }
                if ($row->topic_id) {
                    $subjects[$sid]['topics'][] = [
                        'id' => $row->topic_id,
                        'name' => $row->topic,
                        'total' => (int) $row->total,
                    ];
                }
                $subjects[$sid]['total'] += (int) $row->total;
            }

            // Re-sort subjects by total desc
            $subjects = array_values($subjects);
            usort($subjects, fn($a, $b) => $b['total'] - $a['total']);

            // Also sort each subject's topics by total desc
            foreach ($subjects as &$subject) {
                usort($subject['topics'], fn($a, $b) => $b['total'] - $a['total']);
            }

            $totalQuestions = DB::table('questions')
                ->where('organization', $organization)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();

            return [
                'organization' => $organization,
                'total_questions' => $totalQuestions,
                'subjects' => $subjects,
            ];
        });

        return response()->json($data);
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
