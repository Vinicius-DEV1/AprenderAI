<?php

namespace App\Jobs;

use App\Models\QuestionImport;
use App\Services\QuestionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessQuestionImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Define a fila específica de baixa prioridade para não engarrafar chats/simulados.
     */
    public $queue = 'import';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public QuestionImport $import,
        public string $zipPath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(QuestionImportService $service): void
    {
        try {
            $service->processZipFromJob($this->import, $this->zipPath);
            
            // Depois de processado com sucesso, remove o zip temporário
            if (Storage::disk('local')->exists($this->zipPath)) {
                Storage::disk('local')->delete($this->zipPath);
            }
        } catch (\Throwable $e) {
            Log::error("[ProcessQuestionImportJob] Falha crítica no background job na importação {$this->import->id}: " . $e->getMessage());
            
            $this->import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
