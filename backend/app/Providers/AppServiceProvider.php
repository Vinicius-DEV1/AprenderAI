<?php

namespace App\Providers;

use App\Models\Question;
use App\Observers\QuestionObserver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * All view composers and Blade component registrations have been removed.
     * Site name / AI name branding is now served exclusively via GET /api/v1/config.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Registered::class,
            \App\Listeners\SendWelcomeEmail::class
        );

        // ── Xavier Semantic Search: auto-index approved questions ────────────
        Question::observe(QuestionObserver::class);

        // --- Monitoramento de Workers (Performance em Tempo Real) ---
        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            // Usa o ID do job (persistente) para salvar o início no cache por 10 minutos
            $jobId = $event->job->getJobId();
            \Illuminate\Support\Facades\Cache::put("job_start:{$jobId}", microtime(true), 600);
        });

        \Illuminate\Support\Facades\Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event) {
            $jobId = $event->job->getJobId();
            $startTime = \Illuminate\Support\Facades\Cache::pull("job_start:{$jobId}");
            
            if ($startTime) {
                $duration = microtime(true) - $startTime;
                $queue = $event->job->getQueue();
                $jobName = $event->job->resolveName();

                // Registra métricas e log de sucesso
                $tracker = app(\App\Services\QueueTrackerService::class);
                $tracker->recordJob($queue, $duration);
                $tracker->recordCompletedJob($jobName, $queue, $duration);
                
                \Illuminate\Support\Facades\Log::info("[Monitor] Job Processado: {$jobName} na fila {$queue} ({$duration}s)");
            }
        });

        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            $jobId = $event->job->getJobId();
            \Illuminate\Support\Facades\Cache::forget("job_start:{$jobId}");
            
            \Illuminate\Support\Facades\Log::error("[Monitor] Job FALHOU: " . $event->job->resolveName());
            // Opcional: registrar falhas específicas no tracker se necessário
            
            // Notify admins of the failing job
            try {
                app(\App\Services\AdminNotificationService::class)->notifyJobFailure(
                    $event->job->resolveName(), 
                    $event->exception
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[AppServiceProvider] Failed to notify admin of job failure: ' . $e->getMessage());
            }
        });
    }
}
