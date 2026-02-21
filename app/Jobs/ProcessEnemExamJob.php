<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

class ProcessEnemExamJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora de timeout por ano por segurança
    public $failOnTimeout = true;

    protected int $year;
    protected int $importLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $year, int $importLogId)
    {
        $this->year = $year;
        $this->importLogId = $importLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\EnemApiService $apiService, \App\Services\EnemImportService $importService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $limit = 50;
        $offset = 0;
        $hasMore = true;

        $inserted = 0;
        $ignored = 0;
        $errors = 0;
        $errorMessages = [];

        while ($hasMore) {
            if ($this->batch() && $this->batch()->cancelled()) {
                break;
            }

            try {
                $response = $apiService->getExamQuestions($this->year, $limit, $offset);
                
                $questions = $response['questions'] ?? [];
                $metadata = $response['metadata'] ?? [];

                foreach ($questions as $apiQuestion) {
                    try {
                        $question = $importService->processQuestion($apiQuestion);
                        if ($question) {
                            $inserted++;
                        } else {
                            $ignored++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $errorMessages[] = "Erro na questão index {$apiQuestion['index']}: " . $e->getMessage();
                        \Illuminate\Support\Facades\Log::error("EnemImport processQuestion Falhou", ['msg' => $e->getMessage(), 'q' => $apiQuestion]);
                    }
                }

                $hasMore = $metadata['hasMore'] ?? false;
                $offset += $limit;

                // Delay estratégico para não inundar a API
                sleep(2);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("EnemImport getExamQuestions Falhou: Ano {$this->year}, Offset {$offset}", ['msg' => $e->getMessage()]);
                throw $e; // Throw para o backoff da Queue tratar (se configurado) ou para falhar o job
            }
        }

        // Atualizar os contadores do Log
        $log = \App\Models\EnemImportLog::find($this->importLogId);
        if ($log) {
            $log->increment('inserted_count', $inserted);
            $log->increment('ignored_count', $ignored);
            $log->increment('error_count', $errors);
            
            if (!empty($errorMessages)) {
                $existingErrors = $log->errors ?? [];
                $log->update(['errors' => array_merge($existingErrors, $errorMessages)]);
            }
        }
    }
}
