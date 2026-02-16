<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_ai_questions')->default(10)->after('essays_limit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('ai_questions_count')->default(0)->after('is_banned');
            $table->timestamp('last_reset_at')->nullable()->after('ai_questions_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_ai_questions');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_questions_count', 'last_reset_at']);
        });
    }
};
