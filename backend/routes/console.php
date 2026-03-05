<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\DatabaseBackupJob;
use App\Models\BackupJob;

Schedule::command('metrics:collect')->everyMinute();
Schedule::command('api:check-health')->everyThirtyMinutes();
Schedule::command('ai:reset-quotas')->daily();
Schedule::command('ai_keys:recover')->hourly();
Schedule::command('concursos:sync')->hourly();

// Sync Google Analytics 4 data
Schedule::job(new \App\Jobs\SyncDailyAnalyticsJob())->dailyAt('01:00');

// -----------------------------------------------------------------------
// Database Backup — scheduled daily at the time configured by the admin.
// The schedule_enabled and schedule_time settings are stored in the cache
// and managed via the Admin Panel → Backup Settings.
// -----------------------------------------------------------------------
Schedule::call(function () {
    $enabled = Cache::get('setting_backup_schedule_enabled', config('backup.schedule_enabled', true));

    if (!$enabled || $enabled === '0') {
        return; // Backup automático desativado pelo admin
    }

    // Prevent overlapping scheduled backups
    $running = BackupJob::whereIn('status', ['pending', 'running'])->exists();
    if ($running) {
        Log::warning('[Scheduler] Backup agendado ignorado: já existe um backup em andamento.');
        return;
    }

    $backupJob = BackupJob::create([
        'triggered_by' => null, // null = agendado automaticamente
        'status' => 'pending',
        's3_bucket' => Cache::get('setting_backup_s3_bucket', config('backup.s3_bucket')),
    ]);

    DatabaseBackupJob::dispatch($backupJob->id);

    Log::info('[Scheduler] Backup automático agendado. Job ID: ' . $backupJob->id);

})->name('database-backup-scheduled')
    ->dailyAt(Cache::get('setting_backup_schedule_time', config('backup.schedule_time', '00:00')))
    ->withoutOverlapping();

