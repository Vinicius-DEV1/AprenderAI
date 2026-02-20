<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['enem', 'concurso']);
            $table->json('configuration'); // {questões, disciplinas, tempo, redação}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('time_elapsed')->default(0); // em segundos
            $table->enum('status', ['generating', 'pending', 'in_progress', 'finished', 'corrected', 'error'])->default('pending');
            $table->decimal('score', 5, 2)->nullable(); // Nota final
            $table->json('scores_by_subject')->nullable(); // {matemática: X, português: Y}
            $table->json('analysis_by_theme')->nullable(); // Análise detalhada
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations');
    }
};
