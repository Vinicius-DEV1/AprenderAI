<?php

namespace App\Services\AI;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QueryExpansionService
 *
 * Expands detected Subject and Topic IDs via co-occurrence analysis on real questions.
 *
 * Strategy (replaces the legacy concept_relations knowledge graph):
 *
 *  1. For each detected SUBJECT:
 *     – Find Topics that most frequently appear in questions ALSO tagged with this Subject.
 *     – These co-occurring Topics act as semantic "children" of the Subject.
 *
 *  2. For each detected TOPIC:
 *     – Find Subjects that most frequently co-occur with this Topic.
 *     – Then expand to sibling Topics that share those Subjects.
 *
 * Result: a richer set of subject_ids and topic_ids used by HybridSearchService
 * to boost relevance during the ReRank phase.
 */
class QueryExpansionService
{
    /**
     * Expand detected Subject and Topic IDs using real co-occurrence on questions.
     *
     * @param  int[]  $subjectIds     Detected subject IDs from Qdrant intent search
     * @param  int[]  $topicIds       Detected topic IDs from Qdrant intent search
     * @param  int    $maxExpansion   Maximum number of additional items to return per entity type
     * @return array  ['subject_ids' => [...], 'topic_ids' => [...]]
     */
    public function expand(array $subjectIds, array $topicIds, int $maxExpansion = 5): array
    {
        $expandedSubjects = $subjectIds;
        $expandedTopics   = $topicIds;

        try {
            // ── 1. Subject → Co-occurring Topics ────────────────────────────
            // "If the user asked about Subject X, what Topics appear most often
            //  alongside Subject X in real questions?"
            if (!empty($subjectIds)) {
                // Questions that have at least one of the detected Subjects
                $questionIdsForSubjects = DB::table('question_subject')
                    ->whereIn('subject_id', $subjectIds)
                    ->pluck('question_id');

                // Topics that appear in those questions (excluding already-detected ones)
                $relatedTopics = DB::table('question_topic')
                    ->whereIn('question_id', $questionIdsForSubjects)
                    ->whereNotIn('topic_id', $topicIds)
                    ->select('topic_id', DB::raw('COUNT(*) as freq'))
                    ->groupBy('topic_id')
                    ->orderByDesc('freq')
                    ->limit($maxExpansion)
                    ->pluck('topic_id')
                    ->toArray();

                $expandedTopics = array_values(array_unique(array_merge($expandedTopics, $relatedTopics)));
            }

            // ── 2. Topic → Co-occurring Subjects → Sibling Topics ───────────
            // "If the user asked about Topic Y, what Subjects does it live in?
            //  And what other Topics often appear in those Subjects?"
            if (!empty($topicIds)) {
                // Questions that have at least one of the detected Topics
                $questionIdsForTopics = DB::table('question_topic')
                    ->whereIn('topic_id', $topicIds)
                    ->pluck('question_id');

                // Subjects that appear in those questions (co-occurring Subjects)
                $siblingSubjects = DB::table('question_subject')
                    ->whereIn('question_id', $questionIdsForTopics)
                    ->whereNotIn('subject_id', $subjectIds)
                    ->select('subject_id', DB::raw('COUNT(*) as freq'))
                    ->groupBy('subject_id')
                    ->orderByDesc('freq')
                    ->limit(2) // Take top 2 sibling subjects to avoid over-expanding
                    ->pluck('subject_id')
                    ->toArray();

                $expandedSubjects = array_values(array_unique(array_merge($expandedSubjects, $siblingSubjects)));

                // Sibling Topics that appear in those questions with Topics we know
                $siblingTopics = DB::table('question_topic')
                    ->whereIn('question_id', $questionIdsForTopics)
                    ->whereNotIn('topic_id', $topicIds)
                    ->select('topic_id', DB::raw('COUNT(*) as freq'))
                    ->groupBy('topic_id')
                    ->orderByDesc('freq')
                    ->limit($maxExpansion)
                    ->pluck('topic_id')
                    ->toArray();

                $expandedTopics = array_values(array_unique(array_merge($expandedTopics, $siblingTopics)));
            }

        } catch (\Exception $e) {
            Log::warning('[Xavier][QueryExpansion] Co-occurrence expansion failed.', [
                'error'      => $e->getMessage(),
                'subjects'   => $subjectIds,
                'topics'     => $topicIds,
            ]);
            // Graceful degradation: return originals if DB query fails
            return ['subject_ids' => $subjectIds, 'topic_ids' => $topicIds];
        }

        Log::info('[Xavier][QueryExpansion] Co-occurrence expansion complete.', [
            'input_subjects'    => $subjectIds,
            'input_topics'      => $topicIds,
            'expanded_subjects' => $expandedSubjects,
            'expanded_topics'   => $expandedTopics,
        ]);

        return [
            'subject_ids' => $expandedSubjects,
            'topic_ids'   => $expandedTopics,
        ];
    }

    // ─── Backward Compatibility ──────────────────────────────────────────────

    /**
     * @deprecated Use expand(subjectIds, topicIds) instead.
     *             Legacy signature used by old concept-based pipeline.
     */
    public function expandConcepts(array $conceptIds, int $depth = 1): array
    {
        Log::warning('[Xavier][QueryExpansion] expandConcepts() called — concept table is empty. Returning empty.');
        return [];
    }
}
