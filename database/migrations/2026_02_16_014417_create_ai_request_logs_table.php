<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->onDelete('set null');
            $table->string('api_key_name')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->longText('prompt_text')->nullable();
            $table->longText('response_text')->nullable();
            $table->integer('tokens_used_input')->default(0);
            $table->integer('tokens_used_output')->default(0);
            $table->integer('tokens_used_total')->default(0);
            $table->float('execution_time')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
