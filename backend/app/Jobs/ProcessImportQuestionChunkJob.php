<?php

namespace App\Jobs;

use App\Models\QuestionImport;
use App\Services\QuestionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job responsável por processar um CHUNK (fatia) de questões de um SQLite de importação.
 *
 * ARQUITETURA PARALELA:
 * Este job é despachado pelo orquestrador (ProcessQuestionImportJob) múltiplas vezes,
 * cada um com diferentes valores de $offset e $limit. Isso permite que vários workers
 * da fila 'import' processem diferentes partes do mesmo arquivo simultaneamente.
 *
 * Exemplo: Um SQLite com 1000 questões e chunks de 100:
 *   Job 1 → offset=0,   limit=100 (questões 1-100)
 *   Job 2 → offset=100, limit=100 (questões 101-200)
 *   ...
 *   Job 10 → offset=900, limit=100 (questões 901-1000)
 *
 * Segurança contra concorrência:
 *   - A migration `add_unique_constraints_for_parallel_import` garante índices UNIQUE
 *     em questions.external_id, subjects.slug e topics.slug.
 *   - O QuestionImportService usa `firstOrCreate` e trata exceções de Duplicate Entry,
 *     tornando o processamento paralelo idempotente e seguro.
 */
class ProcessImportQuestionChunkJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número de tentativas em caso de falha.
     * Maior que o padrão (3) pois pode haver contenção de banco em lotes paralelos.
     */
    public int $tries = 3;

    /**
     * Timeout estendido para processamento de chunks grandes.
     * 700s (≈ 12min) para acomodar chunks com muitas imagens.
     */
    public int $timeout = 700;

    /**
     * Cria um novo job de chunk de importação.
     *
     * @param QuestionImport $import  O registro pai da importação (controle de progresso).
     * @param string $dbPath          Caminho absoluto para o arquivo SQLite temporário.
     * @param int $offset             Índice da primeira questão deste chunk no SQLite.
     * @param int $limit              Quantidade máxima de questões a processar neste chunk.
     * @param int $chunkIndex         Número do chunk (para logging/debug).
     */
    public function __construct(
        public readonly QuestionImport $import,
        public readonly string $dbPath,
        public readonly int $offset,
        public readonly int $limit,
        public readonly int $chunkIndex = 0,
    ) {
        // Chunks run in the standard 'default' queue.
        // Concurrency is controlled by the semaphore in ProcessQuestionImportJob.
        $this->onQueue('default');
    }

    /**
     * Executa o processamento deste chunk de questões.
     */
    public function handle(QuestionImportService $service): void
    {
        $this->import->refresh();
        if ($this->import->status === 'reverted') {
            Log::info("[ProcessImportQuestionChunkJob] Lote #{$this->import->id} foi revertido. Parando processamento do chunk #{$this->chunkIndex}.");
            return;
        }

        Log::info("[ProcessImportQuestionChunkJob] Iniciando chunk #{$this->chunkIndex}", [
            'import_id' => $this->import->id,
            'offset'    => $this->offset,
            'limit'     => $this->limit,
            'db_path'   => $this->dbPath,
        ]);

        try {
            // Delega para o service o processamento efetivo do chunk
            $stats = $service->processChunk($this->dbPath, $this->import, $this->offset, $this->limit, $this->chunkIndex);

            Log::info("[ProcessImportQuestionChunkJob] Chunk #{$this->chunkIndex} finalizado", [
                'import_id' => $this->import->id,
                'processed' => $stats['total'],
                'skipped'   => $stats['skipped'],
            ]);

        } catch (\Throwable $e) {
            Log::error("[ProcessImportQuestionChunkJob] Falha no chunk #{$this->chunkIndex}", [
                'import_id' => $this->import->id,
                'offset'    => $this->offset,
                'error'     => $e->getMessage(),
            ]);

            // Re-lança para que o Laravel marque o job como falho e tente novamente
            throw $e;
        }
    }
}
