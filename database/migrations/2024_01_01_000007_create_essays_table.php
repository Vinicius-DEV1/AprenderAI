<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('essays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); // Tema da redação
            $table->text('content'); // Texto da redação
            $table->enum('status', ['pending', 'in_progress', 'evaluating', 'correcting', 'corrected', 'completed', 'error'])->default('pending');
            $table->integer('score')->nullable(); // 0 a 1000
            $table->json('competencies')->nullable(); // {C1: 5, C2: 4, C3: 5, C4: 4, C5: 3}
            $table->text('feedback')->nullable(); // Comentários gerais
            $table->json('ai_suggestions')->nullable(); // Sugestões detalhadas
            $table->text('example_essay')->nullable(); // Exemplo nota 1000 (Plus)
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essays');
    }
};
