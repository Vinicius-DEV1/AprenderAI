<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulation_engine_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('simulation_models')->onDelete('cascade');
            $table->integer('total_questoes')->default(90);
            $table->integer('tempo_minutos')->default(270); // 4h30m default ENEM
            $table->decimal('percentual_ia', 5, 2)->default(10.00); // 10% IA
            $table->integer('nao_repetir_ultimos_simulados')->default(10); // exclude last N sims
            $table->enum('difficulty_mode', ['balanceado', 'progressivo', 'aleatorio'])->default('balanceado');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_engine_rules');
    }
};
