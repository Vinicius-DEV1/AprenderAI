<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * ReRankService
 *
 * Reranks up to 200 Qdrant/SQL hybrid search candidates into a final result list
 * using a composite pedagogical score. Zero LLM API cost. Zero MySQL queries.
 *
 * Score formula:
 *   composite = (vector_score × w_vector)
 *             + (popularity_score × w_popularity)
 *             + (quality_score × w_quality)
 *             + (recency_score × w_recency)
 *             + intent_boost
 *             + proficiency_boost
 *
 * Result trimming:
 *   - Max 100 results returned
 *   - Absolute cutoff: composite < xavier_cutoff_min (default 0.20)
 *   - Relative cutoff: composite < best_score × (1 - xavier_cutoff_drop_fraction) (default 40% drop)
 *
 * Weights are configured in config('xavier.search.rerank_weights').
 * Cutoffs are configured via Configuration::get('xavier_cutoff_*').
 */
class ReRankService
{
    private float $wVector;
    private float $wPopularity;
    private float $wQuality;
    private float $wRecency;
    private float $pBoost;
    private float $iBoost;

    // Difficulty → pedagogical quality score mapping
    private const DIFFICULTY_QUALITY = [
        'Médio'  => 1.00,
        'Difícil' => 0.85,
        'Fácil'  => 0.70,
    ];

    // Years considered "recent" for normalization (current year range)
    private const MIN_YEAR = 2010;
    private const MAX_YEAR = 2026;

    public function __construct()
    {
        $weightsJson = \App\Models\Configuration::get('xavier_rerank_weights');
        $weights = $weightsJson ? json_decode($weightsJson, true) : config('xavier.search.rerank_weights', []);
        
        $this->wVector     = (float) ($weights['vector']     ?? 0.60);
        $this->wPopularity = (float) ($weights['popularity'] ?? 0.15);
        $this->wQuality    = (float) ($weights['quality']    ?? 0.15);
        $this->wRecency    = (float) ($weights['recency']    ?? 0.10);
        
        $userWeights = $weights['user_profile'] ?? [];
        $this->pBoost      = (float) ($userWeights['proficiency_boost'] ?? 0.25);
        $this->iBoost      = (float) ($userWeights['intent_boost']      ?? 0.30);
    }

    /**
     * Rerank candidates and return a smart-trimmed result list.
     *
     * - Maximum 100 results
     * - Absolute cutoff: composite < xavier_cutoff_min (default 0.20)
     * - Relative cutoff: composite drops more than xavier_cutoff_drop_fraction (default 40%) from best
     *
     * @param  array  $candidates    [{question_id, score, source, payload}]
     * @param  int    $topN          Hard cap on results (default: 100)
     * @param  array  $intentFilters Optional: Associative array of intents detected
     * @param  int|null $userId      Optional: User ID for proficiency-based boosting
     * @return array  [{question_id, score, composite_score, payload, details}]
     */
    public function rerank(array $candidates, int $topN = 100, array $intentFilters = [], ?int $userId = null): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Normalize vector scores relative to the best candidate in this batch.
        // This handles the transition from Cosine (0-1) to RRF (0-0.05) seamlessly.
        $maxVectorScore = max(array_column($candidates, 'score'));

        // Xavier 2.0: Fetch user proficiency stats for adaptive boosting
        $weakThemes   = [];
        $strongThemes = [];
        if ($userId) {
            $userStat = \App\Models\UserStat::where('user_id', $userId)->first();
            if ($userStat) {
                $weakThemes   = $userStat->weak_themes   ?? [];
                $strongThemes = $userStat->strong_themes ?? [];
            }
        }

