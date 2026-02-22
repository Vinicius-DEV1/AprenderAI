<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controller responsável pela interface administrativa de importação e revisão.
 */
class QuestionImportController extends Controller
{
    public function __construct(private readonly QuestionImportService $importService)
    {
    }

    public function index(): \Illuminate\View\View
    {
        $imports = QuestionImport::with('uploader')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.import.index', compact('imports'));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:204800'], 
        ], [
            'zip_file.required' => 'Selecione um arquivo .zip para realizar a importação.',
            'zip_file.mimes'    => 'O formato do arquivo deve ser obrigatoriamente .zip.',
            'zip_file.max'      => 'O limite máximo para o arquivo de importação é de 200MB.',
        ]);

        try {
            $import = $this->importService->processZip(
                $request->file('zip_file'),
                Auth::user()
            );

            return redirect()
                ->route('admin.import.review.index', ['import' => $import->id])
                ->with('success', "✅ Lote importado com sucesso!");

        } catch (\Throwable $e) {
            Log::error('[QuestionImportController] Falha no upload: ' . $e->getMessage());
            return redirect()
                ->route('admin.import.index')
                ->with('error', '❌ Erro ao processar o arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Lista as questões que aguardam revisão humana.
     */
    public function reviewIndex(Request $request): \Illuminate\View\View
    {
        $pendingQuery = Question::with(['subjects', 'alternatives'])
            ->where('review_status', 'review');

        // Filtro por Banca
        if ($request->filled('organization')) {
            $pendingQuery->where('organization', $request->organization);
        }

        // Filtro por Lote
        if ($request->filled('import_id')) {
            $importIds = QuestionImportItem::where('import_id', $request->import_id)
                ->pluck('question_id');
            $pendingQuery->whereIn('id', $importIds);
        }

        $pendingQuestions = $pendingQuery->latest()->paginate(12);

        // Dados para os filtros
        $imports = QuestionImport::latest()->take(20)->get();
        $organizations = Question::whereNotNull('organization')
            ->distinct()
            ->pluck('organization')
            ->sort();

        // Auditoria
        $recentActions = QuestionImportItem::with(['question', 'import', 'approver'])
            ->where(function($q) {
                $q->whereNotNull('approved_at')->orWhereNotNull('reverted_at');
            })
            ->latest('updated_at')
            ->take(15)
            ->get();

        return view('admin.import.review.index', [
            'pendingQuestions' => $pendingQuestions,
            'imports'          => $imports,
            'organizations'    => $organizations,
            'recentActions'    => $recentActions,
            'selectedImport'   => $request->import_id
        ]);
    }

    public function reviewShow(Question $question): \Illuminate\View\View
    {
        $importItem = QuestionImportItem::where('question_id', $question->id)->first();
        return view('admin.import.review.show', compact('question', 'importItem'));
    }

    public function crop(Request $request, Question $question): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'regex:/^(statement|[A-Ea-e])$/'],
            'x'      => ['required', 'integer'],
            'y'      => ['required', 'integer'],
            'width'  => ['required', 'integer'],
            'height' => ['required', 'integer'],
        ]);

        try {
            $publicUrl = $this->importService->saveCrop(
                $question,
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

    public function deleteImage(Question $question): \Illuminate\Http\JsonResponse
    {
        try {
            $this->importService->deleteImage($question);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Question $question): \Illuminate\Http\RedirectResponse
    {
        $question->update(['review_status' => 'approved']);
        QuestionImportItem::where('question_id', $question->id)->update([
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'reverted_at' => null
        ]);
        return redirect()->back()->with('success', "Questão #{$question->id} aprovada!");
    }

    public function revert(Question $question): \Illuminate\Http\RedirectResponse
    {
        $question->update(['review_status' => 'review']);
        QuestionImportItem::where('question_id', $question->id)->update([
            'reverted_at' => now(),
            'approved_at' => null
        ]);
        return redirect()->back()->with('success', 'Status revertido para fila de revisão humana.');
    }
}
