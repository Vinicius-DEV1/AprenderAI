<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Subject;
use App\Models\Concept;
use App\Models\Question;
use App\Services\AI\QdrantService;
use App\Jobs\IndexQuestionVectorJob;
use App\Jobs\IndexConceptVectorJob;
use App\Jobs\IndexSemanticEntityJob;

class MigrateSubjectPortuguese extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:portuguese';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates all occurrences of "Português" to "Língua Portuguesa" safely (Subject, Concepts, Questions, Qdrant).';

    /**
     * Execute the console command.
     */
    public function handle(QdrantService $qdrant)
    {
        $this->info("Iniciando migração de 'Português' para 'Língua Portuguesa'...");

        // Nomes incorretos mapeados
        $wrongNames = [
            'Português', 'PORTUGUÊS', 'português',
            'Portugues', 'PORTUGUES', 'portugues',
            'Lingua Portuguesa', 'lingua portuguesa', 'LINGUA PORTUGUESA'
        ];

        // 1. Encontrar ou criar o Subject definitivo: "Língua Portuguesa"
        $newSubject = Subject::firstOrCreate(
            ['name' => 'Língua Portuguesa'],
            ['slug' => 'lingua-portuguesa', 'type' => 'concurso']
        );

        $this->info("Subject Definitivo: ID {$newSubject->id} -> {$newSubject->name}");
        
        // 2. Encontrar todos os Subjects errados
        $oldSubjects = tap(Subject::whereIn('name', $wrongNames)->where('id', '!=', $newSubject->id)->get(), function($list) {
            $this->info("Encontrados " . $list->count() . " subjects incorretos para migrar.");
        });

        if ($oldSubjects->isEmpty()) {
            $this->info("Nenhuma disciplina incorreta encontrada. A base já está padronizada!");
            return 0;
        }

        $totalQuestionsMigrated = 0;
        $totalConceptsMigrated = 0;

        foreach ($oldSubjects as $oldSubject) {
            $this->warn("Processando exclusão e fusão do Subject ID {$oldSubject->id} ({$oldSubject->name})...");

            DB::beginTransaction();
            try {
                // Migração de Concepts
                $conceptsToMigrate = Concept::where('subject_id', $oldSubject->id)->get();
                $conceptIds = $conceptsToMigrate->pluck('id')->toArray();
                if (!empty($conceptIds)) {
                    Concept::whereIn('id', $conceptIds)->update(['subject_id' => $newSubject->id]);
                    $totalConceptsMigrated += count($conceptIds);
                    
                    // Reindexar Concepts no final
                    foreach ($conceptsToMigrate as $concept) {
                        IndexConceptVectorJob::dispatch((string) $concept->id)->onQueue(config('xavier.embeddings.queue', 'embeddings'));
                    }
                }

                // Migração de Questions (Pivot)
                $questionIds = DB::table('question_subject')
                    ->where('subject_id', $oldSubject->id)
                    ->pluck('question_id')
                    ->toArray();

                if (!empty($questionIds)) {
                    // Verificar quais questões já possuem a nova disciplina para evitar Constraint Violation (Duplicate entry)
                    $alreadyHasNew = DB::table('question_subject')
                        ->where('subject_id', $newSubject->id)
                        ->whereIn('question_id', $questionIds)
                        ->pluck('question_id')
                        ->toArray();

                    $needsNew = array_diff($questionIds, $alreadyHasNew);

                    // 1. Inserir a nova disciplina nas questões que não tem
                    $inserts = array_map(function($qId) use ($newSubject) {
                        return [
                            'question_id' => $qId,
                            'subject_id' => $newSubject->id,
                        ];
                    }, $needsNew);

                    if (!empty($inserts)) {
                        DB::table('question_subject')->insert($inserts);
                    }

                    // 2. Deletar a antiga disciplina de todas essas questões
                    DB::table('question_subject')
                        ->where('subject_id', $oldSubject->id)
                        ->whereIn('question_id', $questionIds)
                        ->delete();

                    $totalQuestionsMigrated += count($questionIds);

                    // Reindexar Questions
                    foreach ($questionIds as $qId) {
                        IndexQuestionVectorJob::dispatch($qId)->onQueue(config('xavier.embeddings.queue', 'embeddings'));
                    }
                }

                // Remoção do filtro semântico antigo no Qdrant
                $entityId = "subject:{$oldSubject->id}";
                $hash = hash('sha256', $entityId);
                $qdrantId = intval(substr($hash, 0, 15), 16);
                
                // Qdrant HTTP call
                $collection = config('xavier.qdrant.collections.filters', 'concepts_vectors');
                $qdrant->post("/collections/{$collection}/points/delete?wait=true", [
                    'points' => [$qdrantId]
                ]);

                // Deletar o Subject permanentemente
                $oldSubject->delete();

                DB::commit();
                $this->info("Subject ID {$oldSubject->id} removido e dados mesclados com sucesso.");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Erro ao processar Subject {$oldSubject->id}: " . $e->getMessage());
                return 1;
            }
        }

        // Reindexar a nova entidade para garantir consistência semântica no motor de busca
        IndexSemanticEntityJob::dispatch((string) $newSubject->id, 'subject')->onQueue(config('xavier.embeddings.queue', 'embeddings'));

        // Limpar Cache
        $this->info("Limpando cache tags 'subjects'...");
        try {
            \Illuminate\Support\Facades\Cache::tags(['subjects'])->flush();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Cache::flush();
        }

        $this->info("--- Resumo da Migração ---");
        $this->info("Questões re-associadas e enviadas para indexação: $totalQuestionsMigrated");
        $this->info("Conceitos atualizados e enviados para indexação: $totalConceptsMigrated");
        $this->info("Migração concluída com sucesso! (As atualizações no Qdrant ocorrerão em background pelos workers da fila 'indexing')");
        
        return 0;
    }
}
