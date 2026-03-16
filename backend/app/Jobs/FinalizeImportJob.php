<?php

namespace App\Jobs;

use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Job de finalização do processo de importação paralela.
 *
 * É despachado pelo ProcessQuestionImportJob (orquestrador) com um delay
 * estimado para aguardar que todos os chunks sejam processados pelos workers.
 *
 * Responsabilidades:
 *   1. Verificar se todos os chunks já foram processados (contando os itens no DB).
 *   2. Se ainda há chunks pendentes, reagendar a si mesmo com novo delay.
 *   3. Se todos os chunks foram processados, atualizar o status do import para 'completed'
 *      e limpar os arquivos temporários (SQLite e ZIP).
 */
class FinalizeImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número máximo de reagendamentos antes de desistir.
     * Evita loops infinitos se algum chunk falhar permanentemente.
     */
    private const MAX_RETRIES = 30;

    /**
     * Delay em segundos entre verificações de progresso.
     */
    private const RETRY_DELAY_SECONDS = 30;

    /**
     * Cria um novo job de finalização.
     *
     * @param QuestionImport $import    Registro do lote de importação.
     * @param string $tmpDir            Diretório temporário de extração para limpeza.
     * @param string $zipPath           Caminho do ZIP no storage 'public' para deletar.
     * @param int $retryCount           Contador de quantas vezes já re-agendamos.
     */
    public function __construct(
        public readonly QuestionImport $import,
        public readonly string $tmpDir,
        public readonly string $zipPath,
        public readonly int $retryCount = 0,
    ) {
        $this->onQueue('import');
    }

    /**
     * Verifica e finaliza a importação quando todos os chunks estão prontos.
     */
    public function handle(): void
    {
        // Recarrega o import do banco para ter o estado mais atual
        $import = $this->import->fresh();

        // Se o import já foi finalizado (completed/failed/reverted) por outra causa, para aqui.
        if (!in_array($import->status, ['pending', 'processing'])) {
            Log::info("[FinalizeImportJob] Import #{$import->id} já está com status '{$import->status}'. Nada a fazer.");
            $this->cleanup();
            return;
        }

        // Conta quantos itens já foram salvos no banco (via QuestionImportItem)
        $processedCount = QuestionImportItem::where('import_id', $import->id)->count();

        Log::info("[FinalizeImportJob] Import #{$import->id}: {$processedCount}/{$import->total_questions} questões processadas.", [
            'retry_count' => $this->retryCount,
        ]);

        // Atualiza a contagem de progresso para o frontend em tempo real
        $import->update(['processed_questions' => $processedCount]);

        // Verifica se está completo ou se ainda há work pendente
        $isComplete = ($import->total_questions > 0) && ($processedCount >= $import->total_questions);

        if ($isComplete) {
            // Finalização bem-sucedida: calcula as estatísticas finais e atualiza o status
            $pendingCount  = QuestionImportItem::where('import_id', $import->id)
                ->whereHas('question', fn($q) => $q->whereIn('review_status', ['pending', 'review']))
                ->count();

            $approvedCount = QuestionImportItem::where('import_id', $import->id)
                ->whereHas('question', fn($q) => $q->where('review_status', 'approved'))
                ->count();

            $import->update([
                'status'               => 'completed',
                'processed_questions'  => $processedCount,
                'pending_count'        => $pendingCount,
                'approved_count'       => $approvedCount,
            ]);

            Log::info("[FinalizeImportJob] Import #{$import->id} CONCLUÍDO. Total: {$processedCount}, Pendentes: {$pendingCount}, Aprovadas: {$approvedCount}.");

            // Limpa arquivos temporários
            $this->cleanup();

        } elseif ($this->retryCount >= self::MAX_RETRIES) {
            // Atingiu o limite de reagendamentos: falha definitiva
            Log::error("[FinalizeImportJob] Import #{$import->id} atingiu o limite de {self::MAX_RETRIES} verificações. Marcando como falho.");

            $import->update([
                'status'        => 'failed',
                'processed_questions' => $processedCount,
                'error_message' => "Timeout: apenas {$processedCount} de {$import->total_questions} questões foram processadas após " . (self::MAX_RETRIES * self::RETRY_DELAY_SECONDS) . "s.",
            ]);

            $this->cleanup();

        } else {
            // Chunks ainda em processamento: re-agenda com delay
            Log::info("[FinalizeImportJob] Aguardando chunks. Re-agendando verificação #{$this->retryCount} em " . self::RETRY_DELAY_SECONDS . "s.");

            self::dispatch($this->import, $this->tmpDir, $this->zipPath, $this->retryCount + 1)
                ->delay(now()->addSeconds(self::RETRY_DELAY_SECONDS));
        }
    }

    /**
     * Limpa os arquivos temporários criados durante a extração.
     * É chamado tanto em caso de sucesso quanto de falha definitiva.
     */
    private function cleanup(): void
    {
        // Remove o ZIP do storage compartilhado
        if (Storage::disk('public')->exists($this->zipPath)) {
            Storage::disk('public')->delete($this->zipPath);
            Log::info("[FinalizeImportJob] ZIP removido: {$this->zipPath}");
        }

        // Remove o diretório temporário de extração recursivamente
        if (is_dir($this->tmpDir)) {
            $this->cleanupDir($this->tmpDir);
            Log::info("[FinalizeImportJob] Diretório temporário removido: {$this->tmpDir}");
        }
    }

    /**
     * Remove recursivamente um diretório e todo o seu conteúdo.
     */
    private function cleanupDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->cleanupDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
