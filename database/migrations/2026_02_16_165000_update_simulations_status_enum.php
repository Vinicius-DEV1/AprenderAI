<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Normalize NULL or empty values to 'pending'
        DB::statement("UPDATE simulations SET status = 'pending' WHERE status IS NULL OR status = ''");

        // 2. Normalize values that are not in the new allowed list to 'pending'
        // Allowed values: 'generating', 'pending', 'in_progress', 'finished', 'error'
        DB::statement("UPDATE simulations SET status = 'pending' WHERE status NOT IN ('generating', 'pending', 'in_progress', 'finished', 'error')");

        // 3. Modify the column to the new ENUM definition
        DB::statement("ALTER TABLE simulations MODIFY status ENUM('generating', 'pending', 'in_progress', 'finished', 'error') NOT NULL DEFAULT 'generating'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Normalize values to fit into the old schema (pending, in_progress, finished, corrected)
        // Convert 'generating', 'error' or others to 'pending'
        DB::statement("UPDATE simulations SET status = 'pending' WHERE status NOT IN ('pending', 'in_progress', 'finished', 'corrected')");

        // Revert to the previous ENUM definition
        DB::statement("ALTER TABLE simulations MODIFY status ENUM('pending', 'in_progress', 'finished', 'corrected') NOT NULL DEFAULT 'pending'");
    }
};
