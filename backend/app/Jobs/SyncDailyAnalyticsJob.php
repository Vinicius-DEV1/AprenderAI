<?php

namespace App\Jobs;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsDevice;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsPage;
use App\Models\AnalyticsSource;
use App\Services\AnalyticsInsightService;
use App\Services\GoogleAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncDailyAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $date;

    /**
     * @param string|null $date Data no formato YYYY-MM-DD. Se nulo, usará "ontem".
     */
    public function __construct(?string $date = null)
    {
        $this->date = $date ?? Carbon::yesterday()->format('Y-m-d');
    }

    public function handle(GoogleAnalyticsService $gaService, AnalyticsInsightService $insightService)
    {
        if (!$gaService->isConfigured()) {
            Log::info('SyncDailyAnalyticsJob: GA4 não configurado. Ignorando sincronização.');
            return;
        }

        try {
            // 1. Visão Geral (Daily)
            $dailyData = $gaService->fetchDailyMetrics($this->date);
            $dailyModel = null;

            if (!empty($dailyData)) {
                $row = $dailyData[0];
                $dailyModel = AnalyticsDaily::updateOrCreate(
                    ['date' => $this->date],
                    [
                        'active_users' => $row['activeUsers'] ?? 0,
                        'sessions' => $row['sessions'] ?? 0,
                        'new_users' => $row['newUsers'] ?? 0,
                        'bounce_rate' => $row['bounceRate'] ?? 0,
                        'avg_session_duration' => $row['averageSessionDuration'] ?? 0,
                        'screen_page_views_per_session' => $row['screenPageViewsPerSession'] ?? 0,
                    ]
                );
            }

            // 2. Comportamento (Pages)
            $pageData = $gaService->fetchPageMetrics($this->date);
            foreach ($pageData as $row) {
                AnalyticsPage::updateOrCreate(
                    [
                        'date' => $this->date,
                        'page_path' => mb_substr($row['pagePath'] ?? '', 0, 255)
                    ],
                    [
                        'page_title' => mb_substr($row['pageTitle'] ?? 'No title', 0, 255),
                        'views' => $row['screenPageViews'] ?? 0,
                        'avg_time_on_page' => $row['averageSessionDuration'] ?? 0,
                        // Exit rate would theoretically come from GA or be calculated. 
                        // Simplified to 0 if not explicitly available in basic metrics.
                        'exit_rate' => 0,
                    ]
                );
            }

            // 3. Dispositivos (Devices)
            $deviceData = $gaService->fetchDeviceMetrics($this->date);
            foreach ($deviceData as $row) {
                AnalyticsDevice::updateOrCreate(
                    [
                        'date' => $this->date,
                        'device_category' => mb_substr($row['deviceCategory'] ?? 'Unknown', 0, 255)
                    ],
                    [
                        'sessions' => $row['sessions'] ?? 0,
                        'users' => $row['activeUsers'] ?? 0,
                    ]
                );
            }

            // 4. Aquisição (Sources)
            $sourceData = $gaService->fetchSourceMetrics($this->date);
            foreach ($sourceData as $row) {
                AnalyticsSource::updateOrCreate(
                    [
                        'date' => $this->date,
                        'source_medium' => mb_substr($row['sessionSourceMedium'] ?? 'direct', 0, 255),
                        'country' => mb_substr($row['country'] ?? '', 0, 255),
                        'city' => mb_substr($row['city'] ?? '', 0, 255),
                    ],
                    [
                        'sessions' => $row['sessions'] ?? 0,
                        'users' => $row['activeUsers'] ?? 0,
                    ]
                );
            }

            // 5. Conversão (Events)
            $eventData = $gaService->fetchEventMetrics($this->date);
            foreach ($eventData as $row) {
                AnalyticsEvent::updateOrCreate(
                    [
                        'date' => $this->date,
                        'event_name' => mb_substr($row['eventName'] ?? 'unknown', 0, 255)
                    ],
                    [
                        'event_count' => $row['eventCount'] ?? 0,
                        'users' => $row['activeUsers'] ?? 0,
                    ]
                );
            }

            // 6. Gerar Alertas/Insights (Comparando Hoje vs Ontem)
            if ($dailyModel) {
                $yesterdayDate = Carbon::parse($this->date)->subDay()->format('Y-m-d');
                $yesterdayModel = AnalyticsDaily::where('date', $yesterdayDate)->first();

                if ($yesterdayModel) {
                    $insightService->analyzeDailyMetrics($dailyModel, $yesterdayModel);
                }
            }

        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar GA4 diário: ' . $e->getMessage());
        }
    }
}
