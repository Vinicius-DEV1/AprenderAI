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
        $statement = $this->formatStatement(
            $context,
            $apiQuestion['alternativesIntroduction'] ?? '',
            $year,
            $apiQuestion['files'] ?? []
        );

        // 4. Resolver grande área do conhecimento (knowledge_area) e matéria (subject)
        // Correção de mapeamento: 'discipline' = grande área -> knowledge_area
        //                        'language'   = idioma específico -> subject (ex: 'Inglês')
        $knowledgeArea = $apiQuestion['discipline'] ?? null;
        $subjectId = $this->resolveSubjectId($apiQuestion['discipline'] ?? 'Geral', $apiQuestion['language'] ?? null);

        // 5. Iniciar transação
        try {
            $question = \Illuminate\Support\Facades\DB::transaction(function () use ($apiQuestion, $externalId, $year, $statement, $subjectId, $knowledgeArea, $organization, $institution, $role, $context) {

                $theme = ($apiQuestion['language'] ?? null) ? 'Língua Estrangeira: ' . ucfirst($apiQuestion['language']) : null;

                // Image Detection: Comprehensive check across all possible sources.
                // 1. Explicitly attached files array at question level.
                $hasQuestionFiles = !empty($apiQuestion['files']) && collect($apiQuestion['files'])->filter()->isNotEmpty();

                // 2. Explicitly attached files at alternative level.
                $hasAlternativeFiles = collect($apiQuestion['alternatives'] ?? [])->contains(
                    fn($alt) => !empty($alt['file'])
                );

                // 3. Inline images in context/introduction via Markdown or URL.
                // We use the same logic as formatStatement to check for presence.
                $hasInlineImages = preg_match('/!\[.*?\]\(.*?\)|https?:\/\/[^\s"\')]+?\.(?:png|jpg|jpeg|gif|webp|svg)/i', $context . ($apiQuestion['alternativesIntroduction'] ?? ''));

                $hasImage = $hasQuestionFiles || $hasAlternativeFiles || $hasInlineImages;

                // review_status semantics:
                //   'review'   → "Contains images, needs manual layout/context check"
                //   'approved' → "Pure text question, safe for public bank"
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
                    'knowledge_area' => $knowledgeArea,
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
                    $content = $altData['text'] ?? '';

                    if (!empty($altData['file'])) {
                        $imagePath = $this->downloadImage($altData['file'], $year);
                        if ($imagePath) {
                            // Ensure the image is rendered in the UI by appending markdown if not present
                            if (!str_contains($content, $imagePath)) {
                                $content = trim($content . "\n\n![Imagem da Alternativa]({$imagePath})");
                            }
                        }
                    }

                    \App\Models\QuestionAlternative::create([
                        'question_id' => $question->id,
                        'label' => $altData['letter'] ?? '?',
                        'content' => $content,
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
     * E baixa localmente as imagens do markdown e anexos extras.
     */
    protected function formatStatement(?string $context, ?string $introduction, int $year, array $files = []): string
    {
        $statement = $context ?? '';

        // 1. Processar Markdown Imagens e URLs diretas no texto
        // Regex robusto para capturar links de imagem comuns inclusive sem markdown
        $statement = preg_replace_callback('/(!\[.*?\]\((https?:\/\/.*?)\))|(https?:\/\/[^\s"\')]+?\.(?:png|jpg|jpeg|gif|webp|svg))/i', function ($matches) use ($year) {
            // Se for Markdown ![](), a URL está no index 2. Se for URL direta, está no index 3.
            $url = !empty($matches[2]) ? $matches[2] : $matches[3];
            $alt = !empty($matches[1]) && str_starts_with($matches[1], '!') ? 'Imagem do enunciado' : '';

            $localUrl = $this->downloadImage($url, $year);
            if ($localUrl) {
                return "![{$alt}]({$localUrl})";
            }

            return $matches[0];
        }, $statement);

        if (!empty($introduction)) {
            $statement .= "\n\n**" . trim($introduction) . "**";
        }

        // 2. Processar imagens anexas (files[]) da API ENEM Dev
        // Evita duplicar se a imagem já foi processada via Regex no passo 1 (check via MD5 seria ideal, mas aqui fazemos simples)
        foreach ($files as $fileUrl) {
            if (!empty($fileUrl) && !str_contains($statement, md5($fileUrl))) {
                $localUrl = $this->downloadImage($fileUrl, $year);
                if ($localUrl) {
                    $statement .= "\n\n![Imagem de Apoio]({$localUrl})";
                }
            }
        }

        return trim($statement);
    }

    /**
     * Faz o download de uma imagem externa e a salva no storage local.
     * 
     * @param string $url URL original da imagem.
     * @param int $year Ano da prova.
     * @return string|null Retorna a URL pública local ou null.
     */
    protected function downloadImage(string $url, int $year): ?string
    {
        try {
            $url = trim($url);
            if (empty($url))
                return null;

            // 1. Gerar nome determinístico
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'enem_' . $year . '_' . md5($url) . '.' . $extension;
            $path = "questions/images/{$year}/{$filename}";

            // 2. Cache local
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return \Illuminate\Support\Facades\Storage::url($path);
            }

            // 3. Download com User-Agent para evitar bloqueios criminosos da CDN
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'AprovadoAI-Importer/2.0'
            ])->timeout(20)->get($url);

            if ($response->successful()) {
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $response->body());
                return \Illuminate\Support\Facades\Storage::url($path);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Falha ao baixar imagem ENEM: {$url} - " . $e->getMessage());
        }

        return null;
    }

    /**
     * Normaliza a área do conhecimento e o idioma para o Subject correto.
     *
     * Mapeamento corrigido:
     *   Se houver 'language' (ex: 'ingles'), o Subject será o idioma (ex: 'Inglês').
     *   Senão, usa a grande área (ex: 'Linguagens', 'Matemática') como Subject fallback.
     *
     * A 'grande área' (discipline) agora é persistida na coluna 'knowledge_area' da questão.
     */
    protected function resolveSubjectId(string $discipline, ?string $language = null): ?int
    {
        // Mapa do idioma da API -> nome do Subject
        $languageMap = [
            'ingles' => 'Inglês',
            'espanhol' => 'Espanhol',
            'ingles_2' => 'Inglês',
        ];

        // Se NÃO houver idioma específico no mapa, retornamos null.
        // Isso garante que a questão NÃO terá um Subject (matéria) vinculado,
        // ficando classificada apenas pela 'knowledge_area' (grande área).
        if (empty($language) || !isset($languageMap[strtolower($language)])) {
            return null;
        }

        $subjectName = $languageMap[strtolower($language)];

        $subject = \App\Models\Subject::firstOrCreate(
            ['name' => $subjectName],
            ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'enem']
        );

        return $subject->id;
    }
}
