<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImage;
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

    public function store(Request $request)
    {
        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:204800'], 
        ], [
            'zip_file.required' => 'Selecione um arquivo .zip para realizar a importação.',
            'zip_file.mimes'    => 'O formato do arquivo deve ser obrigatoriamente .zip.',
            'zip_file.max'      => 'O limite máximo para o arquivo de importação é de 200MB.',
        ]);

        try {
            // Salva o zip de forma local temporária para o Worker conseguir acessar
            $zipPath = $request->file('zip_file')->store('imports_tmp', 'local');

            $import = QuestionImport::create([
                'batch_name'        => Auth::user()->name . ' — ' . now()->format('d/m/Y H:i'),
                'original_filename' => $request->file('zip_file')->getClientOriginalName(),
                'uploaded_by'       => Auth::user()->id,
                'status'            => 'pending',
                'total_questions'   => 0,
            ]);

            \App\Jobs\ProcessQuestionImportJob::dispatch($import, $zipPath);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'   => true,
                    'import_id' => $import->id,
                    'message'   => 'Importação enviada para fila de processamento.',
                ]);
            }

            return redirect()
                ->route('admin.import.index')
                ->with('success', "✅ Lote na fila! Aguarde o processamento.");

        } catch (\Throwable $e) {
            Log::error('[QuestionImportController] Falha ao enfileirar upload: ' . $e->getMessage());
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Erro interno ao processar o arquivo. Tente novamente.'], 500);
            }

            return redirect()
                ->route('admin.import.index')
                ->with('error', '❌ Erro ao enviar arquivo para fila: ' . $e->getMessage());
        }
    }

    /**
     * Retorna o progresso atual de uma importação em andamento (Endpoint AJAX para UI Alpine)
     */
    public function progress($id): \Illuminate\Http\JsonResponse
    {
        $import = QuestionImport::findOrFail($id);

        return response()->json([
            'status'    => $import->status,
            'total'     => $import->total_questions,
            'processed' => $import->processed_questions,
            'error'     => $import->error_message
        ]);
    }

    /**
     * Busca se o usuário possui alguma importação pendente/processando atualmente.
     * Útil para retomar a barra de progresso caso ele recarregue a página (Non-Blocking UX).
     */
    public function activeJob(): \Illuminate\Http\JsonResponse
    {
        $activeImport = QuestionImport::where('uploaded_by', Auth::id())
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if (!$activeImport) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active'    => true,
            'import_id' => $activeImport->id,
            'status'    => $activeImport->status,
            'total'     => $activeImport->total_questions,
            'processed' => $activeImport->processed_questions,
        ]);
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
        $question->load('images');
        $importItem = QuestionImportItem::where('question_id', $question->id)->first();
        return view('admin.import.review.show', compact('question', 'importItem'));
    }

    public function crop(Request $request, QuestionImage $image): \Illuminate\Http\JsonResponse
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

    public function deleteImage(QuestionImage $image): \Illuminate\Http\JsonResponse
    {
        try {
            $this->importService->deleteImage($image);
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
