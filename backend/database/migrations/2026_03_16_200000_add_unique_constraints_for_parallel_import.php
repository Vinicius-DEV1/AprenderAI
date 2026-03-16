<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona índices UNIQUE necessários para o processamento paralelo seguro de importações.
 *
 * CONTEXTO:
 * Com múltiplos workers processando chunks do mesmo ZIP simultaneamente, é possível que
 * dois workers tentem criar a mesma Matéria (Subject), Assunto (Topic) ou Questão ao
 * mesmo tempo. Sem índices UNIQUE, isso resultaria em registros duplicados no banco.
 *
 * Com esses índices, o banco de dados rejeita a segunda inserção duplicada com uma
 * exceção (PDOException: Duplicate Entry). O código da aplicação captura esse erro
 * e usa `firstOrCreate` de forma segura.
 *
 * IMPORTANTE: Esta migração verifica dados duplicados antes de aplicar o índice.
 * Se houver duplicatas existentes, elas são removidas preservando sempre o registro mais antigo.
 */
return new class extends Migration {
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. SUBJECTS → Índice UNIQUE em 'slug'
        //
        // Por que 'slug' e não 'name'? O slug é derivado do nome normalizado
        // e é o campo usado internamente como identificador canônico.
        // ------------------------------------------------------------------
        if (Schema::hasTable('subjects')) {
            // Remove duplicatas antes de criar o índice (preserva o mais antigo)
            DB::statement("
                DELETE s1 FROM subjects s1
                INNER JOIN subjects s2
                WHERE s1.id > s2.id AND s1.slug = s2.slug
            ");

            // Cria o índice UNIQUE no slug
            Schema::table('subjects', function (Blueprint $table) {
                $table->unique('slug', 'subjects_slug_unique');
            });
        }

        // ------------------------------------------------------------------
        // 2. TOPICS → Índice UNIQUE em 'slug'
        //
        // Mesmo raciocínio do subjects: impede criação duplicada de assuntos
        // quando múltiplos workers processam questões da mesma banca.
        // ------------------------------------------------------------------
        if (Schema::hasTable('topics')) {
            // Remove duplicatas antes de criar o índice (preserva o mais antigo)
            DB::statement("
                DELETE t1 FROM topics t1
                INNER JOIN topics t2
                WHERE t1.id > t2.id AND t1.slug = t2.slug
            ");

            // Cria o índice UNIQUE no slug
            Schema::table('topics', function (Blueprint $table) {
                $table->unique('slug', 'topics_slug_unique');
            });
        }

        // ------------------------------------------------------------------
        // 3. QUESTIONS → Índice UNIQUE em 'external_id'
        //
        // O external_id é calculado via MD5(org|year|institution|role|number).
        // É a "impressão digital" única de cada questão no scraper.
        // Sem esse índice, dois workers que processem questões do mesmo exam
        // poderiam criar duplicatas.
        //
        // NOTA: Permite NULL para questões criadas manualmente sem scraper.
        // ------------------------------------------------------------------
        if (Schema::hasTable('questions')) {
            // Remove duplicatas antes de criar o índice (preserva o mais antigo)
            DB::statement("
                DELETE q1 FROM questions q1
                INNER JOIN questions q2
                WHERE q1.id > q2.id AND q1.external_id = q2.external_id
                  AND q1.external_id IS NOT NULL
            ");

            // Cria o índice UNIQUE (não afeta NULLs — múltiplos NULLs são permitidos)
            Schema::table('questions', function (Blueprint $table) {
                $table->unique('external_id', 'questions_external_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Remove os índices criados (sem remover os dados)
        if (Schema::hasTable('questions')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropUnique('questions_external_id_unique');
            });
        }

        if (Schema::hasTable('topics')) {
            Schema::table('topics', function (Blueprint $table) {
                $table->dropUnique('topics_slug_unique');
            });
        }

        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropUnique('subjects_slug_unique');
            });
        }
    }
};
