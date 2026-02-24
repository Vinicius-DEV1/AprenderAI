<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Question;
use App\Models\AiProcessingBatch;
use App\Jobs\AIBatchTriageJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RunLoadTest extends Command
{
    protected $signature = 'app:run-load-test {quantity=50} {type=both}';
    protected $description = 'Runs an AI batch triage load test';

    public function handle()
    {
        $quantity = (int)$this->argument('quantity');
        $type = $this->argument('type');
        $model = null; // Let the provider decide or use key default

        $query = Question::incomplete()->limit($quantity);
        $questions = $query->get();
        $total = $questions->count();

        if ($total === 0) {
            $this->error("Nenhuma questão encontrada para os critérios selecionados.");
            return 1;
        }

        $batchId = Str::uuid()->toString();

        AiProcessingBatch::create([
            'batch_id' => $batchId,
            'model' => $model,
            'type' => $type,
            'total_count' => $total,
            'status' => 'processing',
        ]);

        Cache::put("batch_progress_{$batchId}", [
            'total' => $total,
            'processed' => 0,
            'errors' => 0,
            'status' => 'processing',
            'message' => "Iniciando teste de carga de {$total} questões...",
            'last_error' => null
        ], now()->addHours(2));

        $questions->chunk(5)->each(function ($chunk) use ($batchId, $type, $model) {
            AIBatchTriageJob::dispatch(
                $batchId,
                $chunk->pluck('id')->toArray(),
                $type,
                $model
            );
        });

        $this->info("Batch started: {$batchId}");
        return 0;
    }
}
