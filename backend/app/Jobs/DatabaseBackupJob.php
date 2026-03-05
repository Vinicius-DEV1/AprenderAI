<?php

namespace App\Jobs;

use App\Models\BackupJob;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum time a backup job is allowed to run (90 minutes).
     * Prevents stuck jobs from blocking the worker indefinitely.
     */
    public int $timeout = 5400;

    /**
     * Number of times the job may be attempted.
     * Backup must be idempotent — retrying creates a new S3 object so this is safe.
     */
    public int $tries = 1;

    public function __construct(
        public readonly ?int $backupJobId = null
    ) {
    }

    public function handle(BackupService $backupService): void
    {
        // Resolve the BackupJob record (create one if dispatched without a pre-existing record)
        $job = $this->backupJobId
            ? BackupJob::findOrFail($this->backupJobId)
            : BackupJob::create(['status' => 'pending']);

        // -----------------------------------------------------------------
        // Read configuration
        // -----------------------------------------------------------------
        $bucket = config('backup.s3_bucket', env('AWS_BUCKET', ''));
        $prefix = config('backup.s3_prefix', 'backups/');

        if (empty($bucket)) {
            $this->failJob($job, 'S3 bucket não configurado. Configure em Admin → Configurações de Backup.');
            return;
        }

        // Generate a timestamped key
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $s3Key = rtrim($prefix, '/') . "/backup_{$timestamp}.sql.gz";

        // -----------------------------------------------------------------
        // Mark as running
        // -----------------------------------------------------------------
        $job->update([
            'status' => 'running',
            'started_at' => now(),
            's3_bucket' => $bucket,
            's3_key' => $s3Key,
        ]);

        Log::info("[DatabaseBackupJob] Starting backup → s3://{$bucket}/{$s3Key}");

        // -----------------------------------------------------------------
        // Build the mysqldump command
        // -----------------------------------------------------------------
        $dbHost = env('DB_HOST', 'db');
        $dbPort = env('DB_PORT', '3306');
        $dbDatabase = env('DB_DATABASE');
        $dbUsername = env('DB_USERNAME');
        $dbPassword = env('DB_PASSWORD');

        // We pipe mysqldump through gzip for compression before sending to S3.
        // --single-transaction: InnoDB snapshot (no table locks).
        // --skip-lock-tables:   Prevents LOCK TABLES on tables not supporting transactions.
        // --routines, --triggers: Include stored procedures and triggers.
        // --set-gtid-purged=OFF: Avoids GTID issues on restore.
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --skip-lock-tables --routines --triggers --set-gtid-purged=OFF %s | gzip',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUsername),
            escapeshellarg($dbPassword),
            escapeshellarg($dbDatabase)
        );

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout (mysqldump output → gzip → S3)
            2 => ['pipe', 'w'],  // stderr (error capture)
        ];

        $process = proc_open($command, $descriptorspec, $pipes, null, null, ['bypass_shell' => false]);

        if (!is_resource($process)) {
            $this->failJob($job, 'Falha ao iniciar o processo mysqldump.');
            return;
        }

        // Close stdin — we don't need to write to the process
        fclose($pipes[0]);

        try {
            // Stream stdout directly to S3 (no disk I/O)
            $result = $backupService->streamToS3($pipes[1], $bucket, $s3Key);

            fclose($pipes[1]);

            // Capture any stderr output
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            $process = null;

            if ($exitCode !== 0) {
                $this->failJob($job, "mysqldump encerrou com código {$exitCode}. Stderr: " . substr($stderr, 0, 500));
                return;
            }

            // -----------------------------------------------------------------
            // Success
            // -----------------------------------------------------------------
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_size_bytes' => $result['size'],
                'error_message' => null,
            ]);

            Log::info("[DatabaseBackupJob] Backup concluído. Tamanho: {$result['size']} bytes. Chave S3: {$s3Key}");

        } catch (\Throwable $e) {
            if (is_resource($pipes[1] ?? null))
                fclose($pipes[1]);
            if (is_resource($pipes[2] ?? null))
                fclose($pipes[2]);
            if (is_resource($process))
                proc_close($process);

            $this->failJob($job, $e->getMessage());
        }
    }

    /**
     * Handle a job that has exceeded the maximum number of attempts.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[DatabaseBackupJob] Job falhou definitivamente: " . $exception->getMessage());

        if ($this->backupJobId) {
            BackupJob::where('id', $this->backupJobId)
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => substr($exception->getMessage(), 0, 1000),
                ]);
        }
    }

    // -------------------------------------------------------------------------

    private function failJob(BackupJob $job, string $message): void
    {
        Log::error("[DatabaseBackupJob] Falha: {$message}");

        $job->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => substr($message, 0, 1000),
        ]);
    }
}
