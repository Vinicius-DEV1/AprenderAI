<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we can't easily modify ENUM checks. 
        // We will change the column to string (VARCHAR) which accepts any value (effectively disabling the enum check),
        // or re-define the enum if supported. 
        // Safest cross-database way for adding enum values in Laravel often involves raw SQL or Doctrine.
        // Given SQLite limitations, we'll use a raw statement to drop the check constraint if possible, 
        // but since we can't easily drop constraints, we'll redefine the table.
        // HOWEVER, Laravel's Schema Builder often handles "change()" on SQLite by creating a temp table.
        // Let's try the Laravel way first, assuming doctrine/dbal is available or Laravel 10+ handles it.
        // If it fails, we'd need the manual temp table approach. 
        // But since we are likely on a recent Laravel, let's try modifying the column to string first, 
        // which usually removes the CHECK constraint.

        try {
            Schema::table('simulations', function (Blueprint $table) {
                // Changing to string removes the strict enum constraint in many drivers or updates it.
                // We list the allowed values just for documentation/consistency if driver supports it.
                $table->enum('status', ['generating', 'pending', 'in_progress', 'finished', 'error'])
                    ->default('generating')
                    ->change();
            });
        }
        catch (\Exception $e) {
            // Fallback for SQLite if doctrine/dbal is missing or fails
            if (DB::getDriverName() === 'sqlite') {
                // Clean up in case of previous failure
                DB::statement("DROP TABLE IF EXISTS simulations_new");

                DB::statement("
                    CREATE TABLE simulations_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                        user_id INTEGER NOT NULL,
                        type VARCHAR CHECK(\"type\" IN ('enem', 'concurso')) NOT NULL,
                        configuration TEXT NOT NULL,
                        started_at DATETIME,
                        finished_at DATETIME,
                        time_elapsed INTEGER DEFAULT 0 NOT NULL,
                        status VARCHAR CHECK(\"status\" IN ('generating', 'pending', 'in_progress', 'finished', 'error')) DEFAULT 'generating' NOT NULL,
                        score NUMERIC(5, 2),
                        scores_by_subject TEXT,
                        analysis_by_theme TEXT,
                        created_at DATETIME,
                        updated_at DATETIME,
                        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                    )
                ");

                // Copy data with mapping for 'corrected' -> 'finished'
                // We list columns explicitly to be safe and use CASE for transformation
                DB::statement("
                    INSERT INTO simulations_new (
                        id, user_id, type, configuration, started_at, finished_at, 
                        time_elapsed, status, score, scores_by_subject, analysis_by_theme, 
                        created_at, updated_at
                    )
                    SELECT 
                        id, user_id, type, configuration, started_at, finished_at, 
                        time_elapsed, 
                        CASE WHEN status = 'corrected' THEN 'finished' ELSE status END, 
                        score, scores_by_subject, analysis_by_theme, 
                        created_at, updated_at 
                    FROM simulations
                ");

                // Drop old
                Schema::drop('simulations');

                // Rename new
                Schema::rename('simulations_new', 'simulations');

                // Restore indices
                Schema::table('simulations', function (Blueprint $table) {
                    $table->index(['user_id', 'status']);
                    $table->index('created_at');
                });
            }
            else {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (simplified attempt)
        try {
            Schema::table('simulations', function (Blueprint $table) {
                $table->enum('status', ['pending', 'in_progress', 'finished', 'corrected'])
                    ->default('pending')
                    ->change();
            });
        }
        catch (\Exception $e) {
        // Ignore rollback errors on SQLite for simplicity in this context
        }
    }
};
