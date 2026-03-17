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
        Schema::table('simulations', function (Blueprint $blueprint) {
            $blueprint->timestamp('last_activity_at')->nullable()->after('status');
            $blueprint->json('notifications_sent')->nullable()->after('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simulations', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['last_activity_at', 'notifications_sent']);
        });
    }
};
