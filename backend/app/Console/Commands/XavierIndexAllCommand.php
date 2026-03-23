<?php

namespace App\Console\Commands;

use App\Jobs\IndexQuestionVectorJob;
use App\Models\Question;
use App\Services\AI\QdrantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * XavierIndexAllCommand
 *
 * Artisan command to batch-index all approved questions into Qdrant.
 * Dispatches IndexQuestionVectorJob for each question.
 *
 * Usage:
 *   php artisan xavier:index-all            # async (dispatches to prioritized queue)
 *   php artisan xavier:index-all --sync     # processes synchronously (useful for testing)
 *   php artisan xavier:index-all --chunk=25 # custom chunk size
 *   php artisan xavier:index-all --fresh    # ensures Qdrant collection exists
 */
class XavierIndexAllCommand extends Command
{
    protected $signature = 'xavier:index-all
                            {--sync    : Process synchronously instead of queuing}
                            {--chunk=50 : Number of questions to process per chunk}
                            {--limit=  : Max number of questions to index}
                            {--force   : Re-index even if the question already has vectors}
                            {--fresh   : Delete and recreate the Qdrant collection before indexing}';

    protected $description = 'Batch-index all approved QUESTIONS into Qdrant (Xavier Semantic Search)';

    public function handle(QdrantService $qdrant): int
    {
        $pid = getmypid();
        $this->info("🚀 Xavier Semantic Search — Batch Indexer (PID: {$pid})");
        Log::info("[Xavier][CommandAll] START | PID: {$pid}");
        $this->newLine();

        // Ensure Qdrant collections exist (or recreate if --fresh)
        if ($this->option('fresh')) {
            $this->warn('⚠  --fresh flag detected. Recreating Qdrant collections...');
            // Note: Qdrant collection recreation would require a delete endpoint.
            // For safety, we just ensure the collections exist (no destructive action).
            $this->warn('  (For a true reset, delete the collections manually via Qdrant UI first)');
        }

        $this->info('📦 Ensuring Qdrant collections exist...');
        $qdrant->ensureQuestionsCollection();
        $qdrant->ensureFiltersCollection(); // Concepts were renamed to Filters/Entities in Xavier 2.0
        $this->info('  ✓ Collections ready.');
        $this->newLine();

        $chunkSize = max(1, (int) $this->option('chunk'));
        $isSync    = (bool) $this->option('sync');
        $mode      = $isSync ? 'synchronous' : 'async (queue: embeddings)';

        $this->info("📋 Mode: {$mode} | Chunk size: {$chunkSize}");
        $this->newLine();

        // Count approved questions (filter depends on --force)
        $total = Question::published()
            ->where('tipo_questao', '!=', 'Redação')
            ->when(!$this->option('force'), function ($query) {
                return $query->whereDoesntHave('vectors');
            })
            ->count();

        if ($total === 0) {
            $this->warn('No pending approved questions found. Nothing to index.');
            return 0;
        }

        $this->info("🔢 Found {$total} questions to index" . ($this->option('force') ? ' (FORCE MODE)' : '') . ".");
        $this->newLine();

        $indexed  = 0;
        $failed   = 0;
        $skipped  = 0;

        $this->withProgressBar(
            Question::published()
                ->where('tipo_questao', '!=', 'Redação')
                ->when(!$this->option('force'), function ($query) {
                    return $query->whereDoesntHave('vectors');
                })
                ->when($this->option('limit'), function ($query, $limit) {
                    return $query->limit((int) $limit);
                })
                ->select('id')
                ->cursor(),
            function ($question) use ($isSync, &$indexed, &$failed, $pid) {
                try {
                    $job = new IndexQuestionVectorJob($question->id);
                    
                    if ($isSync) {
                        dispatch_sync($job);
                    } else {
                        dispatch($job)->onQueue(config('xavier.embeddings.queue', 'embeddings')); // Fixed queue name to match batching
                    }
                    $indexed++;

                    if ($indexed % 100 === 0) {
                        Log::debug("[Xavier][CommandAll] Progress: {$indexed} dispatched...");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("[Xavier:index-all] Failed for question #{$question->id}: " . $e->getMessage());
                }
            }
        );

        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Dispatched/Indexed', $indexed],
                ['❌ Failed',            $failed],
                ['⏭  Skipped',           $skipped],
                ['📊 Total',             $total],
            ]
        );

        if ($isSync) {
            $this->newLine();
            $this->info('✅ Synchronous indexing complete.');
        } else {
            $queueName = config('xavier.embeddings.queue', 'low');
            $this->newLine();
            $this->info("✅ Jobs dispatched to [{$queueName}] queue (Prioritized Workers).");
            $this->line("   Hybrid workers will process this as a background task.");
        }

        Log::info("[Xavier][CommandAll] END | Dispatched: {$indexed} | Failed: {$failed}");
        return 0;
    }
}
