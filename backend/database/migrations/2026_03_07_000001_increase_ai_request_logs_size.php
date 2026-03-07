<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            // Upgrade text columns to longText to support large batch payloads (up to 4GB)
            // Original TEXT was limited to 64KB, causing silent failures on large batches.
            $table->longText('prompt_text')->change();
            $table->longText('response_text')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->text('prompt_text')->change();
            $table->text('response_text')->change();
        });
    }
};
