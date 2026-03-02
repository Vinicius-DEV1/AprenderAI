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
        Schema::create('ai_search_cache', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_hash')->unique();
            $table->text('prompt_text');
            $table->json('embedding');
            $table->json('filters_result');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_search_cache');
    }
};
