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
        // 1. Add daily_goal to users table
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('daily_goal')->default(10)->after('max_daily_questions_override');
        });

        // 2. Create user_daily_stats table for performance and streaks
        Schema::create('user_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedInteger('total_answered')->default(0);
            $table->unsignedInteger('total_correct')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_daily_stats');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('daily_goal');
        });
    }
};
