<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout Observability & Tracking System
 * Creates 4 tables to track the full checkout funnel, purchase intentions,
 * errors, and abandonment for analytics and conversion optimization.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Checkout Events ──────────────────────────────────────────────────
        // Tracks every meaningful event in the checkout journey
        if (!Schema::hasTable('checkout_events')) {
            Schema::create('checkout_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('session_id', 64)->nullable()->index();
                $table->string('event_type', 50); // prices_viewed, plan_clicked, checkout_opened, payment_initiated, payment_success, payment_failed, pix_generated, pix_expired
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('checkout_step', 50)->nullable(); // prices, checkout, payment, confirmation
                $table->string('payment_method', 30)->nullable(); // credit_card, pix
                $table->string('device', 30)->nullable();  // desktop, mobile, tablet
                $table->string('browser', 100)->nullable();
                $table->string('ip', 45)->nullable();
                $table->json('metadata')->nullable(); // extra context (coupon_used, installment_count, plan_amount, etc)
                $table->timestamps();

                $table->index(['event_type', 'created_at']);
                $table->index(['plan_id', 'event_type']);
                $table->index(['user_id', 'created_at']);
            });
        }

        // ─── Purchase Intentions ──────────────────────────────────────────────
        // Tracks every click on a plan subscription button and follows through to conversion
        if (!Schema::hasTable('purchase_intentions')) {
            Schema::create('purchase_intentions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('plan_id')->index();
                $table->decimal('plan_amount', 10, 2)->default(0);
                $table->string('source_page', 100)->nullable(); // /planos, /welcome-plans, /dashboard
                $table->string('device', 30)->nullable();
                $table->string('browser', 100)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('status', 20)->default('pending'); // pending, converted, abandoned
                $table->timestamp('converted_at')->nullable();
                $table->unsignedInteger('time_to_convert_seconds')->nullable();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['plan_id', 'status']);
            });
        }

        // ─── Checkout Errors ──────────────────────────────────────────────────
        // Records all checkout-related failures (payment, technical, frontend)
        if (!Schema::hasTable('checkout_errors')) {
            Schema::create('checkout_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('error_type', 50); // payment_failed, gateway_error, validation_error, frontend_error, timeout, network_error, unknown
                $table->text('error_message');
                $table->text('stack_trace')->nullable();
                $table->json('gateway_response')->nullable(); // sanitized Asaas error response
                $table->string('checkout_step', 50)->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('device', 30)->nullable();
                $table->string('browser', 100)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['error_type', 'created_at']);
                $table->index(['plan_id', 'error_type']);
            });
        }

        // ─── Checkout Abandonment ─────────────────────────────────────────────
        // Detected when checkout is opened but not completed within a session
        if (!Schema::hasTable('checkout_abandonment')) {
            Schema::create('checkout_abandonment', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('last_step_reached', 50)->nullable(); // checkout_opened, payment_initiated, pix_generated
                $table->unsignedInteger('time_spent_seconds')->nullable();
                $table->string('payment_method_selected', 30)->nullable();
                $table->boolean('had_coupon')->default(false);
                $table->timestamps();

                $table->index(['plan_id', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_abandonment');
        Schema::dropIfExists('checkout_errors');
        Schema::dropIfExists('purchase_intentions');
        Schema::dropIfExists('checkout_events');
    }
};
