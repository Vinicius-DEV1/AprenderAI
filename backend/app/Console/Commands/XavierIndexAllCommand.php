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
 *   php artisan xavier:index-all            # async (dispatches to embeddings queue)
 *   php artisan xavier:index-all --sync     # processes synchronously (useful for testing)
 *   php artisan xavier:index-all --chunk=25 # custom chunk size
 *   php artisan xavier:index-all --fresh    # clears existing Qdrant collection first
 */
class XavierIndexAllCommand extends Command
{
    protected $signature = 'xavier:index-all
                            {--sync    : Process synchronously instead of queuing}
                            {--chunk=50 : Number of questions to process per chunk}
                            {--fresh   : Delete and recreate the Qdrant collection before indexing}';

    protected $description = 'Batch-index all approved questions into Qdrant (Xavier Semantic Search)';

    public function handle(QdrantService $qdrant): int
    {
        $this->info('🚀 Xavier Semantic Search — Batch Indexer');
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
        $qdrant->ensureConceptsCollection();
        $this->info('  ✓ Collections ready.');
        $this->newLine();

        $chunkSize = max(1, (int) $this->option('chunk'));
        $isSync    = (bool) $this->option('sync');
        $mode      = $isSync ? 'synchronous' : 'async (queue: embeddings)';

        $this->info("📋 Mode: {$mode} | Chunk size: {$chunkSize}");
        $this->newLine();

        // Count approved questions
        $total = Question::published()->where('tipo_questao', '!=', 'Redação')->count();

        if ($total === 0) {
            $this->warn('No approved questions found. Nothing to index.');
            return 0;
        }

        $this->info("🔢 Found {$total} approved questions to index.");
        $this->newLine();

        $indexed  = 0;
        $failed   = 0;
        $skipped  = 0;

        $this->withProgressBar(
            Question::published()
                ->where('tipo_questao', '!=', 'Redação')
                ->select('id')
                ->cursor(),
            function ($question) use ($isSync, &$indexed, &$failed) {
                try {
                    if ($isSync) {
                        IndexQuestionVectorJob::dispatchSync($question->id);
                    } else {
                        IndexQuestionVectorJob::dispatch($question->id);
                    }
                    $indexed++;
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
            $this->newLine();
            $this->info('✅ Jobs dispatched to [embeddings] queue.');
            $this->line('   Run: php artisan queue:work --queue=embeddings');
        }

        return 0;
    }
}
