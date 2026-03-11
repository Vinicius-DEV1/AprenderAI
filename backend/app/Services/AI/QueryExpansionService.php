<?php

namespace App\Services\AI;

use App\Models\ConceptRelation;
use Illuminate\Support\Facades\Log;

/**
 * QueryExpansionService
 *
 * Expands a set of detected concept IDs by traversing the concept_relations
 * knowledge graph at depth=1, prioritizing edges by weight (descending).
 *
 * Example:
 *   detected: ['fotossintese']
 *   expanded: ['fotossintese', 'clorofila', 'producao-primaria', 'organismos-autotróficos']
 */
class QueryExpansionService
{
    /**
     * Expand detected concept IDs via the knowledge graph (depth=1).
     *
     * @param  string[]  $conceptIds     Detected concept slugs from Qdrant concept search
     * @param  int       $depth          Graph traversal depth (currently supports 1)
     * @return string[]  Merged & deduplicated concept IDs (original + related), max $maxConcepts
     */
    public function expand(array $conceptIds, int $depth = 1): array
    {
        if (empty($conceptIds)) {
            return [];
        }

        $maxConcepts = (int) config('xavier.embeddings.max_expanded_concepts', 8);

        // Start with original detected IDs
        $expandedIds = $conceptIds;

        // Fetch related concepts ordered by weight for all input concept IDs
        $relations = ConceptRelation::whereIn('concept_id', $conceptIds)
            ->orderByDesc('weight')
            ->limit($maxConcepts * 3) // Over-fetch then slice, to keep highest-weight ones
            ->pluck('related_id')
            ->toArray();

        $expandedIds = array_values(array_unique(array_merge($expandedIds, $relations)));

        // Cap at max_expanded_concepts
        $result = array_slice($expandedIds, 0, $maxConcepts);

        Log::info('[Xavier][QueryExpansion] Expanded concepts.', [
            'original'  => $conceptIds,
            'expanded'  => $result,
        ]);

        return $result;
    }
}
