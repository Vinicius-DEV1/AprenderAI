<?php

namespace App\Providers;

use App\Models\Question;
use App\Observers\QuestionObserver;
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
        $startTime = 0;

        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) use (&$startTime) {
            $startTime = microtime(true);
        });

        \Illuminate\Support\Facades\Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event) use (&$startTime) {
            $duration = microtime(true) - $startTime;
            $queue = $event->job->getQueue();

            // Resolve QueueTrackerService e registra métrica
            app(\App\Services\QueueTrackerService::class)->recordJob($queue, $duration);
        });

        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            // Opcional: registrar falhas específicas no tracker se necessário
        });
    }
}
