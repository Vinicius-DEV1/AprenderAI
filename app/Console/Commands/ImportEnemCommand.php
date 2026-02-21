<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Question;

class ImportEnemCommand extends Command
{
    protected $signature = 'enem:import {--from=2009} {--to=2023}';
    protected $description = 'Importa questões do ENEM (2009-2023) via API com suporte a imagens e filtros estritos';

    private $baseUrl = 'https://api.enem.dev/v1/exams';

    public function handle()
    {
        $from = (int)$this->option('from');
        $to = (int)$this->option('to');

        $this->info("Iniciando importação ENEM de {$from} a {$to}...");
        $this->info("Filtro Estrito: Apenas 'Linguagens' (PT) e 'Matemática'. Excluindo Inglês/Espanhol.");
        $this->info("Imagens: Download e substituição local.");

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
        $limit = 25; // Aumentar limit para eficiência

        while (true) {
            $offset = ($page - 1) * $limit;

            try {
                // Rate Limiting (1 req/sec)
                usleep(500000); // 0.5s

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

                $this->info("Ano {$year} - Página {$page} processada (" . count($questions) . " itens).");
                $page++;

            }
            catch (\Exception $e) {
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

        // Normalização de acentos para busca
        $disciplineSlug = Str::slug($discipline);
        $languageSlug = $language ?Str::slug($language) : '';

        // Lógica Matemática
        if (str_contains($disciplineSlug, 'matematica')) {
            $targetSubject = 'matemática';
        }
        // Lógica Linguagens (Português)
        elseif (str_contains($disciplineSlug, 'linguagens') || str_contains($disciplineSlug, 'portugues')) {

            // Exclusão Explícita de Língua Estrangeira
            $isForeign = false;

            if (str_contains($languageSlug, 'ingles') || str_contains($languageSlug, 'espanhol')) {
                $isForeign = true;
            }
            if (str_contains($disciplineSlug, 'ingles') || str_contains($disciplineSlug, 'espanhol')) {
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
        $rawStatement = trim($context . "\n\n" . $intro);

        // Fallback
        if (empty(trim($rawStatement))) {
            $rawStatement = $q['title'] ?? 'Questão sem enunciado de texto (verificar imagem).';
        }

        // 2.1 TRATAMENTO DE IMAGENS
        $statement = $this->processImages($rawStatement, $q['files'] ?? [], $year);

        // Mapear alternativas
        $alternativesMap = [];
        $correctLetter = 'A'; // Default

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

        if (isset($q['correctAlternative'])) {
            $correctLetter = strtoupper($q['correctAlternative']);
        }
        ksort($alternativesMap);

        // 3. DEDUPLICAÇÃO (External ID > Statement Check)
        $externalId = $q['id'] ?? null;

        if ($externalId) {
            $exists = Question::where('external_id', $externalId)->exists();
        }
        else {
            // Fallback para statement + year se não tiver ID (improvável na API nova)
            $exists = Question::where('type', 'enem')
                ->where('year', $year)
                ->where('statement', $statement)
                ->exists();
        }

        if ($exists) {
            $stats['skipped_duplicate']++;
            return;
        }

        // 4. INSERÇÃO
        $question = Question::create([
            'type' => 'enem',
            'theme' => null,
            'difficulty' => 'medium',
            'year' => $year,
            'statement' => $statement,
            'alternatives' => $alternativesMap,
            'correct_answer' => $correctLetter,
            'explanation' => null,
            'source' => 'enem_api',
            'external_id' => $externalId,
            'origin' => 'ENEM ' . $year
        ]);

        // N:N Relationship:
        // Find or create the subject model and attach it to the question via the pivot table.
        // This replaces the old single-column 'subject' logic.
        $subjectModel = \App\Models\Subject::firstOrCreate(
        ['name' => $targetSubject],
        ['slug' => \Illuminate\Support\Str::slug($targetSubject), 'type' => 'enem']
        );
        $question->subjects()->attach($subjectModel->id);

        $stats['imported']++;
        $stats['by_year'][$year]++;
        $stats['by_subject'][$targetSubject]++;
    }

    /**
     * Parseia o enunciado em busca de URLs de imagens para download local.
     */
    private function processImages($text, $files, $year)
    {
        // 1. Procurar por links de imagens (Markdown ou URL direta terminada em imagem)
        // O regex captura URLs que terminam com extensões de imagem comuns.
        return preg_replace_callback('/(https?:\/\/[^\s"\')]+?\.(?:png|jpg|jpeg|gif|webp))/i', function ($matches) use ($year) {
            $url = $matches[1];
            return $this->downloadImage($url, $year);
        }, $text);
    }

    /**
     * Realiza o download e retorna a URL pública local.
     * Segue o padrão unificado: questions/images/{year}/enem_{year}_{md5}.ext
     */
    private function downloadImage($url, $year)
    {
        try {
            // 1. Gerar nome determinístico baseado no MD5 da URL original.
            // Isso permite a deduplicação automática no storage.
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?? 'jpg';
            $filename = 'enem_' . $year . '_' . md5($url) . '.' . $extension;
            $path = "questions/images/{$year}/{$filename}";

            // 2. Verificar se já existe para evitar re-download
            if (Storage::disk('public')->exists($path)) {
                return Storage::url($path);
            }

            // 3. Efetuar o download do conteúdo
            $contents = @file_get_contents($url); 
            if ($contents) {
                // 4. Salvar no diretório público
                Storage::disk('public')->put($path, $contents);
                return Storage::url($path);
            }
        }
        catch (\Exception $e) {
            // Em caso de falha, retorna a URL original como fallback
        }

        return $url;
    }


    private function printFinalStats($stats)
    {
        $this->info("\n================================================");
        $this->info("RELATÓRIO FINAL DE IMPORTAÇÃO (ENEM)");
        $this->info("================================================");

        $this->info("Total Geral Inserido: " . $stats['imported']);
        $this->info("Filtrados (Ignorados): " . $stats['skipped_filter']);
        $this->info("Duplicados: " . $stats['skipped_duplicate']);
        $this->info("Erros de Requisição: " . $stats['errors']);

        $this->info("\n--- Por Matéria ---");
        $this->info("Português: " . $stats['by_subject']['português']);
        $this->info("Matemática: " . $stats['by_subject']['matemática']);

        $this->info("\n--- Por Ano ---");
        foreach ($stats['by_year'] as $y => $count) {
            $this->info("Ano {$y}: {$count} questões");
        }
    }
}
