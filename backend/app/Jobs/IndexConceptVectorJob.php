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

    public int $tries   = 20;
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
            $msg = "[Xavier][IndexConcept] Concept '{$this->conceptId}' NOT FOUND in MySQL database.";
            Log::error($msg);
            // Permanent error, no retry
            return;
        }

        Log::info("[Xavier][IndexConcept] Found concept #{$concept->id} ({$concept->name}). Starting indexing...");

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

        // Gerar embedding do conceito com texto enriquecido (nome, aliases, relacionados).
        // Usa taskType='RETRIEVAL_DOCUMENT' para que o Gemini otimize o vetor
        // para ser encontrado por queries de busca (RETRIEVAL_QUERY).
        Log::info("[Xavier][IndexConcept] Generating vector for concept '{$this->conceptId}'...");
        $text   = $textBuilder->buildForConcept($concept, $relatedNames);
        
        try {
            $vector = $aiService->generateEmbedding($text, null, 'RETRIEVAL_DOCUMENT');

            if (!$vector) {
                throw new \RuntimeException("Vetor retornado vazio.");
            }
        } catch (\App\Exceptions\AIServiceBusyException $e) {
            // POOL BUSY OR LOCKED: Use 5-minute backoff to avoid aggressive key contention.
            Log::info("[IndexConceptVectorJob] AI pool busy for concept #{$this->conceptId}. Releasing for 5m backoff.");
            $this->release(300);
            return;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            
            // Check for Quota limit or other retriable failover failures
            $isQuota = str_contains(strtolower($msg), '429') || 
                       str_contains(strtolower($msg), 'quota') || 
                       str_contains(strtolower($msg), 'full failover failure');

            if ($isQuota) {
                Log::warning("[IndexConceptVectorJob] Quota limit hit or no keys available for concept #{$this->conceptId}. Releasing for 5m. Error: {$msg}");
                $this->release(300);
                return;
            }

            // Permanent failure: Log and fail the job definitively.
            Log::error("[IndexConceptVectorJob] Permanent error indexing concept #{$this->conceptId}: " . $msg);
            $this->fail($e);
        }

        Log::debug("[Xavier][IndexConcept] #{$this->conceptId} vector generated successfully.");

        $qdrant->ensureConceptsCollection();

        $payload = [
            'name'             => $concept->name,
            'subject_id'       => $concept->subject_id,
            'topic_id'         => $concept->topic_id,
            'aliases'          => $concept->aliases ?? [],
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser'),
        ];

        $success = $qdrant->upsertConcept($this->conceptId, $vector, $payload);

        if ($success) {
            $concept->update(['qdrant_indexed_at' => now()]);
            Log::info("[Xavier][IndexConcept] Concept '{$this->conceptId}' indexed successfully.");
        } else {
            $msg = "[Xavier][IndexConcept] Qdrant upsert failed for concept '{$this->conceptId}'. Check Qdrant connectivity/logs.";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }
    }
}
