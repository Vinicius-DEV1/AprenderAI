<?php

namespace App\Services;

/**
 * EnemApiService
 * 
 * Este serviço é responsável pela comunicação direta com a API externa (https://api.enem.dev).
 * Possui lógica integrada de Retry (tentativa automática) e Exponential Backoff para lidar
 * com o rate limit (Error 429) da API, garantindo que o importador não falhe por excesso de requisições.
 */
class EnemApiService
{
    protected string $baseUrl = 'https://api.enem.dev/v1';

    /**
     * Get questions from a specific exam year.
     * With Retry and Backoff for 429 Rate Limit.
     *
     * @param int $year
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getExamQuestions(int $year, int $limit = 50, int $offset = 0): array
    {
        $response = \Illuminate\Support\Facades\Http::retry(5, 1000, function (\Exception $exception, \Illuminate\Http\Client\PendingRequest $request) {
            if ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 429) {
                // Return true to retry
                return true;
            }
            return false;
        }, throw: false)
            ->timeout(60)
            ->get("{$this->baseUrl}/exams/{$year}/questions", [
                'limit' => $limit,
                'offset' => $offset,
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 429) {
            throw new \Exception("Rate Limit reached on Enem Dev API (429) for year {$year} even after retries.");
        }

        throw new \Exception("Failed to fetch from Enem Dev API: " . $response->status());
    }

    /**
     * Get fully listed exams available from API.
     */
    public function getExams(): array
    {
        $response = \Illuminate\Support\Facades\Http::retry(3, 1000)->timeout(30)->get("{$this->baseUrl}/exams");
        
        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception("Failed to fetch exams list from Enem Dev API: " . $response->status());
    }
}
