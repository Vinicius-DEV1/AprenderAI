<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Question;

class ImportEnemCommand extends Command
{
    protected $signature = 'enem:import {--from=2009} {--to=2023}';
    protected $description = 'Importa questões do ENEM (2009-2023) via api.enem.dev (Apenas Português e Matemática)';

    public function handle()
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        $this->info("Iniciando importação ENEM de {$from} a {$to}...");
        $this->info("Filtro Estrito: Apenas 'linguagens' (PT) e 'matematica'.");

        $stats = [
            'imported' => 0,
            'skipped_filter' => 0,
            'skipped_duplicate' => 0,
            'errors' => 0
        ];

        for ($year = $from; $year <= $to; $year++) {
            $this->processYear($year, $stats);
        }

        $this->info('------------------------------------------------');
        $this->info("Importação Finalizada!");
        $this->info("Total Importado: {$stats['imported']}");
        $this->info("Total Pulado (Filtro): {$stats['skipped_filter']}");
        $this->info("Total Pulado (Duplicado): {$stats['skipped_duplicate']}");
        $this->info("Erros: {$stats['errors']}");
        $this->info('------------------------------------------------');
    }

    private function processYear($year, &$stats)
    {
        $this->line("Processando ano {$year}...");
        $page = 1;

        while (true) {
            try {
                // Rate Limiting
                sleep(1);

                $url = "https://api.enem.dev/v1/exams/{$year}/questions?page={$page}";
                $response = Http::get($url);

                if ($response->status() === 429) {
                    $this->warn("Rate limit atingido. Aguardando 5s...");
                    sleep(5);
                    continue;
                }

                if ($response->failed()) {
                    $this->error("Erro ao acessar API (Ano {$year}, Pág {$page}): " . $response->status());
                    $stats['errors']++;
                    break;
                }

                $data = $response->json();
                $questions = $data['questions'] ?? [];

                if (empty($questions)) {
                    break; // Fim da paginação
                }

                DB::beginTransaction();
                foreach ($questions as $q) {
                    $this->processQuestion($q, $year, $stats);
                }
                DB::commit();

                $this->info("Ano {$year} - Página {$page} processada.");

                // Verificar se tem mais páginas
                $hasMore = $data['metadata']['hasMore'] ?? false;
                if (!$hasMore) {
                    break;
                }

                $page++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error($e->getMessage());
                $stats['errors']++;
                break;
            }
        }
    }

    private function processQuestion($q, $year, &$stats)
    {
        // 1. FILTRO ESTRITO
        $discipline = $q['discipline'] ?? '';
        $language = $q['language'] ?? null; // null = português (geralmente)

        $targetSubject = null;

        if ($discipline === 'matematica') {
            $targetSubject = 'matemática';
        } elseif ($discipline === 'linguagens' && $language === null) {
            // Apenas Linguagens SEM língua estrangeira (inglês/espanhol)
            $targetSubject = 'português';
        }

        if (!$targetSubject) {
            $stats['skipped_filter']++;
            return;
        }

        // 2. PREPARA DADOS
        $context = $q['context'] ?? '';
        $intro = $q['alternativesIntroduction'] ?? '';
        $statement = trim($context . "\n\n" . $intro);

        // Mapear alternativas para formato JSON: Key (A,B,C,D,E) => Text
        $alternativesMap = [];
        foreach ($q['alternatives'] ?? [] as $alt) {
            if (isset($alt['letter']) && isset($alt['text'])) {
                $alternativesMap[$alt['letter']] = $alt['text'];
            }
        }

        // Ordenar chaves para consistência no hash
        ksort($alternativesMap);
        $alternativesJson = json_encode($alternativesMap);

        // 3. DEDUPLICAÇÃO PERSISTENTE (HASH + DB)
        // Verificar duplicidade exata no banco
        $exists = Question::where('type', 'enem')
            ->where('year', $year)
            ->where('statement', $statement) // Statement exato
            ->exists();

        // Opcional: hash check se statement for muito longo ou instável, 
        // mas query direta é mais seguro para "mesma questão".
        // Se quiser ser ultra seguro contra espaços em branco:
        // $exists = Question::where('type', 'enem')->where('year', $year)
        //          ->whereRaw("SHA1(statement) = ?", [sha1($statement)])->exists();

        if ($exists) {
            $stats['skipped_duplicate']++;
            return;
        }

        // 4. INSERÇÃO
        Question::create([
            'type' => 'enem',
            'subject' => $targetSubject,
            'theme' => null, // API não fornece tópico granular confiavelmente
            'difficulty' => 'medium', // Default
            'year' => $year,
            'statement' => $statement,
            'alternatives' => $alternativesMap, // Cast array automaticamente cf. model
            'correct_answer' => $q['correctAlternative'] ?? 'A',
            'explanation' => null,
            'source' => 'enem_real_2009_2023',
        ]);

        $stats['imported']++;
    }
}
