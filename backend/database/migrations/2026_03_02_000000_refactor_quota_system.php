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
        // 1. Alter subscription_cycles
        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            // Make subscription_id nullable so cycles can exist without a formal payment subscription
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });

        // Update existing cycles to link to the user via their subscription
        \Illuminate\Support\Facades\DB::update('
            UPDATE subscription_cycles sc
            JOIN subscriptions s ON sc.subscription_id = s.id
            SET sc.user_id = s.user_id
        ');

        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Legacy columns removed from earlier migrations, no need to drop them here.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('simulations_used_this_month')->default(0);
            $table->integer('essays_used_this_month')->default(0);
            $table->integer('daily_questions_used')->default(0);
            $table->timestamp('daily_questions_reset_at')->nullable();
            $table->timestamp('usage_reset_at')->nullable();
        });

        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            // Revert subscription_id to non-nullable (may fail if there are records with null)
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });
    }
};
