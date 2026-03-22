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
        $context = trim($apiQuestion['context'] ?? '');
        $introduction = trim($apiQuestion['alternativesIntroduction'] ?? '');
        
        $baseTextForHash = $context ?: ($introduction ?: "Questao_ENEM_{$year}_{$index}");

        // 1. Gerar external_id usando também o $index para evitar colisão absoluta
        $organization = 'ENEM';
        $institution = 'INEP';
        $role = 'Estudante';

        $uniqueString = $organization . '|' . $year . '|' . $institution . '|' . $role . '|' . $baseTextForHash . '|' . $index;
        $externalId = md5($uniqueString);

        // 2. Verificar se já existe (Bridge Match para suportar Upsert na VPS)
        // O hash antigo (bugado) gerado para esta questão na VPS original
        $uniqueStringOld = $organization . '|' . $year . '|' . $institution . '|' . $role . '|' . $context;
        $externalIdOld = md5($uniqueStringOld);

        $existingQuestion = \App\Models\Question::where('external_id', $externalId)
            ->orWhere('external_id', $externalIdOld)
            ->first();

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
            $introduction,
            $year,
            $apiQuestion['files'] ?? []
        );

        // 4. Resolver grande área do conhecimento (knowledge_area) e matéria (subject)
        $knowledgeArea = $apiQuestion['discipline'] ?? null;
        $subjectId = $this->resolveSubjectId($apiQuestion['discipline'] ?? 'Geral', $apiQuestion['language'] ?? null);

        // 5. Iniciar transação (Insert ou Upsert)
        try {
            $isUpdate = false;
            
            $question = \Illuminate\Support\Facades\DB::transaction(function () use ($apiQuestion, $existingQuestion, $externalId, $year, $statement, $subjectId, $knowledgeArea, $organization, $institution, $role, $context, &$isUpdate) {

                $theme = ($apiQuestion['language'] ?? null) ? 'Língua Estrangeira: ' . ucfirst($apiQuestion['language']) : null;

                $hasQuestionFiles = !empty($apiQuestion['files']) && collect($apiQuestion['files'])->filter()->isNotEmpty();
                $hasAlternativeFiles = collect($apiQuestion['alternatives'] ?? [])->contains(
                    fn($alt) => !empty($alt['file'])
                );
                $hasInlineImages = preg_match('/!\[.*?\]\(.*?\)|https?:\/\/[^\s"\')]+?\.(?:png|jpg|jpeg|gif|webp|svg)/i', $context . ($apiQuestion['alternativesIntroduction'] ?? ''));

                $hasImage = $hasQuestionFiles || $hasAlternativeFiles || $hasInlineImages;
                $initialStatus = $hasImage ? 'review' : 'approved';

                if ($existingQuestion) {
                    // --- UPSERT BRANCH ---
                    $isUpdate = true;
                    $question = $existingQuestion;
                    
                    $question->update([
                        'external_id' => $externalId, // Cura o hash legado da VPS!
                        'statement' => $statement,
                        'knowledge_area' => $knowledgeArea,
                        'theme' => $theme,
                        'review_status' => $initialStatus,
                    ]);

                    if ($subjectId) {
                        $question->subjects()->syncWithoutDetaching([$subjectId]);
                    }

                    // Limpar antigas alternativas para reconstruir puras (livres do bug de double-embedding)
                    $question->alternatives()->delete();
                } else {
                    // --- INSERT BRANCH ---
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
                }

                // Inserção Limpa das Alternativas
                foreach ($apiQuestion['alternatives'] as $altData) {
                    $imagePath = null;
                    $content = trim($altData['text'] ?? '');

                    if (!empty($altData['file'])) {
                        $imagePath = $this->downloadImage($altData['file'], $year);
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
                'status' => $isUpdate ? 'updated' : 'success',
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
    protected function formatStatement(string $context, string $introduction, int $year, array $files = []): string
    {
        $statement = $context;

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
            if (empty($statement)) {
                // Se context for vazio, a introduction se torna o texto base puro
                $statement = $introduction;
            } else {
                // Se ambos existirem, concatena adicionando negrito para o comando da questão
                $statement .= "\n\n**" . $introduction . "**";
            }
        }

        // 2. Processar imagens anexas (files[]) da API ENEM Dev
        foreach ($files as $fileUrl) {
            if (!empty($fileUrl)) {
                $localUrl = $this->downloadImage($fileUrl, $year);
                if ($localUrl) {
                    // Prevenir inserção dupla: checamos se a URL local já foi inserida pelo Regex do Passo 1
                    if (!str_contains($statement, $localUrl)) {
                        $statement .= "\n\n![Imagem de Apoio]({$localUrl})";
                    }
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
