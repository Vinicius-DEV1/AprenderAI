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
    private string $filtersCollection;
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
        // Backward-compat: se a chave 'filters' não existir, cai no nome legado 'concepts_vectors'
        $this->filtersCollection   = config('xavier.qdrant.collections.filters', config('xavier.qdrant.collections.concepts', 'concepts_vectors'));
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
            $this->ensurePayloadIndexes(); // Ensure indexes on existing collections too
            return true;
        }

        $payload = [
            'vectors' => [
                // Original 3 vectors
                'statement'    => ['size' => 3072, 'distance' => 'Cosine'],
                'concept'      => ['size' => 3072, 'distance' => 'Cosine'],
                'explanation'  => ['size' => 3072, 'distance' => 'Cosine'],
                // V2: New vectors for richer semantic matching
                'alternatives' => ['size' => 3072, 'distance' => 'Cosine'],
                'skills'       => ['size' => 3072, 'distance' => 'Cosine'],
            ],
            'hnsw_config' => ['m' => 16, 'ef_construct' => 100],
        ];

        $result = $this->put("/collections/{$this->questionsCollection}", $payload);
        Log::info('[Qdrant] Questions collection created (5 named vectors).', ['result' => $result]);

        $this->ensurePayloadIndexes();

        return (bool) ($result['result'] ?? false);
    }

    /**
     * Creates payload indexes on the questions collection for O(log n) filtering.
     * Safe to call multiple times (Qdrant ignores if index already exists).
     */
    public function ensurePayloadIndexes(): void
    {
        $indexes = [
            'subject_id'   => 'keyword',
            'subject_ids'  => 'keyword',
            'topic_id'     => 'keyword',
            'topic_ids'    => 'keyword',
            'organization' => 'keyword',
            'institution'  => 'keyword',
            'type'         => 'keyword',
            'is_active'    => 'bool',
            'year'         => 'integer',
            'difficulty'   => 'keyword',
            'has_explanation' => 'bool',
            'has_image'    => 'bool',
        ];

        foreach ($indexes as $field => $type) {
            $this->put(
                "/collections/{$this->questionsCollection}/index",
                ['field_name' => $field, 'field_schema' => $type]
            );
        }

        Log::info('[Qdrant] Payload indexes ensured for questions collection.');
    }



    /**
     * Ensures the 'filters' collection (Subjects + Topics + Orgs) exists in Qdrant.
     * Backward-compatible: the Qdrant collection name defaults to 'concepts_vectors'
     * if a legacy collection already exists (avoids unnecessary re-indexing).
     */
    public function ensureFiltersCollection(): bool
    {
        $response = $this->get("/collections/{$this->filtersCollection}");
        if ($response && isset($response['result'])) {
            return true; // Already exists
        }

        $payload = [
            'vectors' => [
                'size'     => 3072,
                'distance' => 'Cosine'
            ]
        ];

        $result = $this->put("/collections/{$this->filtersCollection}", $payload);
        Log::info('[Qdrant] Filters (semantic intents) collection created.', ['result' => $result]);
        return (bool) ($result['result'] ?? false);
    }

    /** @deprecated Use ensureFiltersCollection() instead */
    public function ensureConceptsCollection(): bool { return $this->ensureFiltersCollection(); }

    /**
     * Deletes a Qdrant collection entirely.
     * Used by the "Reset Completo" feature to start fresh.
     *
     * @param string $collectionName  Name of the collection to delete.
     * @return bool  True if deletion succeeded or collection didn't exist.
     */
    public function deleteCollection(string $collectionName): bool
    {
        $result = $this->delete("/collections/{$collectionName}");
        Log::info("[Qdrant] Collection '{$collectionName}' deleted.", ['result' => $result]);
        return (bool) ($result['result'] ?? true);
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
        float $scoreThreshold = 0.45
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
     * Upserts a semantic filter entity (Subject, Topic, Organization, Institution).
     * Uses a deterministic uint64 hash of the entity slug as Qdrant ID.
     *
     * @param string $entityId   Unique identifier string (e.g. "subject:42" or "topic:123")
     * @param array  $vector     768/3072-dim Gemini embedding
     * @param array  $payload    Structured metadata (entity_type, name, subject_id, topic_id, etc.)
     */
    public function upsertSemanticEntity(string $entityId, array $vector, array $payload): bool
    {
        $body = [
            'points' => [
                [
                    'id'      => $this->entitySlugToQdrantId($entityId),
                    'vector'  => $vector,
                    'payload' => array_merge($payload, ['entity_slug' => $entityId]),
                ],
            ],
        ];

        $result = $this->put("/collections/{$this->filtersCollection}/points?wait=true", $body);
        $status = $result['status'] ?? ($result['result']['status'] ?? null);

        if (!in_array($status, ['ok', 'completed', 'acknowledged'])) {
            Log::error('[Qdrant] Failed to upsert semantic entity.', [
                'entity_id' => $entityId,
                'result'    => $result,
                'body'      => $body
            ]);
            return false;
        }

        return true;
    }

    /** @deprecated Use upsertSemanticEntity() instead */
    public function upsertConcept(string $conceptId, array $vector, array $payload): bool
    {
        return $this->upsertSemanticEntity($conceptId, $vector, $payload);
    }

    /**
     * Converts any entity slug (string) into a deterministic uint64 ID for Qdrant.
     * Uses the first 15 hex chars of SHA-256 (60 bits) for collision safety.
     */
    private function entitySlugToQdrantId(string $slug): int
    {
        $hash = hash('sha256', $slug);
        return intval(substr($hash, 0, 15), 16);
    }

    /** @deprecated Use entitySlugToQdrantId() instead */
    private function conceptSlugToQdrantId(string $slug): int
    {
        return $this->entitySlugToQdrantId($slug);
    }

    /**
     * Searches for the most similar semantic filter entities (Subjects, Topics, Orgs, Insts)
     * to a query vector in the filters collection.
     *
     * @return array [{id, score, payload}] — payload includes 'entity_type', 'name', etc.
     */
    public function searchIntents(array $queryVector, int $limit = 5, float $threshold = 0.45): array
    {
        $body = [
            'vector'          => $queryVector,
            'limit'           => $limit,
            'score_threshold' => $threshold,
            'with_payload'    => true,
        ];

        $result = $this->post("/collections/{$this->filtersCollection}/points/search", $body);
        return $result['result'] ?? [];
    }

    /** @deprecated Use searchIntents() instead */
    public function searchConcepts(array $queryVector, int $limit = 5, float $threshold = 0.45): array
    {
        return $this->searchIntents($queryVector, $limit, $threshold);
    }

    /**
     * Get detailed information about a collection, including point count.
     */
    public function getCollectionInfo(string $name): ?array
    {
        $response = $this->get("/collections/{$name}");
        return $response['result'] ?? null;
    }

    /**
     * Checks if the Qdrant database is using the specified pipeline version.
     * It fetches 1 random point and checks its payload.
     */
    public function checkIndexVersion(string $collection, string $expectedVersion): array
    {
        try {
            // Buscamos 1 ponto com scroll (sem filtro) para analisar o payload
            $body = [
                'limit'        => 1,
                'with_payload' => true,
                'with_vector'  => false,
            ];

            $result = $this->post("/collections/{$collection}/points/scroll", $body);
            $points = $result['result']['points'] ?? [];

            if (empty($points)) {
                return ['status' => 'empty', 'message' => 'Coleção está vazia.'];
            }

            $point = $points[0];
            $actualVersion = $point['payload']['pipeline_version'] ?? 'legacy';

            $isMatch = ($actualVersion === $expectedVersion);
            
            return [
                'status'   => $isMatch ? 'ok' : 'outdated',
                'expected' => $expectedVersion,
                'actual'   => $actualVersion,
                'message'  => $isMatch 
                                ? "O Qdrant está atualizado ({$expectedVersion})." 
                                : "Atenção: Qdrant está usando a versão '{$actualVersion}' e o sistema espera '{$expectedVersion}'. É necessário Re-indexar."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Não foi possível verificar a versão do índice. ' . $e->getMessage()];
        }
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
