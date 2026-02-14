<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['enem', 'concurso']);
            $table->enum('subject', ['matemática', 'português']);
            $table->string('theme')->nullable(); // Geometria, Interpretação, etc
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->integer('year')->nullable(); // Ano da prova (se real)
            $table->text('statement'); // Enunciado
            $table->json('alternatives'); // {A: "texto", B: "texto", ...}
            $table->char('correct_answer', 1); // A, B, C, D ou E
            $table->text('explanation')->nullable(); // Explicação da resposta
            $table->enum('source', ['manual', 'ai_generated'])->default('manual');
            $table->timestamps();

            $table->index(['type', 'subject', 'theme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
