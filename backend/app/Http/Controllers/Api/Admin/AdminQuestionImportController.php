<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Services\QuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminQuestionImportController extends Controller
{
    public function __construct(private readonly QuestionImportService $importService)
    {
    }

    /**
     * Get import history and stats.
     */
    public function index(Request $request)
    {
        $imports = QuestionImport::with('uploader')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'imports' => $imports,
            'stats' => [
                'pending_import' => Question::where('review_status', 'review')->count()
            ]
        ]);
    }

    /**
     * Start a new import batch.
     */
    public function store(Request $request)
    {
        Log::debug('[AdminQuestionImportController] store started', [
            'has_file' => $request->hasFile('zip_file'),
            'all_params' => $request->all()
        ]);

        $request->validate([
            // Limite de 10GB = 10 * 1024 * 1024 KB = 10485760 KB
            // O `max` do Laravel para arquivos usa KILOBYTES.
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:10485760'],
        ], [
            'zip_file.max'   => 'O arquivo é muito grande (Máx: 10GB).',
            'zip_file.mimes' => 'O arquivo deve ser um .zip válido.',
        ]);

        try {
            $file = $request->file('zip_file');
            $originalName = $file->getClientOriginalName();

            // Salva o zip de forma local no disco 'public' (que é compartilhado via volume do Docker entre app e worker)
            $filename = $file->hashName();
            $path = $file->storeAs('imports_tmp', $filename, 'public');

            Log::debug('[AdminQuestionImportController] Saved ZIP locally.', [
                'path' => $path,
                'exists' => Storage::disk('public')->exists($path),
                'absolute' => Storage::disk('public')->path($path),
                'filesize' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : 0
            ]);

            $import = QuestionImport::create([
                'batch_name' => Auth::user()->name . ' — ' . now()->format('d/m/Y H:i'),
                'original_filename' => $originalName,
                'uploaded_by' => Auth::id(),
                'status' => 'pending',
            ]);

            // Despacha o Job para processamento em background (ou executa síncrono para teste simplificado)
            // Para garantir que o usuário veja o progresso, usamos o Job real.
            \App\Jobs\ProcessQuestionImportJob::dispatch($import, $path);

            return response()->json([
                'success' => true,
                'import_id' => $import->id,
                'message' => 'Importação enviada para fila de processamento.',
            ]);

        } catch (\Exception $e) {
            Log::error('[AdminQuestionImportController] Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar arquivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check progress of an active batch.
     */
    public function progress($id)
    {
        Log::debug("[AdminQuestionImportController] progress called for ID: {$id}");

        $import = QuestionImport::findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'total' => $import->total_questions,
            'processed' => $import->processed_questions,
            'progress' => $import->total_questions > 0
                ? round(($import->processed_questions / $import->total_questions) * 100)
                : 0,
            'error_message' => $import->error_message
        ]);
    }

    /**
     * Check if there's an active job for the user.
     */
    public function activeJob()
    {
        Log::debug('[AdminQuestionImportController] activeJob called');

        $activeImport = QuestionImport::where('uploaded_by', Auth::id())
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        return response()->json([
            'active_import' => $activeImport
        ]);
    }
    /**
     * Safely destroy (rollback) an entire import batch and all its questions.
     */
    public function destroy(Request $request, $id)
    {
        Log::debug("[AdminQuestionImportController] destroy called for ID: {$id}");

        $import = QuestionImport::findOrFail($id);

        try {
            $this->importService->rollback($import, Auth::user(), $request->ip());

            return response()->json([
                'success' => true,
                'message' => 'Lote revertido com sucesso (Log mantido).'
            ]);
        } catch (\Exception $e) {
            Log::error("[AdminQuestionImportController] Error rolling back batch {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro crítico ao reverter lote: ' . $e->getMessage()
            ], 500);
        }
    }
}
