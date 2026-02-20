<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de revisão de importação à tabela questions.
     *
     * - review_status: controla o fluxo do painel de revisão pós-importação.
     *     NULL       → questão existente (anterior ao módulo de importação)
     *     'pending'  → importada via scraper, aguarda revisão do admin (ex: tem imagem/alternativas visuais)
     *     'approved' → revisão concluída, questão visível no banco público para alunos
     *
     * - image_path: caminho relativo no storage público para a imagem principal
     *     da questão (ex: "questoes/fgv/p1_q5_img.jpg"). Substituirá o nome
     *     de arquivo bruto gerado pelo scraper pelo path correto na VPS.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Status de revisão; NULL significa que a questão não veio do importador
            $table->enum('review_status', ['pending', 'approved'])
                  ->nullable()
                  ->after('source')
                  ->index(); // Indexado pois o painel de revisão filtra muito por este campo

            // Caminho relativo da imagem principal da questão no storage público
            $table->string('image_path')->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['review_status']); // Remove o índice antes da coluna
            $table->dropColumn(['review_status', 'image_path']);
        });
    }
};
