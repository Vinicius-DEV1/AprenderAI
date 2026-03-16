<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Monitoring Tables
 * Native, internal platform usage tracking system.
 * Completely independent from Google Analytics.
 *
 * Tables:
 *   - platform_sessions   : one row per user login session
 *   - platform_events     : one row per tracked action/event
 *   - platform_heartbeats : one row per user (upsert), for online detection
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Sessions ───────────────────────────────────────────────────────────
        Schema::create('platform_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 64)->unique()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index('started_at');
        });

        // ─── Events ─────────────────────────────────────────────────────────────
        Schema::create('platform_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 64)->nullable()->index();
            // Type examples: 'question.answered', 'simulation.created', 'essay.submitted', 'page.visited'
            $table->string('event_type', 80)->index();
            $table->string('page', 255)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_type', 50)->nullable(); // 'question', 'simulation', 'essay'
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable(); // is_correct, score, etc.
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        // ─── Heartbeats ──────────────────────────────────────────────────────────
        // One row per user — upserted every 60s while user is active.
        // A user is considered "online" if pinged_at >= now() - 3 minutes.
        Schema::create('platform_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('session_token', 64)->nullable();
            $table->string('current_page', 255)->nullable();
            $table->timestamp('pinged_at')->useCurrent();
            $table->timestamps();

            $table->index('pinged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_heartbeats');
        Schema::dropIfExists('platform_events');
        Schema::dropIfExists('platform_sessions');
    }
};
