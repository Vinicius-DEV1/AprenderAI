<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->index();
            $table->string('type')->nullable(); // enem or concurso, or null if shared
            $table->timestamps();
        });

        Schema::create('question_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_subject');
        Schema::dropIfExists('subjects');
    }
};
