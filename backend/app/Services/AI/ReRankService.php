<?php

namespace App\Services\AI;

use App\Models\UserQuestionAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReRankService
 *
 * Reranks up to 50 Qdrant/SQL hybrid search candidates into a top-N list
 * using a composite pedagogical score. Zero LLM API cost.
 *
 * Score formula:
 *   composite = (vector_score × w_vector)
 *             + (popularity_score × w_popularity)
 *             + (quality_score × w_quality)
 *             + (recency_score × w_recency)
 *
 * Weights are configured in config('xavier.search.rerank_weights').
 */
class ReRankService
{
    private float $wVector;
    private float $wPopularity;
    private float $wQuality;
    private float $wRecency;

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
    }

    /**
     * Rerank candidates and return top $topN question IDs ordered by composite score.
     *
     * @param  array  $candidates   [{question_id, score, source, payload}]
     * @param  int    $topN         Max results to return (default: 20)
     * @return array  [{question_id, score, composite_score}]
     */
    public function rerank(array $candidates, int $topN = 20): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Fetch popularity counts in a single query
        $questionIds   = array_column($candidates, 'question_id');
        $popularityCounts = UserQuestionAnswer::whereIn('question_id', $questionIds)
            ->groupBy('question_id')
            ->pluck(DB::raw('COUNT(*) as cnt'), 'question_id')
            ->toArray();

        $maxVectorScore = !empty($candidates) ? max(array_column($candidates, 'score')) : 1.0;
        $maxPopularity  = !empty($popularityCounts) ? max($popularityCounts) : 1;

        // Score each candidate
        $scored = [];
        foreach ($candidates as $candidate) {
            $qid     = $candidate['question_id'];
            $payload = $candidate['payload'] ?? [];

            // Vector score is normalized relative to the best candidate in this batch
            // This handles the transition from Cosine (0-1) to RRF (0-0.05) seamlessly.
            $vectorScore     = $this->normalizeVector((float) $candidate['score'], (float) $maxVectorScore);
            $popularityScore = $this->normalizePopularity($popularityCounts[$qid] ?? 0, $maxPopularity);
            $qualityScore    = $this->qualityScore($payload['difficulty'] ?? null);
            $recencyScore    = $this->recencyScore((int) ($payload['year'] ?? 0));

            // Weights are applied to unified [0, 1] scales
            $composite = ($vectorScore     * $this->wVector)
                       + ($popularityScore * $this->wPopularity)
                       + ($qualityScore    * $this->wQuality)
                       + ($recencyScore    * $this->wRecency);

            $scored[] = [
                'question_id'     => $qid,
                'vector_score'    => round($vectorScore,     4),
                'composite_score' => round($composite,       4),
                'source'          => $candidate['source'] ?? 'qdrant',
            ];
        }

        // Sort descending by composite score
        usort($scored, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        $result = array_slice($scored, 0, $topN);

        Log::info('[Xavier][ReRank] Reranked ' . count($candidates) . ' candidates → top ' . count($result) . '.');

        return $result;
    }

    // ─── Normalizers ─────────────────────────────────────────────────────────

    private function normalizeVector(float $score, float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }
        return min(1.0, $score / $max);
    }

    private function normalizePopularity(int $count, int $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }
        return min(1.0, $count / $max);
    }

    private function qualityScore(?string $difficulty): float
    {
        return self::DIFFICULTY_QUALITY[$difficulty] ?? 0.75;
    }

    private function recencyScore(int $year): float
    {
        if ($year < self::MIN_YEAR) {
            return 0.0;
        }
        $range = self::MAX_YEAR - self::MIN_YEAR;
        return min(1.0, ($year - self::MIN_YEAR) / max($range, 1));
    }
}
