<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Models\QuestionImage;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Serviço responsável pela orquestração do processo de importação de questões.
 * 
 * Este serviço lida com:
 * 1. Extração de arquivos .zip enviados via Painel Admin.
 * 2. Migração de imagens do diretório temporário para o storage permanente da VPS.
 * 3. Leitura e processamento do banco SQLite (.db) gerado pelo scraper.
 * 4. Persistência de questões, alternativas e metadados no banco de produção.
 * 5. Lógica de manipulação de imagens (crop/recorte) durante a fase de revisão.
 * 
 * @package App\Services
 */
class QuestionImportService
{
    // --------------------------------------------------------------------
    // CONSTANTES DE CONFIGURAÇÃO
    // --------------------------------------------------------------------

    /** Disco do Laravel Storage onde as imagens serão salvas definitivamente. */
    private const IMPORT_STORAGE_DISK = 'public';

    /** Subdiretório base dentro do disco de storage para as imagens de questões. */
    private const IMPORT_STORAGE_BASE = 'questoes';

    // --------------------------------------------------------------------

    /**
     * Ponto de entrada principal do fluxo de importação.
     * 
     * Orquestra as etapas de extração, migração de arquivos e importação de dados.
     * Garante que diretórios temporários sejam limpos independentemente do resultado.
     *
     * @param  UploadedFile $zipFile   Arquivo .zip contendo o .db e a pasta 'imagens/'.
     * @param  User         $uploader  Usuário administrador que iniciou o processo.
     * @return QuestionImport          Registro do lote de importação criado.
     * @throws \Exception              Falhas críticas interrompem o processo e marcam o lote como 'failed'.
     */
    public function processZip(UploadedFile $zipFile, User $uploader): QuestionImport
    {
        // Usa o disco 'public' compartilhado para que todos os workers tenham acesso
        $importId = Str::uuid();
        $tmpDir = Storage::disk('public')->path("imports_tmp/{$importId}");
        
        // Garante que o diretório base exista
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        // Registro de auditoria inicial
        $import = QuestionImport::create([
            'batch_name' => $uploader->name . ' — ' . now()->format('d/m/Y H:i'),
            'original_filename' => $zipFile->getClientOriginalName(),
            'uploaded_by' => $uploader->id,
            'status' => 'processing',
        ]);

        try {
            // ETAPA 1: Extração — descompacta o .zip para o servidor
            $this->extractZip($zipFile->getRealPath(), $tmpDir);

            // ETAPA 2: Mapeamento — identifica o SQLite e deduz o slug da banca
            $dbPath = $this->findDatabaseFile($tmpDir);
            $bancaSlug = strtolower(
                preg_replace('/^banco_/', '', pathinfo($dbPath, PATHINFO_FILENAME))
            );

            // ETAPA 3: Ativos — move imagens para o storage definitivo (/public/questoes/{banca})
            $imageMap = $this->migrateImages($tmpDir, $bancaSlug);

            // ETAPA 4: Persistência — converte dados do SQLite para o banco de produção (DB Transaction)
            $stats = $this->importFromDatabase($dbPath, $imageMap, $import, $uploader);

            // Finalização bem-sucedida
            $import->update([
                'total_questions' => $stats['total'],
                'pending_count' => $stats['pending'],
                'approved_count' => $stats['approved'],
                'status' => 'completed',
            ]);

        } catch (\Throwable $e) {
            // Registro de falha para auditoria
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error("[QuestionImportService] Falha na importação do lote #{$import->id}: " . $e->getMessage());
            throw $e;
        } finally {
            // Garante que o servidor não acumule lixo técnico
            $this->cleanupTmpDir($tmpDir);
        }

        return $import;
    }

