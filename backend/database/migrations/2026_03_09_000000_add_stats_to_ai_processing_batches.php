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
        Schema::table('ai_processing_batches', function (Blueprint $table) {
            $table->json('stats')->nullable()->after('errors_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_processing_batches', function (Blueprint $table) {
            $table->dropColumn('stats');
        });
    }
};
