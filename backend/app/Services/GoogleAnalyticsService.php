<?php

namespace App\Services;

use App\Models\Configuration;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\Dimension;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsService
{
    protected ?BetaAnalyticsDataClient $client = null;
    protected ?string $propertyId = null;

    public function __construct()
    {
        $this->initializeClient();
    }

    protected function initializeClient()
    {
        $enabled = Configuration::get('analytics_enabled', false);
        $propertyId = Configuration::get('analytics_property_id');

        if (!$enabled || !$propertyId) {
            return;
        }

        $this->propertyId = $propertyId;

        try {
            $credentialsArray = null;

            // First Priority: Try loading the JSON file directly from storage
            $jsonFilePath = storage_path('app/google/service-account.json');
            if (file_exists($jsonFilePath)) {
                $credentialsArray = json_decode(file_get_contents($jsonFilePath), true);
            } else {
                // Secondary Priority: Load encrypted string from DB config
                $encryptedJson = Configuration::get('analytics_service_account_json');
                if ($encryptedJson) {
                    $jsonCredentials = Crypt::decryptString($encryptedJson);
                    $credentialsArray = json_decode($jsonCredentials, true);
                }
            }

            if ($credentialsArray) {
                $this->client = new BetaAnalyticsDataClient([
                    'credentials' => $credentialsArray,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Google Analytics API Initialization Error: ' . $e->getMessage());
        }
    }

    public function isConfigured(): bool
    {
        return $this->client !== null && $this->propertyId !== null;
    }

    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            // Um simples teste pra validar credenciais: busca usuários de "hoje"
            $this->client->runReport([
                'property' => 'properties/' . $this->propertyId,
                'dateRanges' => [new DateRange(['start_date' => 'today', 'end_date' => 'today'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('GA4 Connection Test Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper genérico para rodar relatório no GA4.
     */
    public function runReport(string $startDate, string $endDate, array $metrics, array $dimensions = [])
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $formattedMetrics = array_map(fn($m) => new Metric(['name' => $m]), $metrics);
            $formattedDimensions = array_map(fn($d) => new Dimension(['name' => $d]), $dimensions);

            $response = $this->client->runReport([
                'property' => 'properties/' . $this->propertyId,
                'dateRanges' => [new DateRange(['start_date' => $startDate, 'end_date' => $endDate])],
                'metrics' => $formattedMetrics,
                'dimensions' => $formattedDimensions,
            ]);

            $results = [];

            foreach ($response->getRows() as $row) {
                $item = [];

                foreach ($row->getDimensionValues() as $index => $dimensionValue) {
                    $item[$dimensions[$index]] = $dimensionValue->getValue();
                }

                foreach ($row->getMetricValues() as $index => $metricValue) {
                    $item[$metrics[$index]] = $metricValue->getValue();
                }

                $results[] = $item;
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('GA4 runReport Error: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchDailyMetrics(string $date)
    {
        return $this->runReport($date, $date, ['activeUsers', 'sessions', 'newUsers', 'bounceRate', 'averageSessionDuration', 'screenPageViewsPerSession']);
    }

    public function fetchHourlyMetrics(string $date)
    {
        return $this->runReport($date, $date, ['activeUsers', 'sessions'], ['hour']);
    }

    public function fetchPageMetrics(string $date)
    {
        return $this->runReport($date, $date, ['screenPageViews', 'averageSessionDuration'], ['pagePath', 'pageTitle']);
    }

    public function fetchDeviceMetrics(string $date)
    {
        return $this->runReport($date, $date, ['sessions', 'activeUsers'], ['deviceCategory']);
    }

    public function fetchSourceMetrics(string $date)
    {
        return $this->runReport($date, $date, ['sessions', 'activeUsers'], ['sessionSourceMedium', 'country', 'city']);
    }

    public function fetchEventMetrics(string $date)
    {
        return $this->runReport($date, $date, ['eventCount', 'activeUsers'], ['eventName']);
    }
}
