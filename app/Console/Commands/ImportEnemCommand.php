<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Question;

class ImportEnemCommand extends Command
{
    protected $signature = 'enem:import {--from=2009} {--to=2023}';
    protected $description = 'Importa questões do ENEM (2009-2023) via API';

    private $baseUrl = 'https://api.enem.dev/v1/exams';

    public function handle()
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        $this->info("Iniciando importação ENEM de {$from} a {$to}...");
        $this->info("Filtro Estrito: Apenas 'linguagens' (PT) e 'matematica'.");

        $globalStats = [
            'imported' => 0,
            'skipped_filter' => 0,
            'skipped_duplicate' => 0,
            'errors' => 0,
            'by_year' => [],
            'by_subject' => [
                'português' => 0,
                'matemática' => 0
            ]
        ];

        // Inicializar stats por ano
        for ($y = $from; $y <= $to; $y++) {
            $globalStats['by_year'][$y] = 0;
        }

        for ($year = $from; $year <= $to; $year++) {
            $this->processYear($year, $globalStats);
        }

        $this->printFinalStats($globalStats);
    }

    private function processYear($year, &$stats)
    {
        $this->line("Processando ano {$year}...");
        $page = 1;
        $failedPages = [];
        $limit = 10; // API usually limits to 10 or 20

        while (true) {
            $offset = ($page - 1) * $limit;
            
            try {
                // Rate Limiting (1 req/sec)
                usleep(1000000); // 1s

                $response = Http::timeout(30)->get("{$this->baseUrl}/{$year}/questions", [
                    'limit' => $limit,
                    'offset' => $offset, 
                ]);

                if ($response->status() === 429) {
                    $this->warn("Rate limit atingido. Aguardando 5s...");
                    sleep(5);
                    continue; // Retry same page
                }

                if ($response->failed()) {
                    $this->error("Erro ao acessar API (Ano {$year}, Pág {$page}): " . $response->status());
                    $stats['errors']++;
                    $failedPages[] = $page;
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
                $hasMore = $data['metadata']['hasMore'] ?? false; // A API retorna hasMore boolean? As vezes é total pages.
                // Ajuste: A API enem.dev retorna um array direto em datas antigas ou paginada?
                // Verificando padrão comum: se vier vazio o array questions, break.
                // Se a API retornar paginação explícita, usar.
                // Assumindo loop até vazio.
                
                $page++;

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Exception Ano {$year} Pág {$page}: " . $e->getMessage());
                $stats['errors']++;
                break;
            }
        }
    }

    private function processQuestion($q, $year, &$stats)
    {
        // 1. FILTRO ESTRITO
        $discipline = isset($q['discipline']) ? strtolower($q['discipline']) : '';
        $language = isset($q['language']) ? strtolower($q['language']) : null;
        
        $targetSubject = null;

        // Normalizar
        if (str_contains($discipline, 'matematica') || str_contains($discipline, 'matemática')) {
            $targetSubject = 'matemática';
        } elseif (str_contains($discipline, 'linguagens') || str_contains($discipline, 'portugues') || str_contains($discipline, 'português')) {
            // Verificar se é lingua estrangeira
            $isForeign = false;
            
            // Check explicit language field (common in recent API data)
            if ($language && (str_contains($language, 'ingles') || str_contains($language, 'espanhol') || str_contains($language, 'inglês'))) {
                $isForeign = true;
            }

            // Check discipline field fallback (just in case)
            if (str_contains($discipline, 'ingles') || str_contains($discipline, 'espanhol') || str_contains($discipline, 'inglês')) {
                $isForeign = true;
            }
            
            if (!$isForeign) {
                $targetSubject = 'português';
            }
        }

        if (!$targetSubject) {
            $stats['skipped_filter']++;
            return;
        }

        // 2. PREPARA DADOS
        $context = $q['context'] ?? '';
        $intro = $q['alternativesIntroduction'] ?? '';
        $statement = trim($context . "\n\n" . $intro);
        
        // Fallback para statement vazio (algumas questoes so tem img ou titulo)
        if (empty($statement)) {
            $statement = $q['title'] ?? 'Sem enunciado (verificar imagem)';
        }

        // Mapear alternativas
        $alternativesMap = [];
        $correctLetter = 'A'; // Default fallback

        // Formato da API enem.dev varia.
        // Opcao A: array de objects [{letter: 'a', text: '...'}, ...]
        // Opcao B: as vezes vem diferente. Focar A.
        
        if (isset($q['alternatives']) && is_array($q['alternatives'])) {
            foreach ($q['alternatives'] as $alt) {
                $letter = strtoupper($alt['letter'] ?? '');
                $text = $alt['text'] ?? '';
                if ($letter) {
                    $alternativesMap[$letter] = $text;
                    if (isset($alt['isCorrect']) && $alt['isCorrect']) {
                        $correctLetter = $letter;
                    }
                }
            }
        }
        
        // Se a API mandar correctAlternative separado
        if (isset($q['correctAlternative'])) {
            $correctLetter = strtoupper($q['correctAlternative']);
        }

        ksort($alternativesMap);

        // 3. DEDUPLICAÇÃO
        // Usar statement exato + ano
        $exists = Question::where('type', 'enem')
            ->where('year', $year)
            ->where('statement', $statement)
            ->exists();

        if ($exists) {
            $stats['skipped_duplicate']++;
            return;
        }

        // 4. INSERÇÃO
        Question::create([
            'type' => 'enem',
            'subject' => $targetSubject,
            'difficulty' => 'medium',
            'year' => $year,
            'statement' => $statement,
            'alternatives' => $alternativesMap,
            'correct_answer' => $correctLetter,
            'source' => 'manual',
        ]);

        $stats['imported']++;
        $stats['by_year'][$year]++;
        $stats['by_subject'][$targetSubject]++;
    }

    private function printFinalStats($stats)
    {
        $this->info("\n================================================");
        $this->info("RELATÓRIO FINAL DE IMPORTAÇÃO (ENEM)");
        $this->info("================================================");
        
        $this->info("Total Geral Inserido: " . $stats['imported']);
        $this->info("Filtrados (Ignorados): " . $stats['skipped_filter']);
        $this->info("Duplicados (Já existiam): " . $stats['skipped_duplicate']);
        $this->info("Erros de Requisição: " . $stats['errors']);
        
        $this->info("\n--- Por Matéria ---");
        $this->info("Português: " . $stats['by_subject']['português']);
        $this->info("Matemática: " . $stats['by_subject']['matemática']);
        
        $this->info("\n--- Por Ano ---");
        foreach ($stats['by_year'] as $y => $count) {
            $this->info("Ano {$y}: {$count} questões");
        }
        
        $this->info("\n================================================");
        
        // Validar no Banco
        $dbCount = Question::where('type', 'enem')->where('source', 'manual')->count();
        $this->info("Confirmação via DB (COUNT): {$dbCount}");
    }
}
