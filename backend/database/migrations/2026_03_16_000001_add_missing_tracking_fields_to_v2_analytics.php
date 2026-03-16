<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add IP, Device, Browser to checkout_abandonment for anonymous visitor tracking
        Schema::table('checkout_abandonment', function (Blueprint $table) {
            if (!Schema::hasColumn('checkout_abandonment', 'ip')) {
                $table->string('ip', 45)->nullable()->after('payment_method_selected');
            }
            if (!Schema::hasColumn('checkout_abandonment', 'device')) {
                $table->string('device', 30)->nullable()->after('ip');
            }
            if (!Schema::hasColumn('checkout_abandonment', 'browser')) {
                $table->string('browser', 100)->nullable()->after('device');
            }
        });

        // Add had_coupon to purchase_intentions to track financial impact of coupons
        Schema::table('purchase_intentions', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_intentions', 'had_coupon')) {
                $table->boolean('had_coupon')->default(false)->after('plan_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkout_abandonment', function (Blueprint $table) {
            $table->dropColumn(['ip', 'device', 'browser']);
        });

        Schema::table('purchase_intentions', function (Blueprint $table) {
            $table->dropColumn('had_coupon');
        });
    }
};
