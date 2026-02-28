<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration: backfill_knowledge_area_from_subjects
 *
 * Motivação: Antes da correção do mapeamento da API ENEM Dev, questão importadas
 * via o comando artisan `enem:import` não tinham o campo `knowledge_area` preenchido.
 *
 * Esta migration de dados popula retroativamente a coluna `knowledge_area`
 * para questões do tipo 'enem' que:
 *   - Possuem `source` = 'enem_api' (importadas pelo artisan command)
 *   - Não possuem `knowledge_area` preenchido
 *
 * O mapeamento reverso usa o nome do subject para inferir a grande área:
 *   'Matemática'  → 'matematica'
 *   'Português'   → 'linguagens'
 *   'Português'   → 'linguagens'  (forma anterior lowercase)
 *
 * NOTA: Como o banco foi reseta com `migrate:fresh`, esta migration é essencialmente
 * preventiva — garante que dados importados NO FUTURO via scripts antigos sejam
 * também corrigidos caso esta migration não tenha sido aplicada antes da importação.
 */
return new class extends Migration {
    public function up(): void
    {
        // Buscar questões ENEM do artisan import sem knowledge_area
        DB::table('questions')
            ->where('type', 'enem')
            ->where('source', 'enem_api')
            ->whereNull('knowledge_area')
            ->orderBy('id')
            ->each(function ($question) {
                // Inferir knowledge_area a partir do subject via pivot
                $subjects = DB::table('question_subject')
                    ->join('subjects', 'question_subject.subject_id', '=', 'subjects.id')
                    ->where('question_subject.question_id', $question->id)
                    ->pluck('subjects.name');

                $knowledgeArea = null;
                foreach ($subjects as $subjectName) {
                    $lower = strtolower($subjectName);
                    if (in_array($lower, ['matemática', 'matematica'])) {
                        $knowledgeArea = 'matematica';
                        break;
                    } elseif (in_array($lower, ['português', 'portugues', 'linguagens'])) {
                        $knowledgeArea = 'linguagens';
                        break;
                    } elseif (in_array($lower, ['inglês', 'ingles', 'espanhol'])) {
                        $knowledgeArea = 'linguagens';
                        break;
                    } elseif (in_array($lower, ['ciências humanas', 'ciencias humanas', 'ciencias-humanas'])) {
                        $knowledgeArea = 'ciencias-humanas';
                        break;
                    } elseif (in_array($lower, ['ciências da natureza', 'ciencias natureza', 'ciencias-natureza'])) {
                        $knowledgeArea = 'ciencias-natureza';
                        break;
                    }
                }

                if ($knowledgeArea) {
                    DB::table('questions')
                        ->where('id', $question->id)
                        ->update(['knowledge_area' => $knowledgeArea]);
                }
            });
    }

    public function down(): void
    {
        // Reverter: limpar knowledge_area apenas para enem_api sem modificação posterior
        DB::table('questions')
            ->where('type', 'enem')
            ->where('source', 'enem_api')
            ->update(['knowledge_area' => null]);
    }
};
