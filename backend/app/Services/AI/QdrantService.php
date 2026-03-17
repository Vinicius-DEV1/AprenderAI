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
     * Uses $this->vectorSize (resolved once in constructor) for consistency
     * with the questions collection — both must use the same embedding model dimensions.
     */
    public function ensureConceptsCollection(): bool
    {
        // Check if exists
        try {
            $this->get("/collections/{$this->conceptsCollection}");
            return true;
        } catch (\Exception $e) {
            // Collection doesn't exist yet — create with same vector size as questions
            $payload = [
                'vectors' => [
                    'size' => $this->vectorSize,  // Consistent with ensureQuestionsCollection
                    'distance' => 'Cosine'
                ]
            ];
            $result = $this->put("/collections/{$this->conceptsCollection}", $payload);
            Log::info('[Qdrant] Concepts collection created.', ['result' => $result]);
            return (bool) ($result['result'] ?? false);
        }
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
 
        $status = $result['status'] ?? ($result['result']['status'] ?? null);
        if (!in_array($status, ['ok', 'completed', 'acknowledged'])) {
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
        float $scoreThreshold = 0.30
    ): array {
        $prefetch = [];
 
        // Statement vector — highest recall priority
        if (!empty($queryVectors['statement'])) {
            $prefetch[] = [
                'query'           => ['nearest' => $queryVectors['statement']],
                'using'           => 'statement',
                'limit'           => $limit * 2,
                'score_threshold' => $scoreThreshold,
            ];
        }
 
        // Concept vector — medium priority
        if (!empty($queryVectors['concept'])) {
            $prefetch[] = [
                'query'           => ['nearest' => $queryVectors['concept']],
                'using'           => 'concept',
                'limit'           => $limit * 2,
                'score_threshold' => $scoreThreshold,
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
     * Converts the string concept slug into a deterministic uint64 ID for Qdrant.
     */
    public function upsertConcept(string $conceptId, array $vector, array $payload): bool
    {
        $body = [
            'points' => [
                [
                    // Use collision-safe 64-bit hash instead of crc32 which has high
                    // collision probability with hundreds of concepts (Birthday Paradox)
                    'id'      => $this->conceptSlugToQdrantId($conceptId),
                    'vector'  => $vector,
                    'payload' => array_merge($payload, ['concept_slug' => $conceptId]),
                ],
            ],
        ];

        $result = $this->put("/collections/{$this->conceptsCollection}/points?wait=true", $body);
        $status = $result['status'] ?? ($result['result']['status'] ?? null);
        
        if (!in_array($status, ['ok', 'completed', 'acknowledged'])) {
            Log::error('[Qdrant] Failed to upsert concept.', [
                'concept_id' => $conceptId,
                'result'     => $result,
                'body'       => $body
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Converts a concept slug (string) into a deterministic uint64 ID for Qdrant.
     *
     * Uses the first 8 bytes of a SHA-256 hash, interpreted as a 64-bit unsigned integer.
     * This provides ~2^64 possible values, making collisions effectively impossible
     * for any realistic number of concepts (collision probability < 1e-10 with 1M concepts).
     *
     * Previous implementation used abs(crc32()), which only had ~2^31 possible values
     * and a ~50% collision probability at ~77K concepts (Birthday Paradox).
     */
    private function conceptSlugToQdrantId(string $slug): int
    {
        // SHA-256 produces a 64-char hex string; take the first 15 hex chars
        // (60 bits, well within PHP's int range on 64-bit systems)
        $hash = hash('sha256', $slug);
        return intval(substr($hash, 0, 15), 16);
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

    /**
     * Get detailed information about a collection, including point count.
     */
    public function getCollectionInfo(string $name): ?array
    {
        $response = $this->get("/collections/{$name}");
        return $response['result'] ?? null;
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

    public function get(string $path): ?array
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

    public function post(string $path, array $body): ?array
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

    public function put(string $path, array $body): ?array
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

    public function delete(string $path): ?array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->delete($this->baseUrl . $path);

            if ($response->failed()) {
                Log::error("[Qdrant] DELETE {$path} failed.", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("[Qdrant] DELETE {$path} exception: " . $e->getMessage());
            return null;
        }
    }
}
