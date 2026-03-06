<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 - Performance Indexes
 * All additional indexes added after table creation.
 * Consolidated from add_performance_indexes migration.
 */
return new class extends Migration {
    public function up(): void
    {
        // questions performance indexes are already defined inline in M3,
        // users indexes (role, is_banned) also inline in M2,
        // question_subject/topic indexes also inline in M3.
        // This migration is intentionally a no-op stub kept for future
        // index additions without polluting the main table migrations.
    }

    public function down(): void
    {
        // No indexes to drop — all inline in their respective table migrations.
    }
};