        // Score each candidate — all data comes from the Qdrant payload (zero MySQL)
        $scored = [];
        foreach ($candidates as $candidate) {
            $qid     = $candidate['question_id'];
            $payload = $candidate['payload'] ?? [];

            // Vector score normalized [0, 1]
            $vectorScore = $this->normalizeVector((float) $candidate['score'], (float) $maxVectorScore);

            // Popularity: use answer_count from Qdrant payload (avoids MySQL query)
            $popularityRaw = (int) ($payload['answer_count'] ?? 0);

            // Quality from difficulty
            $qualityScore  = $this->qualityScore($payload['difficulty'] ?? null);

            // Recency from year
            $recencyScore  = $this->recencyScore((int) ($payload['year'] ?? 0));

            // Intent Booster: bonus if question belongs to detected subject/org/topic/type
            $intentBoost = 0.0;
            if (!empty($intentFilters)) {
                // Support both subject_id (legacy) and subject_ids (multi-subject V2)
                $payloadSubjectIds = !empty($payload['subject_ids'])
                    ? $payload['subject_ids']
                    : ($payload['subject_id'] ? [$payload['subject_id']] : []);

                $payloadTopicIds = !empty($payload['topic_ids'])
                    ? $payload['topic_ids']
                    : ($payload['topic_id'] ? [$payload['topic_id']] : []);

                // EXACT Matches (Full weight)
                if (!empty($intentFilters['exact_subject_id'])) {
                    $overlap = array_intersect((array) $intentFilters['exact_subject_id'], $payloadSubjectIds);
                    if (!empty($overlap)) $intentBoost += $this->iBoost; // +0.30
                }
                
                // EXPANDED Matches (Penalty/Discounted weight)
                elseif (!empty($intentFilters['expanded_subject_id'])) {
                    $overlap = array_intersect((array) $intentFilters['expanded_subject_id'], $payloadSubjectIds);
                    if (!empty($overlap)) $intentBoost += $this->iBoost * 0.25; // +0.075
                }

                // EXACT Topics
                if (!empty($intentFilters['exact_topic_id'])) {
                    $overlap = array_intersect((array) $intentFilters['exact_topic_id'], $payloadTopicIds);
                    if (!empty($overlap)) $intentBoost += $this->iBoost * 0.5; // +0.15
                }
                
                // EXPANDED Topics
                elseif (!empty($intentFilters['expanded_topic_id'])) {
                    $overlap = array_intersect((array) $intentFilters['expanded_topic_id'], $payloadTopicIds);
                    if (!empty($overlap)) $intentBoost += $this->iBoost * 0.1; // +0.03
                }

                if (!empty($intentFilters['type']) && in_array($payload['type'] ?? null, $intentFilters['type'])) {
                    $intentBoost += 0.10;
                }
                if (!empty($intentFilters['organization']) && in_array($payload['organization'] ?? null, $intentFilters['organization'])) {
                    $intentBoost += $this->iBoost;
                }
                if (!empty($intentFilters['institution']) && in_array($payload['institution'] ?? null, $intentFilters['institution'])) {
                    $intentBoost += $this->iBoost * 0.6;
                }
                $intentBoost = min(0.50, $intentBoost);
            }

            // Xavier 2.0: Proficiency Boost (Pedagogical Growth)
            $proficiencyBoost = 0.0;
            $topicName = $payload['topic'] ?? ($payload['topic_names'][0] ?? null);
            if ($topicName) {
                if (in_array($topicName, $weakThemes)) {
                    $proficiencyBoost = $this->pBoost;   // High priority: study needed
                } elseif (in_array($topicName, $strongThemes)) {
                    $proficiencyBoost = 0.05;            // Low priority: maintenance
                }
            }

            // Composite score
            $composite = ($vectorScore  * $this->wVector)
                       + ($this->normalizePopularity($popularityRaw) * $this->wPopularity)
                       + ($qualityScore * $this->wQuality)
                       + ($recencyScore * $this->wRecency)
                       + $intentBoost
                       + $proficiencyBoost;

            $scored[] = [
                'question_id'     => $qid,
                'vector_score'    => round($vectorScore, 4),
                'composite_score' => round($composite,   4),
                'source'          => $candidate['source'] ?? 'qdrant',
                'payload'         => $payload,
                'details'         => [
                    'vector'      => ['raw' => round($vectorScore, 4),     'weighted' => round($vectorScore * $this->wVector, 4)],
                    'popularity'  => ['raw' => $popularityRaw,             'weighted' => round($this->normalizePopularity($popularityRaw) * $this->wPopularity, 4)],
                    'quality'     => ['raw' => round($qualityScore, 4),    'weighted' => round($qualityScore * $this->wQuality, 4)],
                    'recency'     => ['raw' => round($recencyScore, 4),    'weighted' => round($recencyScore * $this->wRecency, 4)],
                    'intent'      => ['raw' => 1.0,                        'weighted' => round($intentBoost, 4)],
                    'proficiency' => ['raw' => 1.0,                        'weighted' => round($proficiencyBoost, 4)],
                ],
            ];
        }

        // Sort descending by composite score
        usort($scored, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        // ── Adaptive Score Cutoff ────────────────────────────────────────────────
        // Trims low-quality results without forcing a fixed count.
        // Config keys: xavier_cutoff_min (default: 0.20), xavier_cutoff_drop_fraction (default: 0.40)
        $cutoffMin          = (float) \App\Models\Configuration::get('xavier_cutoff_min', 0.20);
        $cutoffDropFraction = (float) \App\Models\Configuration::get('xavier_cutoff_drop_fraction', 0.40);
        $bestScore          = $scored[0]['composite_score'] ?? 0.0;
        $relativeFloor      = $bestScore * (1.0 - $cutoffDropFraction);
        $absoluteFloor      = max($cutoffMin, $relativeFloor);

        $trimmed = array_filter($scored, fn($item) => $item['composite_score'] >= $absoluteFloor);

        // Apply hard cap
        $result = array_slice(array_values($trimmed), 0, $topN);

        Log::info('[Xavier][ReRank] Reranked ' . count($candidates) . ' candidates → ' . count($result) . ' returned (cutoff floor: ' . round($absoluteFloor, 3) . ').');

        return $result;
    }

    // ─── Normalizers ─────────────────────────────────────────────────────────

    private function normalizeVector(float $score, float $max): float
    {
        if ($max <= 0) return 0.0;
        return min(1.0, $score / $max);
    }

    /**
     * Normalize answer_count using a logarithmic scale.
     * This prevents extremely popular questions from completely dominating.
     * log(1001)/log(1001) = 1.0 (100% at 1000+ answers)
     */
    private function normalizePopularity(int $count): float
    {
        if ($count <= 0) return 0.0;
        return min(1.0, log($count + 1) / log(1001));
    }

    private function qualityScore(?string $difficulty): float
    {
        return self::DIFFICULTY_QUALITY[$difficulty] ?? 0.75;
    }

    private function recencyScore(int $year): float
    {
        if ($year < self::MIN_YEAR) return 0.0;
        $range = self::MAX_YEAR - self::MIN_YEAR;
        return min(1.0, ($year - self::MIN_YEAR) / max($range, 1));
    }
}
