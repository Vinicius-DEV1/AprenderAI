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
        Schema::table('study_plans', function (Blueprint $table) {
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing')->after('hours_per_day');
            $table->text('error_message')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('error_message');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->json('plan_json')->nullable()->change();
            $table->json('stats_snapshot')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('study_plans', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message', 'started_at', 'finished_at']);
            // Revert changes to nullable if needed, but usually safe to leave or difficult to revert with data
            // $table->json('plan_json')->nullable(false)->change(); // Can be tricky if null values exist
        });
    }
};
