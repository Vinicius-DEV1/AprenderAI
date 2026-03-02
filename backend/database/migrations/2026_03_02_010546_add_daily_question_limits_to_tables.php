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
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('daily_question_limit')->default(0)->after('essays_limit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('daily_questions_used')->default(0)->after('essays_used_this_month');
            $table->timestamp('daily_questions_reset_at')->nullable()->after('daily_questions_used');
            $table->integer('max_daily_questions_override')->nullable()->after('daily_questions_reset_at');
        });

        // Convert existing '0' values in other limit fields to '9999' (unlimited) for consistency
        // as per the requirement previously discussed.
        \Illuminate\Support\Facades\DB::table('plans')->where('slug', 'plus')->orWhere('slug', 'plus-annual')->update(['daily_question_limit' => 9999]);
        \Illuminate\Support\Facades\DB::table('plans')->where('slug', 'basic')->orWhere('slug', 'basic-annual')->update(['daily_question_limit' => 10]);
        \Illuminate\Support\Facades\DB::table('plans')->where('slug', 'free')->update(['daily_question_limit' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('daily_question_limit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_questions_used', 'daily_questions_reset_at', 'max_daily_questions_override']);
        });
    }
};
