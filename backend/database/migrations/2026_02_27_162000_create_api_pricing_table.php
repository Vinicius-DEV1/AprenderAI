<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('api_name');       // Ex: "Google Gemini"
            $table->string('model_key')->unique(); // Ex: "gemini-1.5-flash"
            $table->decimal('input_price_per_1m', 20, 8)->default(0); // USD per 1M input tokens
            $table->decimal('output_price_per_1m', 20, 8)->default(0); // USD per 1M output tokens
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_pricing');
    }
};
