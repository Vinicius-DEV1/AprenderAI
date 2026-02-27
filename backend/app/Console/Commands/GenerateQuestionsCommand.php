<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\QuestionGeneratorService;

class GenerateQuestionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'concurso:generate {--banca= : A banca do concurso (FGV, CEBRASPE, FCC)} {--include-essay=0 : Incluir redacao}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate original questions for competitions';

    /**
     * Execute the console command.
     */
    public function handle(QuestionGeneratorService $service)
    {
        if (!$service->isAiReady()) {
            $this->error("AI key não configurada. Por favor, adicione uma chave ativa na tabela api_keys ou no painel administrativo.");
            return 1;
        }

        $banca = $this->option('banca');

        if (empty($banca)) {
            $this->error('A opção --banca é obrigatória.');
            return 1;
        }

        $banca = strtoupper($banca);
        if (!in_array($banca, ['FGV', 'CEBRASPE', 'FCC'])) {
            $this->error('Banca inválida. Use: FGV, CEBRASPE ou FCC.');
            return 1;
        }

        $includeEssay = (bool) $this->option('include-essay');

        $this->info("Iniciando geração para banca: $banca (Redação: " . ($includeEssay ? 'Sim' : 'Não') . ")");

        try {
            $result = $service->generate($banca, $includeEssay);

            $this->info("Geração concluída com sucesso!");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total Gerado', $result['total_generated']],
                    ['Total Inserido (Novas)', $result['total_inserted']],
                ]
            );

            if ($includeEssay && !empty($result['essay'])) {
                $this->info("\n--- REDAÇÃO GERADA ---");
                $this->line(json_encode($result['essay'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("----------------------");
            }

        } catch (\Exception $e) {
            $this->error("Erro durante a geração: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
