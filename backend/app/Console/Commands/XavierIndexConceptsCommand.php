<?php

namespace App\Console\Commands;

use App\Jobs\IndexConceptVectorJob;
use App\Models\Concept;
use App\Services\AI\QdrantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * XavierIndexConceptsCommand
 *
 * Artisan command to batch-index all concepts into Qdrant.
 * Dispatches IndexConceptVectorJob for each concept.
 *
 * Usage:
 *   php artisan xavier:index-concepts            # async (dispatches to embeddings queue)
 *   php artisan xavier:index-concepts --sync     # processes synchronously
 *   php artisan xavier:index-concepts --force    # re-index even if already indexed
 */
class XavierIndexConceptsCommand extends Command
{
    protected $signature = 'xavier:index-concepts
                            {--sync    : Process synchronously instead of queuing}
                            {--limit=  : Max number of concepts to index}
                            {--force   : Re-index even if the concept already has a qdrant_indexed_at timestamp}';

    protected $description = 'Batch-index all concepts into Qdrant (Xavier Semantic Search Intent Detection)';

    public function handle(QdrantService $qdrant): int
    {
        $this->info('🚀 Xavier Semantic Search — Concept Indexer');
        $this->newLine();

        $this->info('📦 Ensuring Qdrant concepts collection exists...');
        $qdrant->ensureConceptsCollection();
        $this->info('  ✓ Collection ready.');
        $this->newLine();

        $isSync = (bool) $this->option('sync');
        $mode = $isSync ? 'synchronous' : 'async (queue: embeddings)';

        $this->info("📋 Mode: {$mode}");
        $this->newLine();

        // Query concepts based on --force option
        $query = Concept::query()
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('qdrant_indexed_at');
            });

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No pending concepts found. Nothing to index.');
            return 0;
        }

        $this->info("🔢 Found {$total} concepts to index" . ($this->option('force') ? ' (FORCE MODE)' : '') . ".");
        $this->newLine();

        $indexed = 0;
        $failed = 0;

        $this->withProgressBar(
            $query->when($this->option('limit'), function ($query, $limit) {
                return $query->limit((int) $limit);
            })->cursor(),
            function ($concept) use ($isSync, &$indexed, &$failed) {
                try {
                    if ($isSync) {
                        IndexConceptVectorJob::dispatchSync($concept->id);
                        // Small delay to avoid 429 Too Many Requests on Free Tier APIs
                        sleep(1);
                    } else {
                        IndexConceptVectorJob::dispatch($concept->id);
                    }
                    $indexed++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("[Xavier:index-concepts] Failed for concept '{$concept->id}': " . $e->getMessage());
                }
            }
        );

        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Dispatched/Indexed', $indexed],
                ['❌ Failed',            $failed],
                ['📊 Total',             $total],
            ]
        );

        if ($isSync) {
            $this->newLine();
            $this->info('✅ Synchronous indexing complete.');
        } else {
            $this->newLine();
            $this->info('✅ Jobs dispatched to [embeddings] queue.');
            $this->line('   Run: php artisan queue:work --queue=embeddings');
        }

        return 0;
    }
}
