<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Models\QuestionImage;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // UUID único para evitar colisões entre importações simultâneas
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_' . Str::uuid();

        // Registro de auditoria inicial
        $import = QuestionImport::create([
            'batch_name'        => $uploader->name . ' — ' . now()->format('d/m/Y H:i'),
            'original_filename' => $zipFile->getClientOriginalName(),
            'uploaded_by'       => $uploader->id,
            'status'            => 'processing',
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
                'pending_count'   => $stats['pending'],
                'approved_count'  => $stats['approved'],
                'status'          => 'completed',
            ]);

        } catch (\Throwable $e) {
            // Registro de falha para auditoria
            $import->update([
                'status'        => 'failed',
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
     * Utiliza Database Transactions para garantir integridade.
     * Realiza o mapeamento automático de matérias (Subjects).
     *
     * @param  string         $dbPath   Caminho do SQLite.
     * @param  array          $imageMap Mapeamento de imagens processadas.
     * @param  QuestionImport $import   Registro do lote atual.
     * @param  User           $uploader Usuário executor.
     * @return array          Estatísticas da importação [total, pending, approved].
     */
    private function importFromDatabase(string $dbPath, array $imageMap, QuestionImport $import, User $uploader): array
    {
        $sqlite = new \PDO("sqlite:{$dbPath}");
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $stmt = $sqlite->query("
            SELECT 
                q.*,
                e.organization,
                e.year,
                e.institution,
                e.role,
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
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = ['total' => 0, 'pending' => 0, 'approved' => 0];

        foreach ($questions as $qData) {
            DB::transaction(function () use ($qData, $imageMap, $import, $uploader, &$stats) {
                // Gera uma chave única robusta baseada no conteúdo da questão
                $uniqueString = trim($qData['organization'] ?? '') . '|' . 
                                trim($qData['year'] ?? '') . '|' . 
                                trim($qData['institution'] ?? '') . '|' . 
                                trim($qData['role'] ?? '') . '|' . 
                                trim($qData['statement'] ?? '');
                
                $externalId = md5($uniqueString);

                // Criação ou Atualização da questão base (Upsert)
                $question = Question::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'type'           => 'concurso',
                        'institution'    => $qData['institution'] ?? null,
                        'organization'   => $qData['organization'] ?? null,
                        'role'           => $qData['role'] ?? null,
                        'year'           => $qData['year'] ?? null,
                        'statement'      => $qData['statement'] ?? '',
                        'difficulty'     => 'medium',
                    ]
                );

                // Se a questão acabou de ser criada, defina o status inicial e processe as imagens
                if ($question->wasRecentlyCreated) {
                    $hasImages = !empty($qData['image_path']);
                    $status = $qData['review_status'] ?? 'pending';
                    
                    if ($hasImages) {
                        $status = 'review';
                    }

                    $question->update([
                        'review_status' => $status,
                    ]);

                    // Insere instâncias de imagem iterativamente para a relação 1:N
                    if (!empty($qData['image_path'])) {
                        $imagePaths = explode(',', $qData['image_path']);
                        foreach ($imagePaths as $imgPath) {
                            $imgPath = trim($imgPath);
                            if (isset($imageMap[$imgPath])) {
                                $question->images()->create([
                                    'path' => $imageMap[$imgPath],
                                ]);
                            }
                        }
                    }
                }

                // Registro de auditoria vinculando item ao lote
                QuestionImportItem::firstOrCreate([
                    'import_id'   => $import->id,
                    'question_id' => $question->id,
                ]);

                // Processamento de Matérias (Subjects M:N)
                if (!empty($qData['materias'])) {
                    $subjectNames = array_map('trim', explode(',', $qData['materias']));
                    $subjectIds = [];
                    foreach ($subjectNames as $name) {
                        $subject = Subject::firstOrCreate(
                            ['slug' => Str::slug($name)],
                            ['name' => $name]
                        );
                        $subjectIds[] = $subject->id;
                    }
                    $question->subjects()->syncWithoutDetaching($subjectIds);
                }

                // Processamento de Assuntos (Topics M:N)
                if (!empty($qData['assuntos'])) {
                    $topicNames = array_map('trim', explode(',', $qData['assuntos']));
                    $topicIds = [];
                    foreach ($topicNames as $name) {
                        $topic = \App\Models\Topic::firstOrCreate(
                            ['slug' => Str::slug($name)],
                            ['name' => $name]
                        );
                        $topicIds[] = $topic->id;
                    }
                    $question->topics()->syncWithoutDetaching($topicIds);
                }

                // Processamento de alternativas (JSON -> Tabela Relacional)
                if (!empty($qData['alternatives'])) {
                    $alternatives = json_decode($qData['alternatives'], true);
                    if (is_array($alternatives)) {
                        foreach ($alternatives as $label => $content) {
                            QuestionAlternative::updateOrCreate(
                                [
                                    'question_id' => $question->id,
                                    'label'       => strtoupper($label),
                                ],
                                [
                                    'content'     => $content,
                                    'is_correct'  => (strtoupper($label) === strtoupper($qData['correct_answer'] ?? '')),
                                ]
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
        }

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
            'png'         => imagecreatefrompng($originalAbsPath),
            default       => throw new \RuntimeException("Formato '{$extension}' não suportado via GD."),
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

        if ($isStatement) {
            // Lógica de ENUNCIADO: Sobrescreve in-place para eficiência de storage
            if (in_array($extension, ['png'])) {
                $originalBasename = pathinfo($image->path, PATHINFO_FILENAME);
                $storageDir       = dirname($image->path);
                $cropStoragePath  = $storageDir . '/' . $originalBasename . '_crop.jpg';
            } else {
                $cropStoragePath = $image->path;
            }

            Storage::disk(self::IMPORT_STORAGE_DISK)->put($cropStoragePath, $imageData);

            if ($cropStoragePath !== $image->path) {
                Storage::disk(self::IMPORT_STORAGE_DISK)->delete($image->path);
                $image->update(['path' => $cropStoragePath]);
            }
        } else {
            // Lógica de ALTERNATIVA: Novo arquivo com sufixo da letra (A, B, C...)
            $label            = strtoupper($target);
            $originalBasename = pathinfo($image->path, PATHINFO_FILENAME);
            $storageDir       = dirname($image->path);
            $cropStoragePath  = $storageDir . '/' . $originalBasename . '_' . $label . '.jpg';

            Storage::disk(self::IMPORT_STORAGE_DISK)->put($cropStoragePath, $imageData);

            $alternative = $question->alternatives()->where('label', $label)->first();
            $publicUrl   = Storage::url($cropStoragePath);

            if ($alternative) {
                $alternative->update(['content' => $publicUrl]);
            } else {
                $question->alternatives()->create([
                    'question_id' => $question->id,
                    'label'       => $label,
                    'content'     => $publicUrl,
                    'is_correct'  => false,
                ]);
            }
        }

        return Storage::url($cropStoragePath);
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
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->cleanupTmpDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
