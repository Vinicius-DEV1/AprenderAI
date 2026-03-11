<?php

namespace App\Jobs;

use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Services\AI\AIService;
use App\Services\AI\EmbeddingTextBuilder;
use App\Services\AI\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IndexConceptVectorJob
 *
 * Generates a single embedding vector for a Concept node and upserts it
 * to the Qdrant `concepts_vectors` collection.
 * Updates `concepts.qdrant_indexed_at` on success.
 *
 * Queue: embeddings
 */
class IndexConceptVectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(protected string $conceptId)
    {
        $this->onQueue(config('xavier.embeddings.queue', 'embeddings'));
    }

    public function handle(
        AIService            $aiService,
        EmbeddingTextBuilder $textBuilder,
        QdrantService        $qdrant
    ): void {
        /** @var Concept|null $concept */
        $concept = Concept::with(['subject', 'topic'])->find($this->conceptId);

        if (!$concept) {
            Log::warning("[Xavier][IndexConcept] Concept '{$this->conceptId}' not found.");
            return;
        }

        // Gather related concept names for richer embedding context
        $relatedNames = ConceptRelation::where('concept_id', $this->conceptId)
            ->with('relatedConcept')
            ->orderByDesc('weight')
            ->limit(5)
            ->get()
            ->pluck('relatedConcept.name')
            ->filter()
            ->values()
            ->toArray();

        $text   = $textBuilder->buildForConcept($concept, $relatedNames);
        $vector = $aiService->generateEmbedding($text, null);

        if (!$vector) {
            Log::error("[Xavier][IndexConcept] Embedding failed for concept '{$this->conceptId}'.");
            return;
        }

        $qdrant->ensureConceptsCollection();

        $payload = [
            'name'       => $concept->name,
            'subject_id' => $concept->subject_id,
            'topic_id'   => $concept->topic_id,
            'aliases'    => $concept->aliases ?? [],
        ];

        $success = $qdrant->upsertConcept($this->conceptId, $vector, $payload);

        if ($success) {
            $concept->update(['qdrant_indexed_at' => now()]);
            Log::info("[Xavier][IndexConcept] Concept '{$this->conceptId}' indexed successfully.");
        } else {
            Log::error("[Xavier][IndexConcept] Qdrant upsert failed for concept '{$this->conceptId}'.");
        }
    }
}
