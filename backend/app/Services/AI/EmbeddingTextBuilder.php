<?php

namespace App\Services\AI;

use App\Models\Question;
use App\Models\Concept;

/**
 * EmbeddingTextBuilder
 *
 * Builds structured, semantically rich text blocks used as input for embedding generation.
 * The structured format gives the embedding model full educational context,
 * enabling high-quality vector similarity even when keywords differ.
 */
class EmbeddingTextBuilder
{
    /**
     * Builds the full structured text for a question.
     * This is the input for the STATEMENT embedding vector.
     *
     * Format:
     *   subject: {name}
     *   topic: {name}
     *
     *   question:
     *   {statement}
     *
     *   explanation:
     *   {explanation}
     *
     *   concepts:
     *   {concept1, concept2, ...}
     */
    public function buildForQuestion(Question $question): string
    {
        $subjectName = $question->subjects->first()?->name ?? '';
        $topicName   = $question->topics->first()?->name ?? '';

        $parts = [];

        if ($subjectName) {
            $parts[] = "subject: {$subjectName}";
        }
        if ($topicName) {
            $parts[] = "topic: {$topicName}";
        }

        $parts[] = '';
        $parts[] = 'question:';
        $parts[] = $this->cleanText($question->statement ?? '');

        if (!empty($question->explanation)) {
            $parts[] = '';
            $parts[] = 'explanation:';
            $parts[] = $this->cleanText($question->explanation);
        }

        // Include concepts already linked to the question (pre-loaded)
        $conceptNames = $question->concepts
            ->pluck('name')
            ->toArray();

        if (!empty($conceptNames)) {
            $parts[] = '';
            $parts[] = 'concepts:';
            $parts[] = implode(', ', $conceptNames);
        }

        return implode("\n", $parts);
    }

    /**
     * Builds the CONCEPT embedding text.
     * Used for the question's concept_vector — focused on concept terminology.
     *
     * Format:
     *   subject: {name}
     *   topic: {name}
     *   concepts:
     *   {concept1, concept2, ...} (with aliases expanded)
     */
    public function buildConceptTextForQuestion(Question $question): string
    {
        $subjectName = $question->subjects->first()?->name ?? '';
        $topicName   = $question->topics->first()?->name ?? '';

        $parts = [];

        if ($subjectName) {
            $parts[] = "subject: {$subjectName}";
        }
        if ($topicName) {
            $parts[] = "topic: {$topicName}";
        }

        $allTerms = [];
        foreach ($question->concepts as $concept) {
            $allTerms[] = $concept->name;
            if (!empty($concept->aliases)) {
                foreach ($concept->aliases as $alias) {
                    $allTerms[] = $alias;
                }
            }
        }

        if (!empty($allTerms)) {
            $parts[] = '';
            $parts[] = 'concepts:';
            $parts[] = implode(', ', array_unique($allTerms));
        } else {
            // Fallback: use statement trimmed for concept vector
            $parts[] = '';
            $parts[] = $this->cleanText(mb_substr($question->statement ?? '', 0, 300));
        }

        return implode("\n", $parts);
    }

    /**
     * Builds the EXPLANATION embedding text.
     * Used for the question's explanation_vector — focused on the answer rationale.
     */
    public function buildExplanationTextForQuestion(Question $question): string
    {
        $subjectName = $question->subjects->first()?->name ?? '';

        $parts = [];

        if ($subjectName) {
            $parts[] = "subject: {$subjectName}";
        }

        $parts[] = '';
        $parts[] = 'explanation:';
        $parts[] = $this->cleanText($question->explanation ?? $question->statement ?? '');

        return implode("\n", $parts);
    }

    /**
     * Builds the embedding text for a Concept node.
     * Includes all aliases and related concept names for rich representation.
     */
    public function buildForConcept(Concept $concept, array $relatedConceptNames = []): string
    {
        $parts = [];

        if ($concept->subject) {
            $parts[] = "subject: {$concept->subject->name}";
        }
        if ($concept->topic) {
            $parts[] = "topic: {$concept->topic->name}";
        }

        $parts[] = '';
        $parts[] = "concept: {$concept->name}";

        $allTerms = $concept->getAllTerms();
        if (count($allTerms) > 1) {
            $parts[] = "aliases: " . implode(', ', array_slice($allTerms, 1));
        }

        if ($concept->description) {
            $parts[] = '';
            $parts[] = 'description:';
            $parts[] = $this->cleanText($concept->description);
        }

        if (!empty($relatedConceptNames)) {
            $parts[] = '';
            $parts[] = 'related: ' . implode(', ', $relatedConceptNames);
        }

        return implode("\n", $parts);
    }

    /**
     * Normalizes a user search query for embedding.
     * Removes excessive whitespace and lowercases.
     */
    public function buildForQuery(string $userQuery): string
    {
        $query = strtolower(trim($userQuery));
        $query = preg_replace('/\s+/', ' ', $query);
        return $query;
    }

    /**
     * Strips HTML tags and normalizes whitespace from text.
     */
    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
