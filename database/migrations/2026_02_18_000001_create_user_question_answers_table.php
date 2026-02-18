<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('user_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('selected_answer', 1); // A, B, C, D, E
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'question_id']); // Cada aluno responde cada questão uma vez
            $table->index(['user_id', 'is_correct']); // Para queries de stats
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_question_answers');
    }
};