    /**
     * Ponto de entrada processado por Background Jobs (Fila) — MODO ORIGINAL MONOLÍTICO.
     *
     * Mantido por compatibilidade. Para novos imports, o ProcessQuestionImportJob
     * usa o modo paralelo via prepareForParallelProcessing + processChunk.
     *
     * @param  QuestionImport $import  Registro do lote atual sendo processado.
     * @param  string         $zipPath Caminho do .zip salvo no disk(local).
     * @return void
     * @throws \Exception
     */
    public function processZipFromJob(QuestionImport $import, string $zipPath): void
    {
        $importId = Str::uuid();
        $tmpDir = Storage::disk('public')->path("imports_tmp/{$importId}");
        
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $import->update(['status' => 'processing']);

        try {
            $absoluteZipPath = Storage::disk('public')->path($zipPath);
            $this->extractZip($absoluteZipPath, $tmpDir);

            $dbPath = $this->findDatabaseFile($tmpDir);
            $bancaSlug = strtolower(
                preg_replace('/^banco_/', '', pathinfo($dbPath, PATHINFO_FILENAME))
            );

            $imageMap = $this->migrateImages($tmpDir, $bancaSlug);

            // Carrega o usuário logado que enviou o zip original
            $uploader = User::find($import->uploaded_by);
            if (!$uploader) {
                // Previne crash caso o admin tenha sido deletado
                $uploader = User::first();
            }

            $stats = $this->importFromDatabase($dbPath, $imageMap, $import, $uploader);

            $import->update([
                'total_questions'     => $stats['total'],
                'pending_count'       => $stats['pending'],
                'approved_count'      => $stats['approved'],
                'processed_questions' => $stats['total'],
                'status'              => 'completed',
            ]);

        } catch (\Throwable $e) {
            $import->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::error("[QuestionImportService - Job] Falha no lote #{$import->id}: " . $e->getMessage());
            throw $e;
        } finally {
            $this->cleanupTmpDir($tmpDir);
        }
    }

    /**
     * MODO PARALELO — ETAPA 1: Preparação pelo Orquestrador.
     *
     * Extrai o ZIP, migra imagens para o storage definitivo e conta o total de questões.
     * É executado UMA ÚNICA VEZ pelo ProcessQuestionImportJob (orquestrador).
     *
     * Retorna os dados necessários para que o orquestrador despache os chunks:
     *   - 'db_path'     → caminho absoluto do SQLite extraído (usado por cada chunk)
     *   - 'tmp_dir'     → diretório temporário (será limpo pelo FinalizeImportJob)
     *   - 'total_count' → total de questões no SQLite (para calcular o número de chunks)
     *
     * @param  QuestionImport $import         Registro do lote.
     * @param  string         $absoluteZipPath Caminho absoluto do ZIP no servidor.
     * @return array           ['db_path', 'tmp_dir', 'total_count']
     * @throws \RuntimeException Em caso de falha na extração ou SQLite inválido.
     */
    public function prepareForParallelProcessing(QuestionImport $import, string $absoluteZipPath): array
    {
        // UUID único para este lote: garante que importações simultâneas não colidam. Usa volume compartilhado.
        $importId = Str::uuid();
        $tmpDir = Storage::disk('public')->path("imports_tmp/{$importId}");
        
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        Log::info("[QuestionImportService] Extraindo ZIP para processamento paralelo", [
            'import_id' => $import->id,
            'zip_path'  => $absoluteZipPath,
            'tmp_dir'   => $tmpDir,
        ]);

        // ETAPA 1: Extração do ZIP para o diretório temporário
        $this->extractZip($absoluteZipPath, $tmpDir);

        // ETAPA 2: Identifica o arquivo SQLite e o slug da banca
        $dbPath    = $this->findDatabaseFile($tmpDir);
        $bancaSlug = strtolower(
            preg_replace('/^banco_/', '', pathinfo($dbPath, PATHINFO_FILENAME))
        );

        // ETAPA 3: Migra todas as imagens para o storage permanente.
        // Isso é feito aqui (pelo orquestrador) pois é uma operação de I/O que precisa
        // ocorrer antes que qualquer chunk comece (os chunks referenciam as imagens migradas).
        $imageMap = $this->migrateImages($tmpDir, $bancaSlug);

        // Persiste o mapeamento de imagens como arquivo JSON no diretório temporário.
        // IMPORTANTE: Não usamos mais o campo error_message do banco pois ele pode ser
        // sobrescrito por mensagens de erro dos próprios chunks, corrompendo o mapeamento.
        $imageMapPath = $tmpDir . DIRECTORY_SEPARATOR . '_image_map.json';
        file_put_contents($imageMapPath, json_encode($imageMap));

        Log::info("[QuestionImportService] imageMap salvo em: {$imageMapPath} (" . count($imageMap) . " imagens)");

        // ETAPA 4: Conta o total de questões no SQLite (sem carregar tudo na memória)
        $sqlite = new \PDO("sqlite:{$dbPath}");
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $countStmt  = $sqlite->query("SELECT COUNT(*) FROM questions");
        $totalCount = (int) $countStmt->fetchColumn();

        Log::info("[QuestionImportService] Preparação concluída: {$totalCount} questões encontradas no SQLite.", [
            'import_id' => $import->id,
            'banca'     => $bancaSlug,
            'images'    => count($imageMap),
        ]);

        return [
            'db_path'     => $dbPath,
            'tmp_dir'     => $tmpDir,
            'total_count' => $totalCount,
        ];
    }

