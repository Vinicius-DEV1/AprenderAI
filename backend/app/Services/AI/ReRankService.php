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
     * @param  array  $candidates    [{question_id, score, source, payload}]
     * @param  int    $topN          Max results to return (default: 20)
     * @param  array  $intentFilters Optional: Associative array of intents detected
     * @param  int|null $userId      Optional: User ID for proficiency-based boosting
     * @return array  [{question_id, score, composite_score}]
     */
    public function rerank(array $candidates, int $topN = 20, array $intentFilters = [], ?int $userId = null): array
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

        // Xavier 2.0: Fetch user proficiency stats for adaptive boosting
        $weakThemes = [];
        $strongThemes = [];
        if ($userId) {
            $userStat = \App\Models\UserStat::where('user_id', $userId)->first();
            if ($userStat) {
                $weakThemes   = $userStat->weak_themes   ?? [];
                $strongThemes = $userStat->strong_themes ?? [];
            }
        }

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

            // Intent Booster: Se a questão pertencer à matéria/assunto ou tipo buscado, ganha bônus (+0.10 a +0.30)
            $intentBoost = 0.0;
            if (!empty($intentFilters)) {
                if (!empty($intentFilters['subject_id']) && in_array($payload['subject_id'] ?? null, $intentFilters['subject_id'])) {
                    $intentBoost += 0.30;
                }
                if (!empty($intentFilters['topic_id']) && in_array($payload['topic_id'] ?? null, $intentFilters['topic_id'])) {
                    $intentBoost += 0.15;
                }
                if (!empty($intentFilters['type']) && in_array($payload['type'] ?? null, $intentFilters['type'])) {
                    $intentBoost += 0.10;
                }
                if (!empty($intentFilters['organization']) && in_array($payload['organization'] ?? null, $intentFilters['organization'])) {
                    $intentBoost += 0.30;
                }
                if (!empty($intentFilters['institution']) && in_array($payload['institution'] ?? null, $intentFilters['institution'])) {
                    $intentBoost += 0.20;
                }
                // Maximize boost to keep general coherence
                $intentBoost = min(0.50, $intentBoost);
            }

            // Xavier 2.0: Proficiency Boost (Pedagogical Growth)
            // If the user is struggling with this specific topic (weak_themes), 
            // give it a significant boost to encourage practice/growth.
            $proficiencyBoost = 0.0;
            $topicName = $payload['topic'] ?? null;
            if ($topicName) {
                if (in_array($topicName, $weakThemes)) {
                    $proficiencyBoost = 0.25; // High priority: study needed
                } elseif (in_array($topicName, $strongThemes)) {
                    $proficiencyBoost = 0.05; // Low priority: maintenance
                }
            }

            // Weights are applied to unified [0, 1] scales
            $composite = ($vectorScore     * $this->wVector)
                       + ($popularityScore * $this->wPopularity)
                       + ($qualityScore    * $this->wQuality)
                       + ($recencyScore    * $this->wRecency)
                       + $intentBoost
                       + $proficiencyBoost;

            $scored[] = [
                'question_id'     => $qid,
                'vector_score'    => round($vectorScore,     4),
                'composite_score' => round($composite,       4),
                'source'          => $candidate['source'] ?? 'qdrant',
                'details'         => [
                    'vector'     => ['raw' => round($vectorScore, 4),     'weighted' => round($vectorScore * $this->wVector, 4)],
                    'popularity' => ['raw' => round($popularityScore, 4), 'weighted' => round($popularityScore * $this->wPopularity, 4)],
                    'quality'    => ['raw' => round($qualityScore, 4),    'weighted' => round($qualityScore * $this->wQuality, 4)],
                    'recency'    => ['raw' => round($recencyScore, 4),    'weighted' => round($recencyScore * $this->wRecency, 4)],
                    'intent'     => ['raw' => 1.0,                        'weighted' => round($intentBoost, 4)],
                    'proficiency' => ['raw' => 1.0,                       'weighted' => round($proficiencyBoost, 4)],
                ]
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
