<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DatabaseBackupJob;
use App\Models\BackupJob;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backupService)
    {
    }

    /**
     * List backup history (last 30 jobs).
     */
    public function index()
    {
        $jobs = BackupJob::with('triggeredBy:id,name')
            ->latest()
            ->take(30)
            ->get();

        return response()->json([
            'jobs' => $jobs,
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Trigger a manual backup immediately.
     * Returns 202 Accepted immediately — the actual work happens asynchronously.
     */
    public function trigger(Request $request)
    {
        // Prevent multiple concurrent backups
        $running = BackupJob::whereIn('status', ['pending', 'running'])->exists();
        if ($running) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um backup em andamento. Aguarde a conclusão antes de iniciar outro.',
            ], 409);
        }

        $settings = $this->getSettings();

        if (empty($settings['s3_bucket'])) {
            return response()->json([
                'success' => false,
                'message' => 'Configure o nome do Bucket S3 antes de fazer o backup.',
            ], 422);
        }

        // Create the pre-registered job record so the frontend can start polling immediately
        $backupJob = BackupJob::create([
            'triggered_by' => $request->user()->id,
            'status' => 'pending',
            's3_bucket' => $settings['s3_bucket'],
        ]);

        // Dispatch to the default queue (processed by the worker container)
        DatabaseBackupJob::dispatch($backupJob->id);

        Log::info("[BackupController] Backup manual acionado por {$request->user()->name} (ID: {$request->user()->id})");

        return response()->json([
            'success' => true,
            'backup_id' => $backupJob->id,
            'message' => 'Backup iniciado. Acompanhe o progresso abaixo.',
        ], 202);
    }

    /**
     * Poll the status of a specific backup job.
     * Called repeatedly by the frontend until status is "completed" or "failed".
     */
    public function show(int $id)
    {
        $job = BackupJob::with('triggeredBy:id,name')->findOrFail($id);

        return response()->json(['job' => $job]);
    }

    /**
     * Generate a pre-signed S3 URL for downloading a completed backup.
     * URL is valid for 5 minutes.
     */
    public function download(int $id)
    {
        $job = BackupJob::findOrFail($id);

        if ($job->status !== 'completed' || empty($job->s3_key) || empty($job->s3_bucket)) {
            return response()->json(['message' => 'Backup não disponível para download.'], 422);
        }

        try {
            $url = $this->backupService->getPresignedUrl($job->s3_bucket, $job->s3_key, 300);

            return response()->json([
                'download_url' => $url,
                'expires_in' => 300,
            ]);
        } catch (\Throwable $e) {
            Log::error("[BackupController] Erro ao gerar URL de download: " . $e->getMessage());
            return response()->json(['message' => 'Falha ao gerar link de download.'], 500);
        }
    }

    /**
     * Save backup configuration settings (bucket name, schedule, etc.).
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            's3_bucket' => ['required', 'string', 'max:255'],
            's3_prefix' => ['nullable', 'string', 'max:255'],
            'schedule_enabled' => ['required', 'boolean'],
            'schedule_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $settings = [
            'backup_s3_bucket' => $request->input('s3_bucket'),
            'backup_s3_prefix' => $request->input('s3_prefix', 'backups/'),
            'backup_schedule_enabled' => $request->boolean('schedule_enabled') ? '1' : '0',
            'backup_schedule_time' => $request->input('schedule_time'),
        ];

        foreach ($settings as $key => $value) {
            Cache::put("setting_{$key}", $value, now()->addDays(7));
        }

        return response()->json([
            'success' => true,
            'message' => 'Configurações de backup salvas com sucesso.',
            'settings' => $this->getSettings(),
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Read backup settings from cache (falls back to config defaults).
     */
    private function getSettings(): array
    {
        return [
            's3_bucket' => Cache::get('setting_backup_s3_bucket', config('backup.s3_bucket')),
            's3_prefix' => Cache::get('setting_backup_s3_prefix', config('backup.s3_prefix', 'backups/')),
            'schedule_enabled' => (bool) Cache::get('setting_backup_schedule_enabled', config('backup.schedule_enabled', true)),
            'schedule_time' => Cache::get('setting_backup_schedule_time', config('backup.schedule_time', '00:00')),
        ];
    }
}
