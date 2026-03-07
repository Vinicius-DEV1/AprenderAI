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
            $table->decimal('estimated_cost', 10, 6)->default(0)->after('output_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_processing_batches', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
        });
    }
};
