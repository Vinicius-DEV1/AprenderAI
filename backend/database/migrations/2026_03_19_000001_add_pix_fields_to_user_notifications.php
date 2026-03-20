<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Pix payment recovery fields to user_notifications.
 *
 * - related_payment_id: FK to subscriptions table (nullable) — links the
 *   notification to the specific Pix subscription it belongs to. Used to
 *   avoid duplicate notifications and to invalidate them on payment confirmation.
 *
 * - meta: JSON blob for auxiliary data shown in the frontend
 *   (e.g. plan_name, amount, pix_expires_at).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Link back to the subscription that triggered this notification
            $table->unsignedBigInteger('related_payment_id')->nullable()->after('action_url');
            $table->foreign('related_payment_id')->references('id')->on('subscriptions')->nullOnDelete();

            // Extra data for Pix recovery UI
            $table->json('meta')->nullable()->after('related_payment_id');

            // Index to speed up deduplication queries
            $table->index(['user_id', 'related_payment_id', 'type'], 'un_user_payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropForeign(['related_payment_id']);
            $table->dropIndex('un_user_payment_type');
            $table->dropColumn(['related_payment_id', 'meta']);
        });
    }
};
