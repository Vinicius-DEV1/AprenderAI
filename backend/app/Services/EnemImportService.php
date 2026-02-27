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
     * Retorna um array com o resultado detalhado.
     */
    public function processQuestion(array $apiQuestion): array
    {
        $year = $apiQuestion['year'];
        $index = $apiQuestion['index'] ?? 'N/A';
        $context = $apiQuestion['context'] ?? 'Sem título/enunciado';

        // 1. Gerar external_id
        $organization = 'ENEM';
        $institution = 'INEP';
        $role = 'Estudante';

        $uniqueString = $organization . '|' . $year . '|' . $institution . '|' . $role . '|' . trim($context);
        $externalId = md5($uniqueString);

        // 2. Verificar se já existe (Idempotência)
        if (\App\Models\Question::where('external_id', $externalId)->exists()) {
            return [
                'status' => 'ignored',
                'reason' => 'duplicate',
                'external_id' => $externalId,
                'index' => $index,
                'title' => \Illuminate\Support\Str::limit($context, 100),
                'full_data' => [] // Don't store full data for duplicates to save space
            ];
        }

        // Validação básica de dados essenciais
        if (empty($apiQuestion['alternatives']) || count($apiQuestion['alternatives']) < 2) {
            return [
                'status' => 'ignored',
                'reason' => 'invalid_data',
                'index' => $index,
                'title' => \Illuminate\Support\Str::limit($context, 100),
                'full_data' => $apiQuestion
            ];
        }

        // 3. Processar Enunciado e Imagens
        $statement = $this->formatStatement($context, $apiQuestion['alternativesIntroduction'] ?? '', $year);

        // 4. Normalizar Disciplina
        $subjectId = $this->resolveSubjectId($apiQuestion['discipline'] ?? 'Geral');

        // 5. Iniciar transação
        try {
            $question = \Illuminate\Support\Facades\DB::transaction(function () use ($apiQuestion, $externalId, $year, $statement, $subjectId, $organization, $institution, $role) {

                $theme = ($apiQuestion['language'] ?? null) ? 'Língua Estrangeira: ' . ucfirst($apiQuestion['language']) : null;

                // Image Detection: Use ONLY structured API fields, never markdown heuristics.
                // If the API declares files at question-level or alternative-level, the question
                // requires human review to verify image rendering and context correctness.
                $hasQuestionImages = !empty($apiQuestion['files']);
                $hasAlternativeImages = collect($apiQuestion['alternatives'] ?? [])->contains(
                    function ($alt) {
                        return !empty($alt['file']);
                    }
                );
                $hasImage = $hasQuestionImages || $hasAlternativeImages;

                // review_status semantics:
                //   'review'  → "Has images, requires manual human validation"
                //   'approved' → "No images, can proceed to public question bank"
                $initialStatus = $hasImage ? 'review' : 'approved';

                $question = \App\Models\Question::create([
                    'external_id' => $externalId,
                    'type' => 'enem',
                    'format' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'year' => $year,
                    'statement' => $statement,
                    'source' => 'api',
                    'theme' => $theme,
                    'organization' => $organization,
                    'institution' => $institution,
                    'role' => $role,
                    'review_status' => $initialStatus,
                ]);

                if ($subjectId) {
                    $question->subjects()->attach($subjectId);
                }

                foreach ($apiQuestion['alternatives'] as $altData) {
                    $imagePath = null;
                    if (!empty($altData['file'])) {
                        $imagePath = $this->downloadImage($altData['file'], $year);
                    }

                    \App\Models\QuestionAlternative::create([
                        'question_id' => $question->id,
                        'label' => $altData['letter'] ?? '?',
                        'content' => $altData['text'] ?? '',
                        'image_path' => $imagePath,
                        'is_correct' => $altData['isCorrect'] ?? false,
                    ]);
                }

                return $question;
            });

            return [
                'status' => 'success',
                'question' => $question
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'reason' => $e->getMessage(),
                'index' => $index,
                'title' => \Illuminate\Support\Str::limit($context, 100)
            ];
        }
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

            $localUrl = $this->downloadImage($url, $year);
            if ($localUrl) {
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
     * Faz o download de uma imagem externa e a salva no storage local seguindo o padrão unificado.
     * 
     * O padrão de armazenamento é: /storage/questions/images/{year}/enem_{year}_{md5(url)}.{ext}
     * Isso garante que a mesma imagem (pela URL original) tenha sempre o mesmo caminho local,
     * evitando duplicatas e garantindo consistência com outros importadores (ex: Command).
     * 
     * @param string $url URL original da imagem na API.
     * @param int $year Ano da prova (usado na organização das pastas).
     * @return string|null Retorna a URL pública local (/storage/...) ou null em caso de falha.
     */
    protected function downloadImage(string $url, int $year): ?string
    {
        try {
            // 1. Definir extensão (fallback para jpg se não detectada)
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension))
                $extension = 'jpg';

            // 2. Gerar nome de arquivo determinístico baseado no MD5 da URL original.
            // Isso permite que o sistema saiba se já baixou essa imagem anteriormente.
            $filename = 'enem_' . $year . '_' . md5($url) . '.' . $extension;
            $path = "questions/images/{$year}/{$filename}";

            // 3. Verificar existência prévia para economizar banda e tempo de processamento.
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return \Illuminate\Support\Facades\Storage::url($path);
            }

            // 4. Realizar o download da imagem via HTTP
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);

            if ($response->successful()) {
                // 5. Salvar o binário no disco 'public' (storage/app/public)
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $response->body());

                // Retornar a URL final pronta para uso no Markdown/Banco
                return \Illuminate\Support\Facades\Storage::url($path);
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
