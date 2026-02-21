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
        Schema::create('ai_processing_batches', function (Blueprint $col) {
            $col->id();
            $col->string('batch_id')->unique();
            $col->string('model')->nullable();
            $col->string('type')->comment('difficulty, explanation, both');
            $col->integer('total_count');
            $col->integer('processed_count')->default(0);
            $col->integer('error_count')->default(0);
            $col->enum('status', ['processing', 'completed', 'failed', 'cancelled'])->default('processing');
            $col->json('errors_log')->nullable();
            $col->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_processing_batches');
    }
};
