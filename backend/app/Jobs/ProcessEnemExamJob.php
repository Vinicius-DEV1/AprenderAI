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
        $updated = 0;
        $ignored = 0;
        $errors = 0;
        $errorMessages = [];
        $ignoredItems = [];

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
                        $result = $importService->processQuestion($apiQuestion);

                        if ($result['status'] === 'success') {
                            $inserted++;
                        } elseif ($result['status'] === 'updated') {
                            $updated++;
                        } elseif ($result['status'] === 'ignored') {
                            $ignored++;
                            $ignoredItems[] = [
                                'index' => $result['index'] ?? '?',
                                'title' => $result['title'] ?? 'N/A',
                                'reason' => $result['reason'] ?? 'unknown',
                                'full_data' => $result['full_data'] ?? []
                            ];
                        } elseif ($result['status'] === 'error') {
                            $errors++;
                            $errorMessages[] = "Erro na questão index " . ($result['index'] ?? '?') . ": " . ($result['reason'] ?? 'Erro desconhecido');
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $errorMessages[] = "Exceção na questão index " . ($apiQuestion['index'] ?? '?') . ": " . $e->getMessage();
                    }

                    // --- RATE LIMITING ---
                    // Delay de 0.2 segundos (200.000 microssegundos)
                    // para não estourar o limite de requisições da API ENEM Dev
                    usleep(200000);
                }

                $hasMore = $metadata['hasMore'] ?? false;
                $offset += $limit;

                sleep(2);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("EnemImport getExamQuestions Falhou: Ano {$this->year}, Offset {$offset}", ['msg' => $e->getMessage()]);
                throw $e;
            }
        }

        // Atualizar os contadores do Log
        $log = \App\Models\EnemImportLog::find($this->importLogId);
        if ($log) {
            $log->increment('inserted_count', $inserted);
            $log->increment('updated_count', $updated);
            $log->increment('ignored_count', $ignored);
            $log->increment('error_count', $errors);

            // Persistir Detalhes de Ignorados (Novidade)
            if (!empty($ignoredItems)) {
                $existingIgnored = $log->ignored_details ?? [];
                $log->update(['ignored_details' => array_merge($existingIgnored, $ignoredItems)]);
            }

            if (!empty($errorMessages)) {
                $existingErrors = $log->errors ?? [];
                $log->update(['errors' => array_merge($existingErrors, $errorMessages)]);
            }
        }
    }
}
