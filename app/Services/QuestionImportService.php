<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionAlternative;
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

        $stmt = $sqlite->query("SELECT * FROM questoes");
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = ['total' => 0, 'pending' => 0, 'approved' => 0];

        foreach ($questions as $qData) {
            DB::transaction(function () use ($qData, $imageMap, $import, $uploader, &$stats) {
                // Gera uma chave única robusta baseada no conteúdo da questão
                $uniqueString = trim($qData['banca'] ?? '') . '|' . 
                                trim($qData['ano'] ?? '') . '|' . 
                                trim($qData['orgao'] ?? '') . '|' . 
                                trim($qData['cargo'] ?? '') . '|' . 
                                trim($qData['enunciado'] ?? '');
                
                $externalId = md5($uniqueString);

                // Criação ou Atualização da questão base (Upsert)
                $question = Question::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'institution'    => $qData['orgao'] ?? null,
                        'organization'   => $qData['banca'] ?? null,
                        'role'           => $qData['cargo'] ?? null,
                        'year'           => $qData['ano'] ?? null,
                        'statement'      => $qData['enunciado'] ?? '',
                        'difficulty'     => 'medium',
                        // Somente sobrescreve o review_status se for uma nova inserção ou se ainda estiver pending
                        // Para não voltar uma questão 'approved' para 'pending' acidentalmente.
                    ]
                );

                // Se a questão acabou de ser criada, defina o status inicial e a imagem
                if ($question->wasRecentlyCreated) {
                    $question->update([
                        'review_status' => 'pending',
                        'image_path'    => $imageMap[$qData['image_path']] ?? null,
                    ]);
                }

                // Registro de auditoria vinculando item ao lote (evita duplicar o vínculo no lote)
                QuestionImportItem::firstOrCreate([
                    'import_id'   => $import->id,
                    'question_id' => $question->id,
                ]);

                // Processamento de matérias (Many-to-Many)
                if (!empty($qData['materia'])) {
                    $subjectNames = array_map('trim', explode(',', $qData['materia']));
                    $subjectIds = [];
                    foreach ($subjectNames as $name) {
                        $subject = Subject::firstOrCreate(['name' => $name, 'slug' => Str::slug($name)]);
                        $subjectIds[] = $subject->id;
                    }
                    // Usa syncWithoutDetaching para não remover matérias adicionadas manualmente depois
                    $question->subjects()->syncWithoutDetaching($subjectIds);
                }

                // Processamento de alternativas (JSON -> Tabela Relacional)
                if (!empty($qData['alternativas'])) {
                    $alternativas = json_decode($qData['alternativas'], true);
                    if (is_array($alternativas)) {
                        foreach ($alternativas as $label => $content) {
                            QuestionAlternative::updateOrCreate(
                                [
                                    'question_id' => $question->id,
                                    'label'       => strtoupper($label),
                                ],
                                [
                                    'content'     => $content,
                                    'is_correct'  => (strtoupper($label) === strtoupper($qData['gabarito'] ?? '')),
                                ]
                            );
                        }
                    }
                }

                $stats['total']++;
                $stats['pending']++;
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
     * @param  Question  $question Registro da questão sendo revisada.
     * @param  string    $target   Alvo do recorte: 'statement' ou letra da alternativa.
     * @param  int       $x        Coordenada X inicial (pixels originais).
     * @param  int       $y        Coordenada Y inicial (pixels originais).
     * @param  int       $width    Largura do recorte em pixels.
     * @param  int       $height   Altura do recorte em pixels.
     * @return string              URL pública do recorte para atualização instantânea no frontend.
     * @throws \RuntimeException   Se a imagem original for inexistente ou houver falha no processamento GD.
     */
    public function saveCrop(
        Question $question,
        string $target,
        int $x,
        int $y,
        int $width,
        int $height
    ): string {
        if (empty($question->image_path)) {
            throw new \RuntimeException("A questão #{$question->id} não possui uma imagem associada.");
        }

        $originalAbsPath = Storage::disk(self::IMPORT_STORAGE_DISK)->path($question->image_path);

        if (!file_exists($originalAbsPath)) {
            throw new \RuntimeException("O arquivo físico da imagem não foi encontrado: {$question->image_path}");
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
                $originalBasename = pathinfo($question->image_path, PATHINFO_FILENAME);
                $storageDir       = dirname($question->image_path);
                $cropStoragePath  = $storageDir . '/' . $originalBasename . '_crop.jpg';
            } else {
                $cropStoragePath = $question->image_path;
            }

            Storage::disk(self::IMPORT_STORAGE_DISK)->put($cropStoragePath, $imageData);

            if ($cropStoragePath !== $question->image_path) {
                Storage::disk(self::IMPORT_STORAGE_DISK)->delete($question->image_path);
                $question->update(['image_path' => $cropStoragePath]);
            }
        } else {
            // Lógica de ALTERNATIVA: Novo arquivo com sufixo da letra (A, B, C...)
            $label            = strtoupper($target);
            $originalBasename = pathinfo($question->image_path, PATHINFO_FILENAME);
            $storageDir       = dirname($question->image_path);
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
     * Remove fisicamente a imagem principal da questão e limpa o banco.
     */
    public function deleteImage(Question $question): void
    {
        if (!empty($question->image_path)) {
            Storage::disk(self::IMPORT_STORAGE_DISK)->delete($question->image_path);
            $question->update(['image_path' => null]);
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
