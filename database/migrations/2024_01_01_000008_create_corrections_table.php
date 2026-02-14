<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->morphs('correctable'); // simulation ou essay
            $table->string('ai_provider')->nullable(); // openai, gemini, grok
            $table->string('ai_model')->nullable(); // gpt-4, gemini-pro, etc
            $table->integer('tokens_used')->default(0);
            $table->json('correction_data')->nullable(); // Dados brutos da correção
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
