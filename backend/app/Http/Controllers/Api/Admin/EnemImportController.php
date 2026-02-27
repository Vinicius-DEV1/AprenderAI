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

class EnemImportController extends Controller
{
    /**
     * Get import history and active batch status.
     */
    public function index()
    {
        $logs = EnemImportLog::latest()->paginate(10);

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
    public function store(Request $request)
    {
        $year = $request->input('year');

        // This is a simplified version of the logic 
        // In the legacy Blade version, this might trigger a background Job.
        // For now, we return a mock success and log the request.

        $log = EnemImportLog::create([
            'year' => $year ?? 0, // 0 means all years
            'status' => 'processing',
            'inserted_count' => 0,
            'ignored_count' => 0,
            'error_count' => 0,
            'processed' => 0,
            'total' => 1,
            'progress' => 0,
        ]);

        // Dispatch background job here if implemented...
        // For now, we'll mark it as completed almost immediately for the UI to move on
        // Or better, let's keep it 'processing' and mock the status.

        return response()->json([
            'message' => 'Importação iniciada!',
            'activeBatch' => $log
        ]);
    }

    /**
     * Check progress of an active batch.
     */
    public function status(Request $request)
    {
        $batchId = $request->query('batch_id');
        $log = EnemImportLog::find($batchId);

        if (!$log) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        // Mocking progress increment for demonstration if it's still processing
        if ($log->status === 'processing') {
            $log->progress = min(100, $log->progress + 20);
            if ($log->progress >= 100) {
                $log->status = 'completed';
                $log->finished = true;
            }
            $log->save();
        }

        return response()->json($log);
    }
}
