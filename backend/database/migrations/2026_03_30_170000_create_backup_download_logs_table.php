<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the backup_download_logs table to audit every local backup download.
 * Stores who downloaded, from which IP, what type, and when — for compliance
 * and security review by administrators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_download_logs', function (Blueprint $table) {
            $table->id();

            // Admin who triggered the download (nullable in case user is deleted)
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();     // snapshot at time of download
            $table->string('user_email')->nullable();    // snapshot at time of download

            // Network details
            $table->string('ip_address', 45)->nullable(); // supports IPv6

            // Type of download: sql_only | full_mysql_images | full_all (includes Qdrant)
            $table->string('download_type', 30)->default('sql_only');

            // Generated filename for the download (e.g. backup_2026-03-30_full.tar.gz)
            $table->string('filename')->nullable();

            $table->timestamps(); // created_at used as the "downloaded at" timestamp
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_download_logs');
    }
};
