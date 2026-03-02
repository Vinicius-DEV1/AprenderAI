<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('question_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('action'); // 'viewed' or 'answered'
            $table->boolean('is_correct')->nullable();
            $table->integer('time_spent_seconds')->nullable();
            $table->string('source')->nullable(); // 'banco', 'simulado', 'treino'
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Indexes for Analytics performance
            $table->index(['user_id', 'action']);
            $table->index('created_at');
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_event_logs');
    }
};
