<?php

namespace App\Jobs;

use App\Models\Concept;
use App\Models\Subject;
use App\Models\Topic;
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
 * IndexSemanticEntityJob
 *
 * Generates a single embedding vector for a semantic entity (Concept, Subject, or Topic)
 * and upserts it to the Qdrant `concepts_vectors` collection.
 * This unification allows "Disciplines" to be detected semantically just like "Concepts".
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
     * @param string $entityType 'concept', 'subject', or 'topic'
     */
    public function __construct(
        protected string $entityId,
        protected string $entityType = 'concept'
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
            'concept' => $this->buildConceptText($model, $textBuilder),
            'subject' => $textBuilder->buildForSubject($model),
            'topic'   => $textBuilder->buildForTopic($model),
            'organization' => $textBuilder->buildForOrganization($model->name),
            'institution'  => $textBuilder->buildForInstitution($model->name),
            default   => throw new \InvalidArgumentException("Invalid entity type: {$this->entityType}")
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

        $qdrant->ensureConceptsCollection();

        // Payload unificado que o QuestionController usará para filtrar a busca.
        $payload = [
            'name'             => $model->name,
            'entity_type'      => $this->entityType,
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v6_intent_unification'),
        ];

        // Metadados específicos para permitir o hard filter no MySQL/Vector Search
        if ($this->entityType === 'concept') {
            $payload['subject_id'] = $model->subject_id;
            $payload['topic_id']   = $model->topic_id;
            $payload['concept_slug'] = $model->slug;
            $payload['aliases']    = $model->aliases ?? [];
        } elseif ($this->entityType === 'subject') {
            $payload['subject_id'] = $model->id;
        } elseif ($this->entityType === 'topic') {
            $payload['topic_id']   = $model->id;
        } elseif ($this->entityType === 'organization') {
            $payload['organization'] = $model->name;
        } elseif ($this->entityType === 'institution') {
            $payload['institution'] = $model->name;
        }

        // ID determinístico para evitar duplicatas: prefixamos com o tipo (ex: subject:123).
        // O QdrantService cuida da conversão para UUID v5 internamente.
        $qdrantId = $this->entityType . ':' . $this->entityId;
        $success = $qdrant->upsertConcept($qdrantId, $vector, $payload);

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
            'concept' => Concept::with(['subject', 'topic'])->find($this->entityId),
            'subject' => Subject::find($this->entityId),
            'topic'   => Topic::find($this->entityId),
            'organization', 'institution' => (object) ['name' => $this->entityId, 'id' => $this->entityId],
            default   => null
        };
    }

    private function buildConceptText(Concept $concept, EmbeddingTextBuilder $textBuilder): string
    {
        $relatedNames = ConceptRelation::where('concept_id', $concept->id)
            ->with('relatedConcept')
            ->orderByDesc('weight')
            ->limit(5)
            ->get()
            ->pluck('relatedConcept.name')
            ->filter()
            ->values()
            ->toArray();

        return $textBuilder->buildForConcept($concept, $relatedNames);
    }
}
