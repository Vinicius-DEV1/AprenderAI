<?php

namespace App\Jobs;

use App\Models\QuestionImport;
use App\Services\QuestionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * ORQUESTRADOR de importação de questões via ZIP.
 *
 * Responsabilidades deste job:
 *   1. Receber o caminho do ZIP salvo no storage compartilhado.
 *   2. Extrair o ZIP para um diretório temporário único (UUID).
 *   3. Migrar as imagens para o storage permanente.
 *   4. Contar o total de questões no SQLite.
 *   5. DIVIDIR o trabalho em múltiplos chunks.
 *   6. Despachar um ProcessImportQuestionChunkJob para cada chunk.
 *
 * Por que dividir em chunks?
 *   Com 4 workers (configurado no docker-compose ai-worker replicas: 4), cada
 *   chunk pode ser processado por um worker diferente ao mesmo tempo.
 *   Um arquivo com 2000 questões e chunks de 100 gera 20 jobs — todos
 *   consumidos em paralelo pelos 4 workers, resultando em ~5x mais velocidade.
 *
 * Segurança:
 *   - O diretório temporário usa UUID para evitar colisão entre importações simultâneas.
 *   - O SQLite não é deletado aqui; cada chunk job precisa dele para ler as questões.
 *   - O CLEANUP do diretório temporário é feito apenas após todos os chunks finalizarem
 *     (via um FinalizeImportJob despachado com delay, ou por um Job de finalização).
 *
 * @see ProcessImportQuestionChunkJob  Jobs filhos que fazem o trabalho real.
 * @see QuestionImportService          Service com a lógica de parsing do SQLite.
 */
class ProcessQuestionImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Timeout estendido pois a extração do ZIP pode levar muito tempo para arquivos grandes (10GB).
     * O valor de 3600s (1 hora) cobre o cenário mais pesado.
     */
    public int $timeout = 3600;

    /**
     * Número máximo de questões por chunk.
     * 
     * TUNNING: Ajuste conforme as necessidades:
     *   - Valor menor (50) → mais jobs, mais paralelismo, mais overhead de I/O no SQLite.
     *   - Valor maior (200) → menos jobs, menos overhead, mas menos paralelismo.
     * 100 é um bom equilíbrio para a maioria dos casos.
     */
    private const CHUNK_SIZE = 100;

    /**
     * Cria um novo job orquestrador.
     *
     * @param QuestionImport $import  Registro de controle do lote (status, progresso).
     * @param string $zipPath         Caminho do ZIP dentro do storage 'public' compartilhado.
     */
    public function __construct(
        public QuestionImport $import,
        public string $zipPath
    ) {
        // O ORQUESTRADOR roda na fila 'default' (ou 'essays') 
        // para não competir com os chunks que vão encher a fila 'import'.
        $this->onQueue('default');
    }

    /**
     * Orquestra a extração e despache dos jobs filhos (chunks).
     */
    public function handle(QuestionImportService $service): void
    {
        Log::info("[ProcessQuestionImportJob] Iniciando orquestração do lote #{$this->import->id}", [
            'zip_path' => $this->zipPath,
        ]);

        // Marca o lote como em processamento imediatamente
        $this->import->refresh();
        if ($this->import->status === 'reverted') {
            Log::info("[ProcessQuestionImportJob] Lote #{$this->import->id} foi revertido. Abortando.");
            return;
        }
        
        $this->import->update(['status' => 'processing']);

        try {
            $absoluteZipPath = Storage::disk('public')->path($this->zipPath);

            // ETAPA 1: O service extrai o ZIP, migra as imagens e retorna os caminhos necessários.
            // Isso é feito DE UMA VEZ pelo orquestrador (não paralelizável, pois é I/O sequencial).
            $orchestrationData = $service->prepareForParallelProcessing($this->import, $absoluteZipPath);

            $dbPath      = $orchestrationData['db_path'];
            $totalCount  = $orchestrationData['total_count'];
            $tmpDir      = $orchestrationData['tmp_dir'];

            // ETAPA 2: Atualiza o total para que a barra de progresso do frontend funcione
            $this->import->update(['total_questions' => $totalCount]);

            // ETAPA 3: Calcula quantos chunks são necessários
            $totalChunks = (int) ceil($totalCount / self::CHUNK_SIZE);

            Log::info("[ProcessQuestionImportJob] Lote #{$this->import->id}: {$totalCount} questões → {$totalChunks} chunks de " . self::CHUNK_SIZE, [
                'db_path'      => $dbPath,
                'total_chunks' => $totalChunks,
            ]);

            // ETAPA 4: Despacha um job filho para cada chunk
            // O Laravel/Redis distribui esses jobs entre os workers disponíveis.
            for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
                $offset = $chunkIndex * self::CHUNK_SIZE;

                ProcessImportQuestionChunkJob::dispatch(
                    $this->import,  // Registro pai compartilhado (para atualizar processed_questions)
                    $dbPath,        // Caminho do SQLite (todos os chunks lêem o mesmo arquivo)
                    $offset,        // Posição inicial deste chunk no SQLite
                    self::CHUNK_SIZE, // Limite de registros por chunk
                    $chunkIndex + 1   // Número do chunk para logging (começa em 1)
                );
            }

            // Despacha o job de finalização com delay para aguardar os chunks
            // O delay é estimado: CHUNK_SIZE questões ~= 30s de processamento.
            // O FinalizeImportJob vai verificar se todos os chunks foram processados.
            $estimatedDelaySeconds = max(60, $totalChunks * 5);
            FinalizeImportJob::dispatch($this->import, $tmpDir, $this->zipPath)
                ->delay(now()->addSeconds($estimatedDelaySeconds));

            Log::info("[ProcessQuestionImportJob] {$totalChunks} chunks despachados para o lote #{$this->import->id}. Finalização em ~{$estimatedDelaySeconds}s.");

        } catch (\Throwable $e) {
            // Falha crítica na extração ou na leitura do SQLite
            $this->import->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("[ProcessQuestionImportJob] Falha crítica na orquestração do lote #{$this->import->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
