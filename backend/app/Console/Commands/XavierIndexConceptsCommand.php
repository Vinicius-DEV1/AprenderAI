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
                            {--limit=  : Máximo total de entidades a serem indexadas (global)}
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
        $remaining = $limit;

        $this->info("📋 Modo: {$mode}");
        if ($limit) {
            $this->info("🔢 Limite Global: {$limit} entidades no total.");
        }
        $this->newLine();

        // 1. Indexas as Disciplinas como "Âncoras" primárias de busca
        $remaining = $this->indexEntityType(Subject::class, 'subject', $isSync, $remaining);

        // 2. Indexa os Tópicos (Assuntos)
        $remaining = $this->indexEntityType(Topic::class, 'topic', $isSync, $remaining);

        // 3. Indexa os Conceitos Atômicos (para expansão e recall granular)
        $remaining = $this->indexEntityType(Concept::class, 'concept', $isSync, $remaining);

        // 4. Indexa Bancas (Organizations) extraídas das questões
        $remaining = $this->indexUniqueMetadata('organization', 'organization', $isSync, $remaining);

        // 5. Indexa Órgãos (Institutions) extraídos das questões
        $remaining = $this->indexUniqueMetadata('institution', 'institution', $isSync, $remaining);

        $this->newLine();
        $this->info('✅ Todos os jobs de indexação foram disparados com sucesso.');
        return 0;
    }

    private function indexEntityType(string $modelClass, string $type, bool $isSync, ?int $limit): int
    {
        if ($limit !== null && $limit <= 0) return 0;

        $query = $modelClass::query()
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('qdrant_indexed_at');
            });

        $count = $query->count();
        $processCount = $limit !== null ? min($count, $limit) : $count;
        
        $this->info("🔢 Found {$count} {$type}s. Will process: {$processCount}.");

        if ($processCount === 0) return $limit ?? 0;

        $processed = 0;
        $this->withProgressBar($query->limit($processCount)->cursor(), function ($entity) use ($isSync, $type, &$processed) {
            if ($isSync) {
                \App\Jobs\IndexSemanticEntityJob::dispatchSync((string)$entity->id, $type);
            } else {
                \App\Jobs\IndexSemanticEntityJob::dispatch((string)$entity->id, $type);
            }
            $processed++;
        });

        $this->newLine();
        return $limit !== null ? $limit - $processed : 0;
    }

    private function indexUniqueMetadata(string $column, string $type, bool $isSync, ?int $limit): int
    {
        if ($limit !== null && $limit <= 0) return 0;

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
        $processCount = $limit !== null ? min($count, $limit) : $count;

        $this->info("🔢 Found {$count} unique {$type}s. Will process: {$processCount}.");

        if ($processCount === 0) return $limit ?? 0;

        $bar = $this->output->createProgressBar($processCount);
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

        return $limit !== null ? $limit - $processed : 0;
    }
}
