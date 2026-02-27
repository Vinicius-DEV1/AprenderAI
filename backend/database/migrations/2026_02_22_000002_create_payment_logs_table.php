<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway')->default('asaas');
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_subscription_id')->nullable()->index();
            // event: CHECKOUT_ATTEMPT, PAYMENT_CONFIRMED, PAYMENT_RECEIVED, PAYMENT_OVERDUE, etc.
            $table->string('event');
            // status: success, failed, ignored, error
            $table->string('status');
            // Raw API response body — card data (number, ccv) NEVER stored here
            $table->text('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
