<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usage_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_cycle_id')->constrained()->onDelete('cascade');
            $table->string('feature_name', 50); // ex: 'essay', 'simulations'
            $table->integer('amount')->default(1);
            $table->string('type', 50)->default('consumption'); // ex: 'consumption', 'bonus'
            $table->timestamps();

            $table->index(['subscription_cycle_id', 'feature_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_ledgers');
    }
};
