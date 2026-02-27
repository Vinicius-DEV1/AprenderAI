<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImage;
use App\Models\QuestionImportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImportReviewController extends Controller
{
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
     * Delete an image from a question.
     */
    public function deleteImage(Request $request, $imageId)
    {
        $image = QuestionImage::findOrFail($imageId);
        // In a real scenario, you'd also delete the file from storage
        // \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Imagem removida com sucesso.']);
    }
}
