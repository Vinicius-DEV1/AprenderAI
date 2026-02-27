<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_pricing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_pricing_id')->constrained('api_pricing')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_input_price_per_1m', 20, 8)->default(0);
            $table->decimal('old_output_price_per_1m', 20, 8)->default(0);
            $table->decimal('new_input_price_per_1m', 20, 8)->default(0);
            $table->decimal('new_output_price_per_1m', 20, 8)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_pricing_logs');
    }
};
