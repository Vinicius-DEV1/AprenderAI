<?php

namespace App\Services;

/**
 * EnemImportService
 * 
 * Orquestra a transformação de dados crus vindo da API ENEM Dev para o nosso domínio.
 * Lida com:
 * 1. Idempotência via External ID (evita duplicatas).
 * 2. Download e armazenamento local de imagens para evitar hotlinking.
 * 3. Normalização de matérias e disciplinas.
 * 4. Processamento de markdown de imagens nos enunciados.
 */
class EnemImportService
{
    /**
     * Tenta processar e salvar uma questão vinda da API.
     * Ignora se já existir.
     * Retorna a Question criada, ou null se foi ignorada.
     */
    public function processQuestion(array $apiQuestion): ?\App\Models\Question
    {
        $year = $apiQuestion['year'];
        $discipline = strtolower($apiQuestion['discipline']);
        $index = $apiQuestion['index'];

        // 1. Gerar external_id garantido e único
        $externalId = "enem_{$year}_{$discipline}_{$index}";

        // 2. Verificar se já existe
        if (\App\Models\Question::where('external_id', $externalId)->exists()) {
            return null; // Já importada, idempotente
        }

        // 3. Processar Enunciado e Imagens
        $statement = $this->formatStatement($apiQuestion['context'], $apiQuestion['alternativesIntroduction'], $year);

        // 4. Normalizar Disciplina
        $subjectId = $this->resolveSubjectId($apiQuestion['discipline']);

        // 5. Iniciar transação para garantir integridade
        return \Illuminate\Support\Facades\DB::transaction(function () use ($apiQuestion, $externalId, $year, $statement, $subjectId) {
            
            // Tratamento de idioma embutido no tópico ou theme, se aplicável
            $theme = $apiQuestion['language'] ? 'Língua Estrangeira: ' . ucfirst($apiQuestion['language']) : null;

            // Criar a questão
            $question = \App\Models\Question::create([
                'external_id' => $externalId,
                'type' => 'enem',
                'format' => 'multiple_choice',
                'difficulty' => 'medium', // Default, já que a API não fornece
                'year' => $year,
                'statement' => $statement,
                'source' => 'manual', // or api
                'theme' => $theme,
                'review_status' => 'approved', // Direto da API
                'origin' => 'ENEM Dev API',
            ]);

            if ($subjectId) {
                $question->subjects()->attach($subjectId);
            }

            // Criar as alternativas
            foreach ($apiQuestion['alternatives'] as $altData) {
                
                $imagePath = null;
                // Baixar imagem da alternativa, se houver
                if (!empty($altData['file'])) {
                    $imagePath = $this->downloadImage($altData['file'], "enem/{$year}/alternatives");
                }

                \App\Models\QuestionAlternative::create([
                    'question_id' => $question->id,
                    'label' => $altData['letter'],
                    'content' => $altData['text'] ?? '',
                    'image_path' => $imagePath,
                    'is_correct' => $altData['isCorrect'] ?? false,
                ]);
            }

            return $question;
        });
    }

    /**
     * Formata o statement juntando o contexto com a introdução.
     * E baixa localmente as imagens do markdown.
     */
    protected function formatStatement(?string $context, ?string $introduction, int $year): string
    {
        $statement = $context ?? '';

        // Processar Markdown Imagens: ![](https://...)
        // Fazer download das imagens e trocar a URL para a local Storage
        $statement = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/', function ($matches) use ($year) {
            $alt = $matches[1];
            $url = $matches[2];
            
            $localPath = $this->downloadImage($url, "enem/{$year}/context");
            if ($localPath) {
                $localUrl = \Illuminate\Support\Facades\Storage::url($localPath);
                return "![{$alt}]({$localUrl})";
            }
            
            return $matches[0]; // Manter original se falhar
        }, $statement);

        if (!empty($introduction)) {
            $statement .= "\n\n**" . trim($introduction) . "**";
        }

        return $statement;
    }

    /**
     * Download da imagem para o disco local (public storage).
     */
    protected function downloadImage(string $url, string $pathPrefix): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);
            
            if ($response->successful()) {
                // Obter extensão
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (empty($extension)) $extension = 'jpg';
                
                $filename = \Illuminate\Support\Str::uuid() . '.' . $extension;
                $path = "{$pathPrefix}/{$filename}";
                
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $response->body());
                
                return $path;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Falha ao baixar imagem ENEM Dev: {$url} - " . $e->getMessage());
        }

        return null;
    }

    /**
     * Normaliza a disciplina da API para o nosso Subject.
     */
    protected function resolveSubjectId(string $discipline): ?int
    {
        $map = [
            'ciencias-humanas' => 'Ciências Humanas',
            'ciencias-natureza' => 'Ciências da Natureza',
            'linguagens' => 'Linguagens',
            'matematica' => 'Matemática',
        ];

        $subjectName = $map[strtolower($discipline)] ?? ucfirst($discipline);

        $subject = \App\Models\Subject::firstOrCreate(
            ['name' => $subjectName],
            ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'enem']
        );

        return $subject->id;
    }
}
