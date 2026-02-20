<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas de auditoria do módulo de importação.
     *
     * question_imports: Um registro por "lote" de importação (um .zip submetido).
     *   Armazena quem fez o upload, quantas questões vieram, etc.
     *
     * question_import_items: Um registro por questão dentro de um lote.
     *   Serve como o log de auditoria individual: quem aprovou, quando,
     *   e se foi revertida ("Retornar para Revisão").
     *   É através desta tabela que a seção de "Histórico" é alimentada.
     */
    public function up(): void
    {
        // Lote de importação (um upload de .zip)
        Schema::create('question_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_name');                                          // Ex: "FGV — 2026-02-20 02:00"
            $table->string('original_filename')->nullable();                       // Nome original do .zip
            $table->foreignId('uploaded_by')->constrained('users');               // Admin que fez o upload
            $table->integer('total_questions')->default(0);                       // Total de questões no .zip
            $table->integer('pending_count')->default(0);                         // Questões marcadas como 'pending'
            $table->integer('approved_count')->default(0);                        // Questões marcadas como 'approved' (import direto)
            $table->enum('status', ['processing', 'completed', 'failed'])
                  ->default('processing');                                         // Status geral do processamento
            $table->text('error_message')->nullable();                            // Mensagem de erro, se houver
            $table->timestamps();
        });

        // Log individual por questão dentro de um lote
        Schema::create('question_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')                                        // Lote ao qual pertence
                  ->constrained('question_imports')
                  ->cascadeOnDelete();
            $table->foreignId('question_id')                                      // A questão importada
                  ->constrained('questions')
                  ->cascadeOnDelete();
            $table->foreignId('approved_by')                                      // Admin que aprovou
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();                         // Quando foi aprovada
            $table->timestamp('reverted_at')->nullable();                         // Quando foi revertida para revisão
            $table->timestamps();

            // Uma questão pertence a apenas um item de importação
            $table->unique('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_items');
        Schema::dropIfExists('question_imports');
    }
};
