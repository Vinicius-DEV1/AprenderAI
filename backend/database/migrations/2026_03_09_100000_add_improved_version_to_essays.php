<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add improved_version column to essays table.
 * The improved_version was previously only stored inside feedback_json,
 * but this dedicated column ensures it's easily queryable and
 * persisted correctly by EvaluateEssayJob.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            if (!Schema::hasColumn('essays', 'improved_version')) {
                $table->longText('improved_version')->nullable()->after('ai_suggestions');
            }
            if (!Schema::hasColumn('essays', 'theme')) {
                $table->string('theme')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('essays', function (Blueprint $table) {
            $table->dropColumnIfExists('improved_version');
        });
    }
};
