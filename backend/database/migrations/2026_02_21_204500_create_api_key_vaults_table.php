<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_vaults', function (Blueprint $table) {
            $table->id();
            $table->string('nickname')->unique();
            $table->string('provider'); // openai, gemini, grok
            $table->text('key'); // encrypted
            $table->boolean('is_valid')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_vaults');
    }
};
