<?php

namespace App\Services\AI;

use App\Models\Question;
use Illuminate\Support\Facades\Log;

/**
 * HybridSearchService
 *
 * Orchestrates the Qdrant multi-vector search with optional Qdrant filter
 * built from expanded concept IDs and SQL metadata filters.
 *
 * If Qdrant is unreachable or returns no results, falls back to a SQL query
 * against MySQL (the source of truth) to ensure search never returns empty.
 */
class HybridSearchService
{
    public function __construct(
        private QdrantService $qdrant
    ) {}

    /**
     * Perform hybrid search.
     *
     * @param  array  $queryVectors      ['statement'=>[...], 'concept'=>[...], 'explanation'=>[...]]
     * @param  string[] $expandedConceptIds  Concept slugs from QueryExpansionService
     * @param  array  $sqlFilters        Optional SQL filters: subject, topic, type, difficulty, year
     * @param  int    $limit             Candidate limit for Qdrant (pre-ReRank)
     * @return array  [{question_id, score, source, payload}]
     */
    public function search(
        array  $queryVectors,
        array  $expandedConceptIds = [],
        array  $sqlFilters = [],
        int    $limit = 50
    ): array {
        // Build Qdrant filter from expanded concept IDs + SQL filters
        $qdrantFilter = $this->buildQdrantFilter($expandedConceptIds, $sqlFilters);

        // Try Qdrant multi-vector search
        try {
            $results = $this->qdrant->searchQuestions(
                $queryVectors,
                $qdrantFilter,
                $limit,
                config('xavier.embeddings.concept_detection_threshold', 0.55)
            );

            if (!empty($results)) {
                Log::info('[Xavier][HybridSearch] Qdrant returned ' . count($results) . ' candidates.');
                return $this->formatQdrantResults($results);
            }

            Log::info('[Xavier][HybridSearch] Qdrant returned 0 results — falling back to SQL.');
        } catch (\Exception $e) {
            Log::warning('[Xavier][HybridSearch] Qdrant unreachable — falling back to SQL.', [
                'error' => $e->getMessage(),
            ]);
        }

        // SQL Fallback
        return $this->sqlFallback($sqlFilters, $limit);
    }

    /**
     * Build the Qdrant filter clause from concept IDs and SQL-style metadata filters.
     */
    private function buildQdrantFilter(array $conceptIds, array $sqlFilters): array
    {
        $must = [];

        // Filter by subject
        if (!empty($sqlFilters['subject'])) {
            $must[] = [
                'key'   => 'subject_id',
                'match' => ['value' => (int) $sqlFilters['subject']],
            ];
        }

        // Filter by topic
        if (!empty($sqlFilters['topic'])) {
            $must[] = [
                'key'   => 'topic_id',
                'match' => ['value' => (int) $sqlFilters['topic']],
            ];
        }

        // Filter by question type (enem / concurso)
        if (!empty($sqlFilters['type'])) {
            $must[] = [
                'key'   => 'type',
                'match' => ['value' => $sqlFilters['type']],
            ];
        }
        
        // Filter by concepts (Semantic Expansion)
        // We use 'should' instead of 'must' to allow results that match the vector
        // even if they don't have the explicit concept tagged, but boost those with concepts.
        $should = [];
        if (!empty($conceptIds)) {
            foreach ($conceptIds as $slug) {
                $should[] = [
                    'key' => 'concepts',
                    'match' => ['value' => $slug]
                ];
            }
        }

        // Filter by difficulty
        if (!empty($sqlFilters['difficulty'])) {
            $must[] = [
                'key'   => 'difficulty',
                'match' => ['value' => $sqlFilters['difficulty']],
            ];
        }

        // Only active, approved questions -- always
        $must[] = [
            'key'   => 'is_active',
            'match' => ['value' => true],
        ];

        $filter = [];
        if (!empty($must)) $filter['must'] = $must;
        if (!empty($should)) $filter['should'] = $should;

        return $filter;
    }

    /**
     * Format raw Qdrant result points into a unified structure.
     */
    private function formatQdrantResults(array $points): array
    {
        return array_map(fn($point) => [
            'question_id' => (int) $point['id'],
            'score'       => (float) ($point['score'] ?? 0.0),
            'source'      => 'qdrant',
            'payload'     => $point['payload'] ?? [],
        ], $points);
    }

    /**
     * SQL fallback: returns published questions applying available metadata filters.
     * Returns minimal metadata (no vector score).
     */
    private function sqlFallback(array $sqlFilters, int $limit): array
    {
        $query = Question::published()
            ->where('tipo_questao', '!=', 'Redação')
            ->select('id', 'difficulty', 'year', 'created_at');

        if (!empty($sqlFilters['subject'])) {
            $query->filterBySubject($sqlFilters['subject']);
        }
        if (!empty($sqlFilters['topic'])) {
            $query->filterByTopic($sqlFilters['topic']);
        }
        if (!empty($sqlFilters['type'])) {
            $query->filterByType($sqlFilters['type']);
        }
        if (!empty($sqlFilters['difficulty'])) {
            $query->where('difficulty', $sqlFilters['difficulty']);
        }
        if (!empty($sqlFilters['keyword'])) {
            $query->where(function ($q) use ($sqlFilters) {
                $q->where('statement', 'like', '%' . $sqlFilters['keyword'] . '%')
                  ->orWhere('explanation', 'like', '%' . $sqlFilters['keyword'] . '%');
            });
        }

        $questions = $query->orderByDesc('created_at')->limit($limit)->get();

        Log::info('[Xavier][HybridSearch] SQL fallback returned ' . $questions->count() . ' results.');

        return $questions->map(fn($q) => [
            'question_id' => $q->id,
            'score'       => 0.0,
            'source'      => 'sql_fallback',
            'payload'     => [
                'difficulty' => $q->difficulty,
                'year'       => (int) ($q->year ?? 0),
            ],
        ])->toArray();
    }
}
