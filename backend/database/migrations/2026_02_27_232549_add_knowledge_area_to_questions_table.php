<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_knowledge_area_to_questions_table
 *
 * Motivação: A API ENEM Dev retorna dois campos distintos:
 *   - "discipline": a grande área do conhecimento (ex: "linguagens", "matematica")
 *   - "language": a língua específica, se aplicável (ex: "ingles", "espanhol")
 *
 * Antes desta correção, "discipline" era salvo como Subject (matéria), o que estava
 * conceitualmente incorreto. Esta migration adiciona a coluna `knowledge_area` para
 * armazenar a grande área corretamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Adiciona a coluna de grande área do conhecimento após o campo 'type'
            $table->string('knowledge_area')->nullable()->after('type')->index()->comment('Grande área do conhecimento (ex: linguagens, matematica, ciencias-humanas)');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('knowledge_area');
        });
    }
};
