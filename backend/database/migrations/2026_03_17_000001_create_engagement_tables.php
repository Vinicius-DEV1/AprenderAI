<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement Tables — Communication, Support, Notifications & Feedback
 *
 * Creates all tables for the engagement subsystems:
 *   - support_tickets / support_messages  (in-app chat between user and admin team)
 *   - banners                             (configurable banners/comunicados shown to users)
 *   - banner_interactions                 (tracks views, clicks, closes per user)
 *   - user_notifications                  (internal notification bell system)
 *   - feature_feedbacks                   (quick 👍/👎 feedback per feature key)
 *   - user_suggestions / suggestion_votes (users submit ideas; other users vote)
 *
 * All table creations are guarded with if (!Schema::hasTable(...)) to be safe
 * to re-run in environments where the migration state may be inconsistent.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Support System ───────────────────────────────────────────────────

        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();

                // FK to the user who opened the ticket
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                // Optional subject line set by the user when opening the ticket
                $table->string('subject')->nullable();

                /**
                 * Ticket lifecycle states:
                 *   new             – just opened, no admin has seen it
                 *   waiting_admin   – user sent a message, waiting for admin reply
                 *   waiting_user    – admin replied, waiting for user follow-up
                 *   resolved        – marked resolved by admin
                 *   closed          – archived / no further action needed
                 */
                $table->enum('status', ['new', 'waiting_admin', 'waiting_user', 'resolved', 'closed'])
                      ->default('new');

                // Timestamp of the most recent message (drives "sort by last activity")
                $table->timestamp('last_message_at')->nullable();

                // Admin user currently assigned to this ticket (optional)
                $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('last_message_at');
            });
        }

        if (!Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();

                // 'user' = message sent by the user, 'admin' = sent by an admin
                $table->enum('sender_type', ['user', 'admin'])->default('user');

                // The actual sender's user id (works for both roles since both are in users table)
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

                // Message body (can be empty when sending only an attachment)
                $table->text('body')->nullable();

                // Optional image/file attachment (relative path within storage/app/public)
                $table->string('attachment_path')->nullable();

                // Tracks whether the other party has read this message
                $table->boolean('is_read')->default(false);

                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
            });
        }

        // ─── Banners / Comunicados ────────────────────────────────────────────

        if (!Schema::hasTable('banners')) {
            Schema::create('banners', function (Blueprint $table) {
                $table->id();

                $table->string('title');
                $table->text('body')->nullable();

                // Optional image stored in public storage
                $table->string('image_path')->nullable();

                // CSS-valid color string (hex, rgba, named) for background
                $table->string('background_color', 50)->nullable();

                /**
                 * How the banner is shown to the user:
                 *   modal        – full overlay modal (centered)
                 *   bar          – fixed top/bottom bar
                 *   notification – toast/side notification
                 *   card         – card embedded inside the dashboard
                 */
                $table->enum('display_type', ['modal', 'bar', 'notification', 'card'])
                      ->default('modal');

                // Optional CTA button
                $table->string('button_text')->nullable();
                $table->string('button_url')->nullable();

                // Scheduling (null = no restriction)
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();

                /**
                 * Frequency control per user:
                 *   once         – shown only once per user ever
                 *   limited      – shown up to max_views_per_user times
                 *   until_close  – shown until user explicitly closes it
                 *   always       – shown every time the trigger condition fires
                 */
                $table->enum('frequency', ['once', 'limited', 'until_close', 'always'])
                      ->default('once');

                // Used only when frequency = 'limited'
                $table->unsignedInteger('max_views_per_user')->nullable();

                /**
                 * User segmentation:
                 *   all           – all authenticated users
                 *   new           – registered in the last 7 days
                 *   old           – registered more than 30 days ago
                 *   inactive      – no login in the last 7 days
                 *   no_simulation – never started a simulation
                 *   no_essay      – never submitted an essay
                 *   free          – no active paid plan
                 *   premium       – has an active paid plan
                 */
                $table->enum('target_segment', [
                    'all', 'new', 'old', 'inactive',
                    'no_simulation', 'no_essay', 'free', 'premium'
                ])->default('all');

                $table->boolean('is_active')->default(true);

                // Who created this banner
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['is_active', 'starts_at', 'ends_at']);
                $table->index('target_segment');
            });
        }

        if (!Schema::hasTable('banner_interactions')) {
            Schema::create('banner_interactions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                /**
                 * What the user did:
                 *   view  – banner was displayed to the user
                 *   click – user clicked the CTA button
                 *   close – user dismissed the banner
                 */
                $table->enum('interaction_type', ['view', 'click', 'close']);

                $table->timestamps();

                $table->index(['banner_id', 'interaction_type']);
                $table->index(['user_id', 'banner_id']);
            });
        }

        // ─── Internal Notifications ───────────────────────────────────────────

        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('title');
                $table->text('body')->nullable();

                /**
                 * Visual type: drives icon color and icon displayed in the bell dropdown.
                 *   info    – blue information
                 *   success – green achievement / completion
                 *   warning – orange caution
                 *   tip     – purple suggestion / tip
                 */
                $table->enum('type', ['info', 'success', 'warning', 'tip'])->default('info');

                // Optional deep-link URL (e.g. "/simulados/123")
                $table->string('action_url')->nullable();

                // Null = unread; set to timestamp when user marks it read
                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'read_at']);
            });
        }

        // ─── Feature Feedback (👍 / 👎) ───────────────────────────────────────

        if (!Schema::hasTable('feature_feedbacks')) {
            Schema::create('feature_feedbacks', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                // Identifies which feature is being rated (e.g. "simulado-resultado")
                $table->string('feature_key');

                // true = positive (👍), false = negative (👎)
                $table->boolean('is_positive');

                // Optional free-text comment (future extension)
                $table->text('comment')->nullable();

                $table->timestamps();

                // One vote per user per feature
                $table->unique(['user_id', 'feature_key']);

                $table->index('feature_key');
            });
        }

        // ─── User Suggestions ─────────────────────────────────────────────────

        if (!Schema::hasTable('user_suggestions')) {
            Schema::create('user_suggestions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('title');
                $table->text('body')->nullable();

                // Denormalized vote count — updated via DB increment for performance
                $table->unsignedInteger('votes_count')->default(0);

                /**
                 * Admin moderation status:
                 *   pending      – just submitted, awaiting review
                 *   under_review – admin is evaluating
                 *   planned      – accepted and on the roadmap
                 *   done         – implemented
                 *   rejected     – not accepted
                 */
                $table->enum('status', ['pending', 'under_review', 'planned', 'done', 'rejected'])
                      ->default('pending');

                $table->timestamps();

                $table->index(['status', 'votes_count']);
            });
        }

        if (!Schema::hasTable('suggestion_votes')) {
            Schema::create('suggestion_votes', function (Blueprint $table) {
                $table->id();

                $table->foreignId('suggestion_id')->constrained('user_suggestions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->timestamps();

                // Each user can vote only once per suggestion
                $table->unique(['suggestion_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // Drop in reverse order of dependencies
        Schema::dropIfExists('suggestion_votes');
        Schema::dropIfExists('user_suggestions');
        Schema::dropIfExists('feature_feedbacks');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('banner_interactions');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
