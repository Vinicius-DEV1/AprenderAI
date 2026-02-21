<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AVISO CRÍTICO DE ARQUITETURA
        // Estas tabelas consolidam a amarração N:N pura entre Subjects/Topics e Questions

        Schema::create('question_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'subject_id']);
        });

        Schema::create('question_topic', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            
            $table->primary(['question_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_topic');
        Schema::dropIfExists('question_subject');
    }
};
