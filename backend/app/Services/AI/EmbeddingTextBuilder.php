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

        // Include the correct alternative to reinforce the "answer" keywords
        $correctAlternative = $question->alternatives->where('is_correct', true)->first();
        if ($correctAlternative) {
            $parts[] = '';
            $parts[] = 'correct answer:';
            $parts[] = $this->cleanText($correctAlternative->content);
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

        // Also add the correct answer text to the explanation vector for richer semantics
        $correctAlternative = $question->alternatives->where('is_correct', true)->first();
        if ($correctAlternative) {
            $parts[] = '';
            $parts[] = 'correct answer content:';
            $parts[] = $this->cleanText($correctAlternative->content);
        }

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
     * Builds query text aligned with the STATEMENT vector format.
     *
     * Na indexação, o statement vector é gerado a partir de texto estruturado:
     *   "subject: X\ntopic: Y\nquestion:\n{enunciado}\nexplanation:\n{explicação}..."
     *
     * Para maximizar a similaridade de cosseno, a query de busca deve usar o
     * mesmo formato estruturado. O prefixo "question:" alinha o embedding da
     * query ao mesmo espaço semântico que os documentos indexados.
     *
     * @param string $userQuery  Query natural do usuário
     * @return string  Texto formatado para gerar o embedding de busca do statement
     */
    public function buildStatementQuery(string $userQuery): string
    {
        $query = $this->cleanText($userQuery);
        return "question:\n" . $query;
    }

    /**
     * Builds query text aligned with the CONCEPT vector format.
     *
     * Na indexação, o concept vector é gerado a partir de:
     *   "subject: X\ntopic: Y\nconcepts:\n{conceito1, conceito2, conceito3}"
     *
     * A query para busca conceitual deve usar o mesmo prefixo "concepts:"
     * para que o embedding capture a intenção de buscar por conceitos.
     *
     * @param string $userQuery  Query natural do usuário
     * @return string  Texto formatado para gerar o embedding de busca de conceitos
     */
    public function buildConceptQuery(string $userQuery): string
    {
        $query = $this->cleanText($userQuery);
        return "concepts:\n" . $query;
    }

    /**
     * Builds query text aligned with the EXPLANATION vector format.
     *
     * Na indexação, o explanation vector é gerado a partir de:
     *   "subject: X\nexplanation:\n{explicação}\ncorrect answer content:\n{resposta}"
     *
     * A query para busca por explicação deve usar o mesmo prefixo "explanation:"
     * para alinhar ao espaço semântico das explicações indexadas.
     *
     * @param string $userQuery  Query natural do usuário
     * @return string  Texto formatado para gerar o embedding de busca de explicações
     */
    public function buildExplanationQuery(string $userQuery): string
    {
        $query = $this->cleanText($userQuery);
        return "explanation:\n" . $query;
    }

    /**
     * Normalizes a user search query for embedding (generic version).
     * Used for cache lookups and concept detection where format alignment is not needed.
     *
     * @param string $userQuery  Query natural do usuário
     * @return string  Query normalizada (lowercase, whitespace limpo)
     */
    public function buildForQuery(string $userQuery): string
    {
        $query = strtolower(trim($userQuery));
        $query = preg_replace('/\s+/', ' ', $query);
        return $query;
    }

    /**
     * Strips HTML tags and normalizes whitespace from text.
     * Usado por todos os builders para garantir que o texto embedado
     * esteja limpo de HTML, entidades e espaços excessivos.
     */
    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