    /**
     * MODO PARALELO — ETAPA 2: Processamento de um Chunk específico.
     *
     * É chamado por cada ProcessImportQuestionChunkJob em paralelo.
     * Cada chunk lê apenas as questões no intervalo [offset, offset+limit) do SQLite.
     *
     * Segurança contra Race Condition:
     *   - `firstOrCreate` em Subject e Topic usa o índice UNIQUE de slug para garantir
     *     que, mesmo que dois workers tentem criar a mesma matéria simultaneamente,
     *     apenas um terá sucesso e o outro lirá o registro criado.
     *   - `Question::where('external_id', ...)->first()` + check de hash garante o upsert
     *     idempotente de questões.
     *
     * @param  string         $dbPath  Caminho absoluto do SQLite.
     * @param  QuestionImport $import  Registro pai do lote.
     * @param  int            $offset  Índice da primeira questão do chunk (0-based).
     * @param  int            $limit   Número máximo de questões a processar.
     * @return array          Estatísticas do chunk: ['total', 'pending', 'approved', 'skipped']
     */
    public function processChunk(string $dbPath, QuestionImport $import, int $offset, int $limit, int $chunkIndex = 0): array
    {
        // Recupera o imageMap do arquivo JSON no tmpDir.
        // Este arquivo é criado pelo prepareForParallelProcessing e é mais robusto que
        // salvar no campo error_message do banco (que pode ser sobrescrito por erros).
        $tmpDir = dirname($dbPath);
        $imageMapPath = $tmpDir . DIRECTORY_SEPARATOR . '_image_map.json';
        $imageMap = [];
        if (file_exists($imageMapPath)) {
            $imageMap = json_decode(file_get_contents($imageMapPath), true) ?? [];
        } else {
            // Fallback: tenta ler do campo error_message (compatibilidade com imports antigos)
            $importData = json_decode($import->error_message ?? '{}', true);
            $imageMap   = $importData['_image_map'] ?? [];
            Log::warning("[QuestionImportService] imageMap não encontrado como arquivo, usando fallback do banco.", [
                'import_id' => $import->id,
                'offset'    => $offset,
            ]);
        }

        // Conecta ao SQLite do lote
        $sqlite = new \PDO("sqlite:{$dbPath}");
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Query com LIMIT/OFFSET para pegar apenas a "fatia" deste chunk
        $stmt = $sqlite->prepare("
            SELECT
                q.*,
                e.organization,
                e.year,
                e.institution,
                e.role,
                e.origin,
                e.source_url,
                e.extracted_at,
                (SELECT GROUP_CONCAT(s.name)
                 FROM question_subject qs
                 JOIN subjects s ON s.id = qs.subject_id
                 WHERE qs.question_id = q.id) AS materias,
                (SELECT GROUP_CONCAT(t.name)
                 FROM question_topic qt
                 JOIN topics t ON t.id = qt.topic_id
                 WHERE qt.question_id = q.id) AS assuntos
            FROM questions q
            LEFT JOIN exams e ON q.exam_id = e.id
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $fetchedCount = count($questions);

        Log::error("[DIAGNOSTIC] Chunk #{$chunkIndex} para Import #{$import->id}: Offset {$offset}, Limit {$limit}. Buscou {$fetchedCount} questões do SQLite.");

        // Carrega o uploader (necessário para a lógica interna; usa o primeiro admin como fallback)
        $uploader = User::find($import->uploaded_by) ?? User::first();

        // Processa as questões deste chunk e atualiza o progresso atomicamente
        $stats = $this->importFromDatabase($dbPath, $imageMap, $import, $uploader, $offset, $limit);

        // Atualiza o contador de progresso no banco (incremento atômico via increment)
        // Não usa update direto para evitar race conditions com outros chunks concorrentes
        $before = $import->processed_questions;
        $import->increment('processed_questions', $stats['total']);
        $import->refresh();
        Log::error("[DIAGNOSTIC] Chunk #{$chunkIndex} finalizado. Stats Total: {$stats['total']}. Processed: {$before} -> {$import->processed_questions}");

        return $stats;
    }

    /**
     * Extrai o arquivo .zip para um diretório temporário.
     * 
     * @param  string $zipPath  Caminho absoluto do arquivo .zip.
     * @param  string $tmpDir   Caminho do diretório de destino.
     * @throws \RuntimeException Se a extração falhar.
     */
    private function extractZip(string $zipPath, string $tmpDir): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \RuntimeException("Não foi possível abrir o arquivo .zip. Código: {$result}");
        }

