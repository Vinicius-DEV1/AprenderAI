<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImage;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImportReviewController extends Controller
{
    public function __construct(private readonly QuestionImportService $importService)
    {
    }

    /**
     * List questions pending review with filters and recent audit logs.
     */
    public function index(Request $request)
    {
        $pendingQuery = Question::with(['subjects', 'alternatives'])
            ->where('review_status', 'review');

        // Filter by Organization
        if ($request->filled('organization')) {
            $pendingQuery->where('organization', $request->organization);
        }

        // Filter by Batch
        if ($request->filled('import_id')) {
            $importIds = QuestionImportItem::where('import_id', $request->import_id)
                ->pluck('question_id');
            $pendingQuery->whereIn('id', $importIds);
        }

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
     * Get review data for a specific question/import item.
     */
    public function show(Request $request, $id)
    {
        $question = Question::with(['alternatives', 'images', 'subjects', 'topics', 'importItem.import.uploader'])->findOrFail($id);

        return response()->json([
            'question' => $question,
            'importItem' => $question->importItem
        ]);
    }

    /**
     * Approve the question and mark it for publishing.
     */
    public function approve(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $question->update(['review_status' => 'approved']);

        if ($question->importItem) {
            $question->importItem->update([
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Increment approved count in the batch
            $question->importItem->import->increment('approved_count');
            $question->importItem->import->decrement('pending_count');
        }

        return response()->json(['message' => 'Questão aprovada com sucesso.']);
    }

    /**
     * Revert the question to pending review status.
     */
    public function revert(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $question->update(['review_status' => 'pending']);

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
            'x' => ['required', 'integer'],
            'y' => ['required', 'integer'],
            'width' => ['required', 'integer'],
            'height' => ['required', 'integer'],
        ]);

        try {
            $publicUrl = $this->importService->saveCrop(
                $image,
                $validated['target'],
                $validated['x'],
                $validated['y'],
                $validated['width'],
                $validated['height']
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
}
