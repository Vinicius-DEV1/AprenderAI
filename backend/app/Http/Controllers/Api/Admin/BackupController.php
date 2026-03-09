<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DatabaseBackupJob;
use App\Models\BackupJob;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'message' => 'Nenhum Bucket S3 configurado. Use "Download Direto" para baixar o backup sem S3.',
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
     * Direct local dump — streams mysqldump output (.sql.gz) directly to the
     * admin's browser. Does NOT require S3 configuration.
     *
     * Security:
     *  - Requires authenticated admin session (is.admin middleware).
     *  - Every request is logged: who, from which IP, and when — BEFORE the dump starts.
     *  - The DB password is never exposed in headers or responses.
     *  - Data is piped through gzip before transmission (compressed in-flight).
     */
    public function localDump(Request $request): StreamedResponse
    {
        $user = $request->user();
        $ip = $request->ip();
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_local_{$timestamp}.sql.gz";

        // -----------------------------------------------------------------------
        // AUDIT LOG — registrado ANTES do dump para capturar qualquer tentativa
        // -----------------------------------------------------------------------
        Log::channel('stack')->warning('[BackupController::localDump] Download direto solicitado.', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'ip' => $ip,
            'user_agent' => $request->userAgent(),
            'requested_at' => now()->toIso8601String(),
            'filename' => $filename,
        ]);

        $dbHost = env('DB_HOST', 'db');
        $dbPort = env('DB_PORT', '3306');
        $dbDatabase = env('DB_DATABASE');
        $dbUsername = env('DB_USERNAME');
        $dbPassword = env('DB_PASSWORD');

        $command = sprintf(
            'MYSQL_PWD=%s mysqldump --host=%s --port=%s --user=%s --single-transaction --skip-lock-tables --routines --triggers %s | gzip',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUsername),
            escapeshellarg($dbDatabase)
        );

        return response()->stream(function () use ($command, $user, $ip, $filename) {
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorspec, $pipes);

            if (!is_resource($process)) {
                Log::error('[BackupController::localDump] Falha ao iniciar proc_open.', [
                    'user_id' => $user->id,
                    'ip' => $ip,
                ]);
                echo 'ERRO: Falha ao iniciar o processo de dump.';
                return;
            }

            fclose($pipes[0]);

            $totalBytes = 0;
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk !== false && strlen($chunk) > 0) {
                    echo $chunk;
                    flush();
                    $totalBytes += strlen($chunk);
                }
            }

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            // A file under 200 bytes is likely just a gzip header without actual data
            if ($exitCode !== 0 || $totalBytes < 200) {
                Log::error('[BackupController::localDump] Falha no dump ou arquivo suspeito (vazio).', [
                    'exit_code' => $exitCode,
                    'bytes' => $totalBytes,
                    'stderr' => substr($stderr, 0, 500),
                    'user_id' => $user->id,
                    'ip' => $ip,
                ]);
            } else {
                Log::info('[BackupController::localDump] Download direto concluído com sucesso.', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'ip' => $ip,
                    'bytes_sent' => $totalBytes,
                    'filename' => $filename,
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Save backup configuration settings (bucket name, schedule, etc.).
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            's3_bucket' => ['nullable', 'string', 'max:255'],
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
