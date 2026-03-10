<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_search_requests', function (Blueprint $table) {
            $table->decimal('similarity_threshold', 3, 2)->nullable()->after('filters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_search_requests', function (Blueprint $table) {
            $table->dropColumn('similarity_threshold');
        });
    }
};
