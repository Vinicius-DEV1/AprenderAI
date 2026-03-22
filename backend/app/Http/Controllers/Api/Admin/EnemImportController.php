<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnemImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessEnemExamJob;
use App\Services\EnemApiService;

class EnemImportController extends Controller
{
    /**
     * Get import history and active batch status.
     */
    public function index()
    {
        $logs = EnemImportLog::select([
            'id',
            'year',
            'inserted_count',
            'updated_count',
            'ignored_count',
            'error_count',
            'status',
            'progress',
            'total',
            'finished',
            'created_at',
            'updated_at'
        ])->latest()->paginate(10);

        // Mocking active batch for now as the full background processing 
        // would require more infra setup (Horizon/Redis jobs).
        // In a real scenario, this would check a cache key or a table.
        $activeBatch = EnemImportLog::where('status', 'processing')->first();

        return response()->json([
            'logs' => $logs,
            'activeBatch' => $activeBatch
        ]);
    }

    /**
     * Start a new import batch.
     */
    public function store(Request $request, EnemApiService $apiService)
    {
        $year = $request->input('year');

        try {
            $yearsToImport = [];

            if ($request->filled('year')) {
                $yearsToImport[] = (int) $request->input('year');
            } else {
                // Fetch all years
                $exams = $apiService->getExams();
                foreach ($exams['data'] ?? $exams as $exam) {
                    if (isset($exam['year'])) {
                        $yearsToImport[] = (int) $exam['year'];
                    }
                }
            }

            if (empty($yearsToImport)) {
                return response()->json(['error' => 'Nenhum ano encontrado para importar.'], 400);
            }

            // Converter para log
            $log = EnemImportLog::create([
                'year' => $year ?? 0, // 0 means all years
                'status' => 'processing',
                'inserted_count' => 0,
                'ignored_count' => 0,
                'error_count' => 0,
                'processed' => 0,
                'total' => count($yearsToImport),
                'progress' => 0,
            ]);

            $jobs = [];
            foreach ($yearsToImport as $y) {
                $jobs[] = new ProcessEnemExamJob($y, $log->id);
            }

            $batch = Bus::batch($jobs)
                ->then(function (\Illuminate\Bus\Batch $batch) use ($log) {
                    $log->update(['status' => 'completed', 'finished' => true, 'progress' => 100]);
                })
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($log) {
                    $log->update(['status' => 'failed']);
                })
                ->name('API ENEM Batch ' . ($year ?? 'Todos'))
                ->dispatch();

            return response()->json([
                'message' => 'Importação iniciada!',
                'log' => $log,
                'batch_id' => $batch->id
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao iniciar importação: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check progress of an active batch.
     */
    public function status(Request $request)
    {
        $batchId = $request->query('batch_id');
        $logId = $request->query('log_id');

        $log = null;
        if ($logId) {
            $log = EnemImportLog::find($logId);
        }

        if ($batchId) {
            $batch = Bus::findBatch($batchId);
            if ($batch) {
                if ($log) {
                    return response()->json(array_merge($log->toArray(), [
                        'progress' => $batch->progress(),
                        'finished' => $batch->finished() || $batch->cancelled(),
                        'processed' => $batch->processedJobs(),
                        'total' => $batch->totalJobs
                    ]));
                }
                return response()->json([
                    'progress' => $batch->progress(),
                    'finished' => $batch->finished() || $batch->cancelled(),
                    'processed' => $batch->processedJobs(),
                    'total' => $batch->totalJobs
                ]);
            }
        }

        if ($log) {
            return response()->json($log);
        }

        return response()->json(['error' => 'Status não encontrado'], 404);
    }
}
