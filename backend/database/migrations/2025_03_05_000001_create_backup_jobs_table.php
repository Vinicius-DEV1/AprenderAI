<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('s3_bucket')->nullable();
            $table->string('s3_key')->nullable();           // ex: backups/2025-03-05_00-00-00.sql.gz
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
