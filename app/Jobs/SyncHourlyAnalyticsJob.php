<?php

namespace App\Jobs;

use App\Models\AnalyticsHourly;
use App\Services\GoogleAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncHourlyAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $date;

    public function __construct(?string $date = null)
    {
        $this->date = $date ?? Carbon::now()->format('Y-m-d');
    }

    public function handle(GoogleAnalyticsService $gaService)
    {
        if (!$gaService->isConfigured()) {
            return;
        }

        try {
            $hourlyData = $gaService->fetchHourlyMetrics($this->date);

            foreach ($hourlyData as $row) {
                $hourStr = $row['hour'] ?? null;
                if ($hourStr !== null) {
                    AnalyticsHourly::updateOrCreate(
                        [
                            'date' => $this->date,
                            'hour' => (int) $hourStr
                        ],
                        [
                            'active_users' => $row['activeUsers'] ?? 0,
                            'sessions' => $row['sessions'] ?? 0,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar GA4 por hora: ' . $e->getMessage());
        }
    }
}
