<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add related_essay_id to user_notifications.
 *
 * This is a separate column from related_payment_id (which has a FK to
 * subscriptions) so that essay IDs can be stored without violating the FK
 * constraint on subscriptions.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Nullable, no FK — intentionally generic so any essay ID fits
            $table->unsignedBigInteger('related_essay_id')->nullable()->after('related_payment_id');

            // Speed up deduplication queries for essay notifications
            $table->index(['user_id', 'related_essay_id', 'type'], 'un_user_essay_type');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('un_user_essay_type');
            $table->dropColumn('related_essay_id');
        });
    }
};
