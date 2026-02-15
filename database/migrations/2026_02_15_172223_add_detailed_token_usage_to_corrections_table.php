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
        Schema::table('corrections', function (Blueprint $table) {
            $table->integer('input_tokens')->nullable()->after('ai_model');
            $table->integer('output_tokens')->nullable()->after('input_tokens');
            $table->integer('total_tokens')->nullable()->after('output_tokens');
            // 'tokens_used' will be kept for backward compatibility or could be dropped. I'll keep it as legacy for now.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('corrections', function (Blueprint $table) {
            $table->dropColumn(['input_tokens', 'output_tokens', 'total_tokens']);
        });
    }
};
