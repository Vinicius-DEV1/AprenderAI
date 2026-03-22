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
            DB::raw('COUNT(*) as total_questions'),
            DB::raw('MAX(id) as latest_id')
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
        if ($request->filled('role')) {
            $query->where('role', 'like', '%' . $request->role . '%');
        }
        if ($request->filled('import_id')) {
            $query->whereHas('importItem', fn($q) => $q->where('import_id', $request->import_id));
        }

        // Sorting
        $sort = $request->get('sort', 'last_update');
        $direction = $request->get('direction', 'desc');

        if ($sort === 'year') {
            $query->orderBy('year', $direction)
                ->orderBy('organization', 'asc');
        } else {
            // High-performance sorting: Use MAX(id) instead of timestamp filesort
            $query->orderBy('latest_id', $direction);
        }

        $exams = $query->paginate(20);

        // Fetch precise dates only for the paginated page (20 groups) to prevent 504 Timeouts
        $latestIds = collect($exams->items())->pluck('latest_id')->filter()->toArray();
        if (!empty($latestIds)) {
            $dates = Question::whereIn('id', $latestIds)->pluck('updated_at', 'id');
            // Fallback for created_at if updated_at is somehow missing
            if ($dates->isEmpty() || $dates->containsStrict(null)) {
                $createdDates = Question::whereIn('id', $latestIds)->pluck('created_at', 'id');
            }

            foreach ($exams->items() as $exam) {
                $id = $exam->latest_id;
                $updated = $dates[$id] ?? null;
                if (!$updated && isset($createdDates)) {
                    $updated = $createdDates[$id] ?? null;
                }
                
                // Set the mapped date as a string for frontend parsing
                $exam->last_update = $updated ? (string) $updated : 'N/A';
            }
        }

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
        $examId = ($id === 'null') ? null : base64_decode($id);

        $query = Question::with([
            'alternatives',
            'images',
            'discursiveResponses',
            'triageLogs' => function ($q) {
                // Eager load only the latest log to save bandwidth
                $q->orderByDesc('created_at')->limit(1);
            }
        ]);

        if (!empty($examId)) {
            $query->where('arquivo_origem', $examId);
        } else {
            $query->whereNull('arquivo_origem');

            // Complex fallback if arquivo_origem is null, using other metadata
            if ($request->filled('year')) {
                $query->where('year', $request->year);
            } else {
                $query->whereNull('year');
            }

            if ($request->filled('organization')) {
                $query->where('organization', $request->organization);
            } else {
                $query->whereNull('organization');
            }

            if ($request->filled('institution')) {
                $query->where('institution', $request->institution);
            } else {
                $query->whereNull('institution');
            }

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            } else {
                $query->whereNull('role');
            }
        }

        if ($request->filled('import_id')) {
            $query->whereHas('importItem', function ($q) use ($request) {
                $q->where('import_id', $request->import_id);
            });
        }


        // Must be exactly ID ASC down to the chronological timeline of the PDF parsing.
        $questions = $query->orderBy('id', 'asc')->get();

        return response()->json(['data' => $questions]);
    }
}
