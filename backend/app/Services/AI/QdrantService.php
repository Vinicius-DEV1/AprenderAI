<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * QdrantService
 *
 * Handles all communication with the Qdrant vector database.
 * Supports multi-vector questions (statement + concept + explanation vectors)
 * and single-vector concepts.
 */
class QdrantService
{
    private string $baseUrl;
    private array  $headers;
    private int    $timeout;

    private string $questionsCollection;
    private string $conceptsCollection;
    private int    $vectorSize;

    public function __construct()
    {
        $host   = config('xavier.qdrant.host', 'localhost');
        $port   = config('xavier.qdrant.port', 6333);
        $apiKey = config('xavier.qdrant.api_key');

        $this->baseUrl  = "http://{$host}:{$port}";
        $this->timeout  = config('xavier.qdrant.timeout', 10);
        $this->headers  = ['Content-Type' => 'application/json'];
        $this->vectorSize = config('xavier.qdrant.vector_size', 768);

        if ($apiKey) {
            $this->headers['api-key'] = $apiKey;
        }

        $this->questionsCollection = config('xavier.qdrant.collections.questions', 'questions_vectors');
        $this->conceptsCollection  = config('xavier.qdrant.collections.concepts', 'concepts_vectors');
    }

    // ─── Collection Management ───────────────────────────────────────────────

    /**
     * Creates the questions collection with 3 named vectors.
     * Safe to call multiple times (skips if already exists).
     */
    public function ensureQuestionsCollection(): bool
    {
        // Check if already exists
        $response = $this->get("/collections/{$this->questionsCollection}");
        if ($response && isset($response['result'])) {
            return true; // Already exists
        }

        $payload = [
            'vectors' => [
                'statement'   => ['size' => $this->vectorSize, 'distance' => 'Cosine'],
                'concept'     => ['size' => $this->vectorSize, 'distance' => 'Cosine'],
                'explanation' => ['size' => $this->vectorSize, 'distance' => 'Cosine'],
            ],
            'hnsw_config' => ['m' => 16, 'ef_construct' => 100],
        ];

        $result = $this->put("/collections/{$this->questionsCollection}", $payload);
        Log::info('[Qdrant] Questions collection created.', ['result' => $result]);
        return (bool) ($result['result'] ?? false);
    }

    /**
     * Creates the concepts collection with a single default vector.
     */
    public function ensureConceptsCollection(): bool
    {
        $response = $this->get("/collections/{$this->conceptsCollection}");
        if ($response && isset($response['result'])) {
            return true;
        }

        $payload = [
            'vectors' => [
                'size'     => $this->vectorSize,
                'distance' => 'Cosine',
            ],
        ];

        $result = $this->put("/collections/{$this->conceptsCollection}", $payload);
        Log::info('[Qdrant] Concepts collection created.', ['result' => $result]);
        return (bool) ($result['result'] ?? false);
    }

    // ─── Questions ───────────────────────────────────────────────────────────

