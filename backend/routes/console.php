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
Schedule::command('concursos:sync')->everyTwoHours();
Schedule::command('simulations:manage-timers')->everyMinute();

Schedule::command('subscriptions:notify-expiring')->dailyAt('08:00');

// Sync Google Analytics 4 data
Schedule::job(new \App\Jobs\SyncDailyAnalyticsJob())->dailyAt('01:00');

// -----------------------------------------------------------------------
// Database Backup — scheduled daily at the time configured by the admin.
// The schedule_enabled and schedule_time settings are stored in the cache
// and managed via the Admin Panel → Backup Settings.
// -----------------------------------------------------------------------
$scheduleTime = config('backup.schedule_time', '00:00');
try {
    if (class_exists(\Illuminate\Support\Facades\Schema::class) && \Illuminate\Support\Facades\Schema::hasTable('cache')) {
        $scheduleTime = Cache::get('setting_backup_schedule_time', $scheduleTime);
    }
} catch (\Exception $e) {
}

Schedule::call(function () {
    $enabled = config('backup.schedule_enabled', true);
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('cache')) {
            $enabled = Cache::get('setting_backup_schedule_enabled', $enabled);
        }
    } catch (\Exception $e) {
    }

    if (!$enabled || $enabled === '0') {
        return;
    }

    $running = BackupJob::whereIn('status', ['pending', 'running'])->exists();
    if ($running) {
        Log::warning('[Scheduler] Backup agendado ignorado: já existe um backup em andamento.');
        return;
    }

    $s3Bucket = config('backup.s3_bucket');
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('cache')) {
            $s3Bucket = Cache::get('setting_backup_s3_bucket', $s3Bucket);
        }
    } catch (\Exception $e) {
    }

    $backupJob = BackupJob::create([
        'triggered_by' => null,
        'status' => 'pending',
        's3_bucket' => $s3Bucket,
    ]);

    DatabaseBackupJob::dispatch($backupJob->id);
    Log::info('[Scheduler] Backup automático agendado. Job ID: ' . $backupJob->id);

})->name('database-backup-scheduled')
    ->dailyAt($scheduleTime)
    ->withoutOverlapping();

