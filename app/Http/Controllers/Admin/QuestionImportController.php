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
 * 
 * Este controller gerencia o fluxo de ponta a ponta:
 * 1. Upload e processamento inicial de lotes (.zip).
 * 2. Painel de revisão de questões pendentes.
 * 3. interface de inspeção visual e recorte de imagens.
 * 4. Fluxo de aprovação/publicação e reversão de questões.
 * 
 * @package App\Http\Controllers\Admin
 */
class QuestionImportController extends Controller
{
    /**
     * Injeção de dependência do serviço de importação.
     * 
     * @param QuestionImportService $importService
     */
    public function __construct(private readonly QuestionImportService $importService)
    {
    }

    // ====================================================================
    // 1. GESTÃO DE LOTES (UPLOAD)
    // ====================================================================

    /**
     * Exibe o formulário de upload e o histórico de lotes processados.
     * 
     * GET /admin/import
     */
    public function index(): \Illuminate\View\View
    {
        $imports = QuestionImport::with('uploader')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.import.index', compact('imports'));
    }

    /**
     * Recebe o arquivo .zip e dispara o processamento do lote.
     * 
     * POST /admin/import
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Validação rigorosa: Zip de até 200MB
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
                ->with('success', "✅ Lote importado com sucesso! {$import->total_questions} questões adicionadas para revisão.");

        } catch (\Throwable $e) {
            Log::error('[QuestionImportController] Falha no upload: ' . $e->getMessage());
            return redirect()
                ->route('admin.import.index')
                ->with('error', '❌ Houve um erro crítico ao processar o arquivo: ' . $e->getMessage());
        }
    }

    // ====================================================================
    // 2. PAINEL DE REVISÃO (LISTAGEM)
    // ====================================================================

    /**
     * Lista as questões que aguardam revisão humana.
     * 
     * GET /admin/import/review
     */
    public function reviewIndex(Request $request): \Illuminate\View\View
    {
        $pendingQuery = Question::with(['subjects', 'alternatives'])
            ->where('review_status', 'pending')
            ->latest();

        // Filtro opcional por lote específico
        if ($request->filled('import_id')) {
            $importIds = QuestionImportItem::where('import_id', $request->import_id)
                ->pluck('question_id');
            $pendingQuery->whereIn('id', $importIds);
        }

        // Histórico recente para contexto do administrador
        $recentHistory = QuestionImportItem::with(['question', 'import', 'approver'])
            ->whereHas('question', fn($q) => $q->where('review_status', 'approved'))
            ->latest('approved_at')
            ->take(15)
            ->get();

        return view('admin.import.review.index', [
            'questions'     => $pendingQuery->paginate(12),
            'recentHistory' => $recentHistory,
            'selectedImport'=> $request->import_id
        ]);
    }

    /**
     * Tela de inspeção detalhada e editor de recorte.
     * 
     * GET /admin/import/review/{question}
     */
    public function reviewShow(Question $question): \Illuminate\View\View
    {
        $importItem = QuestionImportItem::where('question_id', $question->id)->first();

        return view('admin.import.review.show', compact('question', 'importItem'));
    }

    // ====================================================================
    // 3. OPERAÇÕES DE REVISÃO (AJAX & ACTIONS)
    // ====================================================================

    /**
     * Endpoint AJAX para processamento de recorte de imagem.
     * 
     * POST /admin/import/review/{question}/crop
     */
    public function crop(Request $request, Question $question): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            // Aceita o alvo (Enunciado ou uma das letras de alternativa)
            'target' => ['required', 'string', 'regex:/^(statement|[A-Ea-e])$/'],
            'x'      => ['required', 'integer', 'min:0'],
            'y'      => ['required', 'integer', 'min:0'],
            'width'  => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
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
            Log::error("[QuestionImportController] Erro no Crop (Q#{$question->id}): " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Endpoint AJAX para remoção da imagem da questão.
     * 
     * DELETE /admin/import/review/{question}/delete-image
     */
    public function deleteImage(Question $question): \Illuminate\Http\JsonResponse
    {
        try {
            $this->importService->deleteImage($question);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Aprova e publica a questão, retirando-a do fluxo de revisão.
     * 
     * POST /admin/import/review/{question}/approve
     */
    public function approve(Question $question): \Illuminate\Http\RedirectResponse
    {
        $question->update(['review_status' => 'approved']);

        // Atualiza log de auditoria
        QuestionImportItem::where('question_id', $question->id)->update([
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'reverted_at' => null // Limpa se for uma re-aprovação
        ]);

        return redirect()
            ->route('admin.import.review.index')
            ->with('success', "Questão #{$question->id} aprovada!");
    }

    /**
     * Reverte uma aprovação, trazendo a questão de volta para revisão.
     * 
     * POST /admin/import/review/{question}/revert
     */
    public function revert(Question $question): \Illuminate\Http\RedirectResponse
    {
        $question->update(['review_status' => 'pending']);

        // Atualiza log de auditoria
        QuestionImportItem::where('question_id', $question->id)->update([
            'reverted_at' => now(),
            'approved_at' => null
        ]);

        return redirect()
            ->route('admin.import.review.show', $question)
            ->with('success', 'Status revertido para pendente.');
    }
}
