<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change 'type' column in user_notifications from ENUM to VARCHAR.
 * 
 * The original ENUM only allowed ('info', 'success', 'warning', 'tip').
 * Since we added 'payment_pending', 'payment_expired', and 'essay_pending',
 * those insertions were being silently truncated or rejected by MySQL.
 * Changing to a flexible string structure resolves this and future-proofs it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // First we drop the column and recreate it as string to avoid strict DBAL ENUM change issues
            // But since this might drop data, we'll try raw SQL to modify it safely without dropping data.
        });

        // Use raw SQL to safely convert ENUM to VARCHAR without losing data
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE user_notifications MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'info'");
    }

    public function down(): void
    {
        // Reverting converts back to ENUM. Note: data outside the enum will be truncated if rolled back.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE user_notifications MODIFY COLUMN type ENUM('info','success','warning','tip') NOT NULL DEFAULT 'info'");
    }
};
