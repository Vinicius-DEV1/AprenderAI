<?php

namespace App\Jobs;

use App\Models\Concept;
use App\Models\Question;
use App\Services\AI\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ConceptExtractionJob
 *
 * Uses the LLM to extract educational concepts from an approved question's
 * statement + explanation. Persists them in the `concepts` and `question_concepts`
 * tables, then dispatches IndexConceptVectorJob for each new concept.
 *
 * Queue: embeddings
 */
class ConceptExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(protected int $questionId)
    {
        $this->onQueue(config('xavier.embeddings.queue', 'embeddings'));
    }

    public function handle(AIService $aiService): void
    {
        /** @var Question|null $question */
        $question = Question::with(['subjects', 'topics'])->find($this->questionId);

        if (!$question) {
            Log::warning("[Xavier][ConceptExtraction] Question #{$this->questionId} not found.");
            return;
        }

        $subjectName = $question->subjects->first()?->name ?? '';
        $topicName   = $question->topics->first()?->name ?? '';

        $prompt = $this->buildPrompt($question, $subjectName, $topicName);

        try {
            $raw = $aiService->sendRawPrompt($prompt, \App\Models\ApiKey::CAPABILITY_QUESTIONS, $this->questionId);
            $concepts = $this->parseConcepts($raw);
        } catch (\Exception $e) {
            Log::error("[Xavier][ConceptExtraction] LLM call failed for #{$this->questionId}: " . $e->getMessage());
            return;
        }

        if (empty($concepts)) {
            Log::info("[Xavier][ConceptExtraction] No concepts extracted for #{$this->questionId}.");
            return;
        }

        $subjectId = $question->subjects->first()?->id;
        $topicId   = $question->topics->first()?->id;

        $newConceptIds = [];

        foreach ($concepts as $conceptName) {
            $slug = Str::slug($conceptName, '-');
            if (empty($slug)) {
                continue;
            }

            $isNew = !Concept::where('id', $slug)->exists();

            $concept = Concept::firstOrCreate(
                ['id' => $slug],
                [
                    'name'          => $conceptName,
                    'subject_id'    => $subjectId,
                    'topic_id'      => $topicId,
                    'auto_detected' => true,
                ]
            );

            // Attach to question with AI confidence = 0.80
            $question->concepts()->syncWithoutDetaching([
                $slug => ['confidence' => 0.80],
            ]);

            Log::info("[Xavier][ConceptExtraction] Concept '{$conceptName}' attached to question #{$this->questionId}.");

            if ($isNew) {
                $newConceptIds[] = $slug;
            }
        }

        // Dispatch vector indexing for each newly created concept
        foreach ($newConceptIds as $conceptId) {
            IndexConceptVectorJob::dispatch($conceptId)
                ->onQueue(config('xavier.embeddings.queue', 'embeddings'));
        }

        Log::info("[Xavier][ConceptExtraction] Extracted " . count($concepts) . " concepts, " . count($newConceptIds) . " new for question #{$this->questionId}.");
    }

    private function buildPrompt(Question $question, string $subject, string $topic): string
    {
        $statement   = strip_tags($question->statement ?? '');
        $explanation = strip_tags($question->explanation ?? '');

        return <<<PROMPT
You are an educational content analyst. Extract a concise list of key educational concepts from the question below.
Return ONLY a JSON array of concept names (strings) in Portuguese, no explanations.
Maximum 5 concepts. Focus on the core academic/educational concepts tested.
Subject: {$subject}
Topic: {$topic}

Question: {$statement}

Explanation: {$explanation}

Return format: ["concept1", "concept2", ...]
PROMPT;
    }

    /**
     * Parses the LLM response into a clean array of concept names.
     */
    private function parseConcepts(string $raw): array
    {
        // Try to find a JSON array anywhere in the response
        if (preg_match('/\[.*?\]/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return array_slice(
                    array_filter(array_map('trim', $decoded), fn($c) => is_string($c) && strlen($c) > 1),
                    0,
                    5
                );
            }
        }

        return [];
    }
}
