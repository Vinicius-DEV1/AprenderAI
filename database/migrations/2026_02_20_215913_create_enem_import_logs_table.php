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
        Schema::create('enem_import_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('inserted_count')->default(0);
            $table->integer('ignored_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->text('errors')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enem_import_logs');
    }
};
