<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('user_answer', 1)->nullable(); // A, B, C, D, E ou null se não respondeu
            $table->boolean('is_correct')->default(false);
            $table->integer('time_spent')->default(0); // em segundos
            $table->boolean('marked_for_review')->default(false);
            $table->timestamps();

            $table->unique(['simulation_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_answers');
    }
};
