<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds concept_ids JSON column to ai_search_cache table.
 * This allows the SemanticCacheService to persist which concept IDs
 * were detected during a search, so L1/L2 cache hits also return
 * concept context for the learning loop.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_search_cache', function (Blueprint $table) {
            $table->json('concept_ids')->nullable()->after('filters_result');
        });
    }

    public function down(): void
    {
        Schema::table('ai_search_cache', function (Blueprint $table) {
            $table->dropColumn('concept_ids');
        });
    }
};
