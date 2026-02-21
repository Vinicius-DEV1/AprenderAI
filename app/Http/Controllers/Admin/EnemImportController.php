<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\EnemImportLog;
use App\Jobs\ProcessEnemExamJob;
use App\Services\EnemApiService;

class EnemImportController extends Controller
{
    public function index()
    {
        $logs = EnemImportLog::orderBy('created_at', 'desc')->paginate(10);
        $activeBatchId = session('enem_import_batch_id');
        $activeBatch = $activeBatchId ? Bus::findBatch($activeBatchId) : null;

        return view('admin.enem-import.index', compact('logs', 'activeBatch'));
    }

    public function store(Request $request, EnemApiService $apiService)
    {
        $request->validate([
            'year' => 'nullable|integer|min:2009|max:' . date('Y'),
        ]);

        try {
            $yearsToImport = [];
            
            if ($request->filled('year')) {
                $yearsToImport[] = $request->input('year');
            } else {
                // Fetch all years
                $exams = $apiService->getExams();
                foreach ($exams['data'] ?? $exams as $exam) {
                    if (isset($exam['year'])) {
                        $yearsToImport[] = $exam['year'];
                    }
                }
            }

            if (empty($yearsToImport)) {
                return back()->with('error', 'Nenhum ano encontrado para importar.');
            }

            // Converter para log
            $log = EnemImportLog::create([
                'year' => $request->filled('year') ? $request->input('year') : 0, // 0 = Todos
                'status' => 'processing',
            ]);

            $jobs = [];
            foreach ($yearsToImport as $year) {
                $jobs[] = new ProcessEnemExamJob($year, $log->id);
            }

            $batch = Bus::batch($jobs)
                ->then(function (\Illuminate\Bus\Batch $batch) use ($log) {
                    $log->update(['status' => 'completed']);
                })
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($log) {
                    $log->update(['status' => 'failed']);
                })
                ->name('Importação API ENEM Dev ' . ($request->filled('year') ? $request->input('year') : 'Todos'))
                ->dispatch();

            session(['enem_import_batch_id' => $batch->id]);

            return back()->with('success', 'Importação iniciada com sucesso em background! Acompanhe o progresso.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar importação: ' . $e->getMessage());
        }
    }
}
