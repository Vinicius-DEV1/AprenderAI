<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Individual Quota Override Columns to Users Table
 *
 * RATIONALE:
 * This mirrors the existing `max_ai_questions_override` pattern for AI Prompts,
 * extending the same admin-override capability to Simulations and Essays.
 *
 * When null → the plan's default limit applies.
 * When set  → this value takes precedence over the plan limit for that specific user.
 *
 * This allows granular control per user without touching plan-wide settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Individual override for the simulation monthly limit.
            // null means "use plan default". 0 means "unlimited" (matching Plan::isUnlimited logic).
            $table->integer('max_simulations_override')
                ->nullable()
                ->after('simulations_used_this_month')
                ->comment('Per-user override for monthly simulations_limit. null = use plan default.');

            // Individual override for the monthly essay creation limit.
            // null means "use plan default". 0 means "unlimited".
            $table->integer('max_essays_override')
                ->nullable()
                ->after('max_simulations_override')
                ->comment('Per-user override for monthly essays_limit. null = use plan default.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['max_simulations_override', 'max_essays_override']);
        });
    }
};
