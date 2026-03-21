<?php

namespace App\Console\Commands;

use App\Models\Concept;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\AI\QdrantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * XavierIndexConceptsCommand
 *
 * Comando Artisan para indexação em lote de todas as entidades semânticas (Fase 3).
 * Este comando é o ponto de entrada para alimentar a "Inteligência de Intenção" do Xavier.
 * Ele percorre Disciplinas (Subjects), Assuntos (Topics) e Conceitos (Concepts)
 * e dispara jobs de vetorização para o Qdrant.
 */
class XavierIndexConceptsCommand extends Command
{
    protected $signature = 'xavier:index-concepts
                            {--sync    : Processa sincronamente em vez de enfileirar}
                            {--limit=  : Máximo de entidades POR TIPO (Subject, Topic, Organization, etc)}
                            {--force   : Re-indexa mesmo entidades já marcadas como indexadas}';

    protected $description = 'Indexa Disciplinas, Tópicos e Conceitos no Qdrant para Detecção de Intenção';

    public function handle(QdrantService $qdrant): int
    {
        $this->info('🚀 Xavier Semantic Search — Entity Indexer');
        $this->newLine();

        $this->info('📦 Garantindo que a coleção de conceitos existe no Qdrant...');
        $qdrant->ensureConceptsCollection();
        $this->info('  ✓ Coleção pronta.');
        $this->newLine();

        $isSync = (bool) $this->option('sync');
        $mode = $isSync ? 'síncrono (bloqueante)' : 'assíncrono (fila: embeddings)';
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info("📋 Modo: {$mode}");
        if ($limit) {
            $this->info("🔢 Limite: {$limit} entidades POR CATEGORIA.");
        }
        $this->newLine();

        // 1. Indexas as Disciplinas como "Âncoras" primárias de busca
        $this->indexEntityType(Subject::class, 'subject', $isSync, $limit);

        // 2. Indexa os Tópicos (Assuntos)
        $this->indexEntityType(Topic::class, 'topic', $isSync, $limit);

        // 3. Indexa os Conceitos Atômicos (para expansão e recall granular)
        $this->indexEntityType(Concept::class, 'concept', $isSync, $limit);

        // 4. Indexa Bancas (Organizations) extraídas das questões
        $this->indexUniqueMetadata('organization', 'organization', $isSync, $limit);

        // 5. Indexa Órgãos (Institutions) extraídos das questões
        $this->indexUniqueMetadata('institution', 'institution', $isSync, $limit);

        $this->newLine();
        $this->info('✅ Todos os jobs de indexação foram disparados com sucesso.');
        return 0;
    }

    private function indexEntityType(string $modelClass, string $type, bool $isSync, ?int $limit): void
    {
        $query = $modelClass::query()
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('qdrant_indexed_at');
            });

        $count = $query->count();
        $this->info("🔢 Found {$count} {$type}s. " . ($limit ? "Indexing up to {$limit}." : "Indexing all."));

        if ($count === 0) return;

        $this->withProgressBar($query->limit($limit)->cursor(), function ($entity) use ($isSync, $type) {
            if ($isSync) {
                \App\Jobs\IndexSemanticEntityJob::dispatchSync((string)$entity->id, $type);
            } else {
                \App\Jobs\IndexSemanticEntityJob::dispatch((string)$entity->id, $type);
            }
        });

        $this->newLine();
    }

    private function indexUniqueMetadata(string $column, string $type, bool $isSync, ?int $limit): void
    {
        // Junk metadata to exclude from indexing (unacceptable to show in UI)
        $exclusions = [
            'N/A', 'NA', 'N/D', 'ND', 'NOME DA INSTITUIÇÃO', 'ÓRGÃO', '-', '.', 'NULL', 'UNDEFINED', 'TESTE', 'NI'
        ];

        $names = \App\Models\Question::whereNotNull($column)
            ->whereRaw("TRIM({$column}) != ''")
            ->whereNotIn(DB::raw("UPPER(TRIM({$column}))"), $exclusions)
            ->distinct()
            ->pluck($column);

        $count = $names->count();
        $this->info("🔢 Found {$count} unique {$type}s. " . ($limit ? "Indexing up to {$limit}." : "Indexing all."));

        if ($count === 0) return;

        $bar = $this->output->createProgressBar($limit ? min($count, $limit) : $count);
        $processed = 0;
        foreach ($names as $name) {
            if ($limit !== null && $processed >= $limit) break;

            if ($isSync) {
                \App\Jobs\IndexSemanticEntityJob::dispatchSync($name, $type);
            } else {
                \App\Jobs\IndexSemanticEntityJob::dispatch($name, $type);
            }
            $bar->advance();
            $processed++;
        }
        $bar->finish();
        $this->newLine();
    }
}
