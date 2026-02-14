<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['openai', 'gemini', 'grok']);
            $table->text('key'); // Será criptografada
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false); // Chave primária de fallback
            $table->timestamp('last_used_at')->nullable();
            $table->integer('requests_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'is_primary'], 'unique_primary_per_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
