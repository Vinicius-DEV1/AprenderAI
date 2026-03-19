<?php

namespace App\Jobs;

use App\Models\Subject;
use App\Models\Topic;
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
 * IndexSemanticEntityJob
 *
 * Generates a single embedding vector for a semantic filter entity (Subject or Topic)
 * and upserts it to the Qdrant `filters` collection (backward-compatible: 'concepts_vectors').
 * This allows Subjects and Topics to be detected semantically during the Xavier intent search.
 *
 * Queue: embeddings
 */
class IndexSemanticEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    /**
     * @param string $entityId   The ID or slug of the entity
     * @param string $entityType 'subject', 'topic', 'organization', or 'institution'
     */
    public function __construct(
        protected string $entityId,
        protected string $entityType = 'subject'
    ) {
        $this->onQueue(config('xavier.embeddings.queue', 'embeddings'));
    }

    public function handle(
        AIService            $aiService,
        EmbeddingTextBuilder $textBuilder,
        QdrantService        $qdrant
    ): void {
        Log::info("[Xavier][IndexEntity] Starting indexing for {$this->entityType} #{$this->entityId}...");

        $model = $this->resolveModel();
        if (!$model) {
            $msg = "[Xavier][IndexEntity] Entity '{$this->entityId}' of type '{$this->entityType}' NOT FOUND.";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        // Constrói o texto rico para o embedding baseado no tipo de entidade.
        // Subjects e Topics agora possuem templates próprios para capturar a semântica da disciplina.
        $text = match ($this->entityType) {
            'subject'      => $textBuilder->buildForSubject($model),
            'topic'        => $textBuilder->buildForTopic($model),
            'organization' => $textBuilder->buildForOrganization($model->name),
            'institution'  => $textBuilder->buildForInstitution($model->name),
            default        => throw new \InvalidArgumentException("Invalid entity type: {$this->entityType}")
        };

        Log::info("[Xavier][IndexEntity] Generating vector for {$this->entityType} '{$this->entityId}'...");
        
        // Gera o embedding usando o modelo configurado (Gemini).
        // Usamos 'RETRIEVAL_DOCUMENT' pois esta entidade será a "âncora" buscada.
        $vector = $aiService->generateEmbedding($text, null, 'RETRIEVAL_DOCUMENT');

        if (!$vector) {
            $msg = "[Xavier][IndexEntity] Embedding FAILED for {$this->entityType} '{$this->entityId}'.";
            Log::error($msg);
            throw new \RuntimeException($msg);
        }

        $qdrant->ensureFiltersCollection();

        // Unified payload that QuestionController uses for intent detection.
        $payload = [
            'name'             => $model->name,
            'entity_type'      => $this->entityType,
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser'),
        ];

        // Type-specific metadata for precise filtering during Qdrant search
        if ($this->entityType === 'subject') {
            $payload['subject_id'] = $model->id;
        } elseif ($this->entityType === 'topic') {
            $payload['topic_id']   = $model->id;
        } elseif ($this->entityType === 'organization') {
            $payload['organization'] = $model->name;
        } elseif ($this->entityType === 'institution') {
            $payload['institution'] = $model->name;
        }

        // Deterministic Qdrant ID: "subject:42" → deterministic uint64
        $qdrantId = $this->entityType . ':' . $this->entityId;
        $success = $qdrant->upsertSemanticEntity($qdrantId, $vector, $payload);

        if ($success) {
            // Marca como indexado para controle no Dashboard (apenas para modelos reais)
            if (method_exists($model, 'update') && !in_array($this->entityType, ['organization', 'institution'])) {
                $model->update(['qdrant_indexed_at' => now()]);
            }
            Log::info("[Xavier][IndexEntity] {$this->entityType} '{$this->entityId}' indexed successfully.");
        } else {
            throw new \RuntimeException("Qdrant upsert failed");
        }
    }

    private function resolveModel()
    {
        return match ($this->entityType) {
            'subject'                    => Subject::find($this->entityId),
            'topic'                      => Topic::find($this->entityId),
            'organization', 'institution' => (object) ['name' => $this->entityId, 'id' => $this->entityId],
            default                      => null
        };
    }
}
