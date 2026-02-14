<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('remember_token')->constrained('plans')->nullOnDelete();
            $table->timestamp('plan_started_at')->nullable()->after('plan_id');
            $table->timestamp('plan_expires_at')->nullable()->after('plan_started_at');
            $table->integer('simulations_used_this_month')->default(0)->after('plan_expires_at');
            $table->integer('essays_used_this_month')->default(0)->after('simulations_used_this_month');
            $table->timestamp('usage_reset_at')->nullable()->after('essays_used_this_month');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'plan_started_at',
                'plan_expires_at',
                'simulations_used_this_month',
                'essays_used_this_month',
                'usage_reset_at',
            ]);
        });
    }
};