        if (!$zip->extractTo($tmpDir)) {
            $zip->close();
            throw new \RuntimeException("Falha ao extrair o conteúdo do .zip.");
        }

        $zip->close();
    }

    /**
     * Localiza o arquivo de banco de dados SQLite dentro do diretório extraído.
     * 
     * @param  string $tmpDir  Diretório raiz da extração.
     * @return string          Caminho absoluto para o arquivo .db.
     * @throws \RuntimeException Caso nenhum arquivo .db seja encontrado.
     */
    private function findDatabaseFile(string $tmpDir): string
    {
        $files = glob($tmpDir . DIRECTORY_SEPARATOR . '*.db');

        if (empty($files)) {
            throw new \RuntimeException("Nenhum arquivo SQLite (.db) encontrado na raiz do pacote.");
        }

        return $files[0];
    }

    /**
     * Move as imagens da pasta temporária para o storage definitivo do Laravel.
     * 
     * @param  string $tmpDir    Diretório temporário.
     * @param  string $bancaSlug Identificador da banca para organização de pastas.
     * @return array             Mapa contendo [nome_original => novo_path_storage].
     */
    private function migrateImages(string $tmpDir, string $bancaSlug): array
    {
        $imagensDir = $tmpDir . DIRECTORY_SEPARATOR . 'imagens';
        $map = [];

        if (!is_dir($imagensDir)) {
            return $map;
        }

        $storageDir = self::IMPORT_STORAGE_BASE . '/' . $bancaSlug;

        foreach (glob($imagensDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,gif}', GLOB_BRACE) as $imgPath) {
            $filename = basename($imgPath);
            $storagePath = Storage::disk(self::IMPORT_STORAGE_DISK)->putFileAs(
                $storageDir,
                new \Illuminate\Http\File($imgPath),
                $filename
            );

            if ($storagePath) {
                $map[$filename] = $storagePath;
            }
        }

        return $map;
    }

    /**
     * Processa o banco SQLite e persiste as questões no banco de produção.
     *
     * Suporta dois modos de operação:
     *   1. MODO COMPLETO (padrão): Carrega todas as questões do SQLite em memória.
     *      Usado pelo fluxo legado (processZipFromJob) para compatibilidade.
     *   2. MODO CHUNK (offset+limit): Carrega apenas uma fatia específica das questões.
     *      Usado pelo processChunk para processamento paralelo com múltiplos workers.
     *
     * Segurança contra Race Conditions (modo chunk):
     *   - firstOrCreate para Subject/Topic usa o índice UNIQUE de slug no banco.
     *     Se dois workers tentarem criar a mesma matéria ao mesmo tempo, o MySQL
     *     rejeita a segunda inserção com DuplicateEntry. O `firstOrCreate` do Eloquent
     *     detecta esse erro e retorna o registro já existente silenciosamente.
     *
     * @param  string         $dbPath   Caminho do SQLite.
     * @param  array          $imageMap Mapeamento de imagens processadas.
     * @param  QuestionImport $import   Registro do lote atual.
     * @param  User           $uploader Usuário executor.
     * @param  int|null       $offset   (Opcional) Índice inicial para processamento em chunk.
     * @param  int|null       $limit    (Opcional) Máximo de questões para este chunk.
     * @return array          Estatísticas da importação [total, pending, approved, skipped].
     */
    protected function importFromDatabase(
        string $dbPath,
        array $imageMap,
        QuestionImport $import,
        User $uploader,
        ?int $offset = null,
        ?int $limit = null
    ): array {
        $sqlite = new \PDO("sqlite:{$dbPath}");
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Modo CHUNK: usa LIMIT + OFFSET para processar apenas a fatia deste worker.
        // Modo COMPLETO: carrega todas as questões (sem LIMIT/OFFSET).
        if ($offset !== null && $limit !== null) {
            $stmt = $sqlite->prepare("
                SELECT
                    q.*,
                    e.organization,
                    e.year,
                    e.institution,
                    e.role,
                    e.origin,
                    e.source_url,
                    e.extracted_at,
                    (SELECT GROUP_CONCAT(s.name)
                     FROM question_subject qs
                     JOIN subjects s ON s.id = qs.subject_id
                     WHERE qs.question_id = q.id) AS materias,
                    (SELECT GROUP_CONCAT(t.name)
                     FROM question_topic qt
                     JOIN topics t ON t.id = qt.topic_id
                     WHERE qt.question_id = q.id) AS assuntos
                FROM questions q
                LEFT JOIN exams e ON q.exam_id = e.id
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $sqlite->query("
                SELECT
                    q.*,
                    e.organization,
                    e.year,
                    e.institution,
                    e.role,
                    e.origin,
                    e.source_url,
                    e.extracted_at,
                    (SELECT GROUP_CONCAT(s.name)
                     FROM question_subject qs
                     JOIN subjects s ON s.id = qs.subject_id
                     WHERE qs.question_id = q.id) AS materias,
                    (SELECT GROUP_CONCAT(t.name)
                     FROM question_topic qt
                     JOIN topics t ON t.id = qt.topic_id
                     WHERE qt.question_id = q.id) AS assuntos
                FROM questions q
                LEFT JOIN exams e ON q.exam_id = e.id
            ");
        }

        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'skipped' => 0, 'updated' => 0];

        // No modo completo, atualiza o total_questions no registro do import.
        // No modo chunk, o orquestrador já fez isso antes de despachar os chunks.
        if ($offset === null) {
            $import->update(['total_questions' => count($questions)]);
        }

        foreach ($questions as $qData) {
            $externalId = 'n/a';
            try {
                DB::transaction(function () use ($qData, $imageMap, $import, $uploader, &$stats, &$externalId) {
                    // 1. A Nova Chave Única (Fim da Duplicação)
                    $uniqueString = trim($qData['organization'] ?? '') . '|' .
                        trim($qData['year'] ?? '') . '|' .
                        trim($qData['institution'] ?? '') . '|' .
                        trim($qData['role'] ?? '') . '|' .
                        trim($qData['number'] ?? '');

                    $externalId = md5($uniqueString);
                    $incomingHash = $qData['content_hash'] ?? null;

                    // 2. O "Smart Upsert"
                    $existingQuestion = Question::where('external_id', $externalId)->first();

                    if ($existingQuestion && $existingQuestion->content_hash !== null && $existingQuestion->content_hash === $incomingHash) {
                        \App\Models\QuestionImportItem::firstOrCreate([
                            'import_id'  => $import->id,
                            'question_id' => $existingQuestion->id,
                        ]);
                        $stats['skipped']++;
                        $stats['total']++;
                        return;
                    }

                    $discursiveAnswer = null;
                    if (!empty($qData['discursive_answer'])) {
                        $parsedAnswer = json_decode($qData['discursive_answer'], true);
                        $discursiveAnswer = (json_last_error() === JSON_ERROR_NONE) ? $parsedAnswer : $qData['discursive_answer'];
                    }

                    $tipoQuestao = $qData['tipo_questao'] ?? 'Objetiva';
                    $hasImages = !empty($qData['image_path']);
                    $status = $qData['review_status'] ?? 'pending';

                    if ($hasImages && $status !== 'approved') $status = 'review';
                    $extractedAt = !empty($qData['extracted_at']) ? \Carbon\Carbon::parse($qData['extracted_at']) : null;

                    $payload = [
                        'type' => 'concurso',
                        'institution' => $qData['institution'] ?? null,
                        'organization' => $qData['organization'] ?? null,
                        'role' => $qData['role'] ?? null,
                        'year' => $qData['year'] ?? null,
                        'number' => $qData['number'] ?? null,
                        'statement' => $qData['statement'] ?? '',
                        'difficulty' => 'medium',
                        'review_status' => $status,
                        'tipo_questao' => $tipoQuestao,
                        'arquivo_origem' => $qData['arquivo_origem'] ?? null,
                        'discursive_answer' => $discursiveAnswer,
                        'pdf_page' => $qData['pdf_page'] ?? null,
                        'origin' => $qData['origin'] ?? null,
                        'source_url' => $qData['source_url'] ?? null,
                        'extracted_at' => $qData['extracted_at'] ?? null,
                        'content_hash' => $incomingHash,
                    ];

                    if ($existingQuestion) {
                        $lastScraped = $existingQuestion->last_scraped_at;
                        if ($existingQuestion->updated_at && $lastScraped && $existingQuestion->updated_at->gt($lastScraped)) {
                            $stats['skipped']++;
                            $stats['total']++;
                            return;
                        }
                        if ($extractedAt && $lastScraped && $extractedAt->lte($lastScraped)) {
                            $stats['skipped']++;
                            $stats['total']++;
                            return;
                        }

                        $payload['updated_at'] = now();
                        $payload['last_scraped_at'] = $extractedAt ?? now();
                        $payload['scraper_update_count'] = $existingQuestion->scraper_update_count + 1;

                        $existingQuestion->update($payload);
                        $stats['updated']++;
                        $question = $existingQuestion;

                        foreach ($question->images as $img) {
                            if (!empty($img->path)) Storage::disk(self::IMPORT_STORAGE_DISK)->delete($img->path);
                        }
                        $question->images()->delete();
                    } else {
                        $payload['external_id'] = $externalId;
                        $payload['updated_at'] = $qData['updated_at'] ?? now();
                        $payload['last_scraped_at'] = $extractedAt ?? now();
                        $payload['scraper_update_count'] = 0;
                        $question = Question::create($payload);
                    }

                    if ($hasImages) {
                        $imagePaths = explode(',', $qData['image_path']);
                        foreach ($imagePaths as $imgPath) {
                            $imgPath = trim($imgPath);
                            if (isset($imageMap[$imgPath])) {
                                $question->images()->create(['path' => $imageMap[$imgPath]]);
                            }
                        }
                    }

                    QuestionImportItem::firstOrCreate([
                        'import_id' => $import->id,
                        'question_id' => $question->id,
                    ]);

                    if (!empty($qData['materias'])) {
                        $subjectNames = array_map('trim', explode(',', $qData['materias']));
                        $subjectIds = [];
                        foreach ($subjectNames as $name) {
                            $normalizedName = mb_strtoupper($name, 'UTF-8');
                            $subject = Subject::firstOrCreate(['name' => $normalizedName], ['slug' => Str::slug($normalizedName)]);
                            $subjectIds[] = $subject->id;
                        }
                        $question->subjects()->syncWithoutDetaching($subjectIds);
                    }

                    if (!empty($qData['assuntos'])) {
                        $topicNames = array_map('trim', explode(',', $qData['assuntos']));
                        $topicIds = [];
                        foreach ($topicNames as $name) {
                            $normalizedName = mb_strtoupper($name, 'UTF-8');
                            $topic = \App\Models\Topic::firstOrCreate(['name' => $normalizedName], ['slug' => Str::slug($normalizedName)]);
                            $topicIds[] = $topic->id;
                        }
                        $question->topics()->syncWithoutDetaching($topicIds);
                    }

                    if (!empty($qData['alternatives'])) {
                        $alternatives = json_decode($qData['alternatives'], true);
                        if (is_array($alternatives)) {
                            foreach ($alternatives as $label => $content) {
                                $isCorrect = ($tipoQuestao === 'Objetiva') ? (strtoupper($label) === strtoupper($qData['correct_answer'] ?? '')) : false;
                                QuestionAlternative::updateOrCreate(
                                    ['question_id' => $question->id, 'label' => strtoupper($label)],
                                    ['content' => $content, 'is_correct' => $isCorrect]
                                );
                            }
                        }
                    }

                    $stats['total']++;
                    $status = $qData['review_status'] ?? 'pending';
                    if ($status === 'pending' || $status === 'review') {
                        $stats['pending']++;
                    } else {
                        $stats['approved']++;
                    }
                });
            } catch (\Throwable $questionError) {
                Log::error("[QuestionImportService] Erro na questão do lote #{$import->id}: " . $questionError->getMessage(), [
                    'q_id' => $qData['id'] ?? 'unknown',
                    'external_id' => $externalId
                ]);
            }
        }

        // Incrementos ATÔMICOS ao final do lote para evitar lock contention (Gargalo de I/O)
        if ($stats['skipped'] > 0) $import->increment('skipped_count', $stats['skipped']);
        if ($stats['updated'] > 0) $import->increment('updated_count', $stats['updated']);

        return $stats;
    }

    /**
     * Executa o recorte (crop) de uma imagem e salva no destino apropriado.
     * 
     * Esta função é o core da "inspeção visual". Ela lida com dois alvos:
     * 
     * 1. 'statement' (Enunciado): Sobrescreve a imagem original in-place.
     *    - Rationale: Evita acúmulo de arquivos órfãos se o admin recortar várias vezes.
     *    - Atualiza questions.image_path apenas se a extensão mudar (ex: de PNG para JPG).
     * 
     * 2. 'A'|'B'|'C'|'D'|'E' (Alternativa): Cria um novo arquivo vinculado à letra.
     *    - Rationale: Mantém os recortes das alternativas associados à questão pai.
     *    - Atualiza question_alternatives.content com a URL do novo recorte.
     *
     * @param  QuestionImage $image  Registro da imagem sendo revisada.
     * @param  string        $target Alvo do recorte: 'statement' ou letra da alternativa.
     * @param  int           $x      Coordenada X inicial (pixels originais).
     * @param  int           $y      Coordenada Y inicial (pixels originais).
     * @param  int           $width  Largura do recorte em pixels.
     * @param  int           $height Altura do recorte em pixels.
     * @return string                URL pública do recorte para atualização instantânea no frontend.
     * @throws \RuntimeException     Se a imagem original for inexistente ou houver falha no processamento GD.
     */
    public function saveCrop(
        QuestionImage $image,
        string $target,
        int $x,
        int $y,
        int $width,
        int $height
    ): string {
        $question = $image->question;

        if (empty($image->path)) {
            throw new \RuntimeException("A imagem informada não possui um path válido no storage.");
        }

        $originalAbsPath = Storage::disk(self::IMPORT_STORAGE_DISK)->path($image->path);

        if (!file_exists($originalAbsPath)) {
            throw new \RuntimeException("O arquivo físico da imagem não foi encontrado: {$image->path}");
        }

        // ------------------------------------------------------------------
        // ETAPA 1: Carregamento via PHP GD
        // ------------------------------------------------------------------
        $extension = strtolower(pathinfo($originalAbsPath, PATHINFO_EXTENSION));

        $srcImage = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($originalAbsPath),
            'png' => imagecreatefrompng($originalAbsPath),
            default => throw new \RuntimeException("Formato '{$extension}' não suportado via GD."),
        };

        if (!$srcImage) {
            throw new \RuntimeException("Falha crítica ao abrir o recurso de imagem via GD.");
        }

        // ------------------------------------------------------------------
        // ETAPA 2: Execução do Recorte
        // ------------------------------------------------------------------
        $cropped = imagecrop($srcImage, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        imagedestroy($srcImage);

        if (!$cropped) {
            throw new \RuntimeException("Falha ao processar o recorte. Verifique as coordenadas.");
        }

        // ------------------------------------------------------------------
        // ETAPA 3: Serialização (Output em memória como JPEG)
        // ------------------------------------------------------------------
        ob_start();
        imagejpeg($cropped, null, 90);
        $imageData = ob_get_clean();
        imagedestroy($cropped);

        // ------------------------------------------------------------------
        // ETAPA 4: Persistência e Roteamento de Destino
        // ------------------------------------------------------------------
        $isStatement = (strtolower($target) === 'statement');
        $timestamp = time();
        $label = $isStatement ? 'statement' : strtoupper($target);
        
        // Novo caminho: crops/{question_id}/{label}_{timestamp}.jpg
        $cropStoragePath = "crops/{$question->id}/{$label}_{$timestamp}.jpg";

        // Garante que o diretório existe
        $storageDir = dirname($cropStoragePath);
        if (!Storage::disk(self::IMPORT_STORAGE_DISK)->exists($storageDir)) {
            Storage::disk(self::IMPORT_STORAGE_DISK)->makeDirectory($storageDir);
        }

        // Salva o novo arquivo
        Storage::disk(self::IMPORT_STORAGE_DISK)->put($cropStoragePath, $imageData);
        $publicUrl = Storage::url($cropStoragePath);

        if ($isStatement) {
            // Lógica de ENUNCIADO: Atualiza a coluna image_path da Questão
            // Preserva a model QuestionImage original (fonte) intacta
            $question->update(['image_path' => $cropStoragePath]);
        } else {
            // Lógica de ALTERNATIVA: Atualiza ou cria a alternativa com o novo conteúdo
            $alternative = $question->alternatives()->where('label', $label)->first();

            if ($alternative) {
                $alternative->update(['content' => $cropStoragePath]);
            } else {
                $question->alternatives()->create([
                    'label' => $label,
                    'content' => $cropStoragePath,
                    'is_correct' => false,
                ]);
            }
        }

        return $publicUrl;
    }

    /**
     * Remove fisicamente a imagem da questão e limpa o banco QuestionImage.
     */
    public function deleteImage(QuestionImage $image): void
    {
        if (!empty($image->path)) {
            Storage::disk(self::IMPORT_STORAGE_DISK)->delete($image->path);
            $image->delete();
        }
    }

    /**
     * Limpa o diretório temporário recursivamente.
     */
    private function cleanupTmpDir(string $dir): void
    {
        if (!is_dir($dir))
            return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->cleanupTmpDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Safely reverts an entire import batch, deleting all associated questions.
     * Can be called manually by admin or automatically by a Job on failure.
     *
     * @param QuestionImport $import The import record to rollback.
     * @param User|null $admin If provided, logs each deletion to user_logs.
     * @param string|null $ip If provided, used for the log entry.
     * @return void
     */
    public function rollback(QuestionImport $import, ?User $admin = null, ?string $ip = null): void
    {
        DB::transaction(function () use ($import, $admin, $ip) {
            // Find all question IDs linked to this import
            $questionIds = QuestionImportItem::where('import_id', $import->id)
                ->pluck('question_id')
                ->toArray();

            if (!empty($questionIds)) {
                $questions = Question::whereIn('id', $questionIds)->get();

                foreach ($questions as $question) {
                    // Cleanup orphan records in legacy tables
                    foreach (['favorites', 'notebook_questions', 'question_reports', 'question_notes'] as $table) {
                        if (Schema::hasTable($table)) {
                            DB::table($table)->where('question_id', $question->id)->delete();
                        }
                    }

                    // Optional Audit Logging
                    if ($admin) {
                        $impactData = [
                            'admin_id' => $admin->id,
                            'batch_rollback' => $import->id,
                            'question_id' => $question->id,
                            'statement_preview' => mb_strimwidth(strip_tags($question->statement), 0, 100, '...'),
                        ];

                        UserLog::create([
                            'user_id' => $admin->id,
                            'action' => 'admin_deleted_question_via_rollback',
                            'description' => json_encode($impactData),
                            'ip_address' => $ip ?? '127.0.0.1',
                        ]);
                    }

                    $question->delete();
                }
            }

            // Delete links
            QuestionImportItem::where('import_id', $import->id)->delete();

            // Mark as reverted and reset counts
            $import->update([
                'status' => 'reverted',
                'pending_count' => 0,
                'approved_count' => 0,
                'skipped_count' => 0,
                'updated_count' => 0,
                'processed_questions' => 0,
            ]);
        });
    }
}
