<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('total_simulations')->default(0);
            $table->integer('total_essays')->default(0);
            $table->decimal('average_math_score', 5, 2)->default(0);
            $table->decimal('average_portuguese_score', 5, 2)->default(0);
            $table->decimal('average_overall_score', 5, 2)->default(0);
            $table->integer('total_time_studied')->default(0); // em segundos
            $table->json('weak_themes')->nullable(); // Temas com pior desempenho
            $table->json('strong_themes')->nullable(); // Temas com melhor desempenho
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
