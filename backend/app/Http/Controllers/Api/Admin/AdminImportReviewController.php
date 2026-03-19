<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImage;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Models\QuestionTriageLog;
use App\Services\QuestionImportService;
use App\Services\QuestionTriageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImportReviewController extends Controller
{
    public function __construct(
        private readonly QuestionImportService $importService,
        private readonly QuestionTriageService $triageService
    ) {
    }

    /**
     * List questions pending review with filters and recent audit logs.
     */
    public function index(Request $request)
    {
        $pendingQuery = $this->applyFilters($request);

        $pendingQuestions = $pendingQuery->latest()->paginate(12);

        // Data for filters
        $imports = QuestionImport::latest()->take(20)->get();
        $organizations = Question::whereNotNull('organization')
            ->distinct()
            ->pluck('organization')
            ->sort()
            ->values();

        // Recent Audit Actions
        $recentActions = QuestionImportItem::with(['question', 'import', 'approver'])
            ->where(function ($q) {
                $q->whereNotNull('approved_at')->orWhereNotNull('reverted_at');
            })
            ->latest('updated_at')
            ->take(15)
            ->get();

        return response()->json([
            'pendingQuestions' => $pendingQuestions,
            'imports' => $imports,
            'organizations' => $organizations,
            'recentActions' => $recentActions,
        ]);
    }

    /**
     * Get a summary counts of issues among pending questions.
     */
    public function summary()
    {
        $logs = QuestionTriageLog::whereHas('question', function ($q) {
            $q->where('review_status', 'review');
        })
            ->where('triage_type', 'ai_batch') // We only care about AI findings
            ->get();

        $summary = [
            'no_alternatives' => 0,
            'no_statement' => 0,
            'wrong_answer' => 0,
            'has_image' => 0,
            'missing_image' => 0,
            'missing_support_text' => 0,
            'low_quality' => 0,
            'hallucination' => 0,
        ];

        foreach ($logs as $log) {
            $issues = $log->issues_detected ?? [];
            foreach ($issues as $issue) {
                if (array_key_exists($issue, $summary)) {
                    $summary[$issue]++;
                }
            }

            if ($log->quality_score !== null && $log->quality_score < 60) {
                $summary['low_quality']++;
            }
        }

        return response()->json($summary);
    }
    /**
     * Get review data for a specific question/import item.
     */
    public function show(Request $request, $id)
    {
        $question = Question::with(['alternatives', 'images', 'subjects', 'topics', 'importItem.import.uploader'])->findOrFail($id);

        $baseQuery = $this->applyFilters($request);

        // Next is the next item in the list (if we consider latest/desc, next is smaller ID)
        $nextId = (clone $baseQuery)->where('id', '<', $id)->orderBy('id', 'desc')->value('id');
        // Prev is the one before (larger ID if latest/desc)
        $prevId = (clone $baseQuery)->where('id', '>', $id)->orderBy('id', 'asc')->value('id');

        return response()->json([
            'question' => $question,
            'importItem' => $question->importItem,
            'next_id' => $nextId,
            'prev_id' => $prevId
        ]);
    }

    /**
     * Approve the question and mark it for publishing.
     */
    public function approve(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        // Fail if question is still incomplete as per strict requirements
        if ($question->isIncomplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível aprovar uma questão incompleta. Certifique-se de que a explicação, o raciocínio e a classificação estão preenchidos.'
            ], 422);
        }

        $question->update([
            'review_status' => 'approved',
            'is_active' => true // Force active so it shows up in search
        ]);

        $this->triageService->logManualAction($question, 'approved', [], Auth::id());

        if ($question->importItem) {
            $question->importItem->update([
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Increment approved count in the batch
            $question->importItem->import->increment('approved_count');
            $question->importItem->import->decrement('pending_count');
        }

        // Get the NEXT ID to return for seamless navigation
        $nextId = $this->applyFilters($request)->latest('id')->value('id');

        return response()->json([
            'message' => 'Questão aprovada com sucesso.',
            'next_id' => $nextId
        ]);
    }

    /**
     * Helper to apply common filters used in listing and "next question" logic.
     */
    private function applyFilters(Request $request)
    {
        $query = Question::with(['subjects', 'alternatives', 'triageLogs'])
            ->where('review_status', 'review');

        if ($request->filled('organization')) {
            $query->where('organization', $request->organization);
        }

        if ($request->filled('issue')) {
            $issue = $request->issue;
            $query->whereHas('triageLogs', function ($q) use ($issue) {
                if ($issue === 'hallucination') {
                    $q->whereJsonContains('issues_detected', 'hallucination')
                      ->orWhere('quality_score', '<', 30); // Hallucinations usually have very low scores
                } else {
                    $q->whereJsonContains('issues_detected', $issue);
                }
            });
        }

        if ($request->filled('quality') && $request->quality === 'low') {
            $query->whereHas('triageLogs', function ($q) {
                $q->where('quality_score', '<', 60);
            });
        }

        if ($request->filled('import_id')) {
            $importIds = QuestionImportItem::where('import_id', $request->import_id)
                ->pluck('question_id');
            $query->whereIn('id', $importIds);
        }

        return $query;
    }

    /**
     * Revert the question to pending review status.
     */
    public function revert(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $question->update(['review_status' => 'review']); // or review if we consider it back to review stage

        $this->triageService->logManualAction($question, 'manual_review', [], Auth::id());

        if ($question->importItem) {
            $question->importItem->update([
                'reverted_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Questão retornada para revisão.']);
    }

    /**
     * Process an image crop using GD via QuestionImportService.
     */
    public function crop(Request $request, $imageId)
    {
        $image = QuestionImage::findOrFail($imageId);

        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(statement|[A-Ea-e])$/'],
            'x'      => ['required', 'numeric'],
            'y'      => ['required', 'numeric'],
            'width'  => ['required', 'numeric', 'min:1'],
            'height' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $publicUrl = $this->importService->saveCrop(
                $image,
                $validated['target'],
                (int) round((float) $validated['x']),
                (int) round((float) $validated['y']),
                (int) round((float) $validated['width']),
                (int) round((float) $validated['height'])
            );
            return response()->json(['success' => true, 'url' => $publicUrl]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete an image from a question.
     */
    public function deleteImage(Request $request, $imageId)
    {
        $image = QuestionImage::findOrFail($imageId);
        $this->importService->deleteImage($image);

        return response()->json(['message' => 'Imagem removida com sucesso.']);
    }

    /**
     * Get the timeline of triage history for a question.
     */
    public function triageHistory($id)
    {
        $logs = QuestionTriageLog::where('question_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    /**
     * Get compact statistics for the review dashboard.
     */
    public function stats()
    {
        $days = 14;
        $startDate = now()->subDays($days)->startOfDay();

        // 1. Get daily totals for the line chart
        $dailyStats = QuestionImportItem::whereNotNull('approved_at')
            ->where('approved_at', '>=', $startDate)
            ->selectRaw('DATE(approved_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // 2. Get breakdown by admin for the last 14 days
        $adminStats = QuestionImportItem::with(['approver:id,name'])
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $startDate)
            ->get()
            ->groupBy(function($item) {
                return $item->approved_at->format('Y-m-d');
            })
            ->map(function($group) {
                return $group->groupBy('approved_by')->map(function($userGroup) {
                    return [
                        'admin_name' => $userGroup->first()->approver->name ?? 'Sistema',
                        'count' => $userGroup->count()
                    ];
                })->values();
            });

        return response()->json([
            'daily_stats' => $dailyStats,
            'admin_breakdown' => $adminStats
        ]);
    }
}
