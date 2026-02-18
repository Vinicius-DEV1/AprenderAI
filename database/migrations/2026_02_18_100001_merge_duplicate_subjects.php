<?php

/**
 * Migration: Consolidar Subjects Duplicados
 * ==========================================
 *
 * CONTEXTO:
 * A tabela `subjects` contém 4 registros, mas apenas 2 matérias reais.
 * Os registros se diferenciam apenas pela capitalização:
 *   - ID 1: "português"  ←  568 questões ENEM + 35 concurso
 *   - ID 2: "matemática"  ← 652 questões ENEM + 35 concurso
 *   - ID 3: "Matemática"  ←  30 questões ENEM + 26 concurso  (DUPLICATA de ID 2)
 *   - ID 4: "Português"   ←  30 questões ENEM + 28 concurso  (DUPLICATA de ID 1)
 *
 * PROBLEMA:
 * Quando o aluno filtra por "Matemática" (capitalizada), o sistema retorna
 * apenas as 56 questões do ID 3 ao invés das 708 totais.
 *
 * SOLUÇÃO:
 * 1. Mover todos os pivots de ID 3 → ID 2 (Matemática → matemática)
 * 2. Mover todos os pivots de ID 4 → ID 1 (Português → português)
 * 3. Deletar os registros órfãos (IDs 3 e 4)
 * 4. Capitalizar os nomes restantes para consistência visual
 *
 * RESULTADO ESPERADO:
 * - Tabela `subjects` com 2 registros: "Matemática" (ID 2), "Português" (ID 1)
 * - Tabela `question_subject` com 1404 pivots inalterados funcionalmente
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // ─── Passo 1: Redirecionar pivots dos duplicados para os originais ───
        //
        // "Matemática" (ID 3) → "matemática" (ID 2)
        // Antes de mover, precisamos lidar com possíveis conflitos de unique key.
        // Como cada questão só tem 1 subject (confirmado pela auditoria),
        // não haverá conflito de duplicidade no pivot.
        DB::table('question_subject')
            ->where('subject_id', 3)
            ->update(['subject_id' => 2]);

        // "Português" (ID 4) → "português" (ID 1)
        DB::table('question_subject')
            ->where('subject_id', 4)
            ->update(['subject_id' => 1]);

        // ─── Passo 2: Deletar os registros órfãos da tabela subjects ───
        //
        // IDs 3 e 4 não terão mais nenhum pivot apontando para eles,
        // então é seguro removê-los.
        DB::table('subjects')->whereIn('id', [3, 4])->delete();

        // ─── Passo 3: Capitalizar os nomes restantes ───
        //
        // De: "português" → "Português"
        // De: "matemática" → "Matemática"
        // Isso garante consistência visual nos filtros/selects da UI.
        DB::table('subjects')->where('id', 1)->update(['name' => 'Português']);
        DB::table('subjects')->where('id', 2)->update(['name' => 'Matemática']);
    }

    /**
     * Rollback: recria os duplicados e redistribui os pivots.
     * NOTA: O rollback é aproximado — não temos como saber exatamente quais
     * pivots pertenciam aos IDs 3/4 originais, então apenas recriamos os
     * registros na tabela subjects sem redistribuir pivots.
     */
    public function down(): void
    {
        // Reverter capitalização
        DB::table('subjects')->where('id', 1)->update(['name' => 'português']);
        DB::table('subjects')->where('id', 2)->update(['name' => 'matemática']);

        // Recriar os registros deletados (sem redistribuir pivots)
        DB::table('subjects')->insert([
            ['id' => 3, 'name' => 'Matemática', 'slug' => 'matematica', 'type' => 'enem', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Português', 'slug' => 'portugues', 'type' => 'enem', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
