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
     * @param  array  $queryVectors      ['statement'=>[...], 'concept'=>[...], 'explanation'=>[...], 'alternatives'=>[...], 'skills'=>[...]]
     * @param  string[] $expandedConceptIds  Concept slugs from QueryExpansionService
     * @param  array  $sqlFilters        Optional SQL filters: subject, topic, type, difficulty, year, has_explanation, word_count_max
     * @param  int    $limit             Candidate limit for Qdrant (pre-ReRank)
     * @return array  [{question_id, score, source, payload}]
     */
    public function search(
        array  $queryVectors,
        array  $expandedConceptIds = [],
        array  $sqlFilters = [],
        int    $limit = 50,
        array  $excludedConceptIds = [],
        array  $expandedSubjectIds = [],
        array  $expandedTopicIds   = []
    ): array {
        // Build Qdrant filter from expanded concept IDs + SQL filters
        $qdrantFilter = $this->buildQdrantFilter($expandedConceptIds, $sqlFilters, $excludedConceptIds, $expandedSubjectIds, $expandedTopicIds);

        // Try Qdrant multi-vector search
        try {
            $results = $this->qdrant->searchQuestions(
                $queryVectors,
                $qdrantFilter,
                $limit,
                (float) \App\Models\Configuration::get('xavier_search_threshold', config('xavier.embeddings.search_threshold', 0.40))
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
    private function buildQdrantFilter(array $conceptIds, array $sqlFilters, array $excludedConceptIds = [], array $expandedSubjectIds = [], array $expandedTopicIds = []): array
    {
        $must = [];

        // Filter by subject — use subject_ids (array) for multi-discipline support
        // Falls back to subject_id for backward compatibility with legacy-indexed points.
        if (!empty($sqlFilters['subject'])) {
            $ids = is_array($sqlFilters['subject'])
                ? array_map('intval', $sqlFilters['subject'])
                : [(int) $sqlFilters['subject']];
            $must[] = [
                'key'   => 'subject_ids',
                'match' => ['any' => $ids],
            ];
        }

        // Filter by topic — use topic_ids (array) for multi-topic support
        if (!empty($sqlFilters['topic'])) {
            $ids = is_array($sqlFilters['topic'])
                ? array_map('intval', $sqlFilters['topic'])
                : [(int) $sqlFilters['topic']];
            $must[] = [
                'key'   => 'topic_ids',
                'match' => ['any' => $ids],
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
        // Semantic Expansion (String Slugs) - Legacy
        $should = [];
        if (!empty($conceptIds)) {
            foreach ($conceptIds as $slug) {
                $should[] = [
                    'key' => 'concepts',
                    'match' => ['value' => $slug]
                ];
            }
        }

        /* 
         * DO NOT use Qdrant's 'should' clause for subject_ids or topic_ids!
         * Qdrant's SHOULD clause acts as a HARD FILTER (Must match at least one).
         * If we add expanded subjects here, it will permanently filter out any question
         * that doesn't explicitly have these tags, destroying the mathematical
         * vector recall for questions that are semantically related but untagged.
         * The intent boosting is handled gracefully by ReRankService instead.
         */

        // Filter by difficulty
        if (!empty($sqlFilters['difficulty'])) {
            $must[] = [
                'key'   => 'difficulty',
                'match' => ['value' => $sqlFilters['difficulty']],
            ];
        }

        // Filter by Year (Range support)
        if (!empty($sqlFilters['year'])) {
            $year = (int) $sqlFilters['year'];
            $op   = $sqlFilters['year_operator'] ?? '=';

            if ($op === '=') {
                $must[] = ['key' => 'year', 'match' => ['value' => $year]];
            } else {
                $range = ['key' => 'year', 'range' => []];
                if ($op === '>=') $range['range']['gte'] = $year;
                if ($op === '<=') $range['range']['lte'] = $year;
                if ($op === '>')  $range['range']['gt']  = $year;
                if ($op === '<')  $range['range']['lt']  = $year;
                $must[] = $range;
            }
        }

        // Filter by organization (Banca)
        if (!empty($sqlFilters['organization'])) {
            if (is_array($sqlFilters['organization'])) {
                $must[] = [
                    'key'   => 'organization',
                    'match' => ['any' => $sqlFilters['organization']],
                ];
            } else {
                $must[] = [
                    'key'   => 'organization',
                    'match' => ['value' => $sqlFilters['organization']],
                ];
            }
        }

        // Filter by institution (Órgão)
        if (!empty($sqlFilters['institution'])) {
            if (is_array($sqlFilters['institution'])) {
                $must[] = [
                    'key'   => 'institution',
                    'match' => ['any' => $sqlFilters['institution']],
                ];
            } else {
                $must[] = [
                    'key'   => 'institution',
                    'match' => ['value' => $sqlFilters['institution']],
                ];
            }
        }

        // --- EXCLUSION FILTERS (must_not) ---
        $mustNot = [];

        // Exclude by organization
        if (!empty($sqlFilters['exclude_organization'])) {
            if (is_array($sqlFilters['exclude_organization'])) {
                $mustNot[] = [
                    'key'   => 'organization',
                    'match' => ['any' => $sqlFilters['exclude_organization']],
                ];
            } else {
                $mustNot[] = [
                    'key'   => 'organization',
                    'match' => ['value' => $sqlFilters['exclude_organization']],
                ];
            }
        }

        // Exclude by institution
        if (!empty($sqlFilters['exclude_institution'])) {
            if (is_array($sqlFilters['exclude_institution'])) {
                $mustNot[] = [
                    'key'   => 'institution',
                    'match' => ['any' => $sqlFilters['exclude_institution']],
                ];
            } else {
                $mustNot[] = [
                    'key'   => 'institution',
                    'match' => ['value' => $sqlFilters['exclude_institution']],
                ];
            }
        }

        // Exclude by Type (ENEM/Concurso)
        if (!empty($sqlFilters['exclude_type'])) {
            $mustNot[] = [
                'key'   => 'type',
                'match' => ['value' => $sqlFilters['exclude_type']],
            ];
        }

        // Exclude by Subject ID — match against subject_ids array for V2 multi-subject support
        if (!empty($sqlFilters['exclude_subject_id'])) {
            $mustNot[] = [
                'key'   => 'subject_ids',
                'match' => ['any' => array_map('intval', (array) $sqlFilters['exclude_subject_id'])],
            ];
        }

        // Exclude by Topic ID — match against topic_ids array for V2 multi-topic support
        if (!empty($sqlFilters['exclude_topic_id'])) {
            $mustNot[] = [
                'key'   => 'topic_ids',
                'match' => ['any' => array_map('intval', (array) $sqlFilters['exclude_topic_id'])],
            ];
        }

        // Filter: only questions with explanation/resolution (V2 field)
        if (!empty($sqlFilters['has_explanation'])) {
            $must[] = [
                'key'   => 'has_explanation',
                'match' => ['value' => true],
            ];
        }

        // Filter: word count ceiling — avoids very long-winded questions (V2 field)
        if (!empty($sqlFilters['word_count_max'])) {
            $must[] = [
                'key'   => 'word_count',
                'range' => ['lte' => (int) $sqlFilters['word_count_max']],
            ];
        }

        // Exclude by Concepts (Negative Semantic Intent)
        if (!empty($excludedConceptIds)) {
            foreach ($excludedConceptIds as $slug) {
                $mustNot[] = [
                    'key'   => 'concepts',
                    'match' => ['value' => $slug]
                ];
            }
        }

        // Only active, approved questions -- always
        $must[] = [
            'key'   => 'is_active',
            'match' => ['value' => true],
        ];

        $filter = [];
        if (!empty($must))    $filter['must'] = $must;
        if (!empty($should))  $filter['should'] = $should;
        if (!empty($mustNot)) $filter['must_not'] = $mustNot;

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
        if (!empty($sqlFilters['year'])) {
            $year = (int) $sqlFilters['year'];
            $op   = $sqlFilters['year_operator'] ?? '=';
            $query->where('year', $op, $year);
        }
        if (!empty($sqlFilters['organization'])) {
            $orgs = (array) $sqlFilters['organization'];
            $query->whereIn('organization', $orgs);
        }
        if (!empty($sqlFilters['institution'])) {
            $insts = (array) $sqlFilters['institution'];
            $query->whereIn('institution', $insts);
        }

        // --- Exclusions ---
        if (!empty($sqlFilters['exclude_subject_id'])) {
            $query->whereDoesntHave('subjects', function ($q) use ($sqlFilters) {
                $q->whereIn('subjects.id', (array) $sqlFilters['exclude_subject_id']);
            });
        }
        if (!empty($sqlFilters['exclude_topic_id'])) {
            $query->whereDoesntHave('topics', function ($q) use ($sqlFilters) {
                $q->whereIn('topics.id', (array) $sqlFilters['exclude_topic_id']);
            });
        }
        if (!empty($sqlFilters['exclude_org'])) {
            $query->whereNotIn('organization', (array) $sqlFilters['exclude_org']);
        }
        if (!empty($sqlFilters['exclude_inst'])) {
            $query->whereNotIn('institution', (array) $sqlFilters['exclude_inst']);
        }
        if (!empty($sqlFilters['exclude_type'])) {
            $query->where('tipo_concurso', '!=', $sqlFilters['exclude_type']);
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