    /**
     * Upserts a question point with 3 named vectors and structured payload.
     *
     * @param int   $questionId
     * @param array $vectors    ['statement' => [...], 'concept' => [...], 'explanation' => [...]]
     * @param array $payload    Structured metadata for filtering
     */
    public function upsertQuestion(int $questionId, array $vectors, array $payload): bool
    {
        $body = [
            'points' => [
                [
                    'id'      => $questionId,
                    'vectors' => $vectors,
                    'payload' => $payload,
                ],
            ],
        ];

        $result = $this->put("/collections/{$this->questionsCollection}/points?wait=true", $body);

        if (!isset($result['status']) || $result['status'] !== 'ok') {
            Log::error('[Qdrant] Failed to upsert question.', [
                'question_id' => $questionId,
                'result'      => $result,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Performs multi-vector hybrid search with RRF fusion.
     *
     * @param array  $queryVectors ['statement' => [...], 'concept' => [...], 'explanation' => [...]]
     * @param array  $filters      Qdrant filter clause (must/should/must_not)
     * @param int    $limit        Number of candidates to return (default: 50 for ReRankService)
     * @param float  $scoreThreshold Minimum score for any prefetch leg
     * @return array [{id, score, payload}]
     */
    public function searchQuestions(
        array $queryVectors,
        array $filters = [],
        int   $limit = 50,
        float $scoreThreshold = 0.45
    ): array {
        $prefetch = [];
 
        // Statement vector — highest recall priority
        if (!empty($queryVectors['statement'])) {
            $prefetch[] = [
                'query'           => ['nearest' => $queryVectors['statement']],
                'using'           => 'statement',
                'limit'           => $limit * 2,
                'score_threshold' => max(0.50, $scoreThreshold),
            ];
        }
 
        // Concept vector — medium priority
        if (!empty($queryVectors['concept'])) {
            $prefetch[] = [
                'query'           => ['nearest' => $queryVectors['concept']],
                'using'           => 'concept',
                'limit'           => $limit * 2,
                'score_threshold' => max(0.50, $scoreThreshold),
            ];
        }

        // Explanation vector — lower priority recall
        if (!empty($queryVectors['explanation'])) {
            $prefetch[] = [
                'query'           => ['nearest' => $queryVectors['explanation']],
                'using'           => 'explanation',
                'limit'           => $limit,
                'score_threshold' => $scoreThreshold,
            ];
        }

        $body = [
            'prefetch' => $prefetch,
            'query'    => ['fusion' => 'rrf'],
            'limit'    => $limit,
            'with_payload' => true,
        ];

        if (!empty($filters)) {
            $body['filter'] = $filters;
        }

        Log::info('[Qdrant] Search body:', ['body_keys' => array_keys($body)]);
        $result = $this->post("/collections/{$this->questionsCollection}/points/query", $body);
        Log::info('[Qdrant] Search result:', ['count' => count($result['result']['points'] ?? [])]);

        return $result['result']['points'] ?? [];
    }

    /**
     * Deletes a question point from Qdrant.
     */
    public function deleteQuestion(int $questionId): bool
    {
        $body = [
            'points' => [$questionId],
        ];

        $result = $this->post(
            "/collections/{$this->questionsCollection}/points/delete?wait=true",
            $body
        );

        return isset($result['result']['status']) && $result['result']['status'] === 'ok';
    }

    // ─── Concepts ────────────────────────────────────────────────────────────

    /**
     * Upserts a concept point with a single vector.
     */
    public function upsertConcept(string $conceptId, array $vector, array $payload): bool
    {
        $body = [
            'points' => [
                [
                    'id'      => abs(crc32($conceptId)), // Qdrant requires uint64 ID
                    'vector'  => $vector,
                    'payload' => array_merge($payload, ['concept_slug' => $conceptId]),
                ],
            ],
        ];

        $result = $this->put("/collections/{$this->conceptsCollection}/points?wait=true", $body);
        return isset($result['result']['status']) && $result['result']['status'] === 'ok';
    }

    /**
     * Searches for the most similar concepts to a query vector.
     *
     * @return array [{id, score, payload}] — payload includes 'concept_slug'
     */
    public function searchConcepts(array $queryVector, int $limit = 5, float $threshold = 0.75): array
    {
        $body = [
            'vector'          => $queryVector,
            'limit'           => $limit,
            'score_threshold' => $threshold,
            'with_payload'    => true,
        ];

        $result = $this->post("/collections/{$this->conceptsCollection}/points/search", $body);
        return $result['result'] ?? [];
    }

    // ─── Health Check ─────────────────────────────────────────────────────────

    public function isHealthy(): bool
    {
        try {
            $result = $this->get('/');
            return isset($result['title']);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── HTTP Helpers ─────────────────────────────────────────────────────────

    private function get(string $path): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get($this->baseUrl . $path);

            return $response->json();
        } catch (\Exception $e) {
            Log::warning("[Qdrant] GET {$path} failed: " . $e->getMessage());
            return null;
        }
    }

    private function post(string $path, array $body): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->post($this->baseUrl . $path, $body);

            if ($response->failed()) {
                Log::error("[Qdrant] POST {$path} failed.", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("[Qdrant] POST {$path} exception: " . $e->getMessage());
            return null;
        }
    }

    private function put(string $path, array $body): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->put($this->baseUrl . $path, $body);

            if ($response->failed()) {
                Log::error("[Qdrant] PUT {$path} failed.", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("[Qdrant] PUT {$path} exception: " . $e->getMessage());
            return null;
        }
    }
}
